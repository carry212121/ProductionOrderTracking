<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\JobControl;
use App\Models\User;
use App\Notifications\LateProductJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class NotifyLateProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:notify-late-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify production about late jobs with severity stages';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = now();
        Log::info("📅 Running late product check at: {$today}");

        $lateJobs = JobControl::with('product') // eager load
            ->whereDate('ScheduleDate', '<', now()->startOfDay())
            ->where('Status', '!=', 'Finish')
            ->get();

        Log::info("🔍 Found late jobs count: {$lateJobs->count()}");

        if ($lateJobs->isEmpty()) {
            $this->info("✅ No late jobs found.");
            return;
        }

        $productionUsers = User::where('role', 'production')->get();

        foreach ($lateJobs as $job) {
            // Ensure we're using Carbon instances
            $scheduleDate = Carbon::parse($job->ScheduleDate);

            if ($scheduleDate->greaterThanOrEqualTo(now()->startOfDay())) {
                continue;
            }

            $daysLate = $scheduleDate->diffInDays(now()); // Now guaranteed to be positive
            $severity = match (true) {
                $daysLate >= 14 => 'darkred',
                $daysLate >= 7 => 'red',
                $daysLate >= 1 => 'yellow',
                default => null,
            };

            Log::info("⏱️ Late Job Detected: Product {$job->product->ProductNumber} | Process: {$job->Process} | Days Late: {$daysLate} | Severity: {$severity}");

            if (!$severity) {
                continue;
            }
            //Log::info("⏱️ Late Job Detected: Product {$job->product->ProductNumber} | Process: {$job->Process} | Days Late: {$daysLate} | Severity: {$severity}");
            foreach ($productionUsers as $user) {
                $productNumber = $job->product->ProductNumber;
                $process = $job->Process;

                // Check for existing notification with same severity
                $existingNotifications = DB::table('notifications')
                    ->where('notifiable_id', $user->id)
                    ->where('notifiable_type', User::class)
                    ->where('type', LateProductJob::class)
                    ->get();

                $alreadySent = $existingNotifications->contains(function ($notification) use ($productNumber, $process) {
                    $data = json_decode($notification->data, true);

                    $match = $data['product_number'] === $productNumber &&
                            $data['process'] === $process;

                    if ($match) {
                        //Log::info("⛔ Duplicate found → Product: $productNumber | Process: $process");
                    } else {
                        //Log::debug("❌ No match → Existing: " . json_encode($data));
                    }

                    return $match;
                });





                if ($alreadySent) {
                    Log::info("⏩ Already notified [{$severity}] for $productNumber / $process to user {$user->name}");
                    continue;
                }

                Log::info("📤 Notifying [{$severity}] to {$user->name} for $productNumber / $process");
                $user->notify(new LateProductJob($job));
            }
        }

        $this->info("🔔 Late product notifications sent.");
    }
}
