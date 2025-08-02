<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use App\Models\JobControl;
use Illuminate\Support\Facades\Log;

class LateProductJob extends Notification implements ShouldBroadcastNow
{
    public $job;

    public function __construct(JobControl $job)
    {
        $this->job = $job;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }
    protected function getThaiProcess($process)
    {
        $map = [
            'Casting' => 'หล่อ',
            'Stamping' => 'ปั้ม',
            'Trimming' => 'แต่ง',
            'Polishing' => 'ขัด',
            'Setting' => 'ฝัง',
            'Plating' => 'ชุบ',
        ];

        return $map[$process] ?? $process;
    }
    public function getSeverity(): string
    {
        $daysLate = $this->job->ScheduleDate->diffInDays(now());
        Log::info("📅 Job {$this->job->id} | ScheduleDate = {$this->job->ScheduleDate} | Days Late = $daysLate");

        return match (true) {
            $daysLate >= 14 => 'darkred',
            $daysLate >= 7 => 'red',
            $daysLate >= 1 => 'yellow',
            default => 'none',
        };
    }

    public function getSeverityLabel(): string
    {
        return match ($this->getSeverity()) {
            'yellow' => 'ล่าช้า >1 วัน',
            'red' => 'ล่าช้า >7 วัน',
            'darkred' => 'ล่าช้า >14 วัน',
            default => '',
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'งานผลิตล่าช้า',
            'message' => "ขั้นตอน {$this->getThaiProcess($this->job->Process)} ของสินค้า {$this->job->product->ProductNumber} ล่าช้า (ถึงวันกำหนด: {$this->job->ScheduleDate->format('d/m/Y')}, สถานะ: {$this->getSeverityLabel()})",
            'product_number' => $this->job->product->ProductNumber, // required for matching
            'process' => $this->job->Process,                        // required for matching
            'severity' => $this->getSeverity(),
            'url' => route('proformaInvoice.show', [
                'id' => $this->job->product->proforma_invoice_id,
                'product_id' => $this->job->product_id
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }


}
