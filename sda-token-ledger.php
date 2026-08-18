<?php
/**
 * Plugin Name: SDA Token Ledger
 * Plugin URI:  https://angelsharks.net
 * Description: Tracks Sustainable Development Award (SDA) tokens and their conversion to Sustainable Development Rewards (SDR) after on-chain smart-contract verification. Supports all 17 UN Sustainable Development Goals. Includes one-click WordPress page setup.
 * Version:     0.1.2
 * Author:      101DAO / AngelSharks.net
 * License:     GPL v2 or later
 * Text Domain: sda-token-ledger
 * Requires PHP: 7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'SDA_VERSION',     '0.1.2' );
define( 'SDA_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'SDA_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'SDA_PLUGIN_FILE', __FILE__ );

/**
 * Return a prefixed table name.
 *
 * @param string $name  Table suffix (e.g. 'ledger', 'projects').
 * @return string
 */
function sda_table( $name ) {
    global $wpdb;
    return $wpdb->prefix . 'sda_' . $name;
}

// Autoload includes
require_once SDA_PLUGIN_DIR . 'includes/class-sda-db.php';
require_once SDA_PLUGIN_DIR . 'includes/class-sda-sdgs.php';
require_once SDA_PLUGIN_DIR . 'includes/class-sda-token.php';
require_once SDA_PLUGIN_DIR . 'includes/class-sda-xero.php';
require_once SDA_PLUGIN_DIR . 'includes/class-sda-api.php';
require_once SDA_PLUGIN_DIR . 'includes/class-sda-genesis.php';
require_once SDA_PLUGIN_DIR . 'admin/class-sda-admin.php';
require_once SDA_PLUGIN_DIR . 'public/class-sda-shortcodes.php';

// Activation / Deactivation / Upgrade
register_activation_hook( __FILE__, array( 'SDA_DB', 'install' ) );
register_deactivation_hook( __FILE__, 'sda_deactivate' );

function sda_deactivate() {
    // Tables preserved; only removed on explicit uninstall.
}

// Run DB upgrade check on each load (handles manual plugin updates)
add_action( 'plugins_loaded', 'sda_maybe_upgrade_db' );
function sda_maybe_upgrade_db() {
    if ( get_option( 'sda_db_version' ) !== SDA_VERSION ) {
        SDA_DB::install();
    }
}

/**
 * Boot the plugin.
 */
function sda_boot() {
    SDA_Admin::init();
    SDA_Shortcodes::init();
    SDA_API::init();
    SDA_Genesis::init();

    // Xero: automatically post each SDA → SDR conversion as a Xero invoice.
    add_action( 'sda_converted_to_sdr', array( 'SDA_Xero', 'on_conversion' ), 10, 3 );

    // Xero: WP-Cron — retry failed syncs every hour.
    add_action( 'sda_xero_retry_failures', array( 'SDA_Xero', 'cron_retry_failures' ) );
    if ( ! wp_next_scheduled( 'sda_xero_retry_failures' ) ) {
        wp_schedule_event( time(), 'hourly', 'sda_xero_retry_failures' );
    }
}
add_action( 'plugins_loaded', 'sda_boot' );

/**
 * Enqueue public assets.
 */
function sda_enqueue_assets() {
    wp_enqueue_style(
        'sda-public',
        SDA_PLUGIN_URL . 'assets/css/sda-public.css',
        array(),
        SDA_VERSION
    );
}
add_action( 'wp_enqueue_scripts', 'sda_enqueue_assets' );
