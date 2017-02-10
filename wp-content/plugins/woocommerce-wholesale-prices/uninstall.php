<?php
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();

global $wc_wholesale_prices;

if ( !isset( $wc_wholesale_prices ) || !is_a( $wc_wholesale_prices , 'WooCommerceWholeSalePrices' ) ) {

    // Include Necessary Files
    require_once ( 'woocommerce-wholesale-prices.options.php' );
    require_once ( 'woocommerce-wholesale-prices.plugin.php' );

    $wc_wholesale_prices = WooCommerceWholeSalePrices::getInstance();

}

$wc_wholesale_prices->uninstall();