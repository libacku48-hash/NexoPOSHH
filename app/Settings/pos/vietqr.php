<?php

/**
 * VietQR bank transfer (QR chuyển khoản) — settings.
 *
 * The customer scans the VietQR code at the POS with their banking app and
 * the cashier confirms the money was received before the order is submitted
 * as paid. No API key is required: the QR image comes from img.vietqr.io
 * and is built from the bank account configured here.
 *
 * Automatic payment confirmation (VNPay) is NOT part of this tab; it is a
 * future step. See docs/thanh-toan-vietqr-vnd.md.
 */

use App\Classes\FormInput;

return [
    'label' => __( 'VietQR' ),
    'fields' => [
        FormInput::switch(
            label: __( 'VietQR Enabled' ),
            name: 'ns_vietqr_enabled',
            options: [
                ['label' => __( 'Yes' ), 'value' => 'yes'],
                ['label' => __( 'No' ), 'value' => 'no'],
            ],
            value: ns()->option->get( 'ns_vietqr_enabled', 'no' ),
            description: __( 'Enable VietQR bank transfer payments at the POS. The customer scans the QR code and the cashier confirms the payment manually.' )
        ),

        FormInput::text(
            label: __( 'VietQR Bank Code' ),
            name: 'ns_vietqr_bank',
            value: ns()->option->get( 'ns_vietqr_bank', '' ),
            description: __( 'Short code of the bank that receives the transfers (for example VCB, TCB, MB, ACB, BIDV). Used to build the VietQR image.' )
        ),

        FormInput::text(
            label: __( 'VietQR Account Number' ),
            name: 'ns_vietqr_account',
            value: ns()->option->get( 'ns_vietqr_account', '' ),
            description: __( 'Bank account number that receives the transfers. This number is shown on the QR code at the POS.' )
        ),

        FormInput::text(
            label: __( 'VietQR Account Name' ),
            name: 'ns_vietqr_account_name',
            value: ns()->option->get( 'ns_vietqr_account_name', '' ),
            description: __( 'Name of the account holder, shown on the QR code.' )
        ),

        FormInput::select(
            label: __( 'VietQR QR Template' ),
            name: 'ns_vietqr_template',
            options: [
                ['label' => __( 'QR Code Only' ), 'value' => 'qr_only'],
                ['label' => __( 'Compact' ), 'value' => 'compact'],
                ['label' => __( 'Compact With Details' ), 'value' => 'compact2'],
                ['label' => __( 'Print Format' ), 'value' => 'print'],
            ],
            value: ns()->option->get( 'ns_vietqr_template', 'compact2' ),
            description: __( 'Layout of the VietQR image shown at the POS.' )
        ),

        FormInput::text(
            label: __( 'VietQR Transfer Content' ),
            name: 'ns_vietqr_addinfo_template',
            value: ns()->option->get( 'ns_vietqr_addinfo_template', '{code}' ),
            description: __( 'Content of the bank transfer. Use {code} for the order code. Keep it short (25 characters max).' )
        ),
    ],
];
