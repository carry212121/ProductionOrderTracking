<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ProformaInvoice;
use App\Models\Product;
use App\Models\JobControl;
use App\Models\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create 10 factories
        $factories = collect();
        for ($i = 1; $i <= 20; $i++) {
            $factories->push(Factory::create([
                'FactoryName' => "Factory $i",
                'FactoryNumber' => "F-$i"
            ]));
        }

        // 2. Create 10 Production Users
        $productionUsers = collect();
        for ($i = 1; $i <= 10; $i++) {
            $productionUsers->push(User::create([
                'name' => "Production User $i",
                'username' => "production$i",
                'password' => Hash::make("production123"),
                'productionID' => "P00$i",
                'role' => 'Production',
            ]));
        }

        $allNextProcesses = ['Trimming', 'Polishing', 'Setting', 'Plating'];

        // 3. Create 100 PIs
        for ($i = 1; $i <= 100; $i++) {
            $user = $productionUsers->random();

            $pi = ProformaInvoice::create([
                'PInumber' => "PI-" . str_pad($i, 4, '0', STR_PAD_LEFT),
                'byOrder' => 'Order-' . $i,
                'CustomerID' => 'CUST-' . rand(1000, 9999),
                'CustomerPO' => 'PO-' . Str::random(5),
                'CustomerInstruction' => 'Handle with care',
                'FOB' => rand(100, 500),
                'FreightPrepaid' => rand(50, 200),
                'InsurancePrepaid' => rand(10, 50),
                'Deposit' => rand(100, 300),
                'OrderDate' => Carbon::now()->subDays(rand(1, 60)),
                'ScheduleDate' => null,
                'CompletionDate' => null,
                'SalesPerson' => 2,
                'user_id' => $user->id,
            ]);

            Log::info("🔁 Generating data for PI: {$pi->PInumber}");

            $allReceiveDates = [];
            $totalDays = [];

            for ($j = 1; $j <= 5; $j++) {
                $productNumber = 'PRD-' . str_pad(($i - 1) * 5 + $j, 4, '0', STR_PAD_LEFT);
                $product = Product::create([
                    'ProductNumber' => $productNumber,
                    'Description' => "Product " . (($i - 1) * 5 + $j),
                    'ProductCustomerNumber' => 'PCN-' . rand(100, 999),
                    'Weight' => rand(10, 100),
                    'Quantity' => rand(1, 20),
                    'UnitPrice' => rand(200, 1000),
                    'Image' => null,
                    'Status' => 'Pending',
                    'proforma_invoice_id' => $pi->id,
                ]);

                Log::info("📦 JobControls for Product {$product->ProductNumber}:");

                $startProcess = rand(0, 1) === 0 ? 'Casting' : 'Stamping';
                $processSequence = [$startProcess];
                $remaining = collect($allNextProcesses)->shuffle()->take(rand(2, 4))->toArray();
                $processSequence = array_merge($processSequence, $remaining);

                $daysForThisProduct = 0;
                $jcList = [];

                foreach ($processSequence as $idx => $process) {
                    // ReceiveDate: random from past 30 days
                    $receiveDate = Carbon::now()->subDays(rand(0, 30));

                    // AssignDate: 50% chance before/after receive date
                    $assignDate = rand(0, 1)
                        ? $receiveDate->copy()->subDays(rand(1, 5))
                        : $receiveDate->copy()->addDays(rand(1, 5));

                    // ScheduleDate: 50% chance before/after receive date
                    $scheduleDate = rand(0, 1)
                        ? $receiveDate->copy()->subDays(rand(1, 3))
                        : $receiveDate->copy()->addDays(rand(1, 3));

                    // Duration for reference use (not needed for assigning dates here)
                    $duration = match ($process) {
                        'Casting' => 10,
                        'Stamping' => 14,
                        default => 7,
                    };
                    // --- 2️⃣  For the *last* process decide whether to null‑out ReceiveDate
                    $isLastProcess   = $idx === array_key_last($processSequence);
                    $dropReceiveDate = $isLastProcess && rand(0, 1) === 1;   // 50 % chance
                    $finalReceive    = $dropReceiveDate ? null : $receiveDate->copy();
                    // Calculate lateness (only if receive after schedule)
                    // --- 3️⃣  Calculate lateness ------------------------------------
                    $daysLate = 0;

                    if ($finalReceive) {                     // we do have a receive date
                        if ($finalReceive->gt($scheduleDate)) {
                            $daysLate = $finalReceive->diffInDays($scheduleDate);
                        }
                    } else {                                 // ReceiveDate is NULL → use today
                        $today = Carbon::today();
                        if ($today->gt($scheduleDate)) {
                            $daysLate = $today->diffInDays($scheduleDate);
                        }
                    }

                    $jcList[] = [
                        'Billnumber' => 'BILL-' . strtoupper(Str::random(4)),
                        'Process' => $process,
                        'QtyOrder' => rand(10, 50),
                        'QtyReceive' => rand(5, 50),
                        'TotalWeightBefore' => rand(100, 500),
                        'TotalWeightAfter' => rand(80, 450),
                        'AssignDate' => $assignDate->copy(),
                        'ScheduleDate' => $scheduleDate->copy(),
                        'ReceiveDate' => $finalReceive,
                        'Days' => $daysLate,
                        'Status' => 'Finish',
                        'product_id' => $product->id,
                        'factory_id' => $factories->random()->id,
                    ];

                    $daysForThisProduct += $duration;
                }

                // 💥 70% chance to simulate incomplete product
                $incomplete = rand(1, 100) <= 70;
                $incompleteIndex = $incomplete ? rand(0, count($jcList) - 1) : null;

                foreach ($jcList as $idx => $jcData) {
                    if ($incomplete && $idx >= $incompleteIndex) {
                        Log::info("❌ Skipping JobControl at index $idx for Product {$product->ProductNumber} (simulate incomplete)");
                        continue;
                    }

                    $jc = JobControl::create($jcData);

                    Log::info("🔧 Process: {$jc->Process} | Assign: {$jc->AssignDate} | Schedule: {$jc->ScheduleDate} | Receive: {$jc->ReceiveDate}");
                    if ($jc->ReceiveDate) {
                        $allReceiveDates[] = $jc->ReceiveDate;
                    }
                }

                $totalDays[] = $incomplete ? 0 : $daysForThisProduct;
            }


            $expectedJCCount = 0;
            $actualJCCount = JobControl::whereHas('product', function ($q) use ($pi) {
                $q->where('proforma_invoice_id', $pi->id);
            })->count();

            foreach ($pi->products as $product) {
                $expectedJCCount += $product->expected_process_count ?? 0;
            }

            // ✅ Determine completion by matching counts
            $hasAllJobControls = $expectedJCCount > 0 && $expectedJCCount === $actualJCCount;

            if ($hasAllJobControls && !empty($allReceiveDates)) {
                $latestReceive = collect($allReceiveDates)->max();
                Log::info("📌 PI {$pi->PInumber} → All JobControls complete. Latest ReceiveDate: $latestReceive");
                $pi->update(['CompletionDate' => $latestReceive]);
                Log::info("✅ PI {$pi->PInumber} → CompletionDate set to $latestReceive");
            } else {
                Log::info("⚠️ PI {$pi->PInumber} → Incomplete JobControl(s) (expected $expectedJCCount, got $actualJCCount). CompletionDate set to null.");
                $pi->update(['CompletionDate' => null]);
            }


            $maxDays = max($totalDays);
            $scheduleDate = $pi->OrderDate->copy()->addDays($maxDays);
            $pi->update(['ScheduleDate' => $scheduleDate]);
            Log::info("📅 PI {$pi->PInumber} → ScheduleDate set to $scheduleDate (Total days: $maxDays)");
        }

    }
}
