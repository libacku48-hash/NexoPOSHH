<template>
    <div class="h-full w-full py-2">
        <div class="px-2 pb-2" v-if="order">
            <div class="grid grid-cols-2 gap-2">
                <div id="vietqr-amount" class="col-span-2 flex justify-between items-center border elevation-surface info text-2xl md:text-4xl font-bold p-3">
                    <span>{{ __( 'Amount to Collect' ) }} : </span>
                    <span>{{ nsCurrency( amount ) }}</span>
                </div>
                <div id="vietqr-reference" class="col-span-2 flex justify-between items-center border elevation-surface text-xl md:text-2xl p-2">
                    <span>{{ __( 'Transfer Content' ) }} : </span>
                    <span class="font-mono font-bold">{{ addInfo }}</span>
                </div>
                <div id="vietqr-paid" v-if="order.tendered > 0" class="col-span-2 flex justify-between items-center border elevation-surface success text-xl md:text-2xl p-2">
                    <span>{{ __( 'Paid' ) }} : </span>
                    <span>{{ nsCurrency( order.tendered ) }}</span>
                </div>
            </div>
        </div>
        <div class="px-2 pb-2 flex flex-col items-center" v-if="order">
            <div v-if="! isConfigured" class="w-full border elevation-surface warning p-4 text-center">
                <h3 class="font-bold text-xl mb-2">{{ __( 'VietQR is not configured.' ) }}</h3>
                <p>{{ __( 'Please set the bank, account number and account name in the POS settings (VietQR tab) before using this payment type.' ) }}</p>
            </div>
            <div v-else-if="! currencyIsVnd" class="w-full border elevation-surface warning p-4 text-center">
                <h3 class="font-bold text-xl mb-2">{{ __( 'VietQR only works with VND.' ) }}</h3>
                <p>{{ __( 'The store currency is not VND. Change the currency to VND in the General settings (Currency preset) to use VietQR.' ) }}</p>
            </div>
            <div v-else-if="amount <= 0" class="w-full border elevation-surface success p-4 text-center">
                <h3 class="font-bold text-xl">{{ __( 'Nothing to collect. The order is already fully paid.' ) }}</h3>
            </div>
            <div v-else class="w-full flex flex-col items-center">
                <div class="relative">
                    <img v-if="! qrFailed" :key="qrUrl + qrNonce" :src="qrUrl" :alt="__( 'VietQR' )" @error="qrFailed = true" class="w-72 max-w-full aspect-square object-contain border bg-white p-2" />
                    <div v-if="qrFailed" class="w-72 max-w-full aspect-square border bg-white p-4 flex flex-col items-center justify-center text-center">
                        <p class="font-semibold mb-2">{{ __( 'Unable to load the QR code. Check the internet connection and the VietQR settings.' ) }}</p>
                        <button @click="qrFailed = false; qrNonce++" class="ns-button info rounded px-3 py-2 font-semibold cursor-pointer">{{ __( 'Retry' ) }}</button>
                    </div>
                </div>
                <div class="text-center text-lg mt-2">
                    <p><span class="font-semibold">{{ vietqr.accountName }}</span> &mdash; {{ vietqr.bank }} &mdash; {{ vietqr.account }}</p>
                    <p class="text-sm text-gray-500">{{ __( 'The customer scans this code with their banking app.' ) }}</p>
                </div>
                <div class="mt-3 flex items-center gap-2 border elevation-surface warning px-4 py-2 text-xl font-bold">
                    <span class="inline-block w-3 h-3 rounded-full bg-yellow-500 animate-pulse"></span>
                    <span>{{ __( 'Waiting for bank transfer' ) }}</span>
                </div>
                <button @click="confirmReceived()" class="mt-4 w-full md:w-auto bg-success-secondary hover:bg-green-600 text-white text-2xl font-bold border border-success-secondary rounded px-6 py-3 cursor-pointer">
                    {{ __( 'I Have Received the Payment' ) }}
                </button>
                <p class="text-sm text-gray-500 mt-2 text-center">{{ __( 'Only confirm after checking the money on the bank account. The order will be submitted as paid.' ) }}</p>
            </div>
        </div>
    </div>
</template>
<script>
import { Popup } from '~/libraries/popup';
import nsPosConfirmPopupVue from '~/popups/ns-pos-confirm-popup.vue';
import { __ } from '~/libraries/lang';
import { nsSnackBar } from '~/bootstrap';
import { nsCurrency } from '~/filters/currency';

export default {
    name: 'vietqr-payment',
    props: [ 'label', 'identifier' ],
    data() {
        return {
            order: null,
            settings: {},
            orderSubscription: null,
            settingsSubscription: null,
            qrFailed: false,
            qrNonce: 0,
            /**
             * Fallback transfer reference for new orders: the order code is
             * only assigned by the server when the order is submitted, so
             * a new order has no code yet at payment time. The reference
             * below (NS + date + time) stays stable while the popup is open
             * and lets the cashier match the transfer by time and amount.
             */
            fallbackReference: '',
        }
    },
    computed: {
        vietqr() {
            return this.settings.vietqr || {};
        },
        isConfigured() {
            return this.vietqr.enabled === true
                && this.vietqr.bank !== undefined && this.vietqr.bank !== ''
                && this.vietqr.account !== undefined && this.vietqr.account !== '';
        },
        currencyIsVnd() {
            try {
                return ns.currency.ns_currency_iso === 'VND';
            } catch ( exception ) {
                return false;
            }
        },
        remaining() {
            if ( ! this.order ) {
                return 0;
            }
            return Math.max( 0, this.order.total - this.order.tendered );
        },
        /**
         * VND has no sub-unit: the QR amount is a whole number of dong.
         */
        amount() {
            return Math.round( this.remaining );
        },
        reference() {
            if ( this.order && this.order.code ) {
                return this.order.code;
            }
            return this.fallbackReference;
        },
        addInfo() {
            const template = this.vietqr.addInfoTemplate || '{code}';
            const content = template.replace( '{code}', this.reference );
            return content.substring( 0, 25 );
        },
        qrUrl() {
            if ( ! this.isConfigured || this.amount <= 0 ) {
                return '';
            }
            const template = [ 'qr_only', 'compact', 'compact2', 'print' ].includes( this.vietqr.template )
                ? this.vietqr.template
                : 'compact2';
            const params = new URLSearchParams({
                amount: String( this.amount ),
                addInfo: this.addInfo,
            });
            if ( this.vietqr.accountName ) {
                params.set( 'accountName', this.vietqr.accountName );
            }
            return `https://img.vietqr.io/image/${encodeURIComponent( this.vietqr.bank )}-${encodeURIComponent( this.vietqr.account )}-${template}.png?${params.toString()}`;
        },
    },
    mounted() {
        const now = new Date();
        const pad = ( value ) => String( value ).padStart( 2, '0' );
        this.fallbackReference = `NS${String( now.getFullYear() ).slice( 2 )}${pad( now.getMonth() + 1 )}${pad( now.getDate() )}${pad( now.getHours() )}${pad( now.getMinutes() )}`;

        this.orderSubscription = POS.order.subscribe( order => {
            this.order = order;
        });

        this.settingsSubscription = POS.settings.subscribe( settings => {
            this.settings = settings;
        });
    },
    unmounted() {
        if ( this.orderSubscription ) {
            this.orderSubscription.unsubscribe();
        }
        if ( this.settingsSubscription ) {
            this.settingsSubscription.unsubscribe();
        }
    },
    methods: {
        __,
        nsCurrency,
        confirmReceived() {
            if ( this.amount <= 0 ) {
                return nsSnackBar.error( __( 'Nothing to collect. The order is already fully paid.' ) );
            }

            Popup.show( nsPosConfirmPopupVue, {
                title: __( 'Confirm VietQR Payment' ),
                message: __( 'You have received {amount} by VietQR bank transfer for content "{reference}". Would you like to submit the order as paid?' )
                    .replace( '{amount}', nsCurrency( this.amount ) )
                    .replace( '{reference}', this.addInfo ),
                onAction: ( action ) => {
                    if ( action ) {
                        POS.addPayment({
                            value: this.amount,
                            identifier: this.identifier,
                            selected: false,
                            label: this.label,
                            readonly: false,
                        });

                        this.$emit( 'submit' );
                    }
                }
            });
        },
    }
}
</script>
