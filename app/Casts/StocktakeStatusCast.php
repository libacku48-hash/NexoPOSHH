<?php

namespace App\Casts;

use App\Services\StocktakeService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class StocktakeStatusCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  Model $model
     * @param  mixed $value
     * @return mixed
     */
    public function get( $model, string $key, $value, array $attributes )
    {
        /**
         * @var StocktakeService $stocktakeService
         */
        $stocktakeService = app()->make( StocktakeService::class );

        return $stocktakeService->getStatusLabel( $value );
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  Model $model
     * @param  mixed $value
     * @return mixed
     */
    public function set( $model, string $key, $value, array $attributes )
    {
        return $value;
    }
}
