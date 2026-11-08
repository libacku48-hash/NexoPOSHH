<?php

/**
 * VietQR bank transfer — payment type
 *
 * Creates the "VietQR Transfer" payment type (identifier "vietqr-payment")
 * so it can be selected at the POS and managed like the other payment types
 * (permission nexopos.manage-payments-types). The accounting account mapping
 * (bank account, increase) is applied by
 * TransactionService::configureDefaultPaymentAccounts() when the accounting
 * foundation is upgraded, exactly like the bank payment type.
 *
 * The type is readonly: it cannot be deleted from the payment types screen,
 * only deactivated.
 */

use App\Models\PaymentType;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ( PaymentType::where( 'identifier', 'vietqr-payment' )->exists() ) {
            return;
        }

        $type = new PaymentType;
        $type->label = 'VietQR Transfer';
        $type->identifier = 'vietqr-payment';
        $type->readonly = true;
        $type->author_id = User::query()->orderBy( 'id' )->value( 'id' ) ?? 1;
        $type->save();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        PaymentType::where( 'identifier', 'vietqr-payment' )->delete();
    }
};
