<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JobControl;

class JobControlController extends Controller
{
    public function storeOrUpdate(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'process' => 'required|string',
            'Billnumber' => 'nullable|string',
            'factory_id' => 'nullable|exists:factories,id',
            'QtyOrder' => 'nullable|numeric',
            'QtyReceive' => 'nullable|numeric',
            'TotalWeightBefore' => 'nullable|numeric',
            'TotalWeightAfter' => 'nullable|numeric',
            'AssignDate' => 'nullable|date',
            'ScheduleDate' => 'nullable|date',
            'ReceiveDate' => 'nullable|date',
        ]);

        $jobControl = JobControl::updateOrCreate(
            [
                'product_id' => $validated['product_id'],
                'Process' => $validated['process'],
            ],
            [
                'Billnumber' => $validated['Billnumber'],
                'factory_id' => $validated['factory_id'],
                'QtyOrder' => $validated['QtyOrder'],
                'QtyReceive' => $validated['QtyReceive'],
                'TotalWeightBefore' => $validated['TotalWeightBefore'],
                'TotalWeightAfter' => $validated['TotalWeightAfter'],
                'AssignDate' => $validated['AssignDate'],
                'ScheduleDate' => $validated['ScheduleDate'],
                'ReceiveDate' => $validated['ReceiveDate'],
            ]
        );

        return back()->with('success', 'JobControl saved successfully.');
    }
}
