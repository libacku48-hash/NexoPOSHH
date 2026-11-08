<?php

namespace App\Listeners;

use App\Enums\NotificationsEnum;
use App\Events\SettingsSavedEvent;
use App\Jobs\TestWorkerJob;
use App\Models\Role;
use App\Services\NotificationService;
use App\Services\Options;

class SettingsListener
{
    protected $notificationService;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(
        NotificationService $notificationService
    ) {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     *
     * @param  object $event
     * @return void
     */
    public function handle( SettingsSavedEvent $event )
    {
        $options = app()->make( Options::class );

        if ( $options->get( 'ns_workers_enabled' ) === 'await_confirm' ) {
            $notification_id = NotificationsEnum::NSWORKERDISABLED;

            TestWorkerJob::dispatch( $notification_id )
                ->delay( now() );

            $this->notificationService->create(
                title: __( 'Workers Disabled' ),
                description: __( 'The workers has been disabled. You can enable them by going to the settings.' ),
                identifier: $notification_id,
                url: route( 'ns.settings.workers' ),
            )->dispatchForGroup(
                [ Role::namespace( 'admin' ) ]
            );
        }

        /**
         * While the scale feature is enabled, we'll enable the decimal quantities.
         */
        if ( $options->get( 'ns_scale_barcode_enabled' ) === 'yes' ) {
            $options->set( 'ns_pos_allow_decimal_quantities', 'yes' );
        }

        /**
         * When a currency preset is chosen on the General settings, fill in
         * the matching currency settings (ISO, symbol, position, precision
         * and separators). Only applies when the preset actually changed —
         * tracked by ns_currency_preset_applied — so manual edits to the
         * individual fields afterwards are never overwritten.
         */
        if ( isset( $event->data['ns_currency_preset'] ) ) {
            $preset = $event->data['ns_currency_preset'];
            $appliedPreset = $options->get( 'ns_currency_preset_applied', 'custom' );

            if ( $preset !== $appliedPreset ) {
                $presets = [
                    'vnd' => [
                        'ns_currency_iso' => 'VND',
                        'ns_currency_symbol' => '₫',
                        'ns_currency_position' => 'after',
                        'ns_currency_prefered' => 'symbol',
                        'ns_currency_precision' => '0',
                        'ns_currency_thousand_separator' => '.',
                        'ns_currency_decimal_separator' => ',',
                    ],
                    'usd' => [
                        'ns_currency_iso' => 'USD',
                        'ns_currency_symbol' => '$',
                        'ns_currency_position' => 'before',
                        'ns_currency_prefered' => 'symbol',
                        'ns_currency_precision' => '2',
                        'ns_currency_thousand_separator' => ',',
                        'ns_currency_decimal_separator' => '.',
                    ],
                ];

                if ( isset( $presets[ $preset ] ) ) {
                    foreach ( $presets[ $preset ] as $key => $value ) {
                        $options->set( $key, $value );
                    }
                }

                $options->set( 'ns_currency_preset_applied', $preset );
            }
        }
    }
}
