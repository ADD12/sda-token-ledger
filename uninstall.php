<?php
/**
 * Fired when the plugin is uninstalled.
 * Removes all plugin tables and options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-sda-db.php';
SDA_DB::uninstall();
