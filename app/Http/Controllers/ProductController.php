<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\JobControl;
use App\Models\Factory;
use App\Models\User;
use Carbon\Carbon;

class ProductController extends Controller
{
    public function toggleStatus(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $product->Status = $request->status;
        $product->save();

        return back()->with('success', 'สถานะของสินค้าได้รับการอัปเดตแล้ว');
    }
    public function show(Request $request, $id)
    {
        $groupBy = $request->query('groupBy', 'factory'); // 'factory' or 'production'

        if ($groupBy === 'production') {
            $products = Product::with('proformaInvoice.user')
                ->whereHas('proformaInvoice', function ($query) use ($id) {
                    $query->where('user_id', $id);
                })->get();

            $sourceName = User::find($id)?->name ?? 'ไม่ทราบชื่อ';
        } else {
            $jobControls = JobControl::with(['product.proformaInvoice', 'factory'])
                ->where('factory_id', $id)
                ->get();

            $products = $jobControls->pluck('product')->filter()->unique('id')->values();

            $sourceName = Factory::find($id)?->FactoryName ?? 'ไม่ทราบชื่อ';
        }
        $today = Carbon::today();
        $lateYellow = 0;
        $lateRed = 0;
        $lateDarkRed = 0;
        $onTime = 0;

        foreach ($products as $product) {
            $processOrder = ['Casting', 'Stamping', 'Trimming', 'Polishing', 'Setting', 'Plating'];
            $jobControls = $product->jobControls->keyBy('Process');

            $latestProcess = null;
            foreach ($processOrder as $process) {
                if (!empty($jobControls[$process]?->AssignDate)) {
                    $latestProcess = $process;
                }
            }

            if ($latestProcess && isset($jobControls[$latestProcess])) {
                $job = $jobControls[$latestProcess];
                $scheduleDate = $job->ScheduleDate ? Carbon::parse($job->ScheduleDate) : null;
                $receiveDate = $job->ReceiveDate;

                if ($scheduleDate && $scheduleDate->lt($today) && !$receiveDate) {
                    $daysLate = $scheduleDate->diffInDays($today);
                    if ($daysLate >= 15) $lateDarkRed++;
                    elseif ($daysLate >= 8) $lateRed++;
                    elseif ($daysLate >= 1) $lateYellow++;
                } else {
                    $onTime++;
                }
            } else {
                $onTime++; // Treat as on-time if no process info
            }
        }

        $invoiceDates = $products->pluck('proformaInvoice.created_at')->filter();

        $years = $invoiceDates
            ->map(fn($date) => \Carbon\Carbon::parse($date)->year)
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

}
