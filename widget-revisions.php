<?php
 /*
 Plugin Name: Widget Revisions
 Plugin URI: https://www.nettantra.com/wordpress/?utm_src=widget-revisions
 Description: Create Revisions for Widget. Widget Revisions is your new widget log keeper in WordPress. It keeps the record of every single parameter change you’ve ever made for any widget. It also enables you to rollback your widget to any of those previous settings with a just single click.
 Version: 1.0.3
 Author: NetTantra
 Author URI: https://www.nettantra.com/wordpress/?utm_src=widget-revisions
 Text Domain: widget-revisions
 License: GPLv2 or later
 */


if ( ! defined( 'ABSPATH' ) ) {
	die;
}


function ntwr_create_table(){
    global $wpdb;
    $table_name = $wpdb->prefix.'widget_revisions';

    if( $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ) {

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
          `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
          `widget_id` int(11) DEFAULT NULL,
          `option_name` varchar(191) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
          `option_value` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
          `widget_author` bigint(20) NOT NULL,
          `creation_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}

function ntwr_on_activate( $network_wide ){
    global $wpdb;
    if ( is_multisite() && $network_wide ) {
        // Get all blogs in the network and activate plugin on each one
        $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
        foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            ntwr_create_table();
            restore_current_blog();
        }
    } else {
        ntwr_create_table();
    }
}

register_activation_hook( __FILE__, 'ntwr_on_activate' );


function ntwr_on_deactivate() {
  // do nothing
}

register_deactivation_hook( __FILE__, 'ntwr_on_deactivate' );


require plugin_dir_path( __FILE__ ) . 'includes/class-wp-widget-revisions.php';

new WPWidgetRevisions();
