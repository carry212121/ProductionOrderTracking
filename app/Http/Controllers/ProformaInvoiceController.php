<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProformaInvoice;
use App\Models\User;
use App\Models\Product;
use App\Models\Factory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Str;
use App\Notifications\NewPIUploaded;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class ProformaInvoiceController extends Controller
{
    /** One place to change where temp Excel files live */
    private string $excelDisk = 'local';         // config/filesystems.php -> disks.local
    private string $excelDir  = 'tmp_excel';     // subfolder in that disk

    /** Session key for the resume stash */
    private string $resumeKey = 'excel_resume';

    /* ---------- Helpers ---------- */

    private function disk()
    {
        return Storage::disk($this->excelDisk);
    }
    private function stashUploadedFile(UploadedFile $file): ?array
    {
        $token   = (string) Str::uuid();
        $ext     = $file->getClientOriginalExtension() ?: 'xlsx';
        $relPath = "{$this->excelDir}/{$token}.{$ext}"; // e.g. tmp_excel/<uuid>.xlsx

        $this->disk()->makeDirectory($this->excelDir);

        // write via bytes to avoid stream quirks
        $bytes = file_get_contents($file->getRealPath());
        $ok    = $this->disk()->put($relPath, $bytes);
        $abs   = $this->disk()->path($relPath);
        $exists= $this->disk()->exists($relPath);

        Log::info('📦 [stash] write', compact('relPath','abs','ok','exists','token'));

        if (!$ok || !$exists) {
            return null;
        }

        return [
            'disk'     => $this->excelDisk,                 // store the disk too
            'path'     => $relPath,                         // relative path on disk
            'token'    => $token,
            'filename' => $file->getClientOriginalName(),
        ];
    }

    public function SummaryPIandProduct(Request $request)
    {
        $viewBy = $request->get('viewBy', 'pi'); // default is 'pi'

        $proformaInvoicesQuery = ProformaInvoice::with('products.jobControls');

        $startMonth = $request->get('start_month') ?? now()->year . '-01'; // Default: January
        $endMonth = $request->get('end_month') ?? now()->year . '-12';     // Default: December

        $startDate = Carbon::parse($startMonth . '-01')->startOfMonth();
        $endDate = Carbon::parse($endMonth . '-01')->endOfMonth();

        $proformaInvoicesQuery->whereBetween('OrderDate', [$startDate, $endDate]);

        $proformaInvoices = $proformaInvoicesQuery->get();

        // ❌ Filter out PIs where all products are finished
        $proformaInvoices = $proformaInvoices->filter(function ($pi) {
            return $pi->products->where('Status', '!=', 'Finish')->isNotEmpty();
        })->values(); // Reset index
        $today = now()->startOfDay();
        $processOrder = ['Casting', 'Stamping', 'Trimming', 'Polishing', 'Setting', 'Plating'];

        // 🔁 Add logic: Count overdue PIs by ScheduleDate where product not Finish
        foreach ($proformaInvoices as $pi) {
            if ($pi->ScheduleDate && Carbon::parse($pi->ScheduleDate)->lt($today)) {
                $pi->lateBySchedule = $pi->products->where('Status', '!=', 'Finish')->count();
            } else {
                $pi->lateBySchedule = 0;
            }
        }

        if ($viewBy === 'product') {
            $total = $proformaInvoices->flatMap->products->filter(fn($p) => $p->Status !== 'Finish')->count();
        } else {
            $total = $proformaInvoices->count();
        }

        $onTime = 0;
        $lateYellow = 0;
        $lateRed = 0;
        $lateDarkRed = 0;

        foreach ($proformaInvoices as $pi) {
            $maxDaysLate = 0;
            $isLate = false;

            foreach ($pi->products as $product) {
                if ($product->Status === 'Finish') continue;

                $jobControls = $product->jobControls->keyBy('Process');
                $latestProcess = null;

                foreach ($processOrder as $process) {
                    if (!empty($jobControls[$process]?->AssignDate)) {
                        $latestProcess = $process;
                    }
                }

                if ($latestProcess && isset($jobControls[$latestProcess])) {
                    $job = $jobControls[$latestProcess];
                    $scheduleDate = Carbon::parse($job->ScheduleDate)->startOfDay();
                    $receiveDate = $job->ReceiveDate;

                    if ($scheduleDate->lt($today) && $receiveDate === null) {
                        $isLate = true;
                        $daysLate = (int) floor($scheduleDate->diffInDays($today));
                        $maxDaysLate = max($maxDaysLate, $daysLate);
                    }
                }
            }

            if ($viewBy === 'product') {
                foreach ($pi->products as $product) {
                    if ($product->Status === 'Finish') {
                        Log::info("⏭️ Skipping finished product: {$product->ProductCode}");
                        continue;
                    }
                    $jobControls = $product->jobControls->keyBy('Process');
                    $latestProcess = null;

                    foreach ($processOrder as $process) {
                        if (!empty($jobControls[$process]?->AssignDate)) {
                            $latestProcess = $process;
                        }
                    }

                    $isLate = false;
                    $daysLate = 0;

                    // Case 1: Late by process
                    if ($latestProcess && isset($jobControls[$latestProcess])) {
                        $job = $jobControls[$latestProcess];
                        $scheduleDate = Carbon::parse($job->ScheduleDate)->startOfDay();
                        $receiveDate = $job->ReceiveDate;

                        if ($scheduleDate->lt($today) && $receiveDate === null) {
                            $isLate = true;
                            $daysLate = (int) floor($scheduleDate->diffInDays($today));
                            Log::info("🔴 [Late by Process] PI {$pi->PINumber} | Product {$product->ProductNumber} | Days Late: $daysLate");
                        }
                    }

                    // Case 2: Late by PI schedule date
                    if (!$isLate && $pi->ScheduleDate && Carbon::parse($pi->ScheduleDate)->lt($today)) {
                        $isLate = true;
                        $daysLate = Carbon::parse($pi->ScheduleDate)->copy()->startOfDay()->diffInDays($today);
                        Log::info("🟠 [Late by PI Schedule] PI {$pi->PINumber} | Product {$product->ProductNumber} | Days Late: $daysLate");
                    }

                    // Count based on daysLate
                    if ($isLate) {
                        if ($daysLate >= 15) {
                            $lateDarkRed++;
                            Log::info("🔥 [Dark Red] Product {$product->ProductNumber}");
                        } elseif ($daysLate >= 8) {
                            $lateRed++;
                            Log::info("🔴 [Red] Product {$product->ProductNumber}");
                        } elseif ($daysLate >= 1) {
                            $lateYellow++;
                            Log::info("🟡 [Yellow] Product {$product->ProductNumber}");
                        }
                    } elseif (!$isLate) {
                        $onTime++;
                        Log::info("✅ [On Time] Product {$product->ProductNumber}");
                    }else{
                        Log::warning("❓ Uncounted Product: {$product->ProductCode} | Status={$product->Status} | isLate=$isLate | Schedule={$pi->ScheduleDate}");
                    }
                }
            } else {
                if ($isLate || $pi->lateBySchedule > 0) {
                    if ($maxDaysLate >= 15 || $pi->lateBySchedule >= 15) $lateDarkRed++;
                    elseif ($maxDaysLate >= 8 || $pi->lateBySchedule >= 8) $lateRed++;
                    elseif ($maxDaysLate >= 1 || $pi->lateBySchedule >= 1) $lateYellow++;
                } else {
                    $onTime++;
                }
            }
        }
        Log::info("🔢 Total Products Checked (non-finished): " . $proformaInvoices->flatMap->products->filter(fn($p) => $p->Status !== 'Finish')->count());
        $late = $lateYellow + $lateRed + $lateDarkRed;
        Log::info("📊 Summary: Totals: $total | On Time: $onTime | Late Yellow: $lateYellow | Late Red: $lateRed | Late Dark Red: $lateDarkRed");
        $groupBy = $request->get('groupBy', 'factory');
        $grouped = $groupBy === 'production'
            ? User::where('role', 'Production')->pluck('name', 'id')
            : Factory::pluck('FactoryName', 'id');

        $barChartLabels = [];
        $barChartOnTime = [];
        $barChartLateYellow = [];
        $barChartLateRed = [];
        $barChartLateDarkRed = [];
        $groupedAmounts = [];

        foreach ($grouped as $id => $name) {
            // Only filter by production (remove groupBy check entirely)
            $pis = $proformaInvoices->filter(fn($pi) => $pi->user_id == $id);

            $totalAmount = 0;

            foreach ($pis as $pi) {
                foreach ($pi->products as $product) {
                    if ($product->Status !== 'Finish') {
                        $totalAmount += (float) $product->Quantity * (float) $product->UnitPrice;
                    }
                }
            }

            $groupedAmounts[$id] = $totalAmount;
        }

        if ($groupBy === 'production') {
            $users = User::where('role', 'Production')->get();
            foreach ($users as $user) {
                $label = $user->name;
                $pisForUser = $proformaInvoices->filter(fn($pi) => $pi->user_id === $user->id);

                $onTimeCount = 0;
                $yellow = 0;
                $red = 0;
                $darkRed = 0;

                if ($viewBy === 'product') {
                    foreach ($pisForUser as $pi) {
                        foreach ($pi->products as $product) {
                            if ($product->Status === 'Finish') continue;

                            $jobControls = $product->jobControls->keyBy('Process');
                            $latestProcess = null;
                            foreach ($processOrder as $process) {
                                if (!empty($jobControls[$process]?->AssignDate)) {
                                    $latestProcess = $process;
                                }
                            }

                            $isLate = false;
                            $daysLate = 0;

                            if ($latestProcess && isset($jobControls[$latestProcess])) {
                                $job = $jobControls[$latestProcess];
                                $scheduleDate = Carbon::parse($job->ScheduleDate)->startOfDay();
                                $receiveDate = $job->ReceiveDate;

                                if ($scheduleDate->lt($today) && $receiveDate === null) {
                                    $isLate = true;
                                    $daysLate = (int) floor($scheduleDate->diffInDays($today));
                                }
                            }

                            if (!$isLate && $pi->ScheduleDate && Carbon::parse($pi->ScheduleDate)->lt($today)) {
                                $isLate = true;
                                $daysLate = (int) Carbon::parse($pi->ScheduleDate)->diffInDays($today);
                            }

                            if ($isLate) {
                                if ($daysLate >= 15) $darkRed++;
                                elseif ($daysLate >= 8) $red++;
                                elseif ($daysLate >= 1) $yellow++;
                            } else {
                                $onTimeCount++;
                            }
                        }
                    }
                } else {
                    foreach ($pisForUser as $pi) {
                        $maxDaysLate = 0;
                        $isLate = false;

                        foreach ($pi->products as $product) {
                            if ($product->Status === 'Finish') continue;

                            $jobControls = $product->jobControls->keyBy('Process');
                            $latestProcess = null;

                            foreach ($processOrder as $process) {
                                if (!empty($jobControls[$process]?->AssignDate)) {
                                    $latestProcess = $process;
                                }
                            }

                            if ($latestProcess && isset($jobControls[$latestProcess])) {
                                $job = $jobControls[$latestProcess];
                                $scheduleDate = Carbon::parse($job->ScheduleDate)->startOfDay();
                                $receiveDate = $job->ReceiveDate;

                                if ($scheduleDate->lt($today) && $receiveDate === null) {
                                    $isLate = true;
                                    $daysLate = (int) floor($scheduleDate->diffInDays($today));
                                    $maxDaysLate = max($maxDaysLate, $daysLate);
                                }
                            }
                        }

                        if ($isLate || $pi->lateBySchedule > 0) {
                            if ($maxDaysLate >= 15 || $pi->lateBySchedule >= 15) $darkRed++;
                            elseif ($maxDaysLate >= 8 || $pi->lateBySchedule >= 8) $red++;
                            elseif ($maxDaysLate >= 1 || $pi->lateBySchedule >= 1) $yellow++;
                        } else {
                            $onTimeCount++;
                        }
                    }
                }

                $barChartLabels[] = $label;
                $barChartOnTime[] = $onTimeCount;
                $barChartLateYellow[] = $yellow;
                $barChartLateRed[] = $red;
                $barChartLateDarkRed[] = $darkRed;
            }
        } else {
            $factories = Factory::all();
            foreach ($factories as $factory) {
                $label = $factory->FactoryName;

                $pisForFactory = $proformaInvoices->filter(function ($pi) use ($factory) {
                    foreach ($pi->products as $product) {
                        foreach ($product->jobControls as $job) {
                            if ($job->factory_id == $factory->id) return true;
                        }
                    }
                    return false;
                });

                $onTimeCount = 0;
                $yellow = 0;
                $red = 0;
                $darkRed = 0;

                if ($viewBy === 'product') {
                    foreach ($pisForFactory as $pi) {
                        foreach ($pi->products as $product) {
                            if ($product->Status === 'Finish') continue;

                            $hasFactory = $product->jobControls->contains(fn($jc) => $jc->factory_id == $factory->id);
                            if (! $hasFactory) continue;

                            $jobControls = $product->jobControls->keyBy('Process');
                            $latestProcess = null;
                            foreach ($processOrder as $process) {
                                if (!empty($jobControls[$process]?->AssignDate)) {
                                    $latestProcess = $process;
                                }
                            }

                            $isLate = false;
                            $daysLate = 0;

                            if ($latestProcess && isset($jobControls[$latestProcess])) {
                                $job = $jobControls[$latestProcess];
                                $scheduleDate = Carbon::parse($job->ScheduleDate)->startOfDay();
                                $receiveDate = $job->ReceiveDate;

                                if ($scheduleDate->lt($today) && $receiveDate === null) {
                                    $isLate = true;
                                    $daysLate = (int) floor($scheduleDate->diffInDays($today));
                                }
                            }

                            if (!$isLate && $pi->ScheduleDate && Carbon::parse($pi->ScheduleDate)->lt($today)) {
                                $isLate = true;
                                $daysLate = (int) Carbon::parse($pi->ScheduleDate)->diffInDays($today);
                            }

                            if ($isLate) {
                                if ($daysLate >= 15) $darkRed++;
                                elseif ($daysLate >= 8) $red++;
                                elseif ($daysLate >= 1) $yellow++;
                            } else {
                                $onTimeCount++;
                            }
                        }
                    }

                } else {
                    foreach ($pisForFactory as $pi) {
                        $maxDaysLate = 0;
                        $isLate = false;

                        foreach ($pi->products as $product) {
                            if ($product->Status === 'Finish') continue;

                            $jobControls = $product->jobControls->keyBy('Process');
                            $latestProcess = null;

                            foreach ($processOrder as $process) {
                                if (!empty($jobControls[$process]?->AssignDate)) {
                                    $latestProcess = $process;
                                }
                            }

                            if ($latestProcess && isset($jobControls[$latestProcess])) {
                                $job = $jobControls[$latestProcess];
                                $scheduleDate = Carbon::parse($job->ScheduleDate)->startOfDay();
                                $receiveDate = $job->ReceiveDate;

                                if ($scheduleDate->lt($today) && $receiveDate === null) {
                                    $isLate = true;
                                    $daysLate = (int) floor($scheduleDate->diffInDays($today));
                                    $maxDaysLate = max($maxDaysLate, $daysLate);
                                }
                            }
                        }

                        if ($isLate || $pi->lateBySchedule > 0) {
                            if ($maxDaysLate >= 15 || $pi->lateBySchedule >= 15) $darkRed++;
                            elseif ($maxDaysLate >= 8 || $pi->lateBySchedule >= 8) $red++;
                            elseif ($maxDaysLate >= 1 || $pi->lateBySchedule >= 1) $yellow++;
                        } else {
                            $onTimeCount++;
                        }
                    }
                }

                $barChartLabels[] = $label;
                $barChartOnTime[] = $onTimeCount;
                $barChartLateYellow[] = $yellow;
                $barChartLateRed[] = $red;
                $barChartLateDarkRed[] = $darkRed;
            }
        }


        $page = $request->get('barPage', 1);
        $perPage = 10;
        $totalEntities = count($barChartLabels);
        $start = ($page - 1) * $perPage;

        $barChartLabels = array_slice($barChartLabels, $start, $perPage);
        $barChartOnTime = array_slice($barChartOnTime, $start, $perPage);
        $barChartLateYellow = array_slice($barChartLateYellow, $start, $perPage);
        $barChartLateRed = array_slice($barChartLateRed, $start, $perPage);
        $barChartLateDarkRed = array_slice($barChartLateDarkRed, $start, $perPage);
        $totalPages = ceil($totalEntities / $perPage);

        return view('dashboard.index', compact(
            'total', 'onTime', 'late',
            'lateYellow', 'lateRed', 'lateDarkRed',
            'startMonth', 'endMonth', 'groupBy', 'grouped',
            'barChartLabels', 'barChartOnTime',
            'barChartLateYellow', 'barChartLateRed', 'barChartLateDarkRed',
            'page', 'totalPages', 'viewBy' , 'groupedAmounts',
        ));
    }



    public function index(Request $request)
    {
        $resume = session($this->resumeKey);
        Log::info('🟢 [index] hit', ['resume' => $resume]);

        if ($resume) {
            $diskName = $resume['disk'] ?? $this->excelDisk;
            $rel      = $resume['path'] ?? '';
            $abs      = Storage::disk($diskName)->path($rel);
            $exists   = Storage::disk($diskName)->exists($rel);
            $root     = config("filesystems.disks.{$diskName}.root");

            Log::info('🟢 [index] check stash', compact('diskName','rel','abs','exists','root'));

            if (!$exists) {
                Log::info('🟢 [index] stash missing -> clear');
                session()->forget($this->resumeKey);
                $resume = null;
            }
        }

        $user = Auth::user();

        $query = ProformaInvoice::with(['user', 'products.jobControls', 'salesPerson']);

        if ($user->role == 'Production') {
            $query->where('user_id', $user->id);
        } elseif ($user->role == 'Sales') {
            $query->where('SalesPerson', $user->id);
        }

        $pis = $query->latest()->get();

        $processOrder = ['Casting', 'Stamping', 'Trimming', 'Polishing', 'Setting', 'Plating'];
        $today = \Carbon\Carbon::today();

        // Calculate lateness info for each PI
        $pis = $pis->map(function ($pi) use ($processOrder, $today, $user) {
            $scheduledDate = \Carbon\Carbon::parse($pi->ScheduleDate)->startOfDay();
            $totalCount = $pi->products->count();
            $finishedCount = $pi->products->where('Status', 'Finish')->count();
            $createdDaysAgo = \Carbon\Carbon::parse($pi->created_at)->diffInDays($today);

            $lateCount = 0;
            $maxLateDays = 0;

            //Log::info("📦 PI: {$pi->PInumber} — ScheduleDate: {$pi->ScheduleDate}");

            foreach ($pi->products as $product) {
                if ($product->Status === 'Finish') {
                    //Log::info("✅ Product ID {$product->id} is finished.");
                    continue;
                }

                $isLate = false;
                $lateDays = 0;
                $jobControls = $product->jobControls->keyBy('Process');
                $latestProcess = null;

                foreach ($processOrder as $process) {
                    if (!empty($jobControls[$process]?->AssignDate)) {
                        $latestProcess = $process;
                    }
                }

                $jobInfo = "❌ No job control found";
                if ($latestProcess && isset($jobControls[$latestProcess])) {
                    $job = $jobControls[$latestProcess];
                    $scheduleDate = \Carbon\Carbon::parse($job->ScheduleDate)->startOfDay();
                    $receiveDate = $job->ReceiveDate;

                    if ($scheduleDate->lt($today) && $receiveDate === null) {
                        $isLate = true;
                        $lateDays = (int) floor($scheduleDate->diffInDays($today));
                    }

                    $jobInfo = "Process: $latestProcess | ScheduleDate: {$job->ScheduleDate} | ReceiveDate: {$job->ReceiveDate}";
                }

                if (!$isLate && $pi->ScheduleDate && $scheduledDate->lt($today)) {
                    $isLate = true;
                    // $lateDays = $scheduledDate->diffInDays($today);
                    $lateDays = (int) floor($scheduledDate->diffInDays($today));
                }

                if ($isLate) {
                    $lateCount++;
                    if ($lateDays > $maxLateDays) {
                        $maxLateDays = $lateDays;
                    }
                }

                //Log::info("🧩 Product ID {$product->id} — Status: {$product->Status} — $jobInfo — Late: " . ($isLate ? 'YES' : 'NO') . " — LateDays: $lateDays");
            }

            // Assign card class
            $cardClass = 'bg-white border border-gray-200';
            if ($maxLateDays > 15) {
                $cardClass = 'bg-red-400 text-white border border-red-800';
            } elseif ($maxLateDays > 7) {
                $cardClass = 'bg-red-200 border border-red-500';
            } elseif ($maxLateDays >= 1) {
                $cardClass = 'bg-yellow-100 border border-yellow-400';
            }

            $priority = $finishedCount === $totalCount ? 5 : (
                $maxLateDays > 15 ? 1 : (
                    $maxLateDays > 7 ? 2 : (
                        $maxLateDays >= 1 ? 3 : 4
                    )
                )
            );
            if ($user->role === 'Production' && $createdDaysAgo <= 2) {
                $priority = 0; // highest priority
            }
            $pi->finishedCount = $finishedCount;
            $pi->lateCount = $lateCount;
            $pi->maxLateDays = $maxLateDays;
            $pi->cardClass = $cardClass;
            $pi->priority = $priority;

            //Log::info("📊 Summary for PI {$pi->PInumber}: Finished: $finishedCount, Late: $lateCount, MaxLateDays: $maxLateDays, Class: $cardClass");

            return $pi;
        });

        $pis = $pis->sortBy('priority');

        return view('proformaInvoice.index', compact('pis', 'resume'));
    }



    public function show($id)
    {
        $pi = ProformaInvoice::findOrFail($id);

        // Paginate products for this PI
        $products = \App\Models\Product::with('jobControls')
            ->where('proforma_invoice_id', $pi->id)
            ->orderBy('id')            // or any order you prefer
            ->paginate(20);            // <= 20 per page

        $factories = Factory::select('id', 'FactoryNumber', 'FactoryName')
            ->orderBy('FactoryNumber')
            ->get();

        $scheduleDate = $pi->ScheduleDate ? \Carbon\Carbon::parse($pi->ScheduleDate)->startOfDay() : null;
        $today        = \Carbon\Carbon::today();
        $dayDiff      = $scheduleDate ? $scheduleDate->diffInDays($today) : null;
        $dayDiff      = abs($dayDiff);
        $isOverdue    = $scheduleDate && $today->gt($scheduleDate);

        // Efficient “all finished?” across ALL products of this PI (not just current page)
        $allFinished  = !$pi->products()->where('Status', '!=', 'Finish')->exists();

        return view('proformaInvoice.detail', compact(
            'pi',
            'products',       // <-- pass paginator
            'factories',
            'scheduleDate',
            'dayDiff',
            'isOverdue',
            'allFinished'
        ));
    }


    public function importExcel(Request $request)
    {
        $resumeKey = 'excel_resume';

        Log::info('🔴 [import] hit', [
            'has_file'      => $request->hasFile('excel_file'),
            'excel_token'   => $request->input('excel_token'),
            'session_token' => session("$resumeKey.token"),
        ]);

        $request->validate([
            'excel_file'  => 'required_without:excel_token|file|mimes:xlsx,xls',
            'excel_token' => 'nullable|string',
        ]);

        $pathToLoad = null;
        $usedStash  = null; // keep what we used so we can clean it up on success

        try {
            if ($request->hasFile('excel_file')) {
                // User uploaded a file directly

                $file       = $request->file('excel_file');
                $pathToLoad = $file->getRealPath();

                Log::info('📂 [import] using uploaded temp', [
                    'tmp'  => $pathToLoad,
                    'name' => $file->getClientOriginalName(),
                ]);

            } elseif ($request->filled('excel_token') && session("$resumeKey.token") === $request->excel_token) {
                // Use previously stashed file from preview
                $stash  = session($resumeKey);
                $disk   = $stash['disk'] ?? 'local';
                $rel    = $stash['path'] ?? '';
                $abs    = Storage::disk($disk)->path($rel);
                $exists = Storage::disk($disk)->exists($rel);

                Log::info('📂 [import] using stashed file', compact('disk','rel','abs','exists'));

                if (!$exists) {
                    // Stash is gone -> can’t import
                    session()->forget($resumeKey);
                    return back()->with('excel_error', 'ไฟล์ชั่วคราวหมดอายุ กรุณาอัปโหลดใหม่');
                }

                $pathToLoad = $abs;
                $usedStash  = $stash; // mark for cleanup after success
            } else {
                return back()->with('excel_error', 'กรุณาเลือกไฟล์ Excel');
            }

            // -------- Load and import ----------
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($pathToLoad);
            Log::info('📑 [import] spreadsheet loaded');

            // $file = $request->file('excel_file');
            // $pathToLoad = $file->getRealPath();
            // $spreadsheet = IOFactory::load($pathToLoad);

            Log::info("📑 [importExcel] Spreadsheet loaded");

            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            $sheet = $spreadsheet->getActiveSheet();
            Log::info("📊 Total rows in sheet: " . count($rows));

            // Extract header
            $header = $rows[1];
            Log::info("🔹 Header row: ", $header);

            $groupedByOrderCode = [];

            // Group rows by OrderID
            Log::info("🔄 Grouping rows by OrderID (Column B)...");
            foreach ($rows as $rowIndex => $row) {
                if ($rowIndex === 1) {
                    Log::info("⏭ Skipping header row");
                    continue;
                }
                $orderCode = $row['B'] ?? null;
                if ($orderCode) {
                    $groupedByOrderCode[$orderCode][] = [
                        'index' => $rowIndex,
                        'data'  => $row,
                    ];
                }
            }
            Log::info("📦 Grouped orders count: " . count($groupedByOrderCode));

            // Process each order group
            foreach ($groupedByOrderCode as $orderCode => $orderRows) {
                Log::info("📌 Processing OrderID: {$orderCode}, rows: " . count($orderRows));

                $firstRowIndex = $orderRows[0]['index'];
                $firstRow      = $orderRows[0]['data'];
                $piNumber = trim($firstRow['B']);
                Log::info("📑 Extracted PI number: {$piNumber}");

                // Skip if duplicate PI
                if (ProformaInvoice::where('PInumber', $piNumber)->exists()) {
                    Log::info("⏩ Skipped duplicate PI: {$piNumber}");
                    return redirect()->back()->with('excel_error', "รหัส PI ซ้ำ: $piNumber");
                }

                // Extract IDs
                $customerIdParts = explode('-', trim($firstRow['D']));
                Log::info("🔍 CustomerID parts: ", $customerIdParts);

                $suffix = $customerIdParts[1] ?? '';
                [$salesID, $productionID] = explode('/', $suffix . '/');
                Log::info("👤 SalesID: {$salesID}, ProductionID: {$productionID}");

                // Find users
                $salesUser = User::where('salesID', $salesID)->first();
                Log::info("👤 Sales user found: " . ($salesUser?->id ?? 'none'));

                $productionUser = User::where('productionID', $productionID)->first();
                Log::info("🏭 Production user found: " . ($productionUser?->id ?? 'none'));

                // Create PI
                Log::info("🆕 Creating ProformaInvoice record...");
                $pi = ProformaInvoice::create([
                    'PInumber'             => $piNumber,
                    'byOrder'              => trim($firstRow['C']),
                    'CustomerID'           => trim($firstRow['D']),
                    'CustomerPO'           => trim($firstRow['I']),
                    'CustomerInstruction'  => trim($firstRow['M']),
                    'FreightPrepaid'       => floatval($firstRow['N']),
                    'InsurancePrepaid'     => floatval($firstRow['O']),
                    'Deposit'              => floatval($firstRow['P']),
                    'OrderDate'      => $this->parseExcelDate($sheet->getCell("F{$firstRowIndex}"), 'DMY'),
                    'ScheduleDate'   => $this->parseExcelDate($sheet->getCell("G{$firstRowIndex}"), 'DMY'),
                    'CompletionDate' => $this->parseExcelDate($sheet->getCell("H{$firstRowIndex}"), 'DMY'),
                    'SalesPerson'          => $salesUser?->id,
                    'user_id'              => $productionUser?->id,
                ]);
                Log::info("✅ PI created with ID: {$pi->id}");

                // Create/Update products
                foreach ($orderRows as $orderRow) {
                    $rowData = $orderRow['data']; // the original A,B,C,... array
                    $quantity = trim($rowData['U']) . ' ' . trim($rowData['V']);
                    Log::info("📦 Processing ProductNumber: " . trim($rowData['Q']));

                    Product::create([
                        'ProductNumber'          => trim($rowData['Q']),
                        'Description'            => trim($rowData['R']),
                        'ProductCustomerNumber'  => trim($rowData['S']),
                        'Weight'                 => floatval($rowData['T']),
                        'Quantity'               => $quantity,
                        'UnitPrice'              => floatval($rowData['W']),
                        'proforma_invoice_id'    => $pi->id,
                    ]);
                    Log::info("✅ Product saved/updated: " . trim($rowData['Q']));
                }

                // Notify production
                if ($productionUser) {
                    Log::info("📤 Sending notification to production user ID: " . $productionUser->id);
                    $productionUser->notify(new NewPIUploaded(Auth::user(), $pi));
                }

                Log::info("✅ Imported PI: {$pi->PInumber} with " . count($orderRows) . " products.");
                Log::info('📄 Raw Excel date cells', [
                    'OrderDate_raw'      => $firstRow['F'],
                    'ScheduleDate_raw'   => $firstRow['G'],
                    'CompletionDate_raw' => $firstRow['H'],
                    'types' => [
                        'OrderDate'      => get_debug_type($firstRow['F']),
                        'ScheduleDate'   => get_debug_type($firstRow['G']),
                        'CompletionDate' => get_debug_type($firstRow['H']),
                    ]
                ]);
            }
            $stashToClear = $usedStash ?: session($resumeKey);
            if ($stashToClear) {
                $disk = $stashToClear['disk'] ?? 'local';
                $rel  = $stashToClear['path'] ?? null;

                if ($rel && Storage::disk($disk)->exists($rel)) {
                    $ok = Storage::disk($disk)->delete($rel);
                    Log::info('🧹 [import] deleted stashed file', [
                        'disk' => $disk, 'rel' => $rel, 'ok' => $ok
                    ]);
                } else {
                    Log::info('🧹 [import] no stashed file to delete', [
                        'disk' => $disk ?? null, 'rel' => $rel, 'exists' => $rel ? Storage::disk($disk)->exists($rel) : null
                    ]);
                }

                session()->forget($resumeKey);
                Log::info('🧹 [import] cleared session resume token');
            }
            Log::info("🎯 [importExcel] Import completed successfully");
            return redirect()->back()->with('excel_success', '📥 นำเข้าข้อมูล Excel สำเร็จแล้ว');
        } catch (\Throwable $e) {
            Log::error('🔴 [import] error: '.$e->getMessage());
            return back()->with('excel_error', 'นำเข้าไม่สำเร็จ');
        }
    }


    private function parseExcelDate(Cell $cell): ?string
    {
        $raw = $cell->getValue();                                // numeric serial or string
        $display = trim((string) $cell->getFormattedValue());    // what Excel shows

        Log::info('📅 [parseExcelDate] cell debug', [
            'raw_value'       => $raw,
            'raw_type'        => get_debug_type($raw),
            'formatted_value' => $display,
        ]);

        // 1) Numeric serial? (most reliable)
        if (is_numeric($raw)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject($raw);    // \DateTime
                $out = Carbon::instance($dt)->format('Y-m-d');
                Log::info('📅 [parseExcelDate] numeric serial -> '.$out);
                return $out;
            } catch (\Throwable $e) {
                Log::warning('⚠️ [parseExcelDate] numeric conversion failed', ['msg' => $e->getMessage()]);
            }
        }

        // 2) Build a text candidate
        $text = $display !== '' ? $display : (is_string($raw) ? trim($raw) : '');
        if ($text === '') {
            Log::info('📅 [parseExcelDate] empty -> null');
            return null;
        }

        // 3) Normalize separators (., /, space -> -)
        $norm = preg_replace('/[\.\/\s]+/u', '-', $text);

        // 4) Map Thai months -> English to let DateTime parse
        $map = [
            // short
            'ม.ค.'=>'Jan', 'ก.พ.'=>'Feb', 'มี.ค.'=>'Mar', 'เม.ย.'=>'Apr', 'พ.ค.'=>'May', 'มิ.ย.'=>'Jun',
            'ก.ค.'=>'Jul', 'ส.ค.'=>'Aug', 'ก.ย.'=>'Sep', 'ต.ค.'=>'Oct', 'พ.ย.'=>'Nov', 'ธ.ค.'=>'Dec',
            // long
            'มกราคม'=>'January','กุมภาพันธ์'=>'February','มีนาคม'=>'March','เมษายน'=>'April','พฤษภาคม'=>'May','มิถุนายน'=>'June',
            'กรกฎาคม'=>'July','สิงหาคม'=>'August','กันยายน'=>'September','ตุลาคม'=>'October','พฤศจิกายน'=>'November','ธันวาคม'=>'December',
        ];
        $norm = str_ireplace(array_keys($map), array_values($map), $norm);

        // Optional: if Thai Buddhist year (25xx), convert to AD
        // e.g. 18-สิงหาคม-2568 -> 18-August-2025
        if (preg_match('/\b(25\d{2})\b/u', $norm, $m)) {
            $be = (int)$m[1];
            $ad = $be - 543;
            $norm = preg_replace('/\b25\d{2}\b/u', (string)$ad, $norm);
        }

        // 5) Try specific formats first
        $formats = [
            'd-M-y','d-M-Y','d-MMM-y','d-MMM-Y','d-MMMM-y','d-MMMM-Y',
            'm/d/Y','m-d-Y','d/m/Y','d-m-Y','Y-m-d',
        ];
        foreach ($formats as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $norm);
                if ($dt && $dt->format($fmt) === $norm) {
                    $out = $dt->format('Y-m-d');
                    Log::info('📅 [parseExcelDate] text parsed', ['format'=>$fmt,'out'=>$out]);
                    return $out;
                }
            } catch (\Throwable $e) {
                // continue
            }
        }

        // 6) Fallback: free parse (handles "18-Aug-25")
        try {
            $out = Carbon::parse($norm)->format('Y-m-d');
            Log::info('📅 [parseExcelDate] fallback parsed', ['src'=>$norm,'out'=>$out]);
            return $out;
        } catch (\Throwable $e) {
            Log::warning('⚠️ [parseExcelDate] no matching format', ['text'=>$text]);
            return null;
        }
    }
    public function preview(Request $request)
    {
        Log::info('🟡 [preview] hit', [
            'has_file'      => $request->hasFile('excel_file'),
            'excel_token'   => $request->input('excel_token'),
            'session_token' => session($this->resumeKey . '.token'),
        ]);

        $request->validate([
            'excel_file'  => 'required_without:excel_token|file|mimes:xlsx,xls',
            'excel_token' => 'nullable|string',
        ]);

        try {
            $loadPath = null;
            $filename = null;
            $token    = null;

            if ($request->hasFile('excel_file')) {

                $this->clearCurrentStash();

                $file     = $request->file('excel_file');
                $loadPath = $file->getRealPath();
                $filename = $file->getClientOriginalName();

                Log::info('🟡 [preview] using uploaded temp', [
                    'realpath' => $loadPath,
                    'name'     => $filename,
                ]);

                // Stash for later "Upload" without reselecting
                $stash = $this->stashUploadedFile($file);
                if ($stash) {
                    session([$this->resumeKey => $stash]);
                    $token = $stash['token'];
                } else {
                    Log::warning('🟡 [preview] stash failed; proceeding without token');
                }

            } elseif ($request->filled('excel_token') && session($this->resumeKey . '.token') === $request->excel_token) {
                $stash   = session($this->resumeKey);
                $disk    = $stash['disk'] ?? $this->excelDisk;
                $rel     = $stash['path'] ?? '';
                $exists  = Storage::disk($disk)->exists($rel);

                Log::info('🟡 [preview] resume via token', [
                    'disk' => $disk,
                    'rel'  => $rel,
                    'abs'  => Storage::disk($disk)->path($rel),
                    'exists' => $exists,
                ]);

                if (!$exists) {
                    $this->clearCurrentStash();
                    // session()->forget($this->resumeKey);
                    return back()->with('excel_error', 'ไฟล์ชั่วคราวหมดอายุ กรุณาเลือกไฟล์อีกครั้ง');
                }

                $loadPath = Storage::disk($disk)->path($rel);
                $token    = $stash['token'];
                $filename = $stash['filename'] ?? basename($rel);
            } else {
                return back()->with('excel_error', 'กรุณาเลือกไฟล์ Excel');
            }

            Log::info('🟡 [preview] loading spreadsheet', compact('loadPath','filename','token'));
            $spreadsheet = IOFactory::load($loadPath);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);
            Log::info('🟡 [preview] rows', ['count' => is_array($rows) ? count($rows) : 0]);

            return view('proformaInvoice.preview', [
                'rows'          => $rows,
                'excelToken'    => $token,     // pass back so index can reuse
                'excelFilename' => $filename,
            ]);
        } catch (\Throwable $e) {
            Log::error('🔴 [preview] error: '.$e->getMessage());
            return back()->with('excel_error', 'ไม่สามารถอ่านไฟล์ Excel ได้');
        }
    }




    public function destroy($id)
    {
        // Optional: Only allow Head or Admin
        if (!in_array(Auth::user()->role, ['Head', 'Admin'])) {
            abort(403, 'Unauthorized action.');
        }

        $pi = ProformaInvoice::findOrFail($id);
        $pi->delete();

        return redirect()
            ->route('proformaInvoice.index')
            ->with('success', 'Proforma Invoice ถูกลบเรียบร้อยแล้ว');
    }

    public function update(Request $request, ProformaInvoice $proformaInvoice)
    {
        if (!in_array(Auth::user()->role ?? '', ['Head', 'Admin'])) {
            abort(403, 'Unauthorized');
        }

        $baseValidator = Validator::make($request->all(), [
            'byOrder'      => ['required', 'string', 'max:255'],
            'CustomerPO'   => ['nullable', 'string', 'max:255'],
            'OrderDate'    => ['nullable'],
            'ScheduleDate' => ['nullable'],
        ], [
            'byOrder.required' => 'กรุณากรอกชื่อลูกค้า',
        ]);
        if ($baseValidator->fails()) {
            return back()->withErrors($baseValidator)->withInput();
        }

        // parse dates (Y-m-d or d-m-Y), allow null
        $orderDate    = $this->parseFlexibleDate($request->input('OrderDate'));
        $scheduleDate = $this->parseFlexibleDate($request->input('ScheduleDate'));

        if ($request->filled('OrderDate') && !$orderDate) {
            return back()->withErrors(['OrderDate' => 'รูปแบบวันสั่งไม่ถูกต้อง'])->withInput();
        }
        if ($request->filled('ScheduleDate') && !$scheduleDate) {
            return back()->withErrors(['ScheduleDate' => 'รูปแบบวันกำหนดรับไม่ถูกต้อง'])->withInput();
        }
        if ($orderDate && $scheduleDate && $scheduleDate->lt($orderDate)) {
            return back()->withErrors(['ScheduleDate' => 'วันกำหนดรับต้องเป็นวันเดียวกันหรือหลัง “วันสั่ง”'])->withInput();
        }

        // capture old values for diff
        $labels = [
            'byOrder'      => 'ชื่อลูกค้า',
            'CustomerPO'   => 'รหัส PO',
            'OrderDate'    => 'วันสั่ง',
            'ScheduleDate' => 'วันกำหนดรับ',
        ];
        $old = $proformaInvoice->only(array_keys($labels));

        // update
        $proformaInvoice->byOrder      = $request->string('byOrder')->toString();
        $proformaInvoice->CustomerPO   = $request->string('CustomerPO')->toString() ?: null;
        $proformaInvoice->OrderDate    = $orderDate?->format('Y-m-d');
        $proformaInvoice->ScheduleDate = $scheduleDate?->format('Y-m-d');
        $proformaInvoice->save();

        // compute diff (old → new)
        $changes = [];
        foreach ($labels as $key => $label) {
            $oldRaw = $old[$key] ?? null;
            $newRaw = $proformaInvoice->{$key};

            $oldNorm = $this->normalizeForCompare($key, $oldRaw);
            $newNorm = $this->normalizeForCompare($key, $newRaw);

            if ($oldNorm !== $newNorm) {
                $changes[$label] = [
                    'old' => $this->formatForDisplay($key, $oldRaw),
                    'new' => $this->formatForDisplay($key, $newRaw),
                ];
            }
        }

        return back()->with('changes', [
            'title'   => 'แก้ไข PI '.$proformaInvoice->PInumber.' สำเร็จ',
            'changes' => $changes,
        ]);
    }

    // --- helpers ---

    protected function normalizeForCompare(string $key, $value): ?string
    {
        if ($value === null || $value === '') return null;

        if (in_array($key, ['OrderDate','ScheduleDate'], true)) {
            try {
                // compare on Y-m-d to avoid time noise
                return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d');
            } catch (\Throwable $e) {
                return (string) $value;
            }
        }
        return trim((string) $value) ?: null;
    }

    protected function formatForDisplay(string $key, $value): string
    {
        if ($value === null || $value === '') return '—';

        if (in_array($key, ['OrderDate','ScheduleDate'], true)) {
            try {
                return \Illuminate\Support\Carbon::parse($value)->format('d-m-Y');
            } catch (\Throwable $e) {
                return (string) $value;
            }
        }
        return (string) $value;
    }

    /**
     * Accept Y-m-d (hidden), d-m-Y (display), or any Carbon-parseable string.
     * Returns Carbon|null. Empty strings become null.
     */
    protected function parseFlexibleDate(?string $value): ?Carbon
    {
        if (!$value) return null;
        $value = trim($value);
        if ($value === '') return null;

        // Try strict formats first
        foreach (['Y-m-d', 'd-m-Y'] as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $value);
                // Guard invalid dates like 31-02-2025
                if ($dt && $dt->format($fmt) === $value) {
                    return $dt->startOfDay();
                }
            } catch (\Throwable $e) { /* continue */ }
        }

        // Fallback: Carbon::parse (looser)
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function clearCurrentStash(): void
    {
        $resume = session($this->resumeKey);
        $had    = (bool) $resume;

        if ($resume && !empty($resume['path'])) {
            $disk = $resume['disk'] ?? $this->excelDisk;
            $rel  = $resume['path'];
            $abs  = Storage::disk($disk)->path($rel);
            try {
                if (Storage::disk($disk)->exists($rel)) {
                    $ok = Storage::disk($disk)->delete($rel);
                    Log::info('🧹 [stash] deleted file', compact('disk','rel','abs','ok'));
                } else {
                    Log::info('🧹 [stash] nothing to delete', compact('disk','rel','abs'));
                }
            } catch (\Throwable $e) {
                Log::warning('🧹 [stash] delete failed', [
                    'path' => $rel, 'err' => $e->getMessage()
                ]);
            }
        }

        session()->forget($this->resumeKey);
        Log::info('🧹 [stash] session cleared', ['had_resume' => $had]);
    }
}
