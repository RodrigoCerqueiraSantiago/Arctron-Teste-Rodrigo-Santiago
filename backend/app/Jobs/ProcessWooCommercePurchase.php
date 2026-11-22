<?php

namespace App\Jobs;

use App\Services\PurchaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWooCommercePurchase implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(private array $purchase) {}

    public function handle(PurchaseService $service): void
    {
        [$purchase, $created] = $service->store($this->purchase);
        Log::info('woocommerce.purchase.processed', ['reference' => $purchase->reference, 'created' => $created]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('woocommerce.purchase.failed', [
            'reference' => $this->purchase['reference'],
            'exception' => $exception::class,
        ]);
    }
}
