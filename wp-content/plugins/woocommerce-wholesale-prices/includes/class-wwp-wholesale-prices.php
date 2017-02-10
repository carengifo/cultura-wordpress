<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WWP_Wholesale_Prices {

    private static $_instance;

    public static function getInstance() {

        if( !self::$_instance instanceof self )
            self::$_instance = new self;

        return self::$_instance;

    }

    /**
     * Return product wholesale price for a given wholesale user role.
     *
     * @deprecated
     * @since 1.0.0
     * @param $product_id
     * @param $userWholesaleRole
     * @return string
     */
    public static function getUserProductWholesalePrice( $product_id , $userWholesaleRole ) {

        return self::getProductWholesalePrice( $product_id , $userWholesaleRole );

    }

    /**
     * Return product wholesale price for a given wholesale user role.
     *
     * @param $product_id
     * @param $userWholesaleRole
     * @param $quantity
     *
     * @return string
     * @since 1.0.0
     */
    public static function getProductWholesalePrice( $product_id , $userWholesaleRole , $quantity = 1 ) {

        if ( empty( $userWholesaleRole ) ) {

            return '';

        } else {

            if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {

                $baseCurrencyWholesalePrice = $wholesalePrice = get_post_meta( $product_id , $userWholesaleRole[ 0 ] . '_wholesale_price' , true );

                if ( $baseCurrencyWholesalePrice ) {

                    $activeCurrency = get_woocommerce_currency();
                    $baseCurrency   = WWP_ACS_Integration_Helper::get_product_base_currency( $product_id );

                    if ( $activeCurrency == $baseCurrency )
                        $wholesalePrice = $baseCurrencyWholesalePrice; // Base Currency
                    else {

                        $wholesalePrice = get_post_meta( $product_id , $userWholesaleRole[ 0 ] . '_' . $activeCurrency . '_wholesale_price' , true );

                        if ( !$wholesalePrice ) {

                            /*
                             * This specific currency has no explicit wholesale price (Auto). Therefore will need to convert the wholesale price
                             * set on the base currency to this specific currency.
                             *
                             * This is why it is very important users set the wholesale price for the base currency if they want wholesale pricing
                             * to work properly with aelia currency switcher plugin integration.
                             */
                            $wholesalePrice = WWP_ACS_Integration_Helper::convert( $baseCurrencyWholesalePrice , $activeCurrency , $baseCurrency );

                        }

                    }

                    $wholesalePrice = apply_filters( 'wwp_filter_' . $activeCurrency . '_wholesale_price' , $wholesalePrice , $product_id , $userWholesaleRole , $quantity );

                } else
                    $wholesalePrice = ''; // Base currency not set. Ignore the rest of the wholesale price set on other currencies.

            } else
                $wholesalePrice = get_post_meta( $product_id , $userWholesaleRole[ 0 ] . '_wholesale_price' , true );

            return apply_filters( 'wwp_filter_wholesale_price' , $wholesalePrice , $product_id , $userWholesaleRole , $quantity );

        }

    }

    /**
     * Filter callback that alters the product price, it embeds the wholesale price of a product for a wholesale user.
     *
     * @param $price
     * @param $product
     * @param $userWholesaleRole
     *
     * @return mixed|string
     * @since 1.0.0
     */
    public function wholesalePriceHTMLFilter( $price , $product , $userWholesaleRole ) {

        if ( !empty( $userWholesaleRole ) ) {

            $wholesalePrice = '';

            if ( $product->product_type == 'simple' ) {

                $wholesalePrice = trim( $this->getProductWholesalePrice( $product->id , $userWholesaleRole ) );
                $wholesalePrice = apply_filters( 'wwp_filter_wholesale_price_shop' , $wholesalePrice , $product->id , $userWholesaleRole );

                if ( strcasecmp( $wholesalePrice , '' ) != 0 )
                    $wholesalePrice = wc_price( $wholesalePrice ) . apply_filters( 'wwp_filter_wholesale_price_display_suffix' , $product->get_price_suffix() );

            } elseif ( $product->product_type == 'variable' ) {

                $variations = $product->get_available_variations();
                $minPrice = '';
                $maxPrice = '';
                $someVariationsHaveWholesalePrice = false;

                foreach ( $variations as $variation ) {

                    if ( !$variation[ 'is_purchasable' ] )
                        continue;

                    if ( function_exists( 'wc_get_product' ) )
                        $variation = wc_get_product( $variation[ 'variation_id' ] );
                    else
                        $variation = WWP_WC_Functions::wc_get_product( $variation[ 'variation_id' ] );

                    $currVarWholesalePrice = trim( $this->getProductWholesalePrice( $variation->variation_id , $userWholesaleRole ) );
                    $currVarWholesalePrice = apply_filters( 'wwp_filter_wholesale_price_shop' , $currVarWholesalePrice, $variation->variation_id, $userWholesaleRole );

                    if ( method_exists( $variation , 'get_display_price' ) )
                        $currVarPrice = $variation->get_display_price();
                    else
                        $currVarPrice = WWP_WC_Functions::get_display_price( $variation );

                    if ( strcasecmp( $currVarWholesalePrice , '' ) != 0 ) {

                        $currVarPrice = $currVarWholesalePrice;

                        if ( !$someVariationsHaveWholesalePrice )
                            $someVariationsHaveWholesalePrice = true;

                    }

                    if ( strcasecmp( $minPrice , '' ) == 0 || $currVarPrice < $minPrice )
                        $minPrice = $currVarPrice;

                    if ( strcasecmp( $maxPrice , '' ) == 0 || $currVarPrice > $maxPrice )
                        $maxPrice = $currVarPrice;

                }

                // Only alter price html if, some/all variations of this variable product have sale price and
                // min and max price have valid values
                if( $someVariationsHaveWholesalePrice && strcasecmp( $minPrice , '' ) != 0 && strcasecmp( $maxPrice , '' ) != 0 ) {

                    if ( $minPrice != $maxPrice && $minPrice < $maxPrice )
                        $wholesalePrice = wc_price( $minPrice ) . ' - ' . wc_price( $maxPrice ) . apply_filters( 'wwp_filter_wholesale_price_display_suffix' , $product->get_price_suffix() );
                    else
                        $wholesalePrice = wc_price( $maxPrice ) . apply_filters( 'wwp_filter_wholesale_price_display_suffix' , $product->get_price_suffix() );

                }

                $wholesalePrice = apply_filters( 'wwp_filter_variable_product_wholesale_price_range' , $wholesalePrice , $price , $product , $userWholesaleRole , $minPrice , $maxPrice );

            }

            if ( strcasecmp( $wholesalePrice , '' ) != 0 ) {

                // Crush out existing prices, regular and sale
                if ( strpos( $price , 'ins') !== false ) {

                    // Handle when regular price is on sale (they use an ins element before the price span)
                    $wholesalePriceHTML = preg_replace( '/<ins><span/' , '<del><ins><span' , $price );

                } else {

                    // Handle regular prices (not on sale)
                    $wholesalePriceHTML = preg_replace( '/^<span/' , '<del><span' , $price , 1 );

                }

                // Handle prices ending in a price suffix
                $beforeSuffixReplace = $wholesalePriceHTML;
                $wholesalePriceHTML = preg_replace( '/<\/small>$/' , '</small></del>' , $wholesalePriceHTML, 1 );

                if ( strpos( $price , 'ins') !== false &&
                    strcmp( $beforeSuffixReplace, $wholesalePriceHTML ) === 0) {

                    // No price suffix AND produce IS on sale
                    $wholesalePriceHTML = preg_replace( '/<\/ins>$/' , '</ins></del>' , $wholesalePriceHTML, 1 );

                } else if ( strpos( $price , 'ins') === false &&
                    strcmp( $beforeSuffixReplace, $wholesalePriceHTML ) === 0) {

                    // No price suffix AND not on sale
                    $wholesalePriceHTML = preg_replace( '/<\/span>$/' , '</span></del>' , $wholesalePriceHTML, 1 );

                }

                $wholesalePriceTitleText = __( 'Wholesale Price:' , 'woocommerce-wholesale-prices' );
                $wholesalePriceTitleText = apply_filters( 'wwp_filter_wholesale_price_title_text' , $wholesalePriceTitleText );

                $wholesalePriceHTML .= '<span style="display: block;" class="wholesale_price_container">
                                            <span class="wholesale_price_title">' . $wholesalePriceTitleText . '</span>
                                            <ins>' . $wholesalePrice . '</ins>
                                        </span>';

                return apply_filters( 'wwp_filter_wholesale_price_html' , $wholesalePriceHTML , $price , $product , $userWholesaleRole , $wholesalePriceTitleText , $wholesalePrice );

            }

        }

        // Only do this, if WooCommerce Wholesale Prices Premium plugin is installed
        if ( in_array( 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php' , apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

            // Variable product price range calculation for none wholesale users -------------------------------------------

            // Fix for the product price range if some variations are only to be displayed to certain wholesale roles
            // If code below is not present, woocommerce will include in the min and max price calculation the variations
            // that are not supposed to be displayed outside the set exclusive wholesale roles.
            // Therefore giving misleading min and max price range.
            if ( $product->product_type == 'variable' ) {

                $variations = $product->get_available_variations();

                $regularPriceRange = $this->_generateRegularVariableProductPriceRange( $product , $variations , 'regular' );
                $salePriceRange = $this->_generateRegularVariableProductPriceRange( $product , $variations , 'sale' );

                if ( $regularPriceRange !== false ) {

                    if ( $salePriceRange[ 'has_sale_price' ] )
                        $price = '<del>' . $regularPriceRange[ 'price_range' ] . '</del> <ins>' . $salePriceRange[ 'price_range' ] . '</ins>';
                    else
                        $price = $regularPriceRange[ 'price_range' ];

                } else {

                    // If no variations is available to none-wholesale customer then make the price empty.
                    // This is the same thing that WooCommerce does, leaving price as empty string.
                    $price = '';

                }

            }

        }

        return $price;

    }

    /**
     * The purpose for this helper function is to generate price range for none wholesale users for variable product.
     * You see, default WooCommerce calculations include all variations of a product to generate min and max price range.
     *
     * Now some variations have filters to be only visible to certain wholesale users ( Set by WWPP ). But WooCommerce
     * Don't have an idea about this, so it will still include those variations to the min and max price range calculations
     * thus giving incorrect price range.
     *
     * This is the purpose of this function, to generate a correct price range that recognizes the custom visibility filter
     * of each variations.
     *
     * @param $product
     * @param $variations
     * @param string $range_type
     * @return array
     *
     * @since 1.0.9
     */
    private function _generateRegularVariableProductPriceRange ( $product , $variations , $range_type = 'regular' ) {

        $hasSalePrice = false;
        $minPrice = '';
        $maxPrice = '';

        $active_currency = get_woocommerce_currency();

        foreach( $variations as $variation_item ) {

            if ( function_exists( 'wc_get_product' ) )
                $variation = wc_get_product( $variation_item[ 'variation_id' ] );
            else
                $variation = WWP_WC_Functions::wc_get_product( $variation_item[ 'variation_id' ] );

            if ( $range_type == 'regular' ) {

                if ( method_exists( $variation , 'get_display_price' ) )
                    $currVarPrice = $variation->get_display_price( $variation->get_regular_price() );
                else
                    $currVarPrice = WWP_WC_Functions::get_display_price( $variation , $variation->get_regular_price() );

                /*
                 * If it has a meta of is_purchasable of false, and it has a valid price.
                 * Meaning, this must be set on different reason, ex. variation currently out of stock, etc.
                 * Lets continue to the next item.
                 */
                if ( !$variation_item[ 'is_purchasable' ] && $currVarPrice )
                    continue;

                /*
                 * is_purchasable is false and it has no valid price and aelia currency switcher isn't present.
                 * Lets continue to the next item.
                 */
                if ( !$variation_item[ 'is_purchasable' ] && !$currVarPrice && !WWP_ACS_Integration_Helper::aelia_currency_switcher_active() )
                    continue;

                /*
                 * Default woocommerce regular price field is empty and Aelia currency switcher is active
                 * Meaning the user must have changed the base currency for this specific product.
                 * We manually get the prices the user sets on various currency and find out which is the base.
                 */
                if ( $currVarPrice == "" && WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {

                    $variation_regular_prices = get_post_meta( $variation_item[ 'variation_id' ] , 'variable_regular_currency_prices' , true );
                    $variation_regular_prices = json_decode( $variation_regular_prices );

                    if ( !empty( $variation_regular_prices ) ) {

                        $variation_regular_prices = get_object_vars( $variation_regular_prices );

                        $product_base_currency = WWP_ACS_Integration_Helper::get_product_base_currency( $variation_item[ 'variation_id' ] );

                        if ( array_key_exists( $product_base_currency , $variation_regular_prices ) && $variation_regular_prices[ $product_base_currency ] )
                            $currVarPrice = WWP_ACS_Integration_Helper::convert( $variation_regular_prices[ $product_base_currency ] , $active_currency , $product_base_currency );
                        else
                            $currVarPrice = 0; // No choice set it to zero. In this case there is an issue of how the user set up the pricing

                    } else
                        $currVarPrice = 0; // No choice set it to zero. In this case there is an issue of how the user set up the pricing

                } elseif ( $currVarPrice == "" && !WWP_ACS_Integration_Helper::aelia_currency_switcher_active() )
                    $currVarPrice = 0; // No choice set it to zero. In this case there is an issue of how the user set up the pricing

            } elseif ( $range_type == 'sale' ) {

                if ( method_exists( $variation , 'get_display_price' ) )
                    $currVarPrice = $variation->get_display_price( $variation->get_price() );
                else
                    $currVarPrice = WWP_WC_Functions::get_display_price( $variation , $variation->get_price() );

                /*
                 * If it has a meta of is_purchasable of false, and it has a valid price.
                 * Meaning, this must be set on different reason, ex. variation currently out of stock, etc.
                 * Lets continue to the next item.
                 */
                if ( !$variation_item[ 'is_purchasable' ] && $currVarPrice )
                    continue;

                /*
                 * is_purchasable is false and it has no valid price and aelia currency switcher isn't present.
                 * Lets continue to the next item.
                 */
                if ( !$variation_item[ 'is_purchasable' ] && !$currVarPrice && !WWP_ACS_Integration_Helper::aelia_currency_switcher_active() )
                    continue;

                // Set up $hasSalePrice variable flag
                if ( !$hasSalePrice && $variation->get_regular_price() != "" && $variation->get_price() != "" && $variation->get_regular_price() != $variation->get_price() )
                    $hasSalePrice = true;

                if ( $currVarPrice == "" && WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {

                    /*
                     * Default woocommerce sale price field is empty and Aelia currency switcher is active
                     * Meaning the user must have changed the base currency for this specific product.
                     * We manually get the prices the user sets on various currency and find out which is the base.
                     */

                    $variation_sale_prices = get_post_meta( $variation_item[ 'variation_id' ] , 'variable_sale_currency_prices' , true );
                    $variation_sale_prices = json_decode( $variation_sale_prices );

                    if ( !empty( $variation_sale_prices ) ) {

                        $variation_sale_prices = get_object_vars( $variation_sale_prices );

                        $product_base_currency = WWP_ACS_Integration_Helper::get_product_base_currency( $variation_item[ 'variation_id' ] );

                        if ( array_key_exists( $product_base_currency , $variation_sale_prices ) && $variation_sale_prices[ $product_base_currency ] ) {

                            $currVarPrice = WWP_ACS_Integration_Helper::convert( $variation_sale_prices[ $product_base_currency ] , $active_currency , $product_base_currency );

                            if ( !$hasSalePrice )
                                $hasSalePrice = true;

                        } else
                            $currVarPrice = 0; // No choice set it to zero. In this case there is an issue of how the user set up the pricing

                    } else
                        $currVarPrice = 0; // No choice set it to zero. In this case there is an issue of how the user set up the pricing

                } elseif ( $currVarPrice == "" && !WWP_ACS_Integration_Helper::aelia_currency_switcher_active() )
                    $currVarPrice = 0; // No choice set it to zero. In this case there is an issue of how the user set up the pricing

            }

            if ( $minPrice == "" || $currVarPrice < $minPrice )
                $minPrice = $currVarPrice;

            if ( $maxPrice == "" || $currVarPrice > $maxPrice )
                $maxPrice = $currVarPrice;

        }

        // Only alter price html if, some/all variations of this variable product have sale price and
        // min and max price have valid values
        if ( strcasecmp( $minPrice , '' ) != 0 && strcasecmp( $maxPrice , '' ) != 0 ) {

            if ( $minPrice != $maxPrice && $minPrice < $maxPrice )
                $priceRange =  wc_price( $minPrice ) . ' - ' . wc_price( $maxPrice ) . $product->get_price_suffix();
            else
                $priceRange = wc_price( $maxPrice ) . $product->get_price_suffix();

        } else {

            // Must be due to regular prices for variations of a variable product not set or regular user not meant
            // to see all variations of a variable product.
            return false;

        }

        $priceRange = apply_filters( 'wwp_filter_variable_product_price_range' , $priceRange , $product , $variations , $range_type , $minPrice , $maxPrice );

        return array(
                    'price_range'       =>  $priceRange,
                    'has_sale_price'    =>  $hasSalePrice
                );

    }

    /**
     * Apply wholesale price whenever "get_html_price" function gets called inside a variation product.
     * Variation product is the actual variation of a variable product.
     * Variable product is the parent product which contains variations.
     *
     * @param $price
     * @param $variation
     * @param $userWholesaleRole
     * @return mixed
     *
     * @since 1.0.3
     */
    public function wholesaleSingleVariationPriceHTMLFilter ( $price , $variation , $userWholesaleRole ) {

        if ( !empty( $userWholesaleRole ) ) {

            $currVarWholesalePrice = trim( $this->getProductWholesalePrice( $variation->variation_id , $userWholesaleRole ) );
            $currVarWholesalePrice = apply_filters( 'wwp_filter_wholesale_price_shop' , $currVarWholesalePrice , $variation->variation_id , $userWholesaleRole );

            if ( method_exists( $variation , 'get_display_price' ) )
                $currVarPrice = $variation->get_display_price();
            else
                $currVarPrice = WWP_WC_Functions::get_display_price( $variation );

            if ( strcasecmp( $currVarWholesalePrice , '' ) != 0 )
                $currVarPrice = $currVarWholesalePrice;

            $wholesalePrice = wc_price( $currVarPrice ) . apply_filters( 'wwp_filter_wholesale_price_display_suffix' , $variation->get_price_suffix() );

            if ( strcasecmp( $currVarWholesalePrice , '' ) != 0 ) {

                // Crush out existing prices, regular and sale
                if ( strpos( $price , 'ins' ) !== false ) {
                    $wholesalePriceHTML = str_replace( 'ins' , 'del' , $price );
                } else {
                    $wholesalePriceHTML = str_replace( '<span' , '<del><span' , $price );
                    $wholesalePriceHTML = str_replace( '</span>' , '</span></del>' , $wholesalePriceHTML );
                }

                $wholesalePriceTitleText = __( 'Wholesale Price:' , 'woocommerce-wholesale-prices' );
                $wholesalePriceTitleText = apply_filters( 'wwp_filter_wholesale_price_title_text' , $wholesalePriceTitleText );

                $wholesalePriceHTML .= '<span style="display: block;" class="wholesale_price_container">
                                            <span class="wholesale_price_title">' . $wholesalePriceTitleText . '</span>
                                            <ins>' . $wholesalePrice . '</ins>
                                        </span>';

                return apply_filters( 'wwp_filter_wholesale_price_html' , $wholesalePriceHTML , $price , $variation , $userWholesaleRole , $wholesalePriceTitleText , $wholesalePrice );

            } else {

                // If wholesale price is empty (""), means that this product has no wholesale price set
                // Just return the regular price
                return $price;

            }

        } else {

            // If $userWholeSaleRole is an empty array, meaning current user is not a wholesale customer,
            // just return original $price html
            return $price;

        }

    }

    /**
     * Apply product wholesale price upon adding to cart.
     *
     * @since 1.0.0
     * @since 1.2.3 Add filter hook 'wwp_filter_get_custom_product_type_wholesale_price' for which extensions can attach and add support for custom product types.
     * @access public
     *
     * @param $cart_object
     * @param $userWholesaleRole
     */
    public function applyProductWholesalePrice( $cart_object , $userWholesaleRole ) {

        $apply_wholesale_price = $this->checkIfApplyWholesalePrice( $cart_object , $userWholesaleRole );

        if ( !empty( $userWholesaleRole ) && $apply_wholesale_price === true ) {

            foreach ( $cart_object->cart_contents as $cart_item_key => $value ) {

                $apply_wholesale_price_product_level = $this->checkIfApplyWholesalePricePerProductLevel( $value , $cart_object , $userWholesaleRole );

                if ( $apply_wholesale_price_product_level === true ) {

                    $wholesalePrice = '';

                    if ( $value[ 'data' ]->product_type == 'simple' ) {

                        $wholesalePrice = trim( $this->getProductWholesalePrice( $value[ 'data' ]->id , $userWholesaleRole ) );
                        $wholesalePrice = apply_filters( 'wwp_filter_wholesale_price_cart' , $wholesalePrice , $value[ 'data' ]->id , $userWholesaleRole , $value );

                    } elseif ( $value[ 'data' ]->product_type == 'variation' ) {

                        $wholesalePrice = trim( $this->getProductWholesalePrice( $value[ 'data' ]->variation_id , $userWholesaleRole ) );
                        $wholesalePrice = apply_filters( 'wwp_filter_wholesale_price_cart' , $wholesalePrice , $value[ 'data' ]->variation_id , $userWholesaleRole , $value );

                    } else
                        $wholesalePrice = apply_filters( 'wwp_filter_get_custom_product_type_wholesale_price' , $wholesalePrice , $value , $userWholesaleRole );

                    if ( strcasecmp ( $wholesalePrice , '' ) != 0 ) {

                        do_action( 'wwp_action_before_apply_wholesale_price' , $wholesalePrice );
                        $value['data']->price = $wholesalePrice;

                    }

                } else {

                    if ( ( is_cart() || is_checkout() ) && ( !defined( 'DOING_AJAX' ) || !DOING_AJAX ) )
                        $this->printWCNotice( $apply_wholesale_price_product_level );

                }

            }

        } else {

            if ( ( is_cart() || is_checkout() ) && ( !defined( 'DOING_AJAX' ) || !DOING_AJAX ) )
                $this->printWCNotice( $apply_wholesale_price );

        }

    }

    /**
     * Add notice to WC Widget if the user (wholesale user) fails to avail the wholesale price requirements.
     * Only applies to wholesale users.
     *
     * @param $userWholesaleRole
     *
     * @since 1.0.0
     */
    public function beforeWCWidget( $userWholesaleRole ) {

        // We have to explicitly call this.
        // You see, WC Widget uses get_sub_total() to for its total field displayed on the widget.
        // This function gets only synced once calculate_totals() is triggered.
        // calculate_totals() is only triggered on the cart and checkout page.
        // So if we don't trigger calculate_totals() manually, there will be a scenario where the cart widget total isn't
        // synced with the cart page total. The user will have to go to the cart page, which triggers calculate_totals,
        // which synced get_sub_total(), for the user to have the cart widget synced the price.
        WC()->cart->calculate_totals();

        $applyWholesalePrice = $this->checkIfApplyWholesalePrice( WC()->cart , $userWholesaleRole );

        // Only display notice if user is a wholesale user.
        if ( !empty( $userWholesaleRole ) && $applyWholesalePrice === true ) {

            foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {

                $apply_wholesale_price_product_level = $this->checkIfApplyWholesalePricePerProductLevel( $values , WC()->cart , $userWholesaleRole );

                if ( $apply_wholesale_price_product_level !== true )
                    $this->printWCNotice( $apply_wholesale_price_product_level );

            }

        } else
            $this->printWCNotice( $applyWholesalePrice );

    }

    /**
     * Apply wholesale price on WC Cart Widget.
     *
     * @since 1.0.0
     * @since 1.2.4 Add filter hook 'wwp_filter_get_custom_product_type_wholesale_price' for which extensions can attach and add support for custom product types.
     * @access public
     *
     * @param $product_price
     * @param $cart_item
     * @param $cart_item_key
     * @param $userWholesaleRole
     * @return mixed
     */
    public function applyProductWholesalePriceOnDefaultWCCartWidget( $product_price , $cart_item , $cart_item_key ,  $userWholesaleRole ) {

        $apply_wholesale_price = $this->checkIfApplyWholesalePrice( WC()->cart , $userWholesaleRole );

        if ( !empty( $userWholesaleRole ) && $apply_wholesale_price === true ) {

            $apply_wholesale_price_product_level = $this->checkIfApplyWholesalePricePerProductLevel( $cart_item , WC()->cart , $userWholesaleRole );

            if ( $apply_wholesale_price_product_level === true ) {

                $wholesalePrice = '';

                if ( $cart_item[ 'data' ]->product_type == 'simple' ) {

                    $wholesalePrice = trim( $this->getProductWholesalePrice( $cart_item[ 'data' ]->id , $userWholesaleRole ) );
                    $wholesalePrice = apply_filters( 'wwp_filter_wholesale_price_cart' , $wholesalePrice , $cart_item[ 'data' ]->id , $userWholesaleRole , $cart_item );

                } elseif ( $cart_item['data']->product_type == 'variation' ) {

                    $wholesalePrice = trim( $this->getProductWholesalePrice( $cart_item[ 'data' ]->variation_id , $userWholesaleRole ) );
                    $wholesalePrice = apply_filters( 'wwp_filter_wholesale_price_cart' , $wholesalePrice , $cart_item[ 'data' ]->variation_id , $userWholesaleRole , $cart_item );

                } else
                    $wholesalePrice = apply_filters( 'wwp_filter_get_custom_product_type_wholesale_price' , $wholesalePrice , $cart_item , $userWholesaleRole );

                if ( strcasecmp( $wholesalePrice , '' ) != 0 ) {

                    do_action( 'wwp_action_before_apply_wholesale_price' , $wholesalePrice );
                    return wc_price( $wholesalePrice );

                }

            }

        }

        return $product_price;

    }

    /**
     * Check if we are good to apply wholesale price. Returns boolean true if we are ok to apply it.
     * Else returns an array of error message.
     *
     * @param $cart_object
     * @param $userWholesaleRole
     * @return bool
     *
     * @since 1.0.0
     */
    public function checkIfApplyWholesalePrice( $cart_object , $userWholesaleRole ) {

        $apply_wholesale_price = true;
        $apply_wholesale_price = apply_filters( 'wwp_filter_apply_wholesale_price_flag' , $apply_wholesale_price , $cart_object , $userWholesaleRole );
        return $apply_wholesale_price;

    }

    /**
     * Check if we are good to apply wholesale price per product basis.
     *
     * @param $value
     * @param $cart_object
     * @param $userWholesaleRole
     * @return bool
     *
     * @since 1.0.7
     */
    public function checkIfApplyWholesalePricePerProductLevel( $value , $cart_object , $userWholesaleRole ) {

        $apply_wholesale_price = true;
        $apply_wholesale_price = apply_filters( 'wwp_filter_apply_wholesale_price_per_product_basis' , $apply_wholesale_price , $value , $cart_object , $userWholesaleRole );
        return $apply_wholesale_price;

    }

    /**
     * Print WP Notices.
     *
     * @param $notices
     *
     * @since 1.0.7
     */
    public function printWCNotice( $notices ) {

        if ( is_array( $notices ) && array_key_exists( 'message' , $notices ) && array_key_exists( 'type' , $notices ) ) {
            // Pre Version 1.2.0 of wwpp where it sends back single dimension array of notice

            wc_print_notice( $notices[ 'message' ] , $notices[ 'type' ] );

        } elseif ( is_array( $notices ) ) {
            // Version 1.2.0 of wwpp where it sends back multiple notice via multi dimensional arrays

            foreach ( $notices as $notice ) {

                if ( array_key_exists( 'message' , $notice ) && array_key_exists( 'type' , $notice ) )
                    wc_print_notice( $notice[ 'message' ] , $notice[ 'type' ] );

            }

        }

    }

    /**
     * Add notice to wc notice queue.
     * Not used, might be useful in the future.
     *
     * @param $notices
     *
     * @since 1.1.4
     */
    public function addWCNotice( $notices ) {

        if ( is_array( $notices ) && array_key_exists( 'message' , $notices ) && array_key_exists( 'type' , $notices ) ) {
            // Pre Version 1.2.0 of wwpp where it sends back single dimension array of notice

            if ( !wc_has_notice( $notices[ 'message' ] , $notices[ 'type' ] ) )
                wc_add_notice( $notices[ 'message' ] , $notices[ 'type' ] );

        } elseif ( is_array( $notices ) ) {
            // Version 1.2.0 of wwpp where it sends back multiple notice via multi dimensional arrays

            foreach ( $notices as $notice ) {

                if ( array_key_exists( 'message' , $notice ) && array_key_exists( 'type' , $notice ) && !wc_has_notice( $notice[ 'message' ] , $notice[ 'type' ] ) )
                    wc_add_notice( $notice[ 'message' ] , $notice[ 'type' ] );

            }

        }

    }

}
