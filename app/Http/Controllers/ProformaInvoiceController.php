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

        $onTime = $proformaInvoices->filter(function ($pi) {
            return $pi->CompletionDate && $pi->ScheduleDate && $pi->CompletionDate <= $pi->ScheduleDate;
        })->count();

        $late = $total - $onTime;

        Log::info("✅ On-time PIs: $onTime");
        Log::info("⛔ Late PIs: $late");

        $groupBy = $request->get('groupBy', 'factory');
        Log::info("📊 Grouping by: $groupBy");

        $grouped = $groupBy === 'production'
            ? User::where('role', 'Production')->pluck('name')
            : Factory::pluck('FactoryName');

        $barChartLabels = [];
        $barChartOnTime = [];
        $barChartLate = [];

        if ($groupBy === 'production') {
            $users = User::where('role', 'Production')->get();
            foreach ($users as $user) {
                $label = $user->name;

                $pisForUser = $proformaInvoices->filter(function ($pi) use ($user) {
                    return $pi->user_id === $user->id;
                });

                $onTimeCount = $pisForUser->filter(function ($pi) {
                    return $pi->CompletionDate && $pi->ScheduleDate && $pi->CompletionDate <= $pi->ScheduleDate;
                })->count();

                $lateCount = $pisForUser->count() - $onTimeCount;

                $barChartLabels[] = $label;
                $barChartOnTime[] = $onTimeCount;
                $barChartLate[] = $lateCount;

                Log::info("👤 $label → On-time: $onTimeCount | Late: $lateCount");
            }
        } else {
            $factories = Factory::all();
            foreach ($factories as $factory) {
                $label = $factory->FactoryName;

                $pisForFactory = $proformaInvoices->filter(function ($pi) use ($factory) {
                    foreach ($pi->products as $product) {
                        foreach ($product->jobControls as $job) {
                            if ($job->factory_id == $factory->id) {
                                return true;
                            }
                        }
                    }
                    return false;
                });

                $onTimeCount = $pisForFactory->filter(function ($pi) {
                    return $pi->CompletionDate && $pi->ScheduleDate && $pi->CompletionDate <= $pi->ScheduleDate;
                })->count();

                $lateCount = $pisForFactory->count() - $onTimeCount;

                $barChartLabels[] = $label;
                $barChartOnTime[] = $onTimeCount;
                $barChartLate[] = $lateCount;

                Log::info("🏭 $label → On-time: $onTimeCount | Late: $lateCount");
            }
        }

        Log::info("📊 Final bar chart labels: " . implode(', ', $barChartLabels));
        Log::info("✅ Final bar chart on-time: " . implode(', ', $barChartOnTime));
        Log::info("⛔ Final bar chart late: " . implode(', ', $barChartLate));

        return view('dashboard', compact(
            'total', 'onTime', 'late', 'selectedMonth',
            'groupBy', 'grouped',
            'barChartLabels', 'barChartOnTime', 'barChartLate'
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
