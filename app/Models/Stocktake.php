<?php

namespace App\Models;

use Carbon\Carbon;

/**
 * @property int         $id
 * @property int         $author_id
 * @property string|null $title
 * @property string      $status
 * @property string|null $description
 * @property int|null    $approved_by
 * @property Carbon|null $approved_at
 * @property int|null    $adjustment_id
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 */
class Stocktake extends NsModel
{
    protected $table = 'nexopos_' . 'stocktakes';

    const STATUS_DRAFT = 'draft';

    const STATUS_COUNTING = 'counting';

    const STATUS_APPROVED = 'approved';

    const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'author_id',
        'title',
        'status',
        'description',
        'approved_by',
        'approved_at',
        'adjustment_id',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany( StocktakeItem::class, 'stocktake_id' );
    }

    public function author()
    {
        return $this->belongsTo( User::class, 'author_id' );
    }

    public function approver()
    {
        return $this->belongsTo( User::class, 'approved_by' );
    }

    public function adjustment()
    {
        return $this->belongsTo( ProductAdjustment::class, 'adjustment_id' );
    }

    /**
     * Whether the stocktake can still be edited.
     * Approved and canceled stocktakes are locked.
     */
    public function isEditable(): bool
    {
        return in_array( $this->status, [ self::STATUS_DRAFT, self::STATUS_COUNTING ] );
    }
}
