<?php

namespace App\Jobs;

use App\Models\Role;
use App\Services\NotificationService;
use App\Services\ProductBatchService;
use App\Traits\NsSerialize;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Daily check for procurement lots about to expire (or already expired)
 * that still have stock left. Notifies administrators and store
 * administrators with a link to the batches list, where expired lots can
 * be written off. The warning window (in days) is configurable from the
 * Inventory settings tab (ns_stock_expiry_warning_days, default 7).
 */
class DetectExpiringBatchesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, NsSerialize, Queueable;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->prepareSerialization();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle( NotificationService $notificationService, ProductBatchService $batchService )
    {
        $expiring = $batchService->getExpiringBatches()->count();
        $expired = $batchService->getExpiredBatches()->count();

        if ( $expiring > 0 || $expired > 0 ) {
            $notificationService->create(
                title: __( 'Expiring Batches Alert' ),
                description: sprintf(
                    __( '%s batch(es) will expire within %s day(s) and %s batch(es) already expired. Review the batches before they are sold.' ),
                    $expiring,
                    $batchService->getExpiryWarningDays(),
                    $expired
                ),
                identifier: 'ns.expiring-batches',
                url: ns()->route( 'ns.dashboard.products.batches' ),
            )->dispatchForGroup(
                [ Role::ADMIN, Role::STOREADMIN ]
            );
        }
    }
}
