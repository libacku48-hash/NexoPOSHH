<?php

namespace App\Services;

use App\Exceptions\NotAllowedException;
use App\Models\Product;
use App\Models\ProductHistory;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Stocktake (Kiểm kê kho định kỳ).
 *
 * Workflow: draft → counting → approved (or canceled). Staff counts the
 * physical stock, the system quantity is snapshotted when the draft is
 * created (and refreshed at approval time), and approving a stocktake
 * creates a regular ProductAdjustment with one ACTION_SET line per counted
 * product — so the actual stock movement reuses the proven
 * ProductService::executeAdjustmentDraft() code path, including history,
 * reports and lot reconciliation.
 */
class StocktakeService
{
    public function getStatusLabel( string $status ): string
    {
        return match ( $status ) {
            Stocktake::STATUS_DRAFT => __( 'Draft' ),
            Stocktake::STATUS_COUNTING => __( 'Counting' ),
            Stocktake::STATUS_APPROVED => __( 'Approved' ),
            Stocktake::STATUS_CANCELED => __( 'Canceled' ),
            default => __( 'Unknown' ),
        };
    }

    public function generateStocktakeTitle( ?Stocktake $stocktake = null ): string
    {
        if ( $stocktake instanceof Stocktake && $stocktake->title ) {
            return sprintf( __( 'Stocktake #%s' ), $stocktake->id );
        }

        $latestStocktake = Stocktake::latest()->first();
        $nextNumber = $latestStocktake ? $latestStocktake->id + 1 : 1;

        return sprintf( __( 'Stocktake #%s' ), $nextNumber );
    }

    /**
     * Build stocktake lines from the counting page payload. The payload uses
     * the same shape as the stock adjustment page: each product carries an
     * `adjust_unit` (unit_id, unit, sale_price) and a `counted_quantity`.
     * The system quantity is snapshotted now and the difference computed.
     */
    public function buildStocktakeItems( array $products ): array
    {
        /**
         * @var ProductService $productService
         */
        $productService = app()->make( ProductService::class );

        return collect( $products )->map( function ( $rawProduct ) use ( $productService ) {
            $product = Product::find( $rawProduct[ 'id' ] );

            if ( ! $product instanceof Product ) {
                throw new NotAllowedException( __( 'One of the counted products doesn\'t exist.' ) );
            }

            $unitId = (int) ( $rawProduct[ 'adjust_unit' ][ 'unit_id' ] ?? $rawProduct[ 'unit_id' ] ?? 0 );
            $countedQuantity = (float) ( $rawProduct[ 'counted_quantity' ] ?? 0 );

            if ( $countedQuantity < 0 ) {
                throw new NotAllowedException( __( 'The counted quantity cannot be negative.' ) );
            }

            $systemQuantity = (float) $productService->getQuantity( $product->id, $unitId );

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit_id' => $unitId,
                'unit_name' => $rawProduct[ 'adjust_unit' ][ 'unit' ][ 'name' ] ?? '',
                'unit_price' => $rawProduct[ 'adjust_unit' ][ 'sale_price' ] ?? 0,
                'system_quantity' => $systemQuantity,
                'counted_quantity' => $countedQuantity,
                'difference' => $countedQuantity - $systemQuantity,
                'description' => $rawProduct[ 'description' ] ?? '',
            ];
        } )->toArray();
    }

    /**
     * Create a draft stocktake with its lines. No stock is modified.
     */
    public function createDraft( array $products, ?string $title = '', string $description = '' ): Stocktake
    {
        $stocktake = Stocktake::create( [
            'author_id' => Auth::id(),
            'title' => $title ?: $this->generateStocktakeTitle(),
            'status' => Stocktake::STATUS_DRAFT,
            'description' => $description,
        ] );

        foreach ( $this->buildStocktakeItems( $products ) as $item ) {
            StocktakeItem::create( array_merge( $item, [
                'stocktake_id' => $stocktake->id,
            ] ) );
        }

        return $stocktake->load( 'items' );
    }

    /**
     * Replace all lines of an editable stocktake (draft or counting).
     */
    public function updateDraft( Stocktake $stocktake, array $products, ?string $title = '', string $description = '' ): Stocktake
    {
        if ( ! $stocktake->isEditable() ) {
            throw new NotAllowedException( __( 'Only draft or counting stocktakes can be updated.' ) );
        }

        $stocktake->update( [
            'title' => $title ?: $this->generateStocktakeTitle( $stocktake ),
            'description' => $description,
        ] );

        $stocktake->items()->delete();

        foreach ( $this->buildStocktakeItems( $products ) as $item ) {
            StocktakeItem::create( array_merge( $item, [
                'stocktake_id' => $stocktake->id,
            ] ) );
        }

        return $stocktake->load( 'items' );
    }

    /**
     * Move a draft stocktake to the counting stage.
     */
    public function startCounting( Stocktake $stocktake ): Stocktake
    {
        if ( $stocktake->status !== Stocktake::STATUS_DRAFT ) {
            throw new NotAllowedException( __( 'Only draft stocktakes can move to counting.' ) );
        }

        $stocktake->update( [ 'status' => Stocktake::STATUS_COUNTING ] );

        return $stocktake;
    }

    /**
     * Approve a counting stocktake: refresh the system quantities (sales
     * may have happened while counting), then apply every difference as a
     * regular stock adjustment (ACTION_SET per line) and lock the stocktake.
     */
    public function approve( Stocktake $stocktake, ?int $approverId = null ): Stocktake
    {
        if ( $stocktake->status !== Stocktake::STATUS_COUNTING ) {
            throw new NotAllowedException( __( 'Only counting stocktakes can be approved.' ) );
        }

        return DB::transaction( function () use ( $stocktake, $approverId ) {
            /**
             * @var ProductService $productService
             */
            $productService = app()->make( ProductService::class );

            $adjustmentProducts = [];

            foreach ( $stocktake->items as $item ) {
                $systemQuantity = (float) $productService->getQuantity( $item->product_id, $item->unit_id );

                $item->update( [
                    'system_quantity' => $systemQuantity,
                    'difference' => (float) $item->counted_quantity - $systemQuantity,
                ] );

                /**
                 * Lines with no difference need no adjustment: the
                 * ACTION_SET handler cannot record a zero-quantity change.
                 */
                if ( (float) $item->difference === 0.0 ) {
                    continue;
                }

                $adjustmentProducts[] = [
                    'id' => $item->product_id,
                    'adjust_unit' => [
                        'unit_id' => $item->unit_id,
                        'unit' => [ 'name' => $item->unit_name ],
                        'sale_price' => $item->unit_price,
                    ],
                    'adjust_quantity' => $item->counted_quantity,
                    'adjust_action' => ProductHistory::ACTION_SET,
                    'adjust_reason' => $item->description ?? '',
                ];
            }

            $adjustment = null;

            if ( count( $adjustmentProducts ) > 0 ) {
                $adjustment = $productService->createAdjustmentDraft(
                    $adjustmentProducts,
                    sprintf( __( 'Stocktake #%s' ), $stocktake->id ),
                    $stocktake->description ?? ''
                );

                $productService->executeAdjustmentDraft( $adjustment );
            }

            $stocktake->update( [
                'status' => Stocktake::STATUS_APPROVED,
                'approved_by' => $approverId ?? Auth::id(),
                'approved_at' => now(),
                'adjustment_id' => $adjustment?->id,
            ] );

            return $stocktake->load( 'items' );
        } );
    }

    /**
     * Cancel an editable stocktake. No stock is modified.
     */
    public function cancel( Stocktake $stocktake ): Stocktake
    {
        if ( ! $stocktake->isEditable() ) {
            throw new NotAllowedException( __( 'Only draft or counting stocktakes can be canceled.' ) );
        }

        $stocktake->update( [ 'status' => Stocktake::STATUS_CANCELED ] );

        return $stocktake;
    }
}
