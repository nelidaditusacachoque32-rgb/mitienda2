<?php
/**
 * Inline plugin install / activate handlers for the Get Started page.
 * Everything runs through admin-ajax so the user never leaves the page.
 */
if ( ! class_exists( 'Jewelry_Outlet_Plugin_Activation_WPElemento_Importer' ) ) {

    class Jewelry_Outlet_Plugin_Activation_WPElemento_Importer {

        private static $jewelry_outlet_instance;

        public static function get_instance() {
            if ( ! isset( self::$jewelry_outlet_instance ) ) {
                self::$jewelry_outlet_instance = new self();
            }
            return self::$jewelry_outlet_instance;
        }

        public function __construct() {
            add_action( 'wp_ajax_jewelry_outlet_gs_install', array( $this, 'install_and_activate' ) );
            add_action( 'wp_ajax_jewelry_outlet_gs_done', array( $this, 'save_import_result' ) );
        }

        /** Plugins handled by the Quick Start flow, read straight from the TGM registration. */
        public function get_plugins_list() {
            if ( ! isset( $GLOBALS['tgmpa'] ) || ! function_exists( 'jewelry_outlet_register_recommended_plugins' ) ) {
                return array();
            }
            // TGM skips its own registration on admin-ajax, so register here when it hasn't run.
            if ( empty( $GLOBALS['tgmpa']->plugins ) ) {
                jewelry_outlet_register_recommended_plugins();
            }
            $jewelry_outlet_list = array();
            foreach ( $GLOBALS['tgmpa']->plugins as $jewelry_outlet_plugin ) {
                $jewelry_outlet_list[] = array(
                    'name' => $jewelry_outlet_plugin['name'],
                    'slug' => $jewelry_outlet_plugin['slug'],
                );
            }
            return $jewelry_outlet_list;
        }

        public function get_plugin_file( $slug ) {
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            foreach ( array_keys( get_plugins() ) as $file ) {
                if ( strpos( $file, $slug . '/' ) === 0 ) {
                    return $file;
                }
            }
            return '';
        }

        /** Returns installed/active flags for every recommended plugin. */
        public function get_status() {
            if ( ! function_exists( 'is_plugin_active' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $jewelry_outlet_out = array();
            foreach ( $this->get_plugins_list() as $jewelry_outlet_plugin ) {
                $jewelry_outlet_file = $this->get_plugin_file( $jewelry_outlet_plugin['slug'] );
                $jewelry_outlet_out[] = array(
                    'name'      => $jewelry_outlet_plugin['name'],
                    'slug'      => $jewelry_outlet_plugin['slug'],
                    'installed' => '' !== $jewelry_outlet_file,
                    'active'    => '' !== $jewelry_outlet_file && is_plugin_active( $jewelry_outlet_file ),
                );
            }
            return $jewelry_outlet_out;
        }

        public function install_and_activate() {
            check_ajax_referer( 'jewelry_outlet_gs', 'nonce' );

            if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'You are not allowed to install plugins.', 'jewelry-outlet' ) ) );
            }

            $jewelry_outlet_slug = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
            $jewelry_outlet_allowed = wp_list_pluck( $this->get_plugins_list(), 'slug' );
            if ( ! in_array( $jewelry_outlet_slug, $jewelry_outlet_allowed, true ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Unknown plugin.', 'jewelry-outlet' ) ) );
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/misc.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

            // Element Pack redirects (and exits) straight after activation, which would cut off this reply.
            add_action( 'activated_plugin', function () {
                remove_action( 'activated_plugin', 'element_pack_activation_redirect', 20 );
            }, 1 );

            // Plugins loaded during install/activation may print notices; keep them out of the JSON reply.
            ob_start();
            $jewelry_outlet_done   = array();
            $jewelry_outlet_result = $this->activate_with_dependencies( $jewelry_outlet_slug, $jewelry_outlet_done );
            ob_end_clean();

            // Stop plugins from redirecting the next admin request to their own welcome/wizard page.
            delete_option( 'wpelemento_importer_do_activation_redirect' );
            delete_transient( 'elementor_activation_redirect' );
            delete_transient( '_elementor_activation_redirect' );
            delete_transient( '_wc_activation_redirect' );
            delete_option( 'woolentor_do_activation_redirect' );
            delete_transient( 'wpr_plugin_do_activation_redirect' );
            if ( 'redirect' === get_option( 'eael_setup_wizard' ) ) {
                update_option( 'eael_setup_wizard', 'init' );
            }

            if ( is_wp_error( $jewelry_outlet_result ) ) {
                // Core activation errors come wrapped in HTML; the page shows plain text.
                wp_send_json_error( array(
                    'message'   => wp_strip_all_tags( $jewelry_outlet_result->get_error_message() ),
                    'activated' => $jewelry_outlet_done,
                ) );
            }

            wp_send_json_success( array( 'slug' => $jewelry_outlet_slug, 'activated' => $jewelry_outlet_done ) );
        }

        /**
         * Installs (when missing) and activates a plugin. Plugins named in its
         * "Requires Plugins" header are handled first, otherwise WordPress refuses
         * the activation, e.g. a WooCommerce add-on while WooCommerce is inactive.
         */
        private function activate_with_dependencies( $slug, &$done, $depth = 0 ) {
            if ( in_array( $slug, $done, true ) ) {
                return true;
            }
            if ( $depth > 3 ) {
                return new WP_Error( 'jewelry_outlet_deps', esc_html__( 'Plugin dependencies are nested too deeply.', 'jewelry-outlet' ) );
            }

            $jewelry_outlet_file = $this->get_plugin_file( $slug );
            if ( '' === $jewelry_outlet_file ) {
                $jewelry_outlet_file = $this->install_plugin( $slug );
                if ( is_wp_error( $jewelry_outlet_file ) ) {
                    return $jewelry_outlet_file;
                }
            }

            $jewelry_outlet_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $jewelry_outlet_file, false, false );
            if ( ! empty( $jewelry_outlet_data['RequiresPlugins'] ) ) {
                foreach ( array_filter( array_map( 'trim', explode( ',', $jewelry_outlet_data['RequiresPlugins'] ) ) ) as $jewelry_outlet_dep ) {
                    $jewelry_outlet_dep_result = $this->activate_with_dependencies( sanitize_key( $jewelry_outlet_dep ), $done, $depth + 1 );
                    if ( is_wp_error( $jewelry_outlet_dep_result ) ) {
                        return $jewelry_outlet_dep_result;
                    }
                }
            }

            if ( ! is_plugin_active( $jewelry_outlet_file ) ) {
                $jewelry_outlet_activated = activate_plugin( $jewelry_outlet_file );
                // "Unexpected output" (often PHP notices with WP_DEBUG on) is reported
                // after core has already activated the plugin, so it isn't a failure.
                if ( is_wp_error( $jewelry_outlet_activated ) && ! ( 'unexpected_output' === $jewelry_outlet_activated->get_error_code() && is_plugin_active( $jewelry_outlet_file ) ) ) {
                    return $jewelry_outlet_activated;
                }
            }

            $done[] = $slug;
            return true;
        }

        /** Downloads a plugin from WordPress.org and returns its main file. */
        private function install_plugin( $slug ) {
            if ( 'direct' !== get_filesystem_method() ) {
                return new WP_Error( 'jewelry_outlet_fs', esc_html__( 'Server needs FTP credentials to install plugins.', 'jewelry-outlet' ) );
            }
            $jewelry_outlet_api = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
            if ( is_wp_error( $jewelry_outlet_api ) ) {
                return $jewelry_outlet_api;
            }
            $jewelry_outlet_upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
            $jewelry_outlet_result   = $jewelry_outlet_upgrader->install( $jewelry_outlet_api->download_link );
            if ( is_wp_error( $jewelry_outlet_result ) ) {
                return $jewelry_outlet_result;
            }
            if ( ! $jewelry_outlet_result ) {
                return new WP_Error( 'jewelry_outlet_install', esc_html__( 'Plugin installation failed.', 'jewelry-outlet' ) );
            }
            wp_clean_plugins_cache( true );

            $jewelry_outlet_file = $this->get_plugin_file( $slug );
            if ( '' === $jewelry_outlet_file ) {
                return new WP_Error( 'jewelry_outlet_install', esc_html__( 'Plugin files not found after install.', 'jewelry-outlet' ) );
            }
            return $jewelry_outlet_file;
        }

        public function save_import_result() {
            check_ajax_referer( 'jewelry_outlet_gs', 'nonce' );
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error();
            }
            $jewelry_outlet_edit = isset( $_POST['edit'] ) ? esc_url_raw( wp_unslash( $_POST['edit'] ) ) : '';
            // Keep the page ID; links are rebuilt from it so they survive site URL changes.
            $jewelry_outlet_id = preg_match( '/[?&]post=(\d+)/', $jewelry_outlet_edit, $jewelry_outlet_m ) ? absint( $jewelry_outlet_m[1] ) : 0;
            update_option( 'jewelry_outlet_demo_imported', array(
                'id'        => $jewelry_outlet_id,
                'permalink' => isset( $_POST['permalink'] ) ? esc_url_raw( wp_unslash( $_POST['permalink'] ) ) : '',
                'edit'      => $jewelry_outlet_edit,
            ) );
            wp_send_json_success();
        }
    }
}

Jewelry_Outlet_Plugin_Activation_WPElemento_Importer::get_instance();
