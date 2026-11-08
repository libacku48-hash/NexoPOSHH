<?php

/**
 * Stocktake (Kiểm kê kho định kỳ) — MVP
 *
 * Creates the stocktake header + lines tables. A stocktake snapshots the
 * system quantity of every counted product/unit when the draft is created,
 * the staff enters the physically counted quantity, and the difference is
 * computed from those two values. Approving a stocktake creates a regular
 * ProductAdjustment (ACTION_SET per line) through the existing
 * ProductService::executeAdjustmentDraft() flow, so stock movements,
 * history and reports all reuse the proven adjustment code path.
 */

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
        if ( ! Schema::hasTable( 'nexopos_stocktakes' ) ) {
            Schema::create( 'nexopos_stocktakes', function ( Blueprint $table ) {
                $table->id();
                $table->integer( 'author_id' );
                $table->string( 'title' )->nullable();
                $table->enum( 'status', [ 'draft', 'counting', 'approved', 'canceled' ] )->default( 'draft' );
                $table->text( 'description' )->nullable();
                $table->integer( 'approved_by' )->nullable();
                $table->datetime( 'approved_at' )->nullable();
                $table->integer( 'adjustment_id' )->nullable();
                $table->timestamps();
            } );
        }

        if ( ! Schema::hasTable( 'nexopos_stocktake_items' ) ) {
            Schema::create( 'nexopos_stocktake_items', function ( Blueprint $table ) {
                $table->id();
                $table->integer( 'stocktake_id' );
                $table->integer( 'product_id' );
                $table->string( 'product_name' );
                $table->integer( 'unit_id' );
                $table->string( 'unit_name' );
                $table->decimal( 'unit_price', 18, 5 )->default( 0 );
                $table->float( 'system_quantity' )->default( 0 );
                $table->float( 'counted_quantity' )->default( 0 );
                $table->float( 'difference' )->default( 0 );
                $table->text( 'description' )->nullable();
                $table->timestamps();
            } );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists( 'nexopos_stocktake_items' );
        Schema::dropIfExists( 'nexopos_stocktakes' );
    }
};
