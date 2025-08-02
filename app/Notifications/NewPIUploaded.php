<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use App\Models\User;
use App\Models\ProformaInvoice;
use Illuminate\Support\Facades\Log;

class NewPIUploaded extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    protected $invoice;
    protected $uploader;

    public function __construct(User $uploader, ProformaInvoice $invoice)
    {
        Log::info('🚀 Notification constructed for uploader: ' . $uploader->name);
        $this->uploader = $uploader;
        $this->invoice = $invoice;
    }

    public function via(object $notifiable): array
    {
        return ['broadcast', 'database'];
    }

    public function toArray(object $notifiable): array
    {
        $totalAmount = $this->invoice->products->reduce(function ($sum, $product) {
            return $sum + ((float) $product->Quantity * (float) $product->UnitPrice);
        }, 0);

        return [
            'title' => 'มีการอัปโหลด PI ใหม่',
            'message' => "มีการอัปโหลด {$this->invoice->PInumber} {$this->invoice->byOrder} รวมยอดเงิน: " . number_format($totalAmount, 2),
            'url' => route('proformaInvoice.index'),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $totalAmount = $this->invoice->products->reduce(function ($sum, $product) {
            return $sum + ((float) $product->Quantity * (float) $product->UnitPrice);
        }, 0);

        return new BroadcastMessage([
            'title' => 'มีการอัปโหลด PI ใหม่',
            'message' => "มีการอัปโหลด {$this->invoice->PInumber} {$this->invoice->byOrder} รวมยอดเงิน: " . number_format($totalAmount, 2),
            'url' => route('proformaInvoice.index'),
        ]);
    }
}
