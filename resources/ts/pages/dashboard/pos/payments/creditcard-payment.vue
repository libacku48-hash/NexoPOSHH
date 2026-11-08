<template>
    <h1>{{ __( 'Credit Card' ) }}</h1>
</template>
<script>
import { __ } from '~/libraries/lang';

export default {
    name: 'creditcart-payment',
    props: [ 'identifier' ],
    methods: {
        __,
    }
}
</script>
