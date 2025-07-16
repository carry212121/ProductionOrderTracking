<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProformaInvoice;
use App\Models\User;
use App\Models\Factory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ProformaInvoiceController extends Controller
{
    public function SummaryPI(Request $request)
    {
        $selectedMonth = $request->get('month');

        $proformaInvoicesQuery = ProformaInvoice::with('products.jobControls');

        if ($selectedMonth) {
            $parsedMonth = Carbon::parse($selectedMonth);
            $proformaInvoicesQuery->whereMonth('OrderDate', $parsedMonth->month)
                                ->whereYear('OrderDate', $parsedMonth->year);
            Log::info("📅 Filtering for month: " . $parsedMonth->format('F Y'));
        }

        $proformaInvoices = $proformaInvoicesQuery->get();
        Log::info("📦 Total PIs fetched: " . $proformaInvoices->count());

        $total = $proformaInvoices->count();

        $today = \Carbon\Carbon::today();
        $processOrder = ['Casting', 'Stamping', 'Trimming', 'Polishing', 'Setting', 'Plating'];

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
                    $scheduleDate = \Carbon\Carbon::parse($job->ScheduleDate);
                    $receiveDate = $job->ReceiveDate;
      
                    Log::info("🔍 PI #{$pi->id} | Product #{$product->id} | Process: $latestProcess | Schedule: $scheduleDate | Receive: " . ($receiveDate ?? 'null'));

                    if ($scheduleDate->lt($today) && $receiveDate === null) {
                        $isLate = true;
                        $daysLate = $scheduleDate->diffInDays($today);
                        $maxDaysLate = max($maxDaysLate, $daysLate); // Track latest overdue

                        Log::warning("⛔ LATE → PI #{$pi->id} | Product #{$product->id} | Days late: $daysLate");
                    }
                }
            }

            if ($isLate) {
                if ($maxDaysLate >= 15) {
                    $lateDarkRed++;
                } elseif ($maxDaysLate >= 8) {
                    $lateRed++;
                } elseif ($maxDaysLate >= 1) {
                    $lateYellow++;
                }
            } else {
                $onTime++;
            }
        }

        $late = $lateYellow + $lateRed + $lateDarkRed;

        Log::info("✅ On-time PIs: $onTime");
        Log::info("⛔ Late PIs: {$lateYellow} (1-7 days), {$lateRed} (8-14 days), {$lateDarkRed} (15+ days)");

        $groupBy = $request->get('groupBy', 'factory');
        Log::info("📊 Grouping by: $groupBy");

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
                            $scheduleDate = \Carbon\Carbon::parse($job->ScheduleDate);
                            $receiveDate = $job->ReceiveDate;

                            if ($scheduleDate->lt($today) && $receiveDate === null) {
                                $isLate = true;
                                $daysLate = $scheduleDate->diffInDays($today);
                                $maxDaysLate = max($maxDaysLate, $daysLate);
                            }
                        }
                    }

                    if ($isLate) {
                        if ($maxDaysLate >= 15) $darkRed++;
                        elseif ($maxDaysLate >= 8) $red++;
                        elseif ($maxDaysLate >= 1) $yellow++;
                    } else {
                        $onTimeCount++;
                    }
                }

                $barChartLabels[] = $label;
                $barChartOnTime[] = $onTimeCount;
                $barChartLateYellow[] = $yellow;
                $barChartLateRed[] = $red;
                $barChartLateDarkRed[] = $darkRed;

                Log::info("👤 $label → ✅ On-time: $onTimeCount | 🟡 Yellow: $yellow | 🔴 Red: $red | 🔥 DarkRed: $darkRed");
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
                            $scheduleDate = \Carbon\Carbon::parse($job->ScheduleDate);
                            $receiveDate = $job->ReceiveDate;

                            if ($scheduleDate->lt($today) && $receiveDate === null) {
                                $isLate = true;
                                $daysLate = $scheduleDate->diffInDays($today);
                                $maxDaysLate = max($maxDaysLate, $daysLate);
                            }
                        }
                    }

                    if ($isLate) {
                        if ($maxDaysLate >= 15) $darkRed++;
                        elseif ($maxDaysLate >= 8) $red++;
                        elseif ($maxDaysLate >= 1) $yellow++;
                    } else {
                        $onTimeCount++;
                    }
                }

                $barChartLabels[] = $label;
                $barChartOnTime[] = $onTimeCount;
                $barChartLateYellow[] = $yellow;
                $barChartLateRed[] = $red;
                $barChartLateDarkRed[] = $darkRed;

                Log::info("🏭 $label → ✅ On-time: $onTimeCount | 🟡 Yellow: $yellow | 🔴 Red: $red | 🔥 DarkRed: $darkRed");
            }
        }

        Log::info("📊 Final bar chart labels: " . implode(', ', $barChartLabels));
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
            'selectedMonth', 'groupBy', 'grouped',
            'barChartLabels', 'barChartOnTime',
            'barChartLateYellow', 'barChartLateRed', 'barChartLateDarkRed',
            'page', 'totalPages'
        ));

    }

    public function index(Request $request)
    {
        $user = Auth::user();

        $pis = ProformaInvoice::with(['user', 'products'])  // eager-load relationships
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return view('proformaInvoice.index', compact('pis'));
    }

    public function show($id)
    {
        $pi = ProformaInvoice::with(['products'])->findOrFail($id);

        return view('proformaInvoice.detail', compact('pi'));
    }


}
