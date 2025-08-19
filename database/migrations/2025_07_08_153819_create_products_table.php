<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('ProductNumber')->nullable();
            $table->string('Description')->nullable();
            $table->string('ProductCustomerNumber')->nullable();
            $table->float('Weight', 10, 2)->nullable();
            $table->string('Quantity')->nullable();
            $table->float('UnitPrice', 10, 2)->nullable();
            $table->string('Image')->nullable();
            $table->enum('Status', ['Pending', 'InProgress', 'Finish'])->default('Pending');

            $table->foreignId('proforma_invoice_id')->constrained('proforma_invoices')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
