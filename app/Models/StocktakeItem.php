<?php

namespace App\Models;

use Carbon\Carbon;

/**
 * @property int         $id
 * @property int         $stocktake_id
 * @property int         $product_id
 * @property string      $product_name
 * @property int         $unit_id
 * @property string      $unit_name
 * @property float       $unit_price
 * @property float       $system_quantity
 * @property float       $counted_quantity
 * @property float       $difference
 * @property string|null $description
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 */
class StocktakeItem extends NsModel
{
    protected $table = 'nexopos_' . 'stocktake_items';

    protected $fillable = [
        'stocktake_id',
        'product_id',
        'product_name',
        'unit_id',
        'unit_name',
        'unit_price',
        'system_quantity',
        'counted_quantity',
        'difference',
        'description',
    ];

    public function stocktake()
    {
        return $this->belongsTo( Stocktake::class, 'stocktake_id' );
    }

    public function product()
    {
        return $this->belongsTo( Product::class, 'product_id' );
    }
}
