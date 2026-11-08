<?php

namespace App\Crud;

use App\Casts\DateCast;
use App\Casts\ProductBatchStatusCast;
use App\Classes\CrudTable;
use App\Models\ProcurementProduct;
use App\Services\CrudEntry;
use App\Services\CrudService;

class ProductBatchCrud extends CrudService
{
    /**
     * Define the autoload status
     */
    const AUTOLOAD = true;

    /**
     * Define the identifier
     */
    const IDENTIFIER = 'ns.product-batches';

    /**
     * Define the base table
     */
    protected $table = 'nexopos_procurements_products';

    /**
     * Define namespace
     */
    protected $namespace = 'ns.product-batches';

    /**
     * Model Used
     */
    protected $model = ProcurementProduct::class;

    /**
     * Display options column before data columns
     */
    protected $prependOptions = true;

    /**
     * Adding relation
     */
    public $relations = [
        [ 'nexopos_procurements as procurement', 'procurement.id', '=', 'nexopos_procurements_products.procurement_id' ],
        [ 'nexopos_units as unit', 'unit.id', '=', 'nexopos_procurements_products.unit_id' ],
        [ 'nexopos_users as user', 'user.id', '=', 'nexopos_procurements_products.author_id' ],
    ];

    public $pick = [
        'procurement' => [ 'name' ],
        'unit' => [ 'name' ],
        'user' => [ 'username' ],
    ];

    protected $permissions = [
        'create' => false,
        'read' => 'nexopos.read.product-batches',
        'update' => false,
        'delete' => false, // batches cannot be deleted, only written off
    ];

    protected $casts = [
        'created_at' => DateCast::class,
        'status' => ProductBatchStatusCast::class,
    ];

    /**
     * Return the label used for the crud instance
     */
    public function getLabels()
    {
        return CrudTable::labels(
            list_title: __( 'Product Batches' ),
            list_description: __( 'Displays all procurement lots with their remaining quantity and expiration date. Sales consume the oldest lots first (FIFO); expired lots are never sold.' ),
            no_entry: __( 'No batches found.' ),
            create_new: __( 'Add a new batch' ),
            create_title: __( 'Create a new batch' ),
            create_description: __( 'Register a new batch.' ),
            edit_title: __( 'Edit Batch' ),
            edit_description: __( 'Modify the batch.' ),
            back_to_list: __( 'Return to Product Batches' ),
        );
    }

    public function getLinks(): array
    {
        return [
            'list' => ns()->url( '/dashboard/products/batches' ),
        ];
    }

    /**
     * Define Columns
     */
    public function getColumns(): array
    {
        return CrudTable::columns(
            CrudTable::column( label: __( '#' ), identifier: 'id', width: '80px' ),
            CrudTable::column( label: __( 'Product' ), identifier: 'name' ),
            CrudTable::column( label: __( 'Procurement' ), identifier: 'procurement_name', width: '150px' ),
            CrudTable::column( label: __( 'Unit' ), identifier: 'unit_name', width: '100px' ),
            CrudTable::column( label: __( 'Quantity' ), identifier: 'quantity', width: '100px' ),
            CrudTable::column( label: __( 'Available' ), identifier: 'available_quantity', width: '100px' ),
            CrudTable::column( label: __( 'Manufactured' ), identifier: 'manufactured_date', width: '120px' ),
            CrudTable::column( label: __( 'Expiration Date' ), identifier: 'expiration_date', width: '130px' ),
            CrudTable::column( label: __( 'Status' ), identifier: 'status', width: '130px' ),
            CrudTable::column( label: __( 'Author' ), identifier: 'user_username', width: '120px' ),
        );
    }

    /**
     * Define actions per entry
     */
    public function setActions( CrudEntry $entry ): CrudEntry
    {
        /**
         * The status is a computed value (see ProductBatchStatusCast), so it
         * is only available on the entry itself, not in the original row.
         */
        $status = $entry->status;

        $entry->{ '$cssClass' } = match ( $status ) {
            __( 'Expired' ) => 'error border text-sm',
            __( 'Expiring Soon' ) => 'warning border text-sm',
            default => '',
        };

        if ( $status === __( 'Expired' ) && (float) $entry->getOriginalValue( 'available_quantity' ) > 0 ) {
            $entry->action(
                identifier: 'write-off',
                label: '<i class="mr-2 las la-trash"></i> ' . __( 'Write Off Expired' ),
                type: 'GET',
                url: ns()->url( '/api/products/batches/' . $entry->id . '/write-off' ),
                confirm: [
                    'message' => __( 'Would you like to write off this expired batch? Its remaining quantity will be removed from the stock.' ),
                ],
            );
        }

        return $entry;
    }

    public function hook( $query ): void
    {
        if ( empty( request()->query( 'direction' ) ) ) {
            $query->orderByRaw( 'expiration_date IS NULL, expiration_date ASC' );
        }
    }
}
