<?php
	
require get_template_directory() . '/includes/tgm/class-tgm-plugin-activation.php';

/**
 * Recommended plugins.
 * This list also drives the Quick Start installer on the Get Started page,
 * so keep Elementor first: the add-ons below depend on it.
 */
function jewelry_outlet_register_recommended_plugins() {
	$plugins = array(
		array(
			'name'             => __( 'Elementor', 'jewelry-outlet' ),
			'slug'             => 'elementor',
			'required'         => false,
			'force_activation' => false,
		),
		array(
			'name'             => __( 'WooCommerce', 'jewelry-outlet' ),
			'slug'             => 'woocommerce',
			'required'         => false,
			'force_activation' => false,
		),
		array(
			'name'             => __( 'Kirki Customizer Framework', 'jewelry-outlet' ),
			'slug'             => 'kirki',
			'required'         => false,
			'force_activation' => false,
		),
		array(
			'name'             => __( 'WPElemento Importer', 'jewelry-outlet' ),
			'slug'             => 'wpelemento-importer',
			'required'         => false,
			'force_activation' => false,
		),
		array(
			'name'             => __( 'GTranslate', 'jewelry-outlet' ),
			'slug'             => 'gtranslate',
			'source'           => '',
			'required'         => false,
			'force_activation' => false,
		),
		array(
			'name'             => __( 'FOX Currency Switcher Professional for WooCommerce', 'jewelry-outlet' ),
			'slug'             => 'woocommerce-currency-switcher',
			'source'           => '',
			'required'         => false,
			'force_activation' => false,
		),
		array(
			'name'             => __( 'ShopLentor', 'jewelry-outlet' ),
			'slug'             => 'woolentor-addons',
			'source'           => '',
			'required'         => false,
			'force_activation' => false,
		),
		array(
			'name'             => __( 'YITH WooCommerce Wishlist', 'jewelry-outlet' ),
			'slug'             => 'yith-woocommerce-wishlist',
			'source'           => '',
			'required'         => false,
			'force_activation' => false,
		),
	);
	
	$config = array();
	tgmpa( $plugins, $config );
}
add_action( 'tgmpa_register', 'jewelry_outlet_register_recommended_plugins' );

/**
 * WPElemento Importer recommends the same plugins (from inc/plugins.json) through its
 * own TGM copy; hide its notice so the recommendation above is shown only once.
 */
function jewelry_outlet_hide_importer_plugin_notice() {
	return 'do_not_allow';
}
add_filter( 'wpelemento_importer_tgmpa_show_admin_notice_capability', 'jewelry_outlet_hide_importer_plugin_notice' );