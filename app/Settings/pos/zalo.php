<?php

/**
 * Zalo OA (Official Account) — settings.
 *
 * Sends order notifications to customers over Zalo OA: order confirmation,
 * dish / process status updates, payment confirmation + invoice, and
 * promotions for linked customers (see docs/zalo-oa.md).
 *
 * Settings are stored with the standard NexoPOS options (ns_zalo_*).
 * They take priority over the .env variables read through
 * config/services.php: if a value is set here, it wins; otherwise
 * the .env value is used as a fallback, so an existing .env
 * configuration keeps working.
 *
 * Access token and OA secret use password fields with no value sent
 * back to the form: leaving them blank on save keeps the stored
 * value (empty submitted values are omitted by NsForms::getFlatForm).
 */

use App\Classes\FormInput;

return [
    'label' => __( 'Zalo OA' ),
    'fields' => [
        FormInput::switch(
            label: __( 'Zalo OA Enabled' ),
            name: 'ns_zalo_enabled',
            options: [
                ['label' => __( 'Yes' ), 'value' => 'yes'],
                ['label' => __( 'No' ), 'value' => 'no'],
            ],
            value: ns()->option->get( 'ns_zalo_enabled', 'no' ),
            description: __( 'Send order notifications to customers using Zalo OA (Official Account).' )
        ),

        FormInput::text(
            label: __( 'Zalo OA ID' ),
            name: 'ns_zalo_oa_id',
            value: ns()->option->get( 'ns_zalo_oa_id', '' ),
            description: __( 'ID of the Zalo Official Account, found on the OA admin page (oa.zalo.me).' )
        ),

        FormInput::text(
            label: __( 'Zalo OA App ID' ),
            name: 'ns_zalo_app_id',
            value: ns()->option->get( 'ns_zalo_app_id', '' ),
            description: __( 'ID of the Zalo application on developers.zalo.me. Used to verify webhook signatures.' )
        ),

        FormInput::password(
            label: __( 'Zalo OA Access Token' ),
            name: 'ns_zalo_access_token',
            value: null,
            description: __( 'Access token of the Zalo OA. Never commit the real token to Git; keep it here or in the .env file on the server. Leave blank to keep the current value.' )
        ),

        FormInput::password(
            label: __( 'Zalo OA Secret' ),
            name: 'ns_zalo_secret',
            value: null,
            description: __( 'OA secret key from developers.zalo.me. Used to verify webhook signatures. Leave blank to keep the current value.' )
        ),

        FormInput::switch(
            label: __( 'Zalo OA Dry Run' ),
            name: 'ns_zalo_dry_run',
            options: [
                ['label' => __( 'Yes' ), 'value' => 'yes'],
                ['label' => __( 'No' ), 'value' => 'no'],
            ],
            value: ns()->option->get( 'ns_zalo_dry_run', 'yes' ),
            description: __( 'When enabled, messages are only written to the log and never sent to the real Zalo API. Turn it off only after the OA ID and access token are configured.' )
        ),

        FormInput::switch(
            label: __( 'Enforce Webhook Signature Verification' ),
            name: 'ns_zalo_webhook_enforce',
            options: [
                ['label' => __( 'Yes' ), 'value' => 'yes'],
                ['label' => __( 'No' ), 'value' => 'no'],
            ],
            value: ns()->option->get( 'ns_zalo_webhook_enforce', 'no' ),
            description: __( 'Only enable after you have seen "signature verified" in the log from a real event. When enabled, webhook events with a wrong or missing signature are rejected with 401.' )
        ),

        FormInput::text(
            label: __( 'Zalo OA API Base URL' ),
            name: 'ns_zalo_api_base_url',
            value: ns()->option->get( 'ns_zalo_api_base_url', 'https://openapi.zalo.me' ),
            description: __( 'Base URL of the Zalo Open API. Leave the default value unless Zalo changes it.' )
        ),

        FormInput::number(
            label: __( 'Zalo OA Timeout' ),
            name: 'ns_zalo_timeout',
            value: ns()->option->get( 'ns_zalo_timeout', '10' ),
            validation: 'numeric|min:1|max:120',
            description: __( 'How long to wait for the Zalo API to respond, in seconds.' )
        ),
    ],
];
