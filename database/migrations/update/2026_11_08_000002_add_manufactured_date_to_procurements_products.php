<?php

/**
 * Product batches — manufacturing date
 *
 * Procurement lines already carry an expiration date, but no manufacturing
 * date. This adds a nullable `manufactured_date` so a lot can be fully
 * described (manufactured on / expires on). Existing lots keep working:
 * the column is nullable and nothing else changes.
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
        if ( Schema::hasTable( 'nexopos_procurements_products' ) && ! Schema::hasColumn( 'nexopos_procurements_products', 'manufactured_date' ) ) {
            Schema::table( 'nexopos_procurements_products', function ( Blueprint $table ) {
                $table->date( 'manufactured_date' )->nullable()->after( 'expiration_date' );
            } );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ( Schema::hasTable( 'nexopos_procurements_products' ) && Schema::hasColumn( 'nexopos_procurements_products', 'manufactured_date' ) ) {
            Schema::table( 'nexopos_procurements_products', function ( Blueprint $table ) {
                $table->dropColumn( 'manufactured_date' );
            } );
        }
    }
};
