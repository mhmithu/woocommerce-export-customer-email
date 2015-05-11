<?php
/**
 * Plugin Name: WooCommerce Export Customer Email
 * Plugin URI: https://github.com/mhmithu/woocommerce-export-customer-email
 * Description: Allows you to export all customer emails into a CSV file.
 * Version: 1.2
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
            add_action( 'init', array( $this, 'generate_csv' ) );
        }

        /**
         * Process content of CSV file
         *
         * @access public
         * @return void
         * */
        public function generate_csv() {
            global $wpdb;
            if ( isset( $_POST['_wpnonce-mhm-export-customer-email'] ) ) {
                check_admin_referer( 'mhm-export-customer-email', '_wpnonce-mhm-export-customer-email' );

                $sitename = sanitize_key( get_bloginfo( 'name' ) );
                if ( ! empty( $sitename ) )
                    $sitename .= '.';
                $filename = $sitename . date( 'ymdHis', current_time( 'timestamp' ) ) . '.csv';

                $ids = $wpdb->get_col(
                    "SELECT DISTINCT `order_id`
                    FROM `{$wpdb->prefix}woocommerce_order_items`"
                );

                foreach ( $ids as $id ) {
                    $fname   = get_post_meta( $id, '_billing_first_name' );
                    $lname   = get_post_meta( $id, '_billing_last_name' );
                    $email[] = get_post_meta( $id, '_billing_email' );
                    $name[]  = $fname[0].' '.$lname[0];
                }

                $email = wp_list_pluck( $email, 0 );

                for ( $i=0; $i<count( $name ); $i++ )
                    $body[$i+1] = array( $name[$i], $email[$i] );

                $headers = array( 'Customer_Name', 'Customer_Email' );
                $body    = array( $headers ) + $body;
                $temp    = fopen( 'php://memory', 'w' );

                if ( $_POST['cname'] == 'no' ) {
                    for ( $j=0; $j<=count( $email ); $j++ )
                        unset( $body[$j][0] );
                }

                if ( $_POST['duplicate'] == 'yes' )
                    $body = array_map( 'unserialize', array_unique( array_map( 'serialize', $body ) ) );

                foreach ( $body as $list )
                    fputcsv( $temp, $list, ',' );

                fseek( $temp, 0 );
                fpassthru( $temp );

                header( 'Content-Description: File Transfer' );
                header( 'Content-Disposition: attachment; filename=' . $filename );
                header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ), true );

                exit( 0 );
            }
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
            echo '<div class="updated"><p><strong>No email found.</strong></p></div>';
        }
?>
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field( 'mhm-export-customer-email', '_wpnonce-mhm-export-customer-email' ); ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row" class="titledesc"><label for="cname">Export customer name</label></th>
                        <td class="forminp forminp-radio">
                            <fieldset>
                                <ul>
                                    <li>
                                        <label><input name="cname" value="yes" type="radio" checked="checked"> Yes</label>
                                    </li>
                                    <li>
                                        <label><input name="cname" value="no" type="radio"> No</label>
                                    </li>
                                </ul>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="titledesc"><label for="duplicate">Remove duplicate emails</label></th>
                        <td class="forminp forminp-radio">
                            <fieldset>
                                <ul>
                                    <li>
                                        <label><input name="duplicate" value="yes" type="radio"> Yes</label>
                                    </li>
                                    <li>
                                        <label><input name="duplicate" value="no" type="radio" checked="checked"> No</label>
                                    </li>
                                </ul>
                            </fieldset>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <input type="hidden" name="_wp_http_referer" value="<?php echo $_SERVER['REQUEST_URI'] ?>" />
                <input type="submit" class="button-primary" value="Export CSV" />
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
                        'title'       => 'Export customer email',
                        'description' => 'Click on <strong>Export</strong> button to generate and download customer\'s billing email data into a CSV file.',
                        'hide_title'  => false,
                        'function'    => 'export_action'
                    ),
                ),
            );
            return $charts;
        }
        add_filter( 'woocommerce_reports_charts', 'export_to_csv' );
    } else {
        function export_to_csv( $reports ) {
            $reports['customers']['reports']['export'] = array(
                'title'       => 'Export Customer Email',
                'description' => 'Click on <strong>Export</strong> button to generate and download customer\'s billing email data into a CSV file.',
                'hide_title'  => true,
                'callback'    => 'export_action'
            );
            return $reports;
        }
        add_filter( 'woocommerce_admin_reports', 'export_to_csv' );
    }

    // Fallback admin notice
} else {
    function wc_export_error_notice() {
        global $current_screen;
        if ( $current_screen->parent_base == 'plugins' ) {
            echo '<div class="error"><p>The <strong>WooCommerce Export Customer Email</strong> plugin requires the <a href="http://wordpress.org/plugins/woocommerce" target="_blank">WooCommerce</a> plugin to be activated in order to work. Please <a href="'.admin_url( 'plugin-install.php?tab=search&type=term&s=WooCommerce' ).'" target="_blank">install WooCommerce</a> or <a href="'.admin_url( 'plugins.php' ).'">activate</a> first.</p></div>';
        }
    }
    add_action( 'admin_notices', 'wc_export_error_notice' );

}
