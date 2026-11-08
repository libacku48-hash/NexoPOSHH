<?php

/**
 * Stocktake & product batches — permissions
 *
 * Creates the permissions used by the stocktake and product batch features
 * and grants them to the administrator and store administrator roles.
 * Cashiers and regular users intentionally get none of these: counting and
 * approving stock, and writing off expired lots, are management tasks.
 */

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISSIONS = [
        'nexopos.create.stocktakes' => 'Stocktake: Create',
        'nexopos.read.stocktakes' => 'Stocktake: Read',
        'nexopos.update.stocktakes' => 'Stocktake: Update',
        'nexopos.approve.stocktakes' => 'Stocktake: Approve',
        'nexopos.delete.stocktakes' => 'Stocktake: Delete',
        'nexopos.read.product-batches' => 'Product Batches: Read',
        'nexopos.write-off.product-batches' => 'Product Batches: Write Off Expired',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ( self::PERMISSIONS as $namespace => $name ) {
            if ( Permission::where( 'name', $name )->where( 'namespace', '!=', $namespace )->exists() ) {
                throw new RuntimeException( "The permission name [{$name}] is already assigned to another namespace." );
            }

            if ( ! Permission::namespace( $namespace ) instanceof Permission ) {
                Permission::create( [
                    'namespace' => $namespace,
                    'name' => $name,
                    'description' => "Introduced by the stocktake & product batches migration: {$namespace}",
                ] );
            }
        }

        foreach ( [ Role::ADMIN, Role::STOREADMIN ] as $roleNamespace ) {
            Role::namespace( $roleNamespace )?->addPermissions( array_keys( self::PERMISSIONS ) );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ( [ Role::ADMIN, Role::STOREADMIN ] as $roleNamespace ) {
            $role = Role::namespace( $roleNamespace );

            if ( $role instanceof Role ) {
                $role->removePermissions( array_keys( self::PERMISSIONS ) );
            }
        }

        foreach ( array_keys( self::PERMISSIONS ) as $namespace ) {
            Permission::namespace( $namespace )?->delete();
        }
    }
};
