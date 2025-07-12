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
        Schema::create('proforma_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('PInumber')->unique();
            $table->string('byOrder')->nullable();
            $table->string('CustomerID')->nullable();
            $table->string('CustomerPO')->nullable();
            $table->string('CustomerInstruction')->nullable();
            $table->float('FOB', 10, 2)->nullable();
            $table->float('FreightPrepaid', 10, 2)->nullable();
            $table->float('InsurancePrepaid', 10, 2)->nullable();
            $table->float('Deposit', 10, 2)->nullable();
            $table->dateTime('OrderDate')->nullable();
            $table->dateTime('ScheduleDate')->nullable();
            $table->dateTime('CompletionDate')->nullable();

            $table->foreignId('SalesPerson')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proforma_invoices');
    }
};
