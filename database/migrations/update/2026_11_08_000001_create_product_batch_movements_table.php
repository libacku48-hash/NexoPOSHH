<?php

/**
 * Product batch movements — FIFO consumption log
 *
 * Every time stock is consumed from (or restored to) a procurement lot
 * following the FIFO rule, a movement row is recorded. Restoring stock
 * (order void / return) walks these rows back, so the same lots that were
 * consumed get their quantities back instead of an arbitrary lot.
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
        if ( ! Schema::hasTable( 'nexopos_product_batch_movements' ) ) {
            Schema::create( 'nexopos_product_batch_movements', function ( Blueprint $table ) {
                $table->id();
                $table->integer( 'procurement_product_id' );
                $table->integer( 'product_id' );
                $table->integer( 'unit_id' );
                $table->integer( 'order_id' )->nullable();
                $table->integer( 'order_product_id' )->nullable();
                $table->enum( 'type', [ 'consume', 'restore' ] )->default( 'consume' );
                $table->float( 'quantity' )->default( 0 );
                $table->timestamps();
            } );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists( 'nexopos_product_batch_movements' );
    }
};
