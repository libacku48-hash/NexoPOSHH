<?php

namespace App\Crud;

use App\Casts\DateCast;
use App\Casts\StocktakeStatusCast;
use App\Classes\CrudTable;
use App\Exceptions\NotAllowedException;
use App\Models\Stocktake;
use App\Services\CrudEntry;
use App\Services\CrudService;
use Illuminate\Http\Request;
use TorMorten\Eventy\Facades\Events as Hook;

class StocktakeCrud extends CrudService
{
    /**
     * Define the autoload status
     */
    const AUTOLOAD = true;

    /**
     * Define the identifier
     */
    const IDENTIFIER = 'ns.stocktakes';

    /**
     * Define the base table
     */
    protected $table = 'nexopos_stocktakes';

    /**
     * Define namespace
     */
    protected $namespace = 'ns.stocktakes';

    /**
     * Model Used
     */
    protected $model = Stocktake::class;

    /**
     * Display options column before data columns
     */
    protected $prependOptions = true;

    /**
     * Adding relation
     */
    public $relations = [
        [ 'nexopos_users as author', 'nexopos_stocktakes.author_id', '=', 'author.id' ],
    ];

    public $pick = [
        'author' => [ 'username' ],
    ];

    protected $permissions = [
        'create' => 'nexopos.create.stocktakes',
        'read' => 'nexopos.read.stocktakes',
        'update' => 'nexopos.update.stocktakes',
        'delete' => 'nexopos.delete.stocktakes',
    ];

    protected $casts = [
        'created_at' => DateCast::class,
        'status' => StocktakeStatusCast::class,
    ];

    /**
     * Return the label used for the crud instance
     */
    public function getLabels()
    {
        return CrudTable::labels(
            list_title: __( 'Stocktakes' ),
            list_description: __( 'Displays all stocktake records.' ),
            no_entry: __( 'No stocktakes found.' ),
            create_new: __( 'Add a new stocktake' ),
            create_title: __( 'Create a new stocktake' ),
            create_description: __( 'Register a new stocktake.' ),
            edit_title: __( 'Edit Stocktake' ),
            edit_description: __( 'Modify the stocktake.' ),
            back_to_list: __( 'Return to Stocktakes' ),
        );
    }

    public function getLinks(): array
    {
        return [
            'list' => ns()->url( '/dashboard/products/stocktakes' ),
            'post' => ns()->url( '/api/crud/' . self::IDENTIFIER ),
            'put' => ns()->url( '/api/crud/' . self::IDENTIFIER . '/{id}' ),
        ];
    }

    /**
     * Define Columns
     */
    public function getColumns(): array
    {
        return CrudTable::columns(
            CrudTable::column( label: __( '#' ), identifier: 'id', width: '80px' ),
            CrudTable::column( label: __( 'Author' ), identifier: 'author_username', width: '150px' ),
            CrudTable::column( label: __( 'Title' ), identifier: 'title' ),
            CrudTable::column( label: __( 'Status' ), identifier: 'status', width: '120px' ),
            CrudTable::column( label: __( 'Description' ), identifier: 'description' ),
            CrudTable::column( label: __( 'Created At' ), identifier: 'created_at', width: '150px' ),
        );
    }

    /**
     * Define actions per entry
     */
    public function setActions( CrudEntry $entry ): CrudEntry
    {
        $entry->{ '$cssClass' } = match ( $entry->getOriginalValue( 'status' ) ) {
            Stocktake::STATUS_DRAFT => 'info border text-sm',
            Stocktake::STATUS_COUNTING => 'warning border text-sm',
            Stocktake::STATUS_APPROVED => 'success border text-sm',
            Stocktake::STATUS_CANCELED => 'error border text-sm',
            default => '',
        };

        $status = $entry->getOriginalValue( 'status' );

        if ( in_array( $status, [ Stocktake::STATUS_DRAFT, Stocktake::STATUS_COUNTING ] ) ) {
            $entry->action(
                identifier: 'edit',
                label: '<i class="mr-2 las la-edit"></i> ' . __( 'Count' ),
                type: 'GOTO',
                url: ns()->url( '/dashboard/products/stocktakes/' . $entry->id )
            );

            if ( $status === Stocktake::STATUS_DRAFT ) {
                $entry->action(
                    identifier: 'start-counting',
                    label: '<i class="mr-2 las la-clipboard-list"></i> ' . __( 'Start Counting' ),
                    type: 'GET',
                    url: ns()->url( '/api/products/stocktakes/' . $entry->id . '/start-counting' ),
                    confirm: [
                        'message' => __( 'Would you like to start counting this stocktake?' ),
                    ],
                );
            }

            if ( $status === Stocktake::STATUS_COUNTING ) {
                $entry->action(
                    identifier: 'approve',
                    label: '<i class="mr-2 las la-check"></i> ' . __( 'Approve' ),
                    type: 'GET',
                    url: ns()->url( '/api/products/stocktakes/' . $entry->id . '/approve' ),
                    confirm: [
                        'message' => __( 'Would you like to approve this stocktake? The differences will be applied to the stock and the stocktake will be locked.' ),
                    ],
                );
            }

            $entry->action(
                identifier: 'cancel',
                label: '<i class="mr-2 las la-ban"></i> ' . __( 'Cancel' ),
                type: 'GET',
                url: ns()->url( '/api/products/stocktakes/' . $entry->id . '/cancel' ),
                confirm: [
                    'message' => __( 'Would you like to cancel this stocktake? No stock will be modified.' ),
                ],
            );

            $entry->action(
                identifier: 'delete',
                label: '<i class="mr-2 las la-trash"></i> ' . __( 'Delete' ),
                type: 'DELETE',
                url: ns()->url( '/api/crud/ns.stocktakes/' . $entry->id ),
                confirm: [
                    'message' => __( 'Would you like to delete this stocktake?' ),
                ],
            );
        } elseif ( $status === Stocktake::STATUS_APPROVED ) {
            $entry->action(
                identifier: 'view',
                label: '<i class="mr-2 las la-eye"></i> ' . __( 'View' ),
                type: 'GOTO',
                url: ns()->url( '/dashboard/products/stocktakes/' . $entry->id )
            );
        }

        return $entry;
    }

    public function hook( $query ): void
    {
        if ( empty( request()->query( 'direction' ) ) ) {
            $query->orderBy( 'id', 'desc' );
        }
    }

    /**
     * Before Delete
     */
    public function beforeDelete( string $namespace, $id, Stocktake $model )
    {
        if ( $namespace === self::IDENTIFIER ) {
            if ( $model->status === Stocktake::STATUS_APPROVED ) {
                throw new NotAllowedException( __( 'Approved stocktakes cannot be deleted.' ) );
            }
        }
    }

    /**
     * Bulk Delete Action
     */
    public function getBulkActions(): array
    {
        return [
            [
                'label' => __( 'Delete Selected' ),
                'identifier' => 'delete_selected',
                'url' => ns()->route( 'ns.api.crud-bulk-actions', [
                    'namespace' => $this->namespace,
                ] ),
            ],
        ];
    }

    public function bulkAction( Request $request )
    {
        if ( $request->input( 'action' ) === 'delete_selected' ) {
            ns()->restrict( $this->permissions[ 'delete' ] );

            $status = [
                'success' => 0,
                'error' => 0,
            ];

            foreach ( $request->input( 'entries' ) as $id ) {
                $entity = $this->model::find( $id );
                if ( $entity instanceof Stocktake ) {
                    if ( $entity->status === Stocktake::STATUS_APPROVED ) {
                        $status[ 'error' ]++;
                    } else {
                        $entity->items()->delete();
                        $entity->delete();
                        $status[ 'success' ]++;
                    }
                } else {
                    $status[ 'error' ]++;
                }
            }

            return $status;
        }

        return Hook::filter( $this->namespace . '-catch-action', false, $request );
    }
}
