<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'ProductNumber',
        'Description',
        'ProductCustomerNumber',
        'Weight',
        'Quantity',
        'UnitPrice',
        'Image',
        'Status',
        'proforma_invoice_id',
    ];

    public function jobControls()
    {
        return $this->hasMany(JobControl::class);
    }

    public function proformaInvoice()
    {
        return $this->belongsTo(ProformaInvoice::class);
    }
}
