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

class ProformaInvoiceController extends Controller
{
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
            'page', 'totalPages', 'viewBy'
        ));
    }


    public function index(Request $request)
    {
        $user = Auth::user();

        $query = ProformaInvoice::with(['user', 'products', 'salesPerson']);

        if ($user->role == 'Production') {
            $query->where('user_id', $user->id);
        }elseif ($user->role == 'Sales') {
            $query->where('SalesPerson', $user->id);
        }

        $pis = $query->latest()->get();

        return view('proformaInvoice.index', compact('pis'));
    }

    public function show($id)
    {
        $pi = ProformaInvoice::with(['products'])->findOrFail($id);

        return view('proformaInvoice.detail', compact('pi'));
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls',
        ]);

        $file = $request->file('excel_file');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        $header = $rows[1]; // Row 1 contains column headers
        $groupedByOrderId = [];

        // Group all rows by OrderID (column A)
        foreach ($rows as $index => $row) {
            if ($index === 1) continue; // Skip header
            $orderId = $row['A'] ?? null;
            if ($orderId) {
                $groupedByOrderId[$orderId][] = $row;
            }
        }

        foreach ($groupedByOrderId as $orderId => $orderRows) {
            $firstRow = $orderRows[0];
            $piNumber = trim($firstRow['B']); // OrderCode

            // 🔍 Skip if PI already exists
            if (ProformaInvoice::where('PInumber', $piNumber)->exists()) {
                Log::info("⏩ Skipped duplicate PI: $piNumber");
                return redirect()->back()->with('excel_error', "รหัส PI ซ้ำ: $piNumber");
            }

            // Extract salesID and productionID from CustomerID (e.g., NES-WR/MT)
            $customerIdParts = explode('-', trim($firstRow['D']));
            $suffix = $customerIdParts[1] ?? ''; // "WR/MT"
            [$salesID, $productionID] = explode('/', $suffix . '/'); // default to prevent explode error

            // 🔍 Lookup Sale and Production Users
            $salesUser = User::where('salesID', $salesID)->first();
            $productionUser = User::where('productionID', $productionID)->first();


            $pi = ProformaInvoice::create([
                'PInumber'             => $piNumber,
                'byOrder'              => trim($firstRow['C']),
                'CustomerID'           => trim($firstRow['D']),
                'CustomerPO'           => trim($firstRow['I']),
                'CustomerInstruction'  => trim($firstRow['M']),
                'FreightPrepaid'       => floatval($firstRow['N']),
                'InsurancePrepaid'     => floatval($firstRow['O']),
                'Deposit'              => floatval($firstRow['P']),
                'OrderDate'            => $this->parseExcelDate($firstRow['F']),
                'ScheduleDate'            => $this->parseExcelDate($firstRow['G']),
                'CompletionDate'            => $this->parseExcelDate($firstRow['H']),
                'SalesPerson'          => $salesUser?->id,
                'user_id'              => $productionUser?->id,
            ]);

            foreach ($orderRows as $row) {
                $quantity = trim($row['U']) . ' ' . trim($row['V']);

                Product::updateOrCreate(
                    ['ProductNumber' => trim($row['Q'])],
                    [
                        'Description'           => trim($row['R']),
                        'ProductCustomerNumber' => trim($row['S']),
                        'Weight'                => floatval($row['T']),
                        'Quantity'              => $quantity,
                        'UnitPrice'             => floatval($row['W']),
                        'proforma_invoice_id'   => $pi->id,
                    ]
                );
            }

            Log::info("✅ Imported PI: {$pi->PInumber} with " . count($orderRows) . " products.");
            Log::info("📅 Raw OrderDate: " . $firstRow['F']);
        }


        return redirect()->back()->with('excel_success', '📥 นำเข้าข้อมูล Excel สำเร็จแล้ว');
    }

    private function parseExcelDate($excelDate)
    {
        if (!$excelDate) return null;

        try {
            if (is_numeric($excelDate)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($excelDate);
            } else {
                // Example input: "4/18/2025 10:23 A4P4"
                // Step 1: Split before noise (keep date + time only)
                $cleaned = preg_split('/\s[A-Za-z0-9]*$/', trim($excelDate))[0];

                // Step 2: Parse cleaned datetime
                return Carbon::parse($cleaned);
            }
        } catch (\Exception $e) {
            Log::warning("⚠️ Date parse failed: " . $excelDate);
            return null;
        }
    }
    public function preview(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            $file = $request->file('excel_file');
            $data = \Maatwebsite\Excel\Facades\Excel::toArray([], $file);

            return view('proformaInvoice.preview', [
                'rows' => $data[0] ?? [],
            ]);
        } catch (\Exception $e) {
            Log::error("📛 Excel Preview Error: " . $e->getMessage());
            return redirect()->back()->with('excel_error', 'ไม่สามารถอ่านไฟล์ Excel ได้');
        }
    }

}
