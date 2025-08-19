<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProformaInvoice extends Model
{
    protected $casts = [
        'OrderDate'    => 'date',
        'ScheduleDate' => 'date',
    ];
    protected $fillable = [
        'PInumber',
        'byOrder',
        'CustomerID',
        'CustomerPO',
        'CustomerInstruction',
        'FOB',
        'FreightPrepaid',
        'InsurancePrepaid',
        'Deposit',
        'OrderDate',
        'ScheduleDate',
        'CompletionDate',
        'SalesPerson',
        'user_id',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function salesPerson()
    {
        return $this->belongsTo(User::class, 'SalesPerson', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
