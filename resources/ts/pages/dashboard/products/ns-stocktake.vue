<script>
import { nsHttpClient, nsSnackBar } from '~/bootstrap';
import { Popup } from '~/libraries/popup';
import nsProcurementQuantityVue from '~/popups/ns-procurement-quantity.vue';
import nsPosConfirmPopupVue from '~/popups/ns-pos-confirm-popup.vue';
import nsPromptPopupVue from '~/popups/ns-prompt-popup.vue';
import { __ } from '~/libraries/lang';
import { forkJoin } from 'rxjs';
import nsSelectPopupVue from '~/popups/ns-select-popup.vue';

export default {
    name: 'ns-stocktake',
    props: [ 'stocktake' ],
    data() {
        return {
            search: '',
            timeout: null,
            suggestions: [],
            products: [],
            title: '',
            description: '',
            isDraft: false,
            stocktakeId: null,
            status: 'draft',
        }
    },
    computed: {
        isEditable() {
            return [ 'draft', 'counting' ].includes( this.status );
        },
    },
    mounted() {
        if ( this.stocktake !== null && this.stocktake !== undefined ) {
            this.loadStocktake( this.stocktake );
        }
    },
    methods: {
        __,
        getSystemQuantity( product ) {
            return product.adjust_unit && product.adjust_unit.quantity !== undefined ? product.adjust_unit.quantity : 0;
        },
        getDifference( product ) {
            return product.counted_quantity - this.getSystemQuantity( product );
        },
        searchProduct( argument ) {
            if ( argument.length > 0 ) {
                nsHttpClient.post( '/api/procurements/products/search-procurement-product', { argument })
                    .subscribe({
                        next: result => {
                            if ( result.from === 'products' ) {
                                if ( result.products.length > 0 ) {
                                    if ( result.products.length === 1 ) {
                                        this.addSuggestion( result.products[0] );
                                    } else {
                                        this.suggestions    =   result.products;
                                    }
                                } else {
                                    this.closeSearch();
                                    return nsSnackBar.error( __( 'Looks like no valid products matched the searched term.' ) );
                                }
                            } else if ( result.from === 'procurements' ) {
                                if ( result.product === null ) {
                                    this.closeSearch();
                                    return nsSnackBar.error( __( 'Looks like no valid products matched the searched term.' ) );
                                } else {
                                    this.addProcurementProduct( result.product );
                                }
                            }
                        },
                        error: error => {
                            nsSnackBar.error( error?.message || __( 'An unexpected error occurred.' ) );
                        }
                    })
            }
        },

        addProcurementProduct( product ) {
            /**
             * A procurement match only tells us which product it is.
             * The system quantity always comes from the product units,
             * like for an ordinary product search.
             */
            nsHttpClient.get( `/api/products/${product.product_id}/units/quantities` )
                .subscribe({
                    next: result => {
                        if ( result.length > 0 ) {
                            const defaultUnit = result.filter( unitQuantity => unitQuantity.unit.base_unit );
                            const line = this.buildLine( { id: product.product_id, name: product.name }, result, defaultUnit.length > 0 ? defaultUnit[0] : result[0] );
                            this.products.unshift( line );
                            this.clearSearch();
                        } else {
                            return nsSnackBar.error( __( `This product doesn't have any stock to count.` ) );
                        }
                    },
                    error: error => {
                        nsSnackBar.error( error?.message || __( 'An unexpected error occurred.' ) );
                    }
                });
        },

        buildLine( suggestion, quantities, defaultUnit ) {
            const systemQuantity = defaultUnit.quantity || 0;

            return {
                id: suggestion.id,
                name: suggestion.name,
                selected: false,
                quantities,
                adjust_unit: defaultUnit,
                counted_quantity: systemQuantity,
                description: '',
            };
        },

        addSuggestion( suggestion ) {
            const alreadyAdded    =   this.products
                .filter( product => product.id === suggestion.id ).length > 0;

            forkJoin([
                nsHttpClient.get( `/api/products/${suggestion.id}/units/quantities` ),
            ]).subscribe({
                next: result => {
                        if ( result[0].length > 0 ) {
                            const defaultUnit = result[0].filter( unitQuantity => unitQuantity.unit.base_unit );
                            const line = this.buildLine( suggestion, result[0], defaultUnit.length > 0 && ! alreadyAdded ? defaultUnit[0] : result[0][0] );

                            this.products.unshift( line );
                            this.clearSearch();
                        } else {
                            return nsSnackBar.error( __( `This product doesn't have any stock to count.` ) );
                        }
                    },
                    error: error => {
                        nsSnackBar.error( error?.message || __( 'An unexpected error occurred.' ) );
                    }
                });
        },
        closeSearch() {
            this.$refs.searchField.select();
            this.suggestions    =   [];
        },
        clearSearch() {
            this.search         =   '';
            this.suggestions    =   [];
        },
        openQuantityPopup( product ) {
            const promise   =   new Promise( ( resolve, reject ) => {
                Popup.show( nsProcurementQuantityVue, { resolve, reject, quantity : product.counted_quantity });
            });

            promise.then( result => {
                if ( result.quantity < 0 ) {
                    return nsSnackBar.error( __( 'The counted quantity cannot be negative.' ) );
                }

                product.counted_quantity     =   result.quantity;
                this.$forceUpdate();
            });
        },
        loadStocktake( stocktake ) {
            this.stocktakeId = stocktake.id;
            this.isDraft = true;
            this.status = stocktake.status;
            this.title = stocktake.title || '';
            this.description = stocktake.description || '';
            this.products = stocktake.items.map( item => {
                const adjustUnit = {
                    unit_id:    item.unit_id,
                    sale_price: item.unit_price,
                    quantity:   item.system_quantity,
                    unit: { name: item.unit_name, id: item.unit_id }
                };

                return {
                    id:                     item.product_id,
                    name:                   item.product_name,
                    adjust_unit:            adjustUnit,
                    counted_quantity:        item.counted_quantity,
                    description:            item.description || '',
                    selected:               false,
                    quantities:             [ adjustUnit ],
                };
            } );
        },
        saveDraft() {
            if ( this.products.length === 0 ) {
                return nsSnackBar.error( __( 'Unable to save a draft as the table is empty.' ) );
            }

            const payload = { products: this.products, title: this.title, description: this.description };

            if ( this.isDraft && this.stocktakeId ) {
                nsHttpClient.put( `/api/products/stocktakes/${this.stocktakeId}`, payload )
                    .subscribe({
                        next: result => nsSnackBar.success( result.message ),
                        error: error => nsSnackBar.error( error.message )
                    });
            } else {
                nsHttpClient.post( '/api/products/stocktakes/draft', payload )
                    .subscribe({
                        next: result => {
                            nsSnackBar.success( result.message );
                            setTimeout( () => {
                                window.location.href = '/dashboard/products/stocktakes';
                            }, 500 );
                        },
                        error: error => nsSnackBar.error( error.message )
                    });
            }
        },
        startCounting() {
            if ( ! this.isDraft || ! this.stocktakeId ) {
                return nsSnackBar.error( __( 'Save the stocktake as a draft first.' ) );
            }

            Popup.show( nsPosConfirmPopupVue, {
                title: __( 'Confirm Your Action' ),
                message: __( 'The stocktake will move to counting. Would you like to confirm?' ),
                onAction: ( action ) => {
                    if ( action ) {
                        nsHttpClient.get( `/api/products/stocktakes/${this.stocktakeId}/start-counting` )
                            .subscribe({
                                next: result => {
                                    nsSnackBar.success( result.message );
                                    this.status = 'counting';
                                },
                                error: error => nsSnackBar.error( error.message )
                            });
                    }
                }
            });
        },
        approveStocktake() {
            if ( ! this.isDraft || ! this.stocktakeId ) {
                return nsSnackBar.error( __( 'Save the stocktake as a draft first.' ) );
            }

            Popup.show( nsPosConfirmPopupVue, {
                title: __( 'Confirm Your Action' ),
                message: __( 'The differences will be applied to the stock and the stocktake will be locked. Would you like to confirm?' ),
                onAction: ( action ) => {
                    if ( action ) {
                        nsHttpClient.get( `/api/products/stocktakes/${this.stocktakeId}/approve` )
                            .subscribe({
                                next: result => {
                                    nsSnackBar.success( result.message );
                                    this.status = 'approved';
                                },
                                error: error => nsSnackBar.error( error.message )
                            });
                    }
                }
            });
        },
        cancelStocktake() {
            if ( ! this.isDraft || ! this.stocktakeId ) {
                return;
            }

            Popup.show( nsPosConfirmPopupVue, {
                title: __( 'Confirm Your Action' ),
                message: __( 'The stocktake will be canceled. No stock will be modified. Would you like to confirm?' ),
                onAction: ( action ) => {
                    if ( action ) {
                        nsHttpClient.get( `/api/products/stocktakes/${this.stocktakeId}/cancel` )
                            .subscribe({
                                next: result => {
                                    nsSnackBar.success( result.message );
                                    this.status = 'canceled';
                                },
                                error: error => nsSnackBar.error( error.message )
                            });
                    }
                }
            });
        },
        provideReason( product ) {
            const promise   =   new Promise( ( resolve, reject ) => {
                Popup.show( nsPromptPopupVue, {
                    title: __( 'More Details' ),
                    resolve,
                    reject,
                    message: __( 'Useful to describe better what are the reasons for the difference.' ),
                    input: product.description,
                    onAction: ( input ) => {
                        if ( input !== false ) {
                            product.description     =   input;
                        }
                    }
                });
            });

            promise.then( result => {
                nsSnackBar.success( __( 'The reason has been updated.' ) ).susbcribe();
            }).catch( error => {
                // ...
            })
        },
        async selectStocktakeUnit( product ) {
            try {
                const result = await new Promise( ( resolve, reject ) => {
                    Popup.show( nsSelectPopupVue, {
                        label: __( 'Select Unit' ),
                        resolve,
                        reject,
                        description: __( 'Select the unit that you want to count the stock with.' ),
                        name: 'adjust_unit',
                        options: product.quantities.map( quantity => {
                            return {
                                label: quantity.unit.name,
                                value: quantity
                            }
                        }),
                    });
                });

                const index             =   this.products.indexOf( product );
                const otherProducts     =   this.products.filter( ( product, _index ) => _index !== index );
                const exists    =   otherProducts
                    .filter( __product => __product.adjust_unit.unit.id === result.unit.id && __product.adjust_unit.product_id === result.product_id ).length > 0;

                if ( exists ) {
                    return nsSnackBar.error( __( 'A similar product with the same unit already exists.' ) );
                }

                product.adjust_unit    =   result;
                product.counted_quantity = result.quantity || 0;
                this.$forceUpdate();

            } catch ( exception ) {
                throw exception;
            }
        },
        removeProduct( product ) {
            Popup.show( nsPosConfirmPopupVue, {
                title: __( 'Confirm Your Action' ),
                message: __( 'Would you like to remove this product from the table ?' ),
                onAction: ( action ) => {
                    if ( action ) {
                        const index     =   this.products.indexOf( product );
                        this.products.splice( index, 1 );
                    }
                }
            });
        },
        deleteSelectedProducts() {
            Popup.show( nsPosConfirmPopupVue, {
                title: __( 'Confirm Your Action' ),
                message: __( 'Would you like to remove the selected products from the table ?' ),
                onAction: ( action ) => {
                    if ( action ) {
                        this.products   =   this.products.filter( product => ! product.selected );
                    }
                }
            });
        }
    },
    watch: {
        search() {
            if ( this.search.length > 0 ) {
                clearTimeout( this.timeout );
                this.timeout    =   setTimeout( () => {
                    this.searchProduct( this.search );
                }, 500 );
            } else {
                this.closeSearch();
            }
        }
    }
}
</script>
<template>
    <div>
        <div class="input-field flex border-2 input-group rounded mb-2">
            <input v-model="title" :disabled="! isEditable" type="text" class="p-2 flex-auto outline-hidden" :placeholder="__( 'Stocktake title (optional)' )">
        </div>
        <div class="input-field flex border-2 input-group rounded mb-2">
            <input v-model="description" :disabled="! isEditable" type="text" class="p-2 flex-auto outline-hidden" :placeholder="__( 'Stocktake description (optional)' )">
        </div>
        <div v-if="isEditable" class="input-field flex border-2 input-group rounded">
            <input @keyup.esc="closeSearch()" ref="searchField" v-model="search" type="text" class="p-2 flex-auto outline-hidden">
            <button class="px-3 py-2 rounded-none">{{ __( 'Search' ) }}</button>
        </div>
        <div class="h-0" v-if="suggestions.length > 0">
            <div class="">
                <ul class="shadow h-96 relative z-10 ns-vertical-menu zoom-in-entrance anim-duration-300 overflow-y-auto">
                    <li @click="addSuggestion( suggestion )" v-for="suggestion of suggestions" :key="suggestion.id" class="cursor-pointer border-b p-2 flex justify-between">
                        <span>{{ suggestion.name }}</span>
                    </li>
                </ul>
            </div>
        </div>
        <div class="ns-box rounded shadow my-2 w-full ">
            <table class="table w-full ns-table">
                <thead class="border-b">
                    <tr>
                        <td class="p-2">{{ __( 'Product' ) }}</td>
                        <td width="120" class="p-2 text-center hidden md:table-cell">{{ __( 'System' ) }}</td>
                        <td width="120" class="p-2 text-center hidden md:table-cell">{{ __( 'Counted' ) }}</td>
                        <td width="120" class="p-2 text-center hidden md:table-cell">{{ __( 'Difference' ) }}</td>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="products.length === 0">
                        <td class="p-2 border-b text-center hidden md:table-cell" colspan="6">{{ __( 'Search and add some products' ) }}</td>
                        <td class="p-2 border-b text-center table-cell md:hidden" colspan="4">{{ __( 'Search and add some products' ) }}</td>
                    </tr>
                    <tr :key="product.id" v-for="product of products">
                        <td class="p-2 border">
                            <div class="flex justify-between">
                                <div>
                                    <h3 class="font-bold cursor-pointer" @click="product.selected  =   ! product.selected"><input type="checkbox" :checked="product.selected" name="" id=""> {{ product.name }} ({{ getSystemQuantity( product ) }})</h3>
                                </div>
                                <div>
                                    <span class="md:hidden cursor-pointer border-dashed border-b border-info-secondary" @click="openQuantityPopup( product )">
                                        {{ __( 'Counted' ) }} : {{ product.counted_quantity }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex -mx-2 md:flex-row flex-wrap">
                                <div class="px-2 w-1/2 md:w-auto" @click="selectStocktakeUnit( product )">
                                    <div class="text-xs cursor-pointer border-b border-dashed border-info-secondary py-1">
                                        <span class="text-xs">{{ __( 'Unit:' ) }}</span>&nbsp;
                                        <span v-if="product.adjust_unit.unit" class="">{{ product.adjust_unit.unit.name }}</span>
                                        <span v-if="! product.adjust_unit.unit">{{ __( 'N/A' ) }}</span>
                                    </div>
                                </div>
                                <div class="px-2 w-1/2 md:w-auto" @click="provideReason( product )">
                                    <div class="text-xs cursor-pointer border-b border-dashed border-info-secondary py-1">
                                        <span class="text-xs">{{ __( 'Reason:' ) }}</span>&nbsp;
                                        <span v-if="product.description">
                                            {{ __( 'Provided' ) }}
                                        </span>
                                        <span v-else="product.description">
                                            {{ __( 'Not Provided' ) }}
                                        </span>
                                    </div>
                                </div>
                                <div v-if="isEditable" class="px-2 w-1/2 md:w-auto" @click="removeProduct( product )">
                                    <div class="text-xs cursor-pointer border-b border-dashed border-danger-secondary py-1">
                                        <span class="text-xs">{{ __( 'Remove' ) }}</span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="p-2 border hidden md:table-cell">
                            <div class="flex items-center justify-center">
                                <span class="border-b border-dashed border-info-secondary py-2 px-4">{{ getSystemQuantity( product ) }}</span>
                            </div>
                        </td>
                        <td class="p-2 border hidden md:table-cell" @click="isEditable && openQuantityPopup( product )">
                            <div class="flex items-center justify-center cursor-pointer">
                                <span class="border-b border-dashed border-info-secondary py-2 px-4">{{ product.counted_quantity }}</span>
                            </div>
                        </td>
                        <td class="p-2 border hidden md:table-cell">
                            <div class="flex items-center justify-center">
                                <span class="py-2 px-4" :class="getDifference( product ) === 0 ? '' : ( getDifference( product ) < 0 ? 'text-red-500 font-bold' : 'text-green-500 font-bold' )">{{ getDifference( product ) }}</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="ns-box-footer p-2 flex justify-end">
                <div class="-mx-2 flex">
                    <div class="px-2">
                        <ns-button v-if="isEditable && products.filter( p => p.selected ).length > 0" @click="deleteSelectedProducts()" type="error">
                            <div class="flex items-center justify-center h-6">
                                <i class="las la-trash"></i>
                            </div>
                            <span class="hidden md:inline-block">{{ __( 'Remove Selected' ) }}</span>
                        </ns-button>
                    </div>
                    <div v-if="isEditable" class="px-2">
                        <ns-button @click="saveDraft()" type="warning">{{ __( 'Save as Draft' ) }}</ns-button>
                    </div>
                    <div v-if="isEditable && status === 'draft'" class="px-2">
                        <ns-button @click="startCounting()" type="info">{{ __( 'Start Counting' ) }}</ns-button>
                    </div>
                    <div v-if="isEditable && status === 'counting'" class="px-2">
                        <ns-button @click="approveStocktake()" type="info">{{ __( 'Approve' ) }}</ns-button>
                    </div>
                    <div v-if="isEditable" class="px-2">
                        <ns-button @click="cancelStocktake()" type="error">{{ __( 'Cancel' ) }}</ns-button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
