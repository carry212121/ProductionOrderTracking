<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobControl extends Model
{
    protected $fillable = [
        'Billnumber',
        'Process',
        'QtyOrder',
        'QtyReceive',
        'TotalWeightBefore',
        'TotalWeightAfter',
        'AssignDate',
        'ScheduleDate',
        'ReceiveDate',
        'Days',
        'Status',
        'product_id',
        'factory_id',
    ];

    protected $casts = [
        'Factory' => 'array',
        'Processes' => 'array',
        'AssignDate' => 'datetime',
        'ScheduleDate' => 'datetime',
        'ReceiveDate' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function factory()
    {
        return $this->belongsTo(Factory::class);
    }

}
