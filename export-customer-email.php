<?php
/**
 * Plugin Name: WooCommerce Export Customer Email
 * Plugin URI: https://github.com/mhmithu/woocommerce-export-customer-email
 * Description: Allows you to export all customer emails into a CSV file.
 * Version: 1.3
 * Author: MH Mithu
 * Author URI: http://mithu.me/
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * ----------------------------------------------------------------------
 * Copyright (C) 2015  MH Mithu  (email: mail@mithu.me)
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
if ( ! function_exists( 'get_plugins' ) )
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Whether plugin active or not
if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {

    class WC_Export_Customer_Email {

        /**
         * Class contructor
         *
         * @access public
         * @return void
         * */
        public function __construct() {
            add_action( 'init', array( $this, 'csv_generate' ) );

            $is_loaded = load_plugin_textdomain( 'woocommerce-export-customer-email', false, plugin_basename( dirname( __FILE__ ) ) . "/languages" );
			var_dump($is_loaded);
        }


        /**
         * Preparing data
         *
         * @access private
         * @return array
         */
        private function csv_data() {
            global $wpdb;

            $ids = $wpdb->get_col( "SELECT DISTINCT `order_id` FROM `{$wpdb->prefix}woocommerce_order_items`" );

            foreach ( $ids as $id ) {
                $fname   = get_post_meta( $id, '_billing_first_name' );
                $lname   = get_post_meta( $id, '_billing_last_name' );
                $email[] = get_post_meta( $id, '_billing_email' );
                $name[]  = $fname[0].' '.$lname[0];
            }

            $email = wp_list_pluck( $email, 0 );

            for ( $i = 0; $i < count( $name ); $i++ ) {
                $body[$i+1] = array( $name[$i], $email[$i] );
            }

            $headers = array( __('Customer_Name', 'woocommerce-export-customer-email'), __('Customer_Email', 'woocommerce-export-customer-email') );
            $body    = array( $headers ) + $body;

            return $body;
        }


        /**
         * Process content of CSV file
         *
         * @access public
         * @return void
         * */
        public function csv_generate() {

            if ( isset( $_POST['_wpnonce-mhm-export-customer-email'] ) ) {
                check_admin_referer( 'mhm-export-customer-email', '_wpnonce-mhm-export-customer-email' );

                $sitename = sanitize_key( get_bloginfo( 'name' ) );

                if ( ! empty( $sitename ) )
                    $sitename .= '.';

                $filename = $sitename . date( 'ymdHis', current_time( 'timestamp' ) ) . '.csv';

                $data = $this->csv_data();

                if ( $_POST['cname'] == 'no' ) {
                    for ( $i = 0; $i < count( $data ); $i++ ) {
                        unset( $data[$i][0] );
                    }
                }

                if ( $_POST['duplicate'] == 'yes' ) {
                    $data = array_map( 'unserialize', array_unique( array_map( 'serialize', $data ) ) );
                }

                $this->csv_header( $filename );
                ob_start();

                $file = @fopen( 'php://output', 'w' );

                foreach ( $data as $list ) {
                    @fputcsv( $file, $list, ',' );
                }

                @fclose( $file );

                ob_end_flush();

                exit();
            }
        }


        /**
         * File headers
         *
         * @access private
         * @param  string $filename
         * @return void
         */
        private function csv_header( $filename ) {
            send_nosniff_header();
            nocache_headers();
            @header( 'Content-Type: application/csv; charset=' . get_option( 'blog_charset' ), true );
            @header( 'Content-Type: application/force-download' );
            @header( 'Content-Description: File Transfer' );
            @header( 'Content-Disposition: attachment; filename=' . $filename );
        }


        /**
         * Get current WooCommerce version
         *
         * @access public
         * @return void
         */
        public function get_wc_version() {
            global $woocommerce;

            $plugin_folder = get_plugins( '/' . 'woocommerce' );
            $plugin_file   = 'woocommerce.php';
            $wc_version    = $plugin_folder[$plugin_file]['Version'];

            return isset( $wc_version ) ? $wc_version : $woocommerce->version;
        }

    }

    // The object
    $export = new WC_Export_Customer_Email;

    /**
     * Export form
     * 
     * @return string
     */
    function export_action() {
        if ( isset( $_GET['error'] ) ) {
            echo '<div class="updated"><p><strong>'.__('No email found.', 'woocommerce-export-customer-email').'</strong></p></div>';
        }
        ?>
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field( 'mhm-export-customer-email', '_wpnonce-mhm-export-customer-email' ); ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row" class="titledesc"><label for="cname"><?php _e('Export customer name', 'woocommerce-export-customer-email'); ?></label></th>
                        <td>
                            <label><input name="cname" value="yes" type="radio" checked="checked"> <?php _e('Yes', 'woocommerce-export-customer-email'); ?></label>&nbsp;&nbsp;&nbsp;
                            <label><input name="cname" value="no" type="radio"> <?php _e('No', 'woocommerce-export-customer-email'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="titledesc"><label for="duplicate"><?php _e('Remove duplicate emails', 'woocommerce-export-customer-email'); ?></label></th>
                        <td>
                            <label><input name="duplicate" value="yes" type="radio"> <?php _e('Yes', 'woocommerce-export-customer-email'); ?></label>&nbsp;&nbsp;&nbsp;
                            <label><input name="duplicate" value="no" type="radio" checked="checked"> <?php _e('No', 'woocommerce-export-customer-email'); ?></label>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <input type="hidden" name="_wp_http_referer" value="<?php echo $_SERVER['REQUEST_URI'] ?>" />
                <input type="submit" class="button-primary" value="<?php _e('Export CSV', 'woocommerce-export-customer-email'); ?>" />
            </p>
        </form>
    <?php
    }

    /**
     * Hooking into WooCommerce by checking current version
     * */
    if ( version_compare( $export->get_wc_version(), '2.1', 'lt' ) ) {
        function export_to_csv( $charts ) {
            $charts['export'] = array(
                'title'  => 'Export',
                'charts' => array(
                    "overview" => array(
                        'title'       => __('Export customer email', 'woocommerce-export-customer-email'),
                        'description' => __('Click on <strong>Export</strong> button to generate and download customer\'s billing email data into a CSV file.', 'woocommerce-export-customer-email'),
                        'hide_title'  => false,
                        'function'    => 'export_action'
                    )
                )
            );
            return $charts;
        }
        add_filter( 'woocommerce_reports_charts', 'export_to_csv' );
    } else {
        function export_to_csv( $reports ) {
            $reports['customers']['reports']['export'] = array(
                'title'       => __('Export customer email', 'woocommerce-export-customer-email'),
                'description' => __('Click on <strong>Export</strong> button to generate and download customer\'s billing email data into a CSV file.', 'woocommerce-export-customer-email'),
                'hide_title'  => true,
                'callback'    => 'export_action'
            );
            return $reports;
        }
        add_filter( 'woocommerce_admin_reports', 'export_to_csv' );
    }

/**
 * Fallback admin notice
 */
} else {
    function wc_export_error_notice() {
        global $current_screen;
        if ( $current_screen->parent_base == 'plugins' ) {
            echo '<div class="error"><p>'.sprintf(__('The <strong>WooCommerce Export Customer Email</strong> plugin requires the <a href="%s" target="_blank">WooCommerce</a> plugin to be activated in order to work. Please <a href="%s" target="_blank">install WooCommerce</a> or <a href="%s">activate</a> first.', 'woocommerce-export-customer-email'), 'http://wordpress.org/plugins/woocommerce', admin_url( 'plugin-install.php?tab=search&type=term&s=WooCommerce', admin_url( 'plugins.php' ) )).'</p></div>';
        }
    }
    add_action( 'admin_notices', 'wc_export_error_notice' );

}
