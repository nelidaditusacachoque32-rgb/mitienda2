<?php

function get_page_id_by_title($jewelry_outlet_pagename){
  $jewelry_outlet_args = array(
 'post_type' => 'page',
 'posts_per_page' => 1,
 'title' => $jewelry_outlet_pagename
  );
  $jewelry_outlet_query = new WP_Query( $jewelry_outlet_args );    $jewelry_outlet_page_id = '1';
 if (isset($jewelry_outlet_query->post->ID)) {
      $jewelry_outlet_page_id = $jewelry_outlet_query->post->ID;
  } return $jewelry_outlet_page_id;
}
//about theme info
add_action( 'admin_menu', 'jewelry_outlet_gettingstarted' );
function jewelry_outlet_gettingstarted() {
	add_theme_page( esc_html__('Jewelry Outlet', 'jewelry-outlet'), esc_html__('Jewelry Outlet', 'jewelry-outlet'), 'edit_theme_options', 'jewelry_outlet_about', 'jewelry_outlet_mostrar_guide');
}

/**
 * Imported home page: links are rebuilt from the page ID on every load so they
 * follow the current site URL and still work if the demo was imported elsewhere.
 */
function jewelry_outlet_get_demo_page() {
	$jewelry_outlet_done = get_option( 'jewelry_outlet_demo_imported', array() );
	$jewelry_outlet_id   = ! empty( $jewelry_outlet_done['id'] ) ? absint( $jewelry_outlet_done['id'] ) : 0;

	// Older saves only kept the edit URL.
	if ( ! $jewelry_outlet_id && ! empty( $jewelry_outlet_done['edit'] ) && preg_match( '/[?&]post=(\d+)/', $jewelry_outlet_done['edit'], $jewelry_outlet_m ) ) {
		$jewelry_outlet_id = absint( $jewelry_outlet_m[1] );
	}
	// Demo imported straight from the WPElemento Importer plugin: its wizard sends the user
	// back here with ?imported=true. Record it for this theme only, so a front page left
	// over from another theme's import doesn't count.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag from the importer's redirect.
	if ( ! $jewelry_outlet_id && isset( $_GET['imported'] ) && 'page' === get_option( 'show_on_front' ) ) {
		$jewelry_outlet_front = absint( get_option( 'page_on_front' ) );
		if ( $jewelry_outlet_front && 'builder' === get_post_meta( $jewelry_outlet_front, '_elementor_edit_mode', true ) ) {
			$jewelry_outlet_id = $jewelry_outlet_front;
			update_option( 'jewelry_outlet_demo_imported', array( 'id' => $jewelry_outlet_id ) );
		}
	}
	if ( ! $jewelry_outlet_id || 'publish' !== get_post_status( $jewelry_outlet_id ) ) {
		return array( 'imported' => false, 'permalink' => home_url( '/' ), 'edit' => '' );
	}

	$jewelry_outlet_edit = did_action( 'elementor/loaded' )
		? admin_url( 'post.php?post=' . $jewelry_outlet_id . '&action=elementor' )
		: get_edit_post_link( $jewelry_outlet_id, 'raw' );

	return array(
		'imported'  => true,
		'permalink' => get_permalink( $jewelry_outlet_id ),
		'edit'      => $jewelry_outlet_edit,
	);
}

/**
 * Bundle price and theme count, read from wpelemento.com
 * and cached so the Get Started page doesn't hit the store on every load.
 */
function jewelry_outlet_get_bundle_data() {
	$jewelry_outlet_data = get_transient( 'jewelry_outlet_bundle_data' );
	if ( is_array( $jewelry_outlet_data ) ) {
		return $jewelry_outlet_data;
	}

	$jewelry_outlet_data = array(
		'price'   => 99,
		'regular' => 3240,
		'count'   => '125+',
	);
	$jewelry_outlet_ok = false;

	$jewelry_outlet_res = wp_safe_remote_get( JEWELRY_OUTLET_THEME_BUNDLE . '.js', array( 'timeout' => 8 ) );
	if ( ! is_wp_error( $jewelry_outlet_res ) && 200 === wp_remote_retrieve_response_code( $jewelry_outlet_res ) ) {
		$jewelry_outlet_json = json_decode( wp_remote_retrieve_body( $jewelry_outlet_res ), true );
		if ( ! empty( $jewelry_outlet_json['price'] ) ) {
			$jewelry_outlet_ok = true;
			$jewelry_outlet_data['price'] = $jewelry_outlet_json['price'] / 100;
			if ( ! empty( $jewelry_outlet_json['compare_at_price'] ) ) {
				$jewelry_outlet_data['regular'] = $jewelry_outlet_json['compare_at_price'] / 100;
			}
			$jewelry_outlet_text = ( isset( $jewelry_outlet_json['title'] ) ? $jewelry_outlet_json['title'] : '' ) . ' ' . ( isset( $jewelry_outlet_json['description'] ) ? $jewelry_outlet_json['description'] : '' );
			if ( preg_match_all( '/(\d+)\+/', $jewelry_outlet_text, $jewelry_outlet_m ) ) {
				$jewelry_outlet_data['count'] = max( array_map( 'intval', $jewelry_outlet_m[1] ) ) . '+';
			}
		}
	}

	// Retry sooner when the store couldn't be reached.
	set_transient( 'jewelry_outlet_bundle_data', $jewelry_outlet_data, $jewelry_outlet_ok ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $jewelry_outlet_data;
}

/**
 * Pro theme price, read from the theme's product page on wpelemento.com
 * and cached the same way as the bundle data.
 */
function jewelry_outlet_get_pro_data() {
	$jewelry_outlet_data = get_transient( 'jewelry_outlet_pro_data' );
	if ( is_array( $jewelry_outlet_data ) ) {
		return $jewelry_outlet_data;
	}

	$jewelry_outlet_data = array(
		'price'   => 40,
		'regular' => 59,
	);
	$jewelry_outlet_ok = false;

	$jewelry_outlet_res = wp_safe_remote_get( JEWELRY_OUTLET_BUY_NOW . '.js', array( 'timeout' => 8 ) );
	if ( ! is_wp_error( $jewelry_outlet_res ) && 200 === wp_remote_retrieve_response_code( $jewelry_outlet_res ) ) {
		$jewelry_outlet_json = json_decode( wp_remote_retrieve_body( $jewelry_outlet_res ), true );
		if ( ! empty( $jewelry_outlet_json['price'] ) ) {
			$jewelry_outlet_ok = true;
			$jewelry_outlet_data['price']   = $jewelry_outlet_json['price'] / 100;
			$jewelry_outlet_data['regular'] = ! empty( $jewelry_outlet_json['compare_at_price'] ) ? $jewelry_outlet_json['compare_at_price'] / 100 : 0;
		}
	}

	set_transient( 'jewelry_outlet_pro_data', $jewelry_outlet_data, $jewelry_outlet_ok ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $jewelry_outlet_data;
}

/** Coupon box with copy button, shown above the buy buttons. */
function jewelry_outlet_coupon_box( $jewelry_outlet_code, $jewelry_outlet_offer ) {
	?>
	<div class="wpet-coupon">
		<span class="wpet-coupon-label"><?php echo esc_html( sprintf( /* translators: %s: discount */ __( 'Coupon · %s', 'jewelry-outlet' ), $jewelry_outlet_offer ) ); ?></span>
		<div class="wpet-coupon-row">
			<code class="wpet-coupon-code"><?php echo esc_html( $jewelry_outlet_code ); ?></code>
			<button type="button" class="wpet-coupon-copy" data-code="<?php echo esc_attr( $jewelry_outlet_code ); ?>"><?php esc_html_e( 'Copy', 'jewelry-outlet' ); ?></button>
		</div>
	</div>
	<?php
}

// Add a Custom CSS file to WP Admin Area
function jewelry_outlet_admin_theme_style( $jewelry_outlet_hook = '' ) {
	// Welcome-notice styles and dismiss script are needed on every admin screen.
	if ( ! get_option( 'jewelry_outlet_admin_notice' ) ) {
		wp_enqueue_style( 'jewelry-outlet-notice', esc_url( get_template_directory_uri() ) . '/includes/getstart/notice.css', array(), wp_get_theme()->get( 'Version' ) );
	}
	wp_register_script('jewelry-outlet-notice', esc_url(get_template_directory_uri()) . '/includes/getstart/js/notice.js', array('jquery'), time(), true);
	wp_localize_script( 'jewelry-outlet-notice', 'jewelryOutletNotice', array(
		'nonce' => wp_create_nonce( 'jewelry_outlet_dismissed_notice_nonce' ),
	) );
	wp_enqueue_script('jewelry-outlet-notice');

	if ( 'appearance_page_jewelry_outlet_about' !== $jewelry_outlet_hook ) {
		return;
	}

	$jewelry_outlet_ver = wp_get_theme()->get( 'Version' );
	wp_enqueue_style('jewelry-outlet-custom-admin-style', esc_url(get_template_directory_uri()) . '/includes/getstart/getstart.css', array(), $jewelry_outlet_ver);
	wp_enqueue_script('jewelry-outlet-getstart', esc_url(get_template_directory_uri()) . '/includes/getstart/js/getstart.js', array(), $jewelry_outlet_ver, true);

	$jewelry_outlet_importer = Jewelry_Outlet_Plugin_Activation_WPElemento_Importer::get_instance();
	$jewelry_outlet_demo     = jewelry_outlet_get_demo_page();
	wp_localize_script( 'jewelry-outlet-getstart', 'jewelryOutletGS', array(
		'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
		'nonce'        => wp_create_nonce( 'jewelry_outlet_gs' ),
		'importNonce'  => wp_create_nonce( 'whizzie_nonce' ),
		'plugins'      => $jewelry_outlet_importer->get_status(),
		'imported'     => $jewelry_outlet_demo['imported'],
		'permalink'    => $jewelry_outlet_demo['permalink'],
		'editUrl'      => $jewelry_outlet_demo['edit'],
		'customizeUrl' => admin_url( 'customize.php' ),
		'i18n'         => array(
			'install'    => esc_html__( 'Install and Activate', 'jewelry-outlet' ),
			'import'     => esc_html__( 'Import Demo', 'jewelry-outlet' ),
			'installing' => esc_html__( 'Installing', 'jewelry-outlet' ),
			'importing'  => esc_html__( 'Importing demo, this can take a minute…', 'jewelry-outlet' ),
			'error'      => esc_html__( 'Something went wrong. Click to try again.', 'jewelry-outlet' ),
			'copied'     => esc_html__( 'Copied!', 'jewelry-outlet' ),
			'copy'       => esc_html__( 'Copy', 'jewelry-outlet' ),
			/* translators: 1: current step, 2: plugins left */
			'stepPlugins'=> esc_html__( 'Step 1 of 3 · %d plugins will be installed and activated.', 'jewelry-outlet' ),
			'stepImport' => esc_html__( 'Step 2 of 3 · Plugins are ready. Import the demo content.', 'jewelry-outlet' ),
			'stepDone'   => esc_html__( 'Step 3 of 3 · Demo imported. Customize your site.', 'jewelry-outlet' ),
		),
	) );
}
add_action('admin_enqueue_scripts', 'jewelry_outlet_admin_theme_style');

// Changelog
if ( ! defined( 'JEWELRY_OUTLET_CHANGELOG_URL' ) ) {
    define( 'JEWELRY_OUTLET_CHANGELOG_URL', get_template_directory() . '/readme.txt' );
}

function jewelry_outlet_changelog_screen() {
	global $wp_filesystem;
	$jewelry_outlet_changelog_file = apply_filters( 'jewelry_outlet_changelog_file', JEWELRY_OUTLET_CHANGELOG_URL );

	if ( ! $jewelry_outlet_changelog_file || ! is_readable( $jewelry_outlet_changelog_file ) ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	$jewelry_outlet_raw = $wp_filesystem->get_contents( $jewelry_outlet_changelog_file );

	$jewelry_outlet_parts = explode( '== Changelog ==', $jewelry_outlet_raw );
	if ( empty( $jewelry_outlet_parts[1] ) ) {
		return;
	}
	$jewelry_outlet_entries = preg_split( '/^= /m', $jewelry_outlet_parts[1] );
	array_shift( $jewelry_outlet_entries );
	usort( $jewelry_outlet_entries, function ( $jewelry_outlet_a, $jewelry_outlet_b ) {
		preg_match( '/\d+(?:\.\d+)+/', strtok( $jewelry_outlet_a, "\n" ), $jewelry_outlet_va );
		preg_match( '/\d+(?:\.\d+)+/', strtok( $jewelry_outlet_b, "\n" ), $jewelry_outlet_vb );
		return version_compare( isset( $jewelry_outlet_vb[0] ) ? $jewelry_outlet_vb[0] : '0', isset( $jewelry_outlet_va[0] ) ? $jewelry_outlet_va[0] : '0' );
	} );

	echo '<div id="jewelry-outlet-changelog-container">';
	foreach ( $jewelry_outlet_entries as $jewelry_outlet_entry ) {
		$jewelry_outlet_lines = array_values( array_filter( array_map( 'trim', explode( "\n", $jewelry_outlet_entry ) ) ) );
		if ( empty( $jewelry_outlet_lines ) ) {
			continue;
		}
		$jewelry_outlet_title = trim( str_replace( '=', '', array_shift( $jewelry_outlet_lines ) ) );
		if ( false !== strpos( $jewelry_outlet_title, '::' ) ) {
			$jewelry_outlet_title = trim( substr( $jewelry_outlet_title, strrpos( $jewelry_outlet_title, '::' ) + 2 ) );
		}
		$jewelry_outlet_notes = trim( implode( ' ', array_map( function ( $jewelry_outlet_l ) {
			return ltrim( $jewelry_outlet_l, '* ' );
		}, $jewelry_outlet_lines ) ) );
		echo '<div class="block-changelog"><strong>' . esc_html( $jewelry_outlet_title ) . '</strong><span>' . esc_html( $jewelry_outlet_notes ) . '</span></div>';
	}
	echo '</div>';
	echo '<button type="button" id="jewelry-outlet-load-more" class="wpet-btn wpet-btn-outline">' . esc_html__( 'Load More', 'jewelry-outlet' ) . '</button>';
}

//guidline for about theme
function jewelry_outlet_mostrar_guide() {
	$jewelry_outlet_theme   = wp_get_theme( 'jewelry-outlet' );
	$jewelry_outlet_plugins = Jewelry_Outlet_Plugin_Activation_WPElemento_Importer::get_instance()->get_status();
	$jewelry_outlet_names   = wp_list_pluck( $jewelry_outlet_plugins, 'name' );
	$jewelry_outlet_img     = esc_url( get_template_directory_uri() ) . '/includes/getstart/images/';
	$jewelry_outlet_star    = '<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path d="M12 3.5l2.6 5.5 6 .8-4.4 4.2 1.1 6-5.3-2.9-5.3 2.9 1.1-6L3.4 9.8l6-.8z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>';
	$jewelry_outlet_bundle  = jewelry_outlet_get_bundle_data();
	$jewelry_outlet_b_price = '$' . number_format_i18n( $jewelry_outlet_bundle['price'], floor( $jewelry_outlet_bundle['price'] ) == $jewelry_outlet_bundle['price'] ? 0 : 2 );
	$jewelry_outlet_b_reg   = '$' . number_format_i18n( $jewelry_outlet_bundle['regular'], floor( $jewelry_outlet_bundle['regular'] ) == $jewelry_outlet_bundle['regular'] ? 0 : 2 );
	$jewelry_outlet_b_save  = $jewelry_outlet_bundle['regular'] > $jewelry_outlet_bundle['price'] ? round( ( 1 - $jewelry_outlet_bundle['price'] / $jewelry_outlet_bundle['regular'] ) * 100 ) : 0;
	$jewelry_outlet_pro     = jewelry_outlet_get_pro_data();
	$jewelry_outlet_p_price = '$' . number_format_i18n( $jewelry_outlet_pro['price'], floor( $jewelry_outlet_pro['price'] ) == $jewelry_outlet_pro['price'] ? 0 : 2 );
	$jewelry_outlet_p_reg   = '$' . number_format_i18n( $jewelry_outlet_pro['regular'], floor( $jewelry_outlet_pro['regular'] ) == $jewelry_outlet_pro['regular'] ? 0 : 2 );
	?>
<div class="wpet-wrap">

	<div class="wpet-notice">
		<span><?php echo esc_html( sprintf( /* translators: %s: plugin names */ __( 'This theme recommends: %s.', 'jewelry-outlet' ), implode( ', ', $jewelry_outlet_names ) ) ); ?></span>
		<em><?php esc_html_e( 'Handled by the Quick Start below', 'jewelry-outlet' ); ?></em>
	</div>

	<div class="wpet-card wpet-topbar">
		<img src="<?php echo $jewelry_outlet_img; ?>sticky-header-logo.png" alt="WP Elemento" />
		<div class="wpet-topbar-right">
			<span class="wpet-pill"><?php echo esc_html( sprintf( __( "You're on Free · v%s", 'jewelry-outlet' ), $jewelry_outlet_theme['Version'] ) ); ?></span>
			<a class="wpet-link" href="<?php echo esc_url( JEWELRY_OUTLET_FREE_THEME_DOC ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Documentation', 'jewelry-outlet' ); ?></a>
			<a class="wpet-btn wpet-btn-blue" href="<?php echo esc_url( JEWELRY_OUTLET_BUY_NOW ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade to Pro', 'jewelry-outlet' ); ?></a>
		</div>
	</div>

	<div class="wpet-card wpet-hero">
		<div class="wpet-hero-left">
			<h1><?php esc_html_e( 'Jewelry Outlet', 'jewelry-outlet' ); ?></h1>
			<h3><?php esc_html_e( 'Welcome to WP Elemento Theme!', 'jewelry-outlet' ); ?></h3>
			<p class="wpet-muted"><?php esc_html_e( 'Quick Start installs the required plugins and imports the demo, so your site looks like the preview.', 'jewelry-outlet' ); ?></p>

			<div class="wpet-steps" id="wpet-steps">
				<ol>
					<li data-step="1" class="is-active"><b>1</b><?php esc_html_e( 'Install plugins', 'jewelry-outlet' ); ?></li>
					<li data-step="2"><b>2</b><?php esc_html_e( 'Import demo', 'jewelry-outlet' ); ?></li>
					<li data-step="3"><b>3</b><?php esc_html_e( 'Customize', 'jewelry-outlet' ); ?></li>
				</ol>
				<div class="wpet-bar"><i id="wpet-bar-fill"></i></div>
				<p class="wpet-muted wpet-small" id="wpet-step-text" aria-live="polite"></p>
			</div>

			<div class="wpet-actions">
				<button type="button" class="wpet-btn wpet-btn-dark wpet-btn-lg" id="wpet-quick-start"><?php esc_html_e( 'Install and Activate', 'jewelry-outlet' ); ?></button>
				<a class="wpet-btn wpet-btn-outline wpet-btn-lg" id="wpet-visit" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener" hidden><?php esc_html_e( 'Visit Site', 'jewelry-outlet' ); ?></a>
				<a class="wpet-btn wpet-btn-outline wpet-btn-lg" id="wpet-edit" href="#" target="_blank" rel="noopener" hidden><?php esc_html_e( 'Edit With Elementor', 'jewelry-outlet' ); ?></a>
				<a class="wpet-btn wpet-btn-outline wpet-btn-lg" id="wpet-demo" href="<?php echo esc_url( JEWELRY_OUTLET_LIVE_DEMO ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Live Demo', 'jewelry-outlet' ); ?></a>
				<a class="wpet-link" href="#wpet-compare"><?php esc_html_e( 'Compare Free vs Pro', 'jewelry-outlet' ); ?></a>
			</div>
		</div>

		<div class="wpet-hero-right">
			<div class="wpet-shot">
				<img src="<?php echo esc_url( get_template_directory_uri() ); ?>/screenshot.png" alt="<?php esc_attr_e( 'Theme preview', 'jewelry-outlet' ); ?>" />
				<span class="wpet-version"><?php echo esc_html( sprintf( __( 'Version: %s', 'jewelry-outlet' ), $jewelry_outlet_theme['Version'] ) ); ?></span>
			</div>
			<div class="wpet-shot-actions">
				<a class="wpet-btn wpet-btn-blue" href="<?php echo esc_url( JEWELRY_OUTLET_BUY_NOW ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Buy Now', 'jewelry-outlet' ); ?></a>
				<a class="wpet-btn wpet-btn-dark" href="<?php echo esc_url( JEWELRY_OUTLET_LIVE_DEMO ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Demo', 'jewelry-outlet' ); ?></a>
				<a class="wpet-btn wpet-btn-pink" href="<?php echo esc_url( JEWELRY_OUTLET_THEME_BUNDLE ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Bundle', 'jewelry-outlet' ); ?></a>
				<a class="wpet-btn wpet-btn-outline" href="<?php echo esc_url( JEWELRY_OUTLET_FREE_THEME_DOC ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Free Doc', 'jewelry-outlet' ); ?></a>
			</div>
		</div>
	</div>

	<div class="wpet-cols">
		<aside class="wpet-side">
			<div class="wpet-pro">
				<div class="wpet-pro-head"><span class="wpet-badge wpet-badge-lg"><?php esc_html_e( 'PRO VERSION', 'jewelry-outlet' ); ?></span><small><?php esc_html_e( 'Most popular', 'jewelry-outlet' ); ?></small></div>
				<h2><?php esc_html_e( 'Jewelry Outlet Pro', 'jewelry-outlet' ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'All premium inner page templates included', 'jewelry-outlet' ); ?></li>
					<li><?php esc_html_e( 'Advanced colour, font and section options', 'jewelry-outlet' ); ?></li>
					<li><?php esc_html_e( 'One-click demo import', 'jewelry-outlet' ); ?></li>
					<li><?php esc_html_e( 'Priority support', 'jewelry-outlet' ); ?></li>
				</ul>
				<div class="wpet-pro-price"><strong><?php echo esc_html( $jewelry_outlet_p_price ); ?></strong><?php if ( $jewelry_outlet_pro['regular'] > $jewelry_outlet_pro['price'] ) : ?><s><?php echo esc_html( $jewelry_outlet_p_reg ); ?></s><?php endif; ?><small><?php esc_html_e( 'one-time', 'jewelry-outlet' ); ?></small></div>
				<?php jewelry_outlet_coupon_box( 'ELEMENTO25', __( '25% OFF', 'jewelry-outlet' ) ); ?>
				<a class="wpet-btn wpet-btn-blue wpet-btn-block" href="<?php echo esc_url( JEWELRY_OUTLET_BUY_NOW ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Buy Pro Theme', 'jewelry-outlet' ); ?></a>
			</div>

			<div class="wpet-card wpet-bundle">
				<img src="<?php echo $jewelry_outlet_img; ?>bundle.png" alt="" />
				<h2><?php esc_html_e( 'Elementor WordPress Theme Bundle', 'jewelry-outlet' ); ?></h2>
				<p class="wpet-price">
					<?php if ( $jewelry_outlet_b_save ) : ?><s><?php echo esc_html( $jewelry_outlet_b_reg ); ?></s><?php endif; ?>
					<strong><?php echo esc_html( $jewelry_outlet_b_price ); ?></strong>
					<?php if ( $jewelry_outlet_b_save ) : ?><span class="wpet-save"><?php echo esc_html( sprintf( /* translators: %d: percent saved */ __( 'SAVE %d%%', 'jewelry-outlet' ), $jewelry_outlet_b_save ) ); ?></span><?php endif; ?>
				</p>
				<p class="wpet-muted"><?php echo esc_html( sprintf( /* translators: %s: number of themes */ __( '%s premium Elementor themes under one license.', 'jewelry-outlet' ), $jewelry_outlet_bundle['count'] ) ); ?></p>
				<?php jewelry_outlet_coupon_box( 'BUNDLE20', __( '20% OFF', 'jewelry-outlet' ) ); ?>
				<a class="wpet-btn wpet-btn-pink wpet-btn-block" href="<?php echo esc_url( JEWELRY_OUTLET_THEME_BUNDLE ); ?>" target="_blank" rel="noopener"><?php echo esc_html( sprintf( /* translators: %s: bundle price */ __( 'Buy Bundle – %s', 'jewelry-outlet' ), $jewelry_outlet_b_price ) ); ?></a>
			</div>
		</aside>

		<main class="wpet-main">
			<section class="wpet-card">
				<h2><?php esc_html_e( 'Customizer Settings', 'jewelry-outlet' ); ?></h2>
				<div class="wpet-grid2">
					<div class="wpet-box"><h4><?php esc_html_e( 'Theme Customizer', 'jewelry-outlet' ); ?></h4><p class="wpet-muted"><?php esc_html_e( 'Logo, colours, menus and homepage sections.', 'jewelry-outlet' ); ?></p><a class="wpet-btn wpet-btn-dark" href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>" target="_blank"><?php esc_html_e( 'Open customizer', 'jewelry-outlet' ); ?></a></div>
					<div class="wpet-box"><h4><?php esc_html_e( 'Help Docs', 'jewelry-outlet' ); ?></h4><p class="wpet-muted"><?php esc_html_e( 'Step-by-step setup and configuration guide.', 'jewelry-outlet' ); ?></p><a class="wpet-btn wpet-btn-dark" href="<?php echo esc_url( JEWELRY_OUTLET_FREE_THEME_DOC ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Documentation', 'jewelry-outlet' ); ?></a></div>
					<div class="wpet-box"><h4><?php esc_html_e( 'Need Support?', 'jewelry-outlet' ); ?></h4><p class="wpet-muted"><?php esc_html_e( 'Free users get forum help.', 'jewelry-outlet' ); ?> <a class="wpet-strong-link" href="<?php echo esc_url( JEWELRY_OUTLET_BUY_NOW ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Pro users get priority support.', 'jewelry-outlet' ); ?></a></p><a class="wpet-btn wpet-btn-dark" href="<?php echo esc_url( JEWELRY_OUTLET_SUPPORT ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Support Forum', 'jewelry-outlet' ); ?></a></div>
					<div class="wpet-box"><h4><?php esc_html_e( 'Enjoying the theme?', 'jewelry-outlet' ); ?></h4>
						<div class="wpet-stars"><?php for ( $jewelry_outlet_s = 1; $jewelry_outlet_s <= 5; $jewelry_outlet_s++ ) : ?><a href="<?php echo esc_url( JEWELRY_OUTLET_REVIEW ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( sprintf( __( 'Rate %d stars', 'jewelry-outlet' ), $jewelry_outlet_s ) ); ?>"><?php echo $jewelry_outlet_star; // phpcs:ignore ?></a><?php endfor; ?></div>
						<p class="wpet-muted wpet-small"><?php esc_html_e( 'Tap a star to rate Jewelry Outlet.', 'jewelry-outlet' ); ?></p></div>
				</div>
			</section>

			<section class="wpet-card" id="wpet-compare">
				<div class="wpet-row-between"><h2><?php esc_html_e( 'Free vs Pro', 'jewelry-outlet' ); ?></h2><a class="wpet-link" href="<?php echo esc_url( JEWELRY_OUTLET_BUY_NOW ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade to Pro →', 'jewelry-outlet' ); ?></a></div>
				<table class="wpet-table">
					<thead><tr><th><?php esc_html_e( 'FEATURE', 'jewelry-outlet' ); ?></th><th><?php esc_html_e( 'FREE', 'jewelry-outlet' ); ?></th><th class="wpet-pro-col">PRO</th></tr></thead>
					<tbody>
						<tr><td><?php esc_html_e( 'Homepage demo import', 'jewelry-outlet' ); ?></td><td>Yes</td><td class="wpet-pro-col">Yes</td></tr>
						<tr><td><?php esc_html_e( 'Inner page templates', 'jewelry-outlet' ); ?></td><td>No</td><td class="wpet-pro-col">All included</td></tr>
						<tr><td><?php esc_html_e( 'Colour & typography options', 'jewelry-outlet' ); ?></td><td>Basic</td><td class="wpet-pro-col">Advanced</td></tr>
						<tr><td><?php esc_html_e( 'Support', 'jewelry-outlet' ); ?></td><td>Forum</td><td class="wpet-pro-col">Priority</td></tr>
					</tbody>
				</table>
			</section>

			<section class="wpet-card">
				<h2><?php esc_html_e( 'Link to customizer', 'jewelry-outlet' ); ?></h2>
				<div class="wpet-grid3">
					<a class="wpet-tile" target="_blank" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[control]=custom_logo' ) ); ?>"><span class="dashicons dashicons-admin-users"></span><?php esc_html_e( 'Upload logo', 'jewelry-outlet' ); ?></a>
					<a class="wpet-tile" target="_blank" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[panel]=nav_menus' ) ); ?>"><span class="dashicons dashicons-menu-alt"></span><?php esc_html_e( 'Edit menus', 'jewelry-outlet' ); ?></a>
					<a class="wpet-tile" target="_blank" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[section]=header_image' ) ); ?>"><span class="dashicons dashicons-align-center"></span><?php esc_html_e( 'Header image', 'jewelry-outlet' ); ?></a>
					<a class="wpet-tile" target="_blank" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[panel]=widgets' ) ); ?>"><span class="dashicons dashicons-screenoptions"></span><?php esc_html_e( 'Footer widget', 'jewelry-outlet' ); ?></a>
					<a class="wpet-tile wpet-tile-pro" target="_blank" rel="noopener" href="<?php echo esc_url( JEWELRY_OUTLET_BUY_NOW ); ?>"><?php esc_html_e( 'Colour presets', 'jewelry-outlet' ); ?><i class="wpet-badge">PRO</i></a>
					<a class="wpet-tile wpet-tile-pro" target="_blank" rel="noopener" href="<?php echo esc_url( JEWELRY_OUTLET_BUY_NOW ); ?>"><?php esc_html_e( 'Typography', 'jewelry-outlet' ); ?><i class="wpet-badge">PRO</i></a>
				</div>
			</section>

			<section class="wpet-card">
				<h2><?php esc_html_e( 'Change log', 'jewelry-outlet' ); ?></h2>
				<?php jewelry_outlet_changelog_screen(); ?>
			</section>
		</main>
	</div>
</div>
<?php } ?>
