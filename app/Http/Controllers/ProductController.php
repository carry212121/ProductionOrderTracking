<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\JobControl;
use App\Models\Factory;
use App\Models\User;
use Carbon\Carbon;
use App\Models\ProformaInvoice;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{

    public function toggleStatus(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $oldStatus = $product->Status;
        $product->Status = $request->status;
        $product->save();

        $pi = $product->proformaInvoice;

        if ($pi) {
            if ($request->status === 'Finish') {
                // ✅ If changed to Finish, check if all products in this PI are now Finish
                $allFinished = $pi->products()->where('Status', '!=', 'Finish')->count() === 0;
                $pi->CompletionDate = $allFinished ? Carbon::today() : null;
            } elseif ($oldStatus === 'Finish' && $request->status !== 'Finish') {
                // 🔄 If changing from Finish to something else, clear CompletionDate
                $pi->CompletionDate = null;
            }
            $pi->save();
        }

        return back()->with('success', 'สถานะของสินค้าได้รับการอัปเดตแล้ว');
    }
    public function show(Request $request, $id)
    {
        $groupBy = $request->query('groupBy', 'factory'); // 'factory' or 'production'

        if ($groupBy === 'production') {
            $products = Product::with(['proformaInvoice.user', 'jobControls'])
                ->where('Status', '!=', 'Finish')
                ->whereHas('proformaInvoice', fn($query) => $query->where('user_id', $id))
                ->get();

            $sourceName = User::find($id)?->name ?? 'ไม่ทราบชื่อ';

        } else {
            $jobControls = JobControl::with(['product.proformaInvoice', 'factory'])
                ->where('factory_id', $id)
                ->get();

            $products = $jobControls->pluck('product')
                ->filter()
                ->filter(fn($product) => $product->Status !== 'Finish')
                ->unique('id')
                ->values();

            $sourceName = Factory::find($id)?->FactoryName ?? 'ไม่ทราบชื่อ';
        }

        $today = Carbon::today();
        $lateYellow = 0;
        $lateRed = 0;
        $lateDarkRed = 0;
        $onTime = 0;
        $processOrder = ['Casting', 'Stamping', 'Trimming', 'Polishing', 'Setting', 'Plating'];

        foreach ($products as $product) {
            $jobControls = $product->jobControls->keyBy('Process');
            $latestProcess = null;

            foreach ($processOrder as $process) {
                if (!empty($jobControls[$process]?->AssignDate)) {
                    $latestProcess = $process;
                }
            }

            $daysLate = 0;
            $isLate = false;

            if ($latestProcess && isset($jobControls[$latestProcess])) {
                $job = $jobControls[$latestProcess];
                $scheduleDate = $job->ScheduleDate ? Carbon::parse($job->ScheduleDate) : null;
                $receiveDate = $job->ReceiveDate;

                if ($scheduleDate && $scheduleDate->lt($today) && !$receiveDate) {
                    $daysLate = $scheduleDate->diffInDays($today);
                    $isLate = true;
                }
            }

            // Fallback: use PI ScheduleDate if not late by process
            if (!$isLate && $product->proformaInvoice && $product->proformaInvoice->ScheduleDate) {
                $piSchedule = Carbon::parse($product->proformaInvoice->ScheduleDate);
                if ($piSchedule->lt($today)) {
                    $daysLate = $piSchedule->diffInDays($today);
                    $isLate = true;
                }
            }

            $product->daysLate = $daysLate;

            // Classification
            if ($isLate) {
                if ($daysLate >= 15) {
                    $lateDarkRed++;
                    $product->late_status = 'darkred';
                } elseif ($daysLate >= 8) {
                    $lateRed++;
                    $product->late_status = 'red';
                } elseif ($daysLate >= 1) {
                    $lateYellow++;
                    $product->late_status = 'yellow';
                }
            } else {
                $onTime++;
                $product->late_status = 'ontime';
            }
        }
        $products = $products->sortByDesc(fn($product) => $product->daysLate ?? 0)->values();
        // 🔁 Log lateness summary for each product
        // foreach ($products as $product) {
        //     Log::info("🕓 Product {$product->ProductNumber} | Status: {$product->late_status} | Days Late: {$product->daysLate}");
        // }

        // Extract years/months for filter options
        $invoiceDates = $products->pluck('proformaInvoice.created_at')->filter();
        $years = $invoiceDates
            ->map(fn($date) => Carbon::parse($date)->year)
            ->unique()
            ->sort()
            ->values();

        $availableMonths = collect();
        foreach ($years as $year) {
            for ($month = 1; $month <= 12; $month++) {
                $availableMonths->push(Carbon::createFromDate($year, $month, 1)->format('Y-m'));
            }
        }

        return view('dashboard.detail', compact(
            'products', 'groupBy', 'sourceName',
            'onTime', 'lateYellow', 'lateRed', 'lateDarkRed', 'availableMonths'
        ));
    }



    public function productProcess()
    {
        $processOrder = ['Casting', 'Stamping', 'Trimming', 'Polishing', 'Setting', 'Plating'];
        $allProducts = Product::with('jobControls', 'proformaInvoice.user')->get();

        $groupedProducts = [
            'รอดำเนินการ' => [],
            'หล่อ' => [],
            'ปั้ม' => [],
            'แต่ง' => [],
            'ขัด' => [],
            'ฝัง' => [],
            'ชุบ' => [],
        ];

        $totalLate = 0;

        foreach ($allProducts as $product) {
            if ($product->Status === 'Finish') continue;
            $latestProcess = null;
            $jobControls = $product->jobControls->keyBy('Process');

            // 🔍 DEBUG: Log all process status for this product
            //Log::info("🔍 Product #{$product->ProductNumber}");
            foreach ($processOrder as $process) {
                $job = $jobControls[$process] ?? null;
                if ($job) {
                    $assign = $job->AssignDate ?? '-';
                    $schedule = $job->ScheduleDate ?? '-';
                    $receive = $job->ReceiveDate ?? '-';
                    //Log::info("↪️ Process: $process | Assign: $assign | Schedule: $schedule | Receive: $receive");
                }
            }

            // ✅ Determine latest process by reversing process order
            foreach (array_reverse($processOrder) as $process) {
                if (!empty($jobControls[$process]?->AssignDate)) {
                    $latestProcess = $process;
                    break;
                }
            }

            $bgClass = 'bg-gray-50';
            $daysLate = 0;

            if ($latestProcess && isset($jobControls[$latestProcess])) {
                $job = $jobControls[$latestProcess];
                $today = \Carbon\Carbon::today();
                $scheduleDate = $job->ScheduleDate ? \Carbon\Carbon::parse($job->ScheduleDate) : null;
                $receiveDate = $job->ReceiveDate;

                if ($scheduleDate && $scheduleDate->lt($today) && is_null($receiveDate)) {
                    $daysLate = $scheduleDate->diffInDays($today);

                    if ($daysLate >= 15) {
                        $bgClass = 'bg-red-400';
                    } elseif ($daysLate >= 8) {
                        $bgClass = 'bg-red-200';
                    } elseif ($daysLate >= 1) {
                        $bgClass = 'bg-yellow-100';
                    }

                    $totalLate++;

                    //Log::warning("⛔ LATE → Product #{$product->ProductNumber} | Process: $latestProcess | Days late: $daysLate | bgClass: $bgClass");
                }
            }

            $product->bgClass = $bgClass;
            $product->daysLate = $daysLate;

            $targetGroup = match ($latestProcess) {
                'Casting' => 'หล่อ',
                'Stamping' => 'ปั้ม',
                'Trimming' => 'แต่ง',
                'Polishing' => 'ขัด',
                'Setting' => 'ฝัง',
                'Plating' => 'ชุบ',
                default => 'รอดำเนินการ',
            };

            $groupedProducts[$targetGroup][] = $product;
        }

        // Sort each group by lateness descending
        foreach ($groupedProducts as $stage => $products) {
            usort($products, fn($a, $b) => $b->daysLate <=> $a->daysLate);
            $groupedProducts[$stage] = $products;
        }

        //Log::info("🛑 Total late products: $totalLate");

        return view('dashboard.product-process', compact('groupedProducts'));
    }

    public function list($pi_id)
    {
        $pi = ProformaInvoice::with(['products.jobControls.factory','user','salesPerson'])->findOrFail($pi_id);
        $processOrder = ['Casting', 'Stamping', 'Trimming', 'Polishing', 'Setting', 'Plating'];
        $processNameMap = [
            'Casting'   => 'หล่อ',
            'Stamping'  => 'ปั๊ม',
            'Trimming'  => 'แต่ง',
            'Polishing' => 'ขัด',
            'Setting'   => 'ฝัง',
            'Plating'   => 'ชุบ',
        ];
        $lateYellow = 0;
        $lateRed = 0;
        $lateDarkRed = 0;
        foreach ($pi->products as $product) {
            $jobControls = $product->jobControls->keyBy('Process');
            $processOrder = ['Casting', 'Stamping', 'Trimming', 'Polishing', 'Setting', 'Plating'];

            $processList = [];
            $latestProcessName = null;
            $latestLateClass = 'bg-gray-100 text-gray-800'; // default

            $latestProcess = null;

            foreach ($processOrder as $process) {
                $jc = $jobControls[$process] ?? null;

                if (!empty($jc?->AssignDate)) {
                    $schedule = $jc->ScheduleDate ? Carbon::parse($jc->ScheduleDate)->startOfDay() : null;
                    $reference = $jc->ReceiveDate ? Carbon::parse($jc->ReceiveDate)->startOfDay() : Carbon::today();
                    $diff = $schedule ? abs($reference->diffInDays($schedule)) : '-';

                    // Normal late class logic for each process (even if ReceiveDate is null)
                    $lateClass = 'bg-gray-100 text-gray-800';

                    if ($schedule && $reference->gt($schedule)) {
                        $lateDays = $schedule->diffInDays($reference);
                        if ($lateDays > 15) {
                            $lateClass = 'bg-red-400 text-white border-red-800';
                        } elseif ($lateDays > 7) {
                            $lateClass = 'bg-red-200 text-red-800 border-red-400';
                        } elseif ($lateDays >= 1) {
                            $lateClass = 'bg-yellow-100 text-yellow-800 border-yellow-400';
                        }
                    }

                    $thaiName = $processNameMap[$process] ?? $process;
                    $factoryName = $jc->factory->FactoryName ?? ''; // 👈 use relation
                    $displayName = $factoryName ? "$thaiName / $factoryName" : $thaiName;

                    $processList[] = [
                        'name' => $displayName,
                        'days' => $diff,
                        'lateClass' => $lateClass,
                    ];


                    // Track the latest (last filled) process
                    $latestProcess = $jc;
                    $latestProcessName = $process;
                }
            }

            // ✅ Only for the tag color in front of card
            if ($latestProcess && $latestProcess->ScheduleDate && is_null($latestProcess->ReceiveDate)) {
                $schedule = Carbon::parse($latestProcess->ScheduleDate)->startOfDay();

                if (Carbon::today()->gt($schedule)) {
                    $lateDays = $schedule->diffInDays(Carbon::today());

                    if ($lateDays > 15) {
                        $latestLateClass = 'bg-red-400 text-white border-red-800';
                    } elseif ($lateDays > 7) {
                        $latestLateClass = 'bg-red-200 text-red-800 border-red-400';
                    } elseif ($lateDays >= 1) {
                        $latestLateClass = 'bg-yellow-100 text-yellow-800 border-yellow-400';
                    }
                }
            }
            $product->latenessLevel = 0;
            if (str_contains($latestLateClass, 'bg-yellow-100')) {
                $lateYellow++;
                $product->latenessLevel = 1;
            } elseif (str_contains($latestLateClass, 'bg-red-200')) {
                $lateRed++;
                $product->latenessLevel = 2;
            } elseif (str_contains($latestLateClass, 'bg-red-400')) {
                $lateDarkRed++;
                $product->latenessLevel = 3;
            }
            $pi->products = $pi->products->sortByDesc('latenessLevel')->values();
            $product->processDays = $processList;
            $product->latestProcessName = $latestProcessName;
            $product->latestProcessLateClass = $latestLateClass;
        }

        return view('products.list', compact('pi', 'lateYellow', 'lateRed', 'lateDarkRed'));
    }

    public function detail($pi_id, $product_id)
    {
        $pi = ProformaInvoice::findOrFail($pi_id);
        $product = $pi->products()->where('id', $product_id)->firstOrFail();

        return view('products.detail', compact('pi', 'product'));
    }


}
