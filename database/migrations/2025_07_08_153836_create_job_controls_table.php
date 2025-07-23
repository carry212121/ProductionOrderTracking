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
        Schema::create('job_controls', function (Blueprint $table) {
            $table->id();
            $table->string('Billnumber')->nullable();
            $table->enum('Process', ['Casting', 'Stamping', 'Trimming', 'Polishing', 'Setting', 'Plating'])->default('Casting');
            $table->integer('QtyOrder')->nullable();
            $table->integer('QtyReceive')->nullable();
            // $table->float('TotalWeightBefore', 10, 2)->nullable();
            // $table->float('TotalWeightAfter', 10, 2)->nullable();
            $table->dateTime('AssignDate')->nullable();
            $table->dateTime('ScheduleDate')->nullable();
            $table->dateTime('ReceiveDate')->nullable();
            $table->integer('Days')->nullable();
            $table->enum('Status', ['Pending', 'InProgress', 'Finish'])->default('Pending');

            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('factory_id')->nullable()->constrained('factories')->onDelete('set null');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_controls');
    }
};
