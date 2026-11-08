<?php

namespace App\Services;

/**
 * VietQR bank transfer (QR chuyển khoản) for the POS.
 *
 * Builds the VietQR image URL served by img.vietqr.io from the store's own
 * bank account — no API key is required and no real account is hardcoded:
 * everything comes from the POS settings (tab VietQR, options ns_vietqr_*).
 *
 * VietQR is a manual-confirmation payment: the customer scans the QR code
 * with their banking app and the cashier confirms the money was received
 * before the order is submitted as paid. Automatic confirmation (VNPay)
 * is NOT part of this service; it is a future step.
 *
 * @see docs/thanh-toan-vietqr-vnd.md
 */
class VietQrService
{
    /**
     * Identifier of the VietQR payment type (see PaymentType).
     */
    public const PAYMENT_IDENTIFIER = 'vietqr-payment';

    /**
     * Base URL of the VietQR image service.
     */
    public const IMAGE_BASE_URL = 'https://img.vietqr.io/image';

    /**
     * Templates supported by img.vietqr.io.
     */
    public const TEMPLATES = [ 'qr_only', 'compact', 'compact2', 'print' ];

    public const DEFAULT_TEMPLATE = 'compact2';

    /**
     * VietQR transfer content (addInfo) is limited to 25 characters.
     */
    public const MAX_ADD_INFO_LENGTH = 25;

    /**
     * Read a VietQR option, falling back silently when the options
     * table is not available yet (fresh install, tests without DB).
     */
    protected static function option( string $key, mixed $default = '' ): mixed
    {
        try {
            return ns()->option->get( $key, $default );
        } catch ( \Throwable ) {
            return $default;
        }
    }

    public static function isEnabled(): bool
    {
        return self::option( 'ns_vietqr_enabled', 'no' ) === 'yes';
    }

    /**
     * True when the QR code can actually be built: enabled + bank + account.
     */
    public static function isConfigured(): bool
    {
        return self::isEnabled()
            && trim( (string) self::option( 'ns_vietqr_bank', '' ) ) !== ''
            && trim( (string) self::option( 'ns_vietqr_account', '' ) ) !== '';
    }

    /**
     * Configuration exposed to the POS frontend (pos.blade.php).
     * The receiving account number is public by design: it is printed
     * on the QR code itself. No secret is exposed here.
     */
    public static function frontendConfig(): array
    {
        $template = (string) self::option( 'ns_vietqr_template', self::DEFAULT_TEMPLATE );

        return [
            'enabled' => self::isEnabled(),
            'bank' => trim( (string) self::option( 'ns_vietqr_bank', '' ) ),
            'account' => trim( (string) self::option( 'ns_vietqr_account', '' ) ),
            'accountName' => trim( (string) self::option( 'ns_vietqr_account_name', '' ) ),
            'template' => in_array( $template, self::TEMPLATES, true ) ? $template : self::DEFAULT_TEMPLATE,
            'addInfoTemplate' => (string) self::option( 'ns_vietqr_addinfo_template', '{code}' ),
        ];
    }

    /**
     * Build the transfer content from the admin template.
     * "{code}" is replaced by the order reference. The result is cut
     * to the 25 characters VietQR accepts.
     */
    public static function buildAddInfo( string $template, string $reference ): string
    {
        $template = trim( $template ) === '' ? '{code}' : $template;
        $addInfo = str_replace( '{code}', $reference, $template );

        return mb_substr( trim( $addInfo ), 0, self::MAX_ADD_INFO_LENGTH );
    }

    /**
     * Build the VietQR image URL for an amount in VND.
     *
     * Returns null when the QR code cannot be built: missing bank or
     * account, or a non-positive amount. The amount must already be a
     * whole number of VND (no decimals — VND has no sub-unit).
     */
    public static function buildQrImageUrl(
        string $bank,
        string $account,
        int $amount,
        string $addInfo,
        ?string $accountName = null,
        string $template = self::DEFAULT_TEMPLATE
    ): ?string {
        $bank = trim( $bank );
        $account = trim( $account );

        if ( $bank === '' || $account === '' || $amount <= 0 ) {
            return null;
        }

        if ( ! in_array( $template, self::TEMPLATES, true ) ) {
            $template = self::DEFAULT_TEMPLATE;
        }

        $query = http_build_query( array_filter( [
            'amount' => $amount,
            'addInfo' => mb_substr( trim( $addInfo ), 0, self::MAX_ADD_INFO_LENGTH ),
            'accountName' => $accountName !== null && trim( $accountName ) !== '' ? trim( $accountName ) : null,
        ] ) );

        return self::IMAGE_BASE_URL . '/' . rawurlencode( $bank ) . '-' . rawurlencode( $account ) . '-' . $template . '.png?' . $query;
    }
}
