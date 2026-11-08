<?php

namespace App\Services;

use App\Exceptions\NotAllowedException;
use App\Models\ProductBatchMovement;
use App\Models\ProductHistory;
use App\Models\ProcurementProduct;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Product batches (lô hàng) & expiry handling.
 *
 * A "batch" is a procurement line (nexopos_procurements_products): every
 * goods receipt creates one lot per product/unit with a quantity, a
 * remaining quantity (available_quantity) and — when provided — an
 * expiration date. This service is the single place that consumes lots
 * following the FIFO rule (oldest receipt first, expired lots never
 * consumed) and that reports lots about to expire.
 *
 * Stock that was received before batches were tracked has no lot rows:
 * sales of such stock simply have no lot to consume (a "shortfall" that
 * is reported, not an error) and the product total quantity stays the
 * source of truth.
 */
class ProductBatchService
{
    /**
     * How many days ahead a lot counts as "expiring soon".
     * Configurable from the Inventory settings tab (ns_stock_expiry_warning_days).
     */
    public function getExpiryWarningDays(): int
    {
        try {
            return max( 0, (int) ns()->option->get( 'ns_stock_expiry_warning_days', 7 ) );
        } catch ( \Throwable ) {
            return 7;
        }
    }

    /**
     * Lots with remaining stock for a product/unit, oldest receipt first (FIFO).
     *
     * @param bool $includeExpired include lots whose expiration date passed.
     */
    public function getAvailableBatches( int $product_id, int $unit_id, bool $includeExpired = false )
    {
        $query = ProcurementProduct::where( 'product_id', $product_id )
            ->where( 'unit_id', $unit_id )
            ->available()
            ->orderBy( 'created_at', 'asc' )
            ->orderBy( 'id', 'asc' );

        if ( ! $includeExpired ) {
            $query->notExpired();
        }

        return $query->get();
    }

    /**
     * Consume stock from lots following FIFO (oldest receipt first).
     * Expired lots are never consumed.
     *
     * @return array{consumed: float, shortfall: float, allocations: array<int, array{procurement_product_id: int, quantity: float}>}
     */
    public function consumeFifo( int $product_id, int $unit_id, float|int $quantity, ?int $order_id = null, ?int $order_product_id = null, bool $includeExpired = false ): array
    {
        return DB::transaction( function () use ( $product_id, $unit_id, $quantity, $order_id, $order_product_id, $includeExpired ) {
            $remaining = abs( $quantity );
            $consumed = 0;
            $allocations = [];

            foreach ( $this->getAvailableBatches( $product_id, $unit_id, $includeExpired ) as $batch ) {
                if ( $remaining <= 0 ) {
                    break;
                }

                $take = min( (float) $batch->available_quantity, $remaining );

                $batch->available_quantity = (float) $batch->available_quantity - $take;
                $batch->save();

                ProductBatchMovement::create( [
                    'procurement_product_id' => $batch->id,
                    'product_id' => $product_id,
                    'unit_id' => $unit_id,
                    'order_id' => $order_id,
                    'order_product_id' => $order_product_id,
                    'type' => ProductBatchMovement::TYPE_CONSUME,
                    'quantity' => $take,
                ] );

                $allocations[] = [
                    'procurement_product_id' => $batch->id,
                    'quantity' => $take,
                ];

                $consumed += $take;
                $remaining -= $take;
            }

            return [
                'consumed' => $consumed,
                'shortfall' => $remaining,
                'allocations' => $allocations,
            ];
        } );
    }

    /**
     * Restore lots consumed by a given order product (void / return).
     * The same lots that were consumed get their quantities back.
     */
    public function restoreForOrderProduct( int $order_product_id ): array
    {
        return DB::transaction( function () use ( $order_product_id ) {
            $restored = 0;

            $consumed = ProductBatchMovement::where( 'order_product_id', $order_product_id )
                ->where( 'type', ProductBatchMovement::TYPE_CONSUME )
                ->selectRaw( 'procurement_product_id, product_id, unit_id, order_id, SUM(quantity) as total' )
                ->groupBy( 'procurement_product_id', 'product_id', 'unit_id', 'order_id' )
                ->get();

            foreach ( $consumed as $row ) {
                $alreadyRestored = (float) ProductBatchMovement::where( 'order_product_id', $order_product_id )
                    ->where( 'procurement_product_id', $row->procurement_product_id )
                    ->where( 'type', ProductBatchMovement::TYPE_RESTORE )
                    ->sum( 'quantity' );

                $toRestore = (float) $row->total - $alreadyRestored;

                if ( $toRestore <= 0 ) {
                    continue;
                }

                $batch = ProcurementProduct::find( $row->procurement_product_id );

                if ( ! $batch instanceof ProcurementProduct ) {
                    continue;
                }

                $batch->available_quantity = (float) $batch->available_quantity + $toRestore;
                $batch->save();

                ProductBatchMovement::create( [
                    'procurement_product_id' => $batch->id,
                    'product_id' => $row->product_id,
                    'unit_id' => $row->unit_id,
                    'order_id' => $row->order_id,
                    'order_product_id' => $order_product_id,
                    'type' => ProductBatchMovement::TYPE_RESTORE,
                    'quantity' => $toRestore,
                ] );

                $restored += $toRestore;
            }

            return [ 'restored' => $restored ];
        } );
    }

    /**
     * Reconcile lots after an absolute stock set (ACTION_SET), e.g. when a
     * stocktake is approved. Missing stock is taken out of lots FIFO
     * (including expired lots: the physical count says the stock is gone).
     * Surplus stock is intentionally left unbatched until the next goods
     * receipt creates a lot for it.
     */
    public function reconcileAfterSet( int $product_id, int $unit_id, float|int $old_quantity, float|int $new_quantity ): array
    {
        if ( $new_quantity >= $old_quantity ) {
            return [ 'consumed' => 0, 'shortfall' => 0, 'allocations' => [] ];
        }

        return $this->consumeFifo(
            product_id: $product_id,
            unit_id: $unit_id,
            quantity: (float) $old_quantity - (float) $new_quantity,
            includeExpired: true
        );
    }

    /**
     * Write off an expired lot: remove its remaining quantity from the
     * product stock (ACTION_REMOVED on the lot itself) so both the product
     * total and the lot reach zero.
     */
    public function writeOffExpired( ProcurementProduct $batch, int $author = 0 ): ProductHistory|EloquentCollection|bool
    {
        $available = (float) $batch->available_quantity;

        if ( $available <= 0 ) {
            throw new NotAllowedException( __( 'This batch has no remaining quantity to write off.' ) );
        }

        if ( ! $this->isExpired( $batch ) ) {
            throw new NotAllowedException( __( 'Only expired batches can be written off.' ) );
        }

        /**
         * @var ProductService $productService
         */
        $productService = app()->make( ProductService::class );

        return $productService->stockAdjustment( ProductHistory::ACTION_REMOVED, [
            'product_id' => $batch->product_id,
            'unit_id' => $batch->unit_id,
            'unit_price' => $batch->purchase_price,
            'quantity' => $available,
            'procurementProduct' => $batch,
            'author' => $author,
            'description' => sprintf( __( 'Write-off of expired batch #%s' ), $batch->id ),
        ] );
    }

    /**
     * Lots with remaining stock expiring within the warning window.
     */
    public function getExpiringBatches( ?int $days = null )
    {
        $days = $days ?? $this->getExpiryWarningDays();

        return ProcurementProduct::available()
            ->expiringWithin( $days )
            ->orderBy( 'expiration_date', 'asc' )
            ->get();
    }

    /**
     * Lots with remaining stock whose expiration date already passed.
     */
    public function getExpiredBatches()
    {
        return ProcurementProduct::available()
            ->expired()
            ->orderBy( 'expiration_date', 'asc' )
            ->get();
    }

    public function isExpired( mixed $batch ): bool
    {
        $expiration = $this->readExpiration( $batch );

        return $expiration instanceof Carbon && $expiration->lessThanOrEqualTo( Carbon::now() );
    }

    public function isExpiringSoon( mixed $batch, ?int $days = null ): bool
    {
        $expiration = $this->readExpiration( $batch );

        if ( ! $expiration instanceof Carbon ) {
            return false;
        }

        $days = $days ?? $this->getExpiryWarningDays();

        return $expiration->greaterThan( Carbon::now() )
            && $expiration->lessThanOrEqualTo( Carbon::now()->addDays( $days ) );
    }

    /**
     * Label used by the batches list: Expired / Expiring Soon / OK / Depleted.
     * Accepts an Eloquent model, a CrudEntry or an array.
     */
    public function getBatchStatusLabel( mixed $batch ): string
    {
        $available = (float) $this->readValue( $batch, 'available_quantity', 0 );

        if ( $available <= 0 ) {
            return __( 'Depleted' );
        }

        if ( $this->isExpired( $batch ) ) {
            return __( 'Expired' );
        }

        if ( $this->isExpiringSoon( $batch ) ) {
            return __( 'Expiring Soon' );
        }

        return __( 'OK' );
    }

    private function readExpiration( mixed $batch ): ?Carbon
    {
        $value = $this->readValue( $batch, 'expiration_date' );

        if ( $value instanceof Carbon ) {
            return $value;
        }

        if ( is_string( $value ) && $value !== '' ) {
            return Carbon::parse( $value );
        }

        return null;
    }

    private function readValue( mixed $batch, string $key, mixed $default = null ): mixed
    {
        if ( is_array( $batch ) ) {
            return $batch[ $key ] ?? $default;
        }

        if ( is_object( $batch ) ) {
            return $batch->$key ?? $default;
        }

        return $default;
    }
}
