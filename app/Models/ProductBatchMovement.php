<?php

namespace App\Models;

use Carbon\Carbon;

/**
 * Records one FIFO movement on a procurement lot.
 *
 * @property int         $id
 * @property int         $procurement_product_id
 * @property int         $product_id
 * @property int         $unit_id
 * @property int|null    $order_id
 * @property int|null    $order_product_id
 * @property string      $type
 * @property float       $quantity
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 */
class ProductBatchMovement extends NsModel
{
    protected $table = 'nexopos_' . 'product_batch_movements';

    const TYPE_CONSUME = 'consume';

    const TYPE_RESTORE = 'restore';

    protected $fillable = [
        'procurement_product_id',
        'product_id',
        'unit_id',
        'order_id',
        'order_product_id',
        'type',
        'quantity',
    ];

    public function batch()
    {
        return $this->belongsTo( ProcurementProduct::class, 'procurement_product_id' );
    }
}
