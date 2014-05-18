<?php
/**
 * Plugin Name: WooCommerce Export Customer Email
 * Plugin URI: https://github.com/mhmithu/woocommerce-export-customer-email
 * Description: Allows you to export all customer emails into a CSV file.
 * Version: 1.0
 * Author: MH Mithu
 * Author URI: http://mithu.me/
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * 
 * ----------------------------------------------------------------------
 * Copyright (C) 2014  MH Mithu  (email: mail@mithu.me)
 * ----------------------------------------------------------------------
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * ----------------------------------------------------------------------
 */


// Including WP core file
if ( !function_exists('get_plugins' ) )
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Whether plugin active or not
if ( is_plugin_active('woocommerce/woocommerce.php' ) ) {

    class WC_Export_Customer_Email {

        /**
         * Class contructor
         * @access public
         * @return void
         **/
        public function __construct() {
            add_action( 'init', array( $this, 'generate_csv' ) );
            add_filter( 'mhm_exclude_data', array( $this, 'exclude_data' ) );
        }

        /**
         * Process content of CSV file
         * @access public
         * @return void
         **/
        public function generate_csv() {
            global $wpdb;
            if ( isset( $_POST['_wpnonce-mhm-export-customer-email'] ) ) {
                check_admin_referer( 'mhm-export-customer-email', '_wpnonce-mhm-export-customer-email' );

                $sitename = sanitize_key( get_bloginfo( 'name' ) );
                if ( ! empty( $sitename ) )
                    $sitename .= '.';
                $filename = $sitename . date( 'ymdHis', time() ) . '.csv';

                header( 'Content-Description: File Transfer' );
                header( 'Content-Disposition: attachment; filename=' . $filename );
                header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ), true );

                $exclude_data = apply_filters( 'mhm_exclude_data', array() );

                $data_keys = array( 'customer_email' );

                $meta_keys = $wpdb->get_results( 
                   "SELECT DISTINCT meta_value
                    AS customer_email
                    FROM $wpdb->postmeta
                    WHERE meta_key = '_billing_email'
                    ORDER BY meta_value ASC"
                );

                $meta_keys = wp_list_pluck( $meta_keys, 'customer_email' );
                $fields = array_merge( $data_keys, $meta_keys );

                $headers = array();
                foreach ( $fields as $key => $field ) {
                    if ( in_array( $field, $exclude_data ) )
                        unset( $fields[$key] );
                    else
                        $headers[] = '"' . $field . '"';
                }
                print( implode( "\n", $headers ) );

                exit(0);
            }
        }

        /**
         * Exclude data hook
         * @access public
         * @return array
         */
        public function exclude_data() {
            $exclude = array( 'user_pass', 'user_activation_key' );
            return $exclude;
        }

        /**
         * Get current WooCommerce version
         * @access public
         * @return void
         */
        public function get_wc_version() {        
            $plugin_folder = get_plugins('/' . 'woocommerce');
            $plugin_file = 'woocommerce.php';

            if ( isset($plugin_folder[$plugin_file]['Version'] ) ) {
                return $plugin_folder[$plugin_file]['Version'];
            } else {
                return NULL;
            }
        }

    }

    $export = new WC_Export_Customer_Email;

    /**
     * Export form
     */
    function export_action() {
        if ( isset( $_GET['error'] ) ) {
            echo '<div class="updated"><p><strong>No email found.</strong></p></div>';
        }
?>
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field( 'mhm-export-customer-email', '_wpnonce-mhm-export-customer-email' ); ?>
            <p class="submit">
                <input type="hidden" name="_wp_http_referer" value="<?php echo $_SERVER['REQUEST_URI'] ?>" />
                <input type="submit" class="button-primary" value="Export CSV" />
            </p>
        </form>
<?php
    }

    /**
     * Hooking into WooCommerce by checking current version
     **/
    add_filter( 'woocommerce_reports_charts', 'export_to_csv' );
    if ( version_compare( $export->get_wc_version(), '2.1.0', 'lt' ) ) {
        function export_to_csv( $charts ) {
            $charts['export'] = array(
                'title'  => 'Export',
                'charts' => array(
                    "overview" => array(
                        'title'       => 'Export customer email',
                        'description' => 'Click on <strong>Export</strong> button to generate and download customer\'s billing email data into a CSV file.',
                        'hide_title'  => false,
                        'function'    => 'export_action'
                    ),
                ),
            );
            return $charts;
        }
    } else {
        function export_to_csv( $reports ) {
            $reports['customers']['reports']['export'] = array(
                'title'       => 'Export customer email',
                'description' => 'Click on <strong>Export</strong> button to generate and download customer\'s billing email data into a CSV file.',
                'hide_title'  => true,
                'callback'    => 'export_action'
            );
            return $reports;
        }
    }

// Fallback admin notice
} else {
    add_action( 'admin_notices', 'wc_export_error_notice' );
    function wc_export_error_notice(){
        global $current_screen;
        if ( $current_screen->parent_base == 'plugins' ) {
            echo '<div class="error"><p>The <strong>WooCommerce Export Customer Email</strong> plugin requires the <a href="http://wordpress.org/plugins/woocommerce" target="_blank">WooCommerce</a> plugin to be activated in order to work. Please <a href="'.admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce').'" target="_blank">install WooCommerce</a> or <a href="'.admin_url('plugins.php').'">activate</a> first.</p></div>';
        }
    }

}
