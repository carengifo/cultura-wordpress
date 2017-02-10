<?php
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * This is the main plugin class. It's purpose generally is for "ALL PLUGIN RELATED STUFF ONLY".
 * This file or class may also serve as a controller to some degree but most if not all business logic is distributed
 * across include files.
 *
 * Class WooCommerceWholeSalePrices
 */
require_once ( 'includes/class-wwp-aelia-currency-switcher-integration-helper.php' );
require_once ( 'includes/class-wwp-wholesale-roles.php' );
require_once ( 'includes/class-wwp-custom-fields.php' );
require_once ( 'includes/class-wwp-wholesale-prices.php' );
require_once ( 'includes/class-wwp-wc-functions.php' );

class WooCommerceWholeSalePrices {

    /*
     |------------------------------------------------------------------------------------------------------------------
     | Class Members
     |------------------------------------------------------------------------------------------------------------------
     */

    private static $_instance;

    private $_wwp_wholesale_roles;
    private $_wwp_custom_fields;
    private $_wwp_wholesale_prices;

    const VERSION = '1.2.6';




    /*
     |------------------------------------------------------------------------------------------------------------------
     | Mesc Functions
     |------------------------------------------------------------------------------------------------------------------
     */

    /**
     * Class constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {

        $this->_wwp_wholesale_roles = WWP_Wholesale_Roles::getInstance();
        $this->_wwp_custom_fields = WWP_Custom_Fields::getInstance();
        $this->_wwp_wholesale_prices = WWP_Wholesale_Prices::getInstance();

    }

    /**
     * Singleton Pattern.
     *
     * @since 1.0.0
     *
     * @return WooCommerceWholeSalePrices
     */
    public static function getInstance() {

        if( !self::$_instance instanceof self )
            self::$_instance = new self;

        return self::$_instance;

    }




    /*
     |------------------------------------------------------------------------------------------------------------------
     | Internationalization and Localization
     |------------------------------------------------------------------------------------------------------------------
     */

    /**
     * Load plugin text domain.
     *
     * @since 1.2.0
     */
    public function loadPluginTextDomain () {

        load_plugin_textdomain( 'woocommerce-wholesale-prices' , false , WWP_PLUGIN_BASE_PATH . 'languages/' );

    }




    /*
     |------------------------------------------------------------------------------------------------------------------
     | Bootstrap/Shutdown Functions
     |------------------------------------------------------------------------------------------------------------------
     */

    /**
     * Plugin activation hook callback.
     *
     * @since 1.0.0
     */
    public function init() {

        // Add plugin custom roles and capabilities
        $this->_wwp_wholesale_roles->addCustomRole( 'wholesale_customer' , __( 'Wholesale Customer' , 'woocommerce-wholesale-prices' ) );
        $this->_wwp_wholesale_roles->registerCustomRole( 'wholesale_customer' ,
                                                         __( 'Wholesale Customer' , 'woocommerce-wholesale-prices' ) ,
                                                        array(
                                                            'desc'  =>  __( 'This is the main wholesale user role.' , 'woocommerce-wholesale-prices' ),
                                                            'main'  =>  true
                                                        ) );
        $this->_wwp_wholesale_roles->addCustomCapability( 'wholesale_customer' , 'have_wholesale_price' );

    }

    /**
     * Plugin deactivation hook callback.
     *
     * @since 1.0.0
     */
    public function terminate() {

        // Remove plugin custom roles and capabilities
        $this->_wwp_wholesale_roles->removeCustomCapability( 'wholesale_customer' , 'have_wholesale_price' );
        $this->_wwp_wholesale_roles->removeCustomRole( 'wholesale_customer' );
        $this->_wwp_wholesale_roles->unregisterCustomRole( 'wholesale_customer' );

    }

    /**
     * Plugin uninstallation hook callback.
     *
     * @since 1.0.4
     */
    public function uninstall () {

        // Un-installation codes here if any ...

    }




    /*
    |-------------------------------------------------------------------------------------------------------------------
    | Admin Functions
    |-------------------------------------------------------------------------------------------------------------------
    */

    /**
     * Load Admin or Backend Related Styles and Scripts.
     *
     * @since 1.0.0
     *
     * @param $handle
     */
    public function loadBackEndStylesAndScripts( $handle ) {
        // Only plugin styles and scripts on the right time and on the right place

        // Scripts
        $screen = get_current_screen();

        $post_type = get_post_type();
        if ( !$post_type && isset( $_GET[ 'post_type' ] ) )
            $post_type = $_GET[ 'post_type' ];

        // Global
        // CSS
        wp_enqueue_style( 'wwp_wcoverrides_css' , WWP_CSS_URL . 'wwp-back-end-wcoverrides.css' , array() , self::VERSION , 'all' );

        if ( in_array( $screen->id , array( 'edit-product' ) ) ) {
            // Product listing

            // Css
            wp_enqueue_style( 'wwp_cpt_product_listing_admin_main_css' , WWP_CSS_URL . 'backend/cpt/product/wwp-cpt-product-listing-admin-main.css' , array() , self::VERSION , 'all' );

            // JS
            wp_enqueue_script( 'wwp_cpt_product_listing_admin_main_js' , WWP_JS_URL . 'backend/cpt/product/wwp-cpt-product-listing-admin-main.js' , array( 'jquery' , 'jquery-ui-core' , 'jquery-ui-accordion' ) , self::VERSION );

        } else if ( ( $handle == 'post-new.php' || $handle == 'post.php' ) && $post_type == 'product' ) {
            // Single product admin page ( new and edit pages )

            // CSS
            wp_enqueue_style( 'wwp_cpt_product_single_admin_main_css' , WWP_CSS_URL . 'backend/cpt/product/wwp-cpt-product-single-admin-main.css' , array() , self::VERSION , 'all' );

            // JS
            wp_enqueue_script( 'wwp_cpt_product_single_admin_main_js' , WWP_JS_URL . 'backend/cpt/product/wwp-cpt-product-single-admin-main.js' , array( 'jquery' , 'jquery-ui-core' , 'jquery-ui-accordion' ) , self::VERSION );
            wp_enqueue_script( 'wwp_single_variable_product_admin_custom_bulk_actions_js' , WWP_JS_URL . 'backend/cpt/product/wwp-single-variable-product-admin-custom-bulk-actions.js' , array( 'jquery' ) , self::VERSION , true );


            wp_localize_script( 'wwp_single_variable_product_admin_custom_bulk_actions_js' , 'wwp_custom_bulk_actions_params' , array(
                'wholesale_roles'     => $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles(),
                'i18n_prompt_message' => __( 'Enter a value (leave blank to remove pricing)' , 'woocommerce-wholesale-prices' )
            ) );

        }

    }

    /**
     * Load Frontend Related Styles and Scripts.
     *
     * @param $handle
     *
     * @since 1.0.0
     */
    public function loadFrontEndStylesAndScripts( $handle ) {

        // Only load plugin styles and scripts on the right time and on the right place

        global $post;

        if ( is_product() ) {

            /*
             * This is about the issue where if variable product has variation with all having the same price.
             * Wholesale price for a selected variation won't show on the single variable product page.
             *
             * This issue is already fixed in wwpp. Now if wwpp is installed and active, let wwpp fix this issue.
             * Only fix this issue here in wwp if wwpp is not present.
             */
            if ( !in_array( 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php' , apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

                wp_enqueue_style( 'wwp_single_product_page_css' , WWP_CSS_URL . 'frontend/product/wwp-single-product-page.css' , array() , self::VERSION , 'all' );

                if ( $post->post_type == 'product' ) {

                    if ( function_exists( 'wc_get_product' ) )
                        $product = wc_get_product( $post->ID );
                    else
                        $product = WWP_WC_Functions::wc_get_product( $post->ID );

                    if ( $product->product_type == 'variable' ) {

                        $userWholesaleRole = $this->_wwp_wholesale_roles->getUserWholesaleRole();
                        $variationsArr = array();

                        if ( !empty( $userWholesaleRole ) ) {

                            foreach ( $product->get_available_variations() as $variation ) {

                                if ( function_exists( 'wc_get_product' ) )
                                    $variationProduct = wc_get_product( $variation[ 'variation_id' ] );
                                else
                                    $variationProduct = WWP_WC_Functions::wc_get_product( $variation[ 'variation_id' ] );

                                if ( method_exists( $variation , 'get_display_price' ) )
                                    $currVarPrice = $variation->get_display_price( $variationProduct );
                                else
                                    $currVarPrice = WWP_WC_Functions::get_display_price( $variationProduct );

                                $wholesalePrice = $this->_wwp_wholesale_prices->getProductWholesalePrice( $variation[ 'variation_id' ] , $userWholesaleRole );
                                $wholesalePrice = apply_filters( 'wwp_filter_wholesale_price_shop' , $wholesalePrice, $variation[ 'variation_id' ] , $userWholesaleRole );

                                // Only pass through to wc_price if a numeric value given otherwise it will spit out $0.00
                                if ( is_numeric( $wholesalePrice ) ) {

                                    $wholesalePriceTitleText = __( 'Wholesale Price:' , 'woocommerce-wholesale-prices' );
                                    $wholesalePriceTitleText = apply_filters( 'wwp_filter_wholesale_price_title_text' , $wholesalePriceTitleText );

                                    $wholesalePriceHTML = '<del>' . wc_price( $currVarPrice ) . $product->get_price_suffix() . '</del>
                                                          <span style="display: block;" class="wholesale_price_container">
                                                              <span class="wholesale_price_title">' . $wholesalePriceTitleText . '</span>
                                                              <ins>' . wc_price( $wholesalePrice ) . apply_filters( 'wwp_filter_wholesale_price_display_suffix' , $product->get_price_suffix() ) . '</ins>
                                                          </span>';

                                    $wholesalePriceHTML = apply_filters( 'wwp_filter_wholesale_price_html' , $wholesalePriceHTML , $currVarPrice , $variationProduct , $userWholesaleRole , $wholesalePriceTitleText , $wholesalePrice );

                                    $wholesalePriceHTML = '<span class="price">' . $wholesalePriceHTML . '</span>';

                                    $priceHTML = $wholesalePriceHTML;

                                } else
                                    $priceHTML = '<p class="price">' . wc_price( $currVarPrice ) . $product->get_price_suffix() . '</p>';


                                $variationsArr[] =  array(
                                    'variation_id' => $variation[ 'variation_id' ],
                                    'price_html'   => $priceHTML
                                );

                            } // foreach ( $product->get_available_variations() as $variation )

                            wp_enqueue_script( 'wwp_single_variable_product_page_js' , WWP_JS_URL . 'frontend/product/wwp-single-variable-product-page.js' , array( 'jquery' ) , self::VERSION , true );
                            wp_localize_script( 'wwp_single_variable_product_page_js' , 'WWPVariableProductPageVars' , array( 'variations' => $variationsArr ) );

                        } // if ( !empty( $userWholesaleRole ) )

                    } // if ( $product->product_type == 'variable' )

                } // if ( $post->post_type == 'product' )

            } // if wwpp is not active

        }

    }

    /**
     * Register plugin menu.
     *
     * @since 1.0.0
     */
    public function registerMenu() {

    }




    /*
    |-------------------------------------------------------------------------------------------------------------------
    | Woocommerce Integration (Settings)
    |-------------------------------------------------------------------------------------------------------------------
    */

    /**
     * Activate plugin settings.
     *
     * @since 1.0.0
     */
    public function activatePluginSettings () {

        add_filter( "woocommerce_get_settings_pages" , array( self::getInstance() , 'initializePluginSettings' ) );

    }

    /**
     * Initialize plugin settings.
     *
     * @param $settings
     *
     * @return array
     * @since 1.0.0
     */
    public function initializePluginSettings ( $settings ) {

        $settings[] = include( WWP_INCLUDES_PATH . "class-wwp-settings.php" );

        return $settings;

    }

    /**
     * Default prices settings content (For upsell purposes).
     *
     * @param $sections
     * @return mixed
     *
     * @since 1.0.1
     */
    public function pluginSettingsSections ( $sections ) {

        return $sections;

    }

    /**
     * Default prices settings content (For upsell purposes).
     *
     * @param $settings
     * @param $current_section
     * @return array
     *
     * @since 1.0.1
     */
    public function pluginSettingsSectionContent ( $settings , $current_section ) {

        if ( $current_section == '' ) {

            // Filters Section
            $wwpGeneralSettings = apply_filters( 'wwp_settings_general_section_settings' , $this->_get_general_section_settings() ) ;
            $settings = array_merge( $settings , $wwpGeneralSettings );

        }

        return $settings;

    }

    /**
     * Default prices settings content (For upsell purposes).
     *
     * @return array
     *
     * @since 1.0.1
     */
    private function _get_general_section_settings() {

        return array(

            array(
                'name'  =>  __( 'Wholesale Prices Settings' , 'woocommerce-wholesale-prices' ),
                'type'  =>  'title',
                'desc'  =>  '',
                'id'    =>  'wwp_settings_section_title'
            ),
            array(
                'name'  =>  '',
                'type'  =>  'upsell_banner',
                'desc'  =>  '',
                'id'    =>  'wwp_settings_section_general_upsell_banner',
            ),
            array(
                'type'  =>  'sectionend',
                'id'    =>  'wwp_settings_sectionend'
            )

        );

    }




    /*
     |------------------------------------------------------------------------------------------------------------------
     | Variable Product Custom Bulk Action ( Single Product Page )
     |------------------------------------------------------------------------------------------------------------------
     */

    /**
     * Add variation custom bulk action options.
     *
     * @since 1.2.3
     * @access public
     */
    public function addVariationCustomBulkActionOptions() {

        $this->_wwp_custom_fields->addVariationCustomBulkActionOptions( $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles() );

    }

    /**
     * Execute variation custom bulk actions.
     *
     * @since 1.2.3
     * @access public
     *
     * @param $bulk_action
     * @param $data
     * @param $product_id
     * @param $variations
     */
    public function executeVariationCustomBulkActions( $bulk_action, $data, $product_id, $variations ) {

        $this->_wwp_custom_fields->executeVariationCustomBulkActions( $bulk_action , $data , $product_id,  $variations , $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles() );

    }




    /*
    |-------------------------------------------------------------------------------------------------------------------
    | Woocommerce Integration (Custom Fields)
    |-------------------------------------------------------------------------------------------------------------------
    */

    /**
     * Add wholesale custom price field to single product edit page.
     *
     * @since 1.0.0
     */
    public function addSimpleProductCustomFields() {

        $this->_wwp_custom_fields->addSimpleProductCustomFields( $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles() );

    }

    /**
     * Add wholesale custom price field to variable product edit page (on the variations section).
     *
     * @param $loop
     * @param $variation_data
     * @param $variation
     *
     * @since 1.0.0
     */
    public function addVariableProductCustomFields ( $loop , $variation_data , $variation ) {

        $this->_wwp_custom_fields->addVariableProductCustomFields ( $loop , $variation_data , $variation , $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles() );

    }

    /**
     * Save wholesale custom price field on single products.
     *
     * @param $post_id
     *
     * @since 1.0.0
     */
    public function saveSimpleProductCustomFields ( $post_id ) {

        $this->_wwp_custom_fields->saveSimpleProductCustomFields( $post_id , $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles() );

    }

    /**
     * Save wholesale custom price field on variable products.
     *
     * @param $post_id
     *
     * @since 1.0.0
     */
    public function saveVariableProductCustomFields( $post_id ) {

        $this->_wwp_custom_fields->saveVariableProductCustomFields( $post_id , $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles() );

    }

    /**
     * Add wholesale custom form fields on the quick edit option.
     *
     * @since 1.0.0
     */
    public function addCustomWholesaleFieldsOnQuickEditScreen(){

        $this->_wwp_custom_fields->addCustomWholesaleFieldsOnQuickEditScreen( $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles() );

    }

    /**
     * Save wholesale custom fields on the quick edit option.
     *
     * @param $product
     *
     * @since 1.0.0
     */
    public function saveCustomWholesaleFieldsOnQuickEditScreen( $product ) {

        $this->_wwp_custom_fields->saveCustomWholesaleFieldsOnQuickEditScreen( $product , $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles() );

    }

    /**
     * Add current user wholesale role, if any, on body tag classes.
     *
     * @param $classes
     * @return array
     *
     * @since 1.1.2
     */
    public function addWholesaleRoleToBodyClass ( $classes ) {

        return $this->_wwp_wholesale_roles->addWholesaleRoleToBodyClass( $classes );

    }

    /**
     * Add wholesale custom fields meta data on the product listing columns, this metadata is used to pre-populate the
     * wholesale custom form fields with the values of the meta data saved on the db.
     * This works in conjunction with wwp-quick-edit.js.
     *
     * @param $column
     * @param $post_id
     *
     * @since 1.0.0
     */
    public function addCustomWholesaleFieldsMetaDataOnProductListingColumn( $column , $post_id ) {

        $this->_wwp_custom_fields->addCustomWholesaleFieldsMetaDataOnProductListingColumn( $column , $post_id , $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles() );

    }

    /**
     * Add wholesale price column to the product listing page.
     *
     * @param $columns
     *
     * @return array
     */
    public function addWholesalePriceListingColumn( $columns ) {

        return $this->_wwp_custom_fields->addWholesalePriceListingColumn( $columns );

    }

    /**
     * Add wholesale price column to the product listing page
     *
     * @param $column
     * @param $post_id
     *
     * @since 1.0.1
     */
    public function addWholesalePriceListingColumnData( $column , $post_id ) {

        $registeredCustomRoles = unserialize( get_option( WWP_OPTIONS_REGISTERED_CUSTOM_ROLES ) );
        return $this->_wwp_custom_fields->addWholesalePriceListingColumnData( $column , $post_id , $registeredCustomRoles );

    }

    /**
     * Add plugin listing custom action link ( settings ).
     *
     * @param $links
     * @param $file
     * @return mixed
     *
     * @since 1.0.1
     */
    public function addPluginListingCustomActionLinks ( $links , $file ) {

        if ( $file == plugin_basename( WWP_PLUGIN_PATH . 'woocommerce-wholesale-prices.bootstrap.php' ) ) {

            $settings_link = '<a href="admin.php?page=wc-settings&tab=wwp_settings">' . __( 'Plugin Settings' , 'woocommerce-wholesale-prices' ) . '</a>';
            array_unshift( $links , $settings_link );

        }

        return $links;

    }




    /*
    |-------------------------------------------------------------------------------------------------------------------
    | Woocommerce Integration (Price)
    |-------------------------------------------------------------------------------------------------------------------
    */

    /**
     * Filter callback that alters the product price, it embeds the wholesale price of a product for a wholesale user.
     *
     * @param $price
     * @param $product
     *
     * @return mixed|string
     * @since 1.0.0
     */
    public function wholesalePriceHTMLFilter( $price , $product ) {

        return $this->_wwp_wholesale_prices->wholesalePriceHTMLFilter( $price , $product , $this->_wwp_wholesale_roles->getUserWholesaleRole() );

    }

    /**
     * Apply wholesale price whenever "get_html_price" function gets called inside a variation product.
     * Variation product is the actual variation of a variable product.
     * Variable product is the parent product which contains variations.
     *
     * @param $price
     * @param $variation
     * @return mixed
     *
     * @since 1.0.3
     */
    public function wholesaleSingleVariationPriceHTMLFilter ( $price , $variation ) {

        return $this->_wwp_wholesale_prices->wholesaleSingleVariationPriceHTMLFilter( $price , $variation , $this->_wwp_wholesale_roles->getUserWholesaleRole() );

    }

    /**
     * Apply product wholesale price upon adding to cart.
     *
     * @param $cart_object
     *
     * @since 1.0.0
     */
    public function applyProductWholesalePrice( $cart_object ) {

        $this->_wwp_wholesale_prices->applyProductWholesalePrice( $cart_object , $this->_wwp_wholesale_roles->getUserWholesaleRole() );

    }

    /**
     * Apply wholesale price on WC Cart Widget.
     *
     * @param $product_price
     * @param $cart_item
     * @param $cart_item_key
     * @return mixed
     *
     * @since 1.0.0
     */
    public function applyProductWholesalePriceOnDefaultWCCartWidget ( $product_price , $cart_item , $cart_item_key ) {

        return $this->_wwp_wholesale_prices->applyProductWholesalePriceOnDefaultWCCartWidget( $product_price , $cart_item , $cart_item_key , $this->_wwp_wholesale_roles->getUserWholesaleRole() );

    }

    /**
     * Add notice to WC Widget if the user (wholesale user) fails to avail the wholesale price requirements.
     * Only applies to wholesale users.
     *
     * @since 1.0.0
     */
    public function beforeWCWidget () {

        $this->_wwp_wholesale_prices->beforeWCWidget( $this->_wwp_wholesale_roles->getUserWholesaleRole() );

    }




    /*
     |------------------------------------------------------------------------------------------------------------------
     | Public Interfaces
     |------------------------------------------------------------------------------------------------------------------
     */

    /**
     * Returns an array that contains the wholesale role of the user. Usually 1 item only.
     *
     * @return array
     *
     * @since 1.2.0
     */
    public function getUserWholesaleRole () {

        return $this->_wwp_wholesale_roles->getUserWholesaleRole();

    }

    /**
     * Returns an array of all registered wholesale roles.
     *
     * @return mixed
     *
     * @since 1.2.0
     */
    public function getAllWholesaleRoles () {

        return $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

    }




    /*
     |------------------------------------------------------------------------------------------------------------------
     | AJAX Handlers
     |------------------------------------------------------------------------------------------------------------------
     */

    /**
     * Register AJAX interface callbacks.
     *
     * @since 1.0.0
     */
    public function registerAJAXCAllHandlers() {

    }




    /*
     |------------------------------------------------------------------------------------------------------------------
     | Utilities
     |------------------------------------------------------------------------------------------------------------------
     */

    /**
     * Write test log.
     *
     * @param      $msg
     * @param bool $append
     *
     * @since 1.0.0
     */
    public function writeTestLog( $msg , $append = true ) {

        if( $append === true )
            file_put_contents( WWP_LOGS_PATH . 'test_logs.txt' , $msg , FILE_APPEND );
        else
            file_put_contents( WWP_LOGS_PATH . 'test_logs.txt' , $msg );

    }

    /**
     * Write error log.
     *
     * @param      $msg
     * @param bool $append
     *
     * @since 1.0.0
     */
    public function writeErrorLog( $msg , $append = true ) {

        if( $append === true )
            file_put_contents( WWP_LOGS_PATH . 'error_logs.txt' , $msg , FILE_APPEND );
        else
            file_put_contents( WWP_LOGS_PATH . 'error_logs.txt' , $msg );

    }

}
