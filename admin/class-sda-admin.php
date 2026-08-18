<?php
/**
 * Admin panel: menus, settings, and management pages.
 * Compatible: PHP 7.2+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SDA_Admin {

    public static function init() {
        add_action( 'admin_menu',            array( __CLASS__, 'add_menus' ) );
        add_action( 'admin_init',            array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_dashboard_setup',    array( __CLASS__, 'add_dashboard_widget' ) );
        add_action( 'admin_post_sda_issue_sda',        array( __CLASS__, 'handle_issue_sda' ) );
        add_action( 'admin_post_sda_register_project', array( __CLASS__, 'handle_register_project' ) );
        add_action( 'admin_post_sda_approve_project',  array( __CLASS__, 'handle_approve_project' ) );
        add_action( 'admin_post_sda_add_verifier',     array( __CLASS__, 'handle_add_verifier' ) );
        add_action( 'admin_post_sda_create_contract',  array( __CLASS__, 'handle_create_contract' ) );
        add_action( 'admin_post_sda_verify_convert',   array( __CLASS__, 'handle_verify_convert' ) );
        add_action( 'admin_post_sda_one_click_setup',  array( __CLASS__, 'handle_one_click_setup' ) );
        add_action( 'admin_post_sda_xero_connect',     array( __CLASS__, 'handle_xero_connect' ) );
        add_action( 'admin_post_sda_xero_callback',    array( __CLASS__, 'handle_xero_callback' ) );
        add_action( 'admin_post_sda_xero_disconnect',  array( __CLASS__, 'handle_xero_disconnect' ) );
        add_action( 'admin_post_sda_xero_backfill',         array( __CLASS__, 'handle_xero_backfill' ) );
        add_action( 'admin_post_sda_xero_retry_failures',   array( __CLASS__, 'handle_xero_retry_failures' ) );
    }

    // ---------------------------------------------------------------- Menus

    public static function add_menus() {
        add_menu_page(
            __( 'SDA Token Ledger', 'sda-token-ledger' ),
            __( 'SDA Tokens', 'sda-token-ledger' ),
            'manage_options',
            'sda-token-ledger',
            array( __CLASS__, 'page_dashboard' ),
            'dashicons-awards',
            56
        );

        add_submenu_page(
            'sda-token-ledger',
            __( 'Dashboard', 'sda-token-ledger' ),
            __( '📊 Dashboard', 'sda-token-ledger' ),
            'manage_options',
            'sda-token-ledger',
            array( __CLASS__, 'page_dashboard' )
        );

        add_submenu_page(
            'sda-token-ledger',
            __( 'Token Ledger', 'sda-token-ledger' ),
            __( '📒 Token Ledger', 'sda-token-ledger' ),
            'manage_options',
            'sda-ledger',
            array( __CLASS__, 'page_ledger' )
        );

        add_submenu_page(
            'sda-token-ledger',
            __( 'Projects', 'sda-token-ledger' ),
            __( '📁 Projects (PIDs)', 'sda-token-ledger' ),
            'manage_options',
            'sda-projects',
            array( __CLASS__, 'page_projects' )
        );

        add_submenu_page(
            'sda-token-ledger',
            __( 'Smart Contracts', 'sda-token-ledger' ),
            __( '📜 Smart Contracts', 'sda-token-ledger' ),
            'manage_options',
            'sda-contracts',
            array( __CLASS__, 'page_contracts' )
        );

        add_submenu_page(
            'sda-token-ledger',
            __( 'Verifiers (VIDs)', 'sda-token-ledger' ),
            __( '🔐 Verifiers (VIDs)', 'sda-token-ledger' ),
            'manage_options',
            'sda-verifiers',
            array( __CLASS__, 'page_verifiers' )
        );

        add_submenu_page(
            'sda-token-ledger',
            __( 'SDG Settings', 'sda-token-ledger' ),
            __( '⚙️ SDG Settings', 'sda-token-ledger' ),
            'manage_options',
            'sda-settings',
            array( __CLASS__, 'page_settings' )
        );

        add_submenu_page(
            'sda-token-ledger',
            __( 'Setup Pages', 'sda-token-ledger' ),
            __( '🚀 Setup Pages', 'sda-token-ledger' ),
            'manage_options',
            'sda-setup',
            array( __CLASS__, 'page_setup' )
        );

        // Dynamic submenu links to front-end pages (added after setup)
        self::add_page_links_to_menu();
    }

    /**
     * After one-click setup runs, add "View → " links in the sidebar.
     */
    private static function add_page_links_to_menu() {
        $pages = get_option( 'sda_created_pages', array() );
        if ( empty( $pages ) ) {
            return;
        }

        $map = array(
            'ledger'   => __( '🌐 View: My Ledger', 'sda-token-ledger' ),
            'totals'   => __( '🌐 View: My Tokens', 'sda-token-ledger' ),
            'projects' => __( '🌐 View: Projects', 'sda-token-ledger' ),
            'goals'    => __( '🌐 View: SDG Goals', 'sda-token-ledger' ),
        );

        foreach ( $map as $key => $label ) {
            if ( ! empty( $pages[ $key ] ) ) {
                $url = get_permalink( (int) $pages[ $key ] );
                if ( $url ) {
                    add_submenu_page(
                        'sda-token-ledger',
                        $label,
                        $label,
                        'manage_options',
                        'sda-view-' . $key,
                        // Redirect handler registered separately
                        array( __CLASS__, 'redirect_to_page_' . $key )
                    );
                }
            }
        }
    }

    // Redirect stubs so clicking the sidebar link opens the front-end page
    public static function redirect_to_page_ledger()   { self::redirect_created_page( 'ledger' ); }
    public static function redirect_to_page_totals()   { self::redirect_created_page( 'totals' ); }
    public static function redirect_to_page_projects() { self::redirect_created_page( 'projects' ); }
    public static function redirect_to_page_goals()    { self::redirect_created_page( 'goals' ); }

    private static function redirect_created_page( $key ) {
        $pages = get_option( 'sda_created_pages', array() );
        if ( ! empty( $pages[ $key ] ) ) {
            $url = get_permalink( (int) $pages[ $key ] );
            if ( $url ) {
                wp_redirect( $url );
                exit;
            }
        }
        wp_die( 'Page not found. Run the one-click setup again from <a href="' . esc_url( admin_url( 'admin.php?page=sda-setup' ) ) . '">Setup Pages</a>.' );
    }

    // ---------------------------------------------------------------- Dashboard Widget

    public static function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'sda_token_widget',
            '🌱 SDA Token Ledger — Quick Stats',
            array( __CLASS__, 'dashboard_widget_content' )
        );
    }

    public static function dashboard_widget_content() {
        global $wpdb;

        $total_sda = $wpdb->get_var( "SELECT SUM(amount) FROM {$wpdb->prefix}sda_ledger WHERE token_type='SDA'" );
        $total_sdr = $wpdb->get_var( "SELECT SUM(amount) FROM {$wpdb->prefix}sda_ledger WHERE token_type='SDR'" );
        $projects  = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sda_projects" );
        $pages     = get_option( 'sda_created_pages', array() );

        echo '<table style="width:100%;font-size:13px;border-collapse:collapse">';
        echo '<tr><td>🌿 Total SDA Issued</td><td style="text-align:right;font-weight:700">' . esc_html( number_format( (float) $total_sda, 0 ) ) . '</td></tr>';
        echo '<tr><td>⭐ Total SDR Awarded</td><td style="text-align:right;font-weight:700">' . esc_html( number_format( (float) $total_sdr, 0 ) ) . '</td></tr>';
        echo '<tr><td>📁 Projects</td><td style="text-align:right">' . esc_html( (int) $projects ) . '</td></tr>';
        echo '</table>';
        echo '<p style="margin-top:10px">';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=sda-token-ledger' ) ) . '" class="button button-small">Open Dashboard</a> ';

        if ( empty( $pages ) ) {
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=sda-setup' ) ) . '" class="button button-small button-primary">🚀 Run Setup</a>';
        } else {
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=sda-setup' ) ) . '" class="button button-small">⚙️ Manage Pages</a>';
        }

        echo '</p>';
    }

    // ---------------------------------------------------------------- Assets

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'sda-' ) === false && $hook !== 'toplevel_page_sda-token-ledger' ) {
            return;
        }
        wp_enqueue_style(
            'sda-admin',
            SDA_PLUGIN_URL . 'assets/css/sda-admin.css',
            array(),
            SDA_VERSION
        );
        wp_enqueue_script(
            'sda-admin',
            SDA_PLUGIN_URL . 'assets/js/sda-admin.js',
            array( 'jquery' ),
            SDA_VERSION,
            true
        );
    }

    // ---------------------------------------------------------------- Settings Registration

    public static function register_settings() {
        register_setting( 'sda_settings_group', 'sda_settings', array(
            'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
            'default'           => array(),
        ) );

        // Section: General
        add_settings_section( 'sda_general', __( 'General Configuration', 'sda-token-ledger' ), null, 'sda-settings' );

        add_settings_field( 'dao_address',  __( '101DAO Approval Address', 'sda-token-ledger' ),      array( __CLASS__, 'field_text' ),     'sda-settings', 'sda_general', array( 'key' => 'dao_address',  'placeholder' => '0x… or DID' ) );
        add_settings_field( 'network_name', __( 'Primary Chain / Network', 'sda-token-ledger' ),       array( __CLASS__, 'field_text' ),     'sda-settings', 'sda_general', array( 'key' => 'network_name', 'placeholder' => 'e.g. Ethereum Mainnet' ) );
        add_settings_field( 'rpc_main',     __( 'Main-Chain RPC URL', 'sda-token-ledger' ),            array( __CLASS__, 'field_text' ),     'sda-settings', 'sda_general', array( 'key' => 'rpc_main',     'placeholder' => 'https://mainnet.infura.io/v3/…' ) );
        add_settings_field( 'rpc_side',     __( 'Sidechain RPC URL', 'sda-token-ledger' ),             array( __CLASS__, 'field_text' ),     'sda-settings', 'sda_general', array( 'key' => 'rpc_side',     'placeholder' => 'https://sidechain-rpc.example.com' ) );
        add_settings_field( 'api_key',      __( 'REST API Key (X-SDA-API-Key)', 'sda-token-ledger' ),  array( __CLASS__, 'field_password' ), 'sda-settings', 'sda_general', array( 'key' => 'api_key' ) );

        // Section: Token Parameters
        add_settings_section( 'sda_token', __( 'Token Parameters', 'sda-token-ledger' ), null, 'sda-settings' );

        add_settings_field( 'token_symbol',      __( 'SDA Token Symbol', 'sda-token-ledger' ),            array( __CLASS__, 'field_text' ),   'sda-settings', 'sda_token', array( 'key' => 'token_symbol',      'placeholder' => 'SDA' ) );
        add_settings_field( 'sdr_symbol',        __( 'SDR Token Symbol', 'sda-token-ledger' ),            array( __CLASS__, 'field_text' ),   'sda-settings', 'sda_token', array( 'key' => 'sdr_symbol',        'placeholder' => 'SDR' ) );
        add_settings_field( 'max_sdr_ratio',     __( 'Max SDR Ratio (per 1 SDA, max 10)', 'sda-token-ledger' ), array( __CLASS__, 'field_number' ), 'sda-settings', 'sda_token', array( 'key' => 'max_sdr_ratio',     'min' => 1, 'max' => 10, 'default' => 10 ) );
        add_settings_field( 'min_coin_proposal', __( 'Min SDA to Propose Own Coin', 'sda-token-ledger' ), array( __CLASS__, 'field_number' ), 'sda-settings', 'sda_token', array( 'key' => 'min_coin_proposal', 'min' => 0, 'default' => 1000000 ) );

        // Section: SDG Goal Configuration
        add_settings_section( 'sda_sdg', __( 'Active UN Sustainability Goals', 'sda-token-ledger' ), array( __CLASS__, 'section_sdg_description' ), 'sda-settings' );
        add_settings_field( 'active_sdgs', __( 'Enabled SDGs', 'sda-token-ledger' ), array( __CLASS__, 'field_sdg_checkboxes' ), 'sda-settings', 'sda_sdg', array( 'key' => 'active_sdgs' ) );

        // Section: Xero
        add_settings_section( 'sda_xero', __( 'Xero Accounting Integration', 'sda-token-ledger' ), array( __CLASS__, 'section_xero_description' ), 'sda-settings' );
        add_settings_field( 'xero_client_id',     __( 'Xero Client ID', 'sda-token-ledger' ),       array( __CLASS__, 'field_text' ),     'sda-settings', 'sda_xero', array( 'key' => 'xero_client_id',     'placeholder' => 'From your Xero app' ) );
        add_settings_field( 'xero_client_secret', __( 'Xero Client Secret', 'sda-token-ledger' ),   array( __CLASS__, 'field_password' ), 'sda-settings', 'sda_xero', array( 'key' => 'xero_client_secret' ) );
        add_settings_field( 'xero_tenant_id',     __( 'Xero Tenant / Org ID', 'sda-token-ledger' ), array( __CLASS__, 'field_text' ),     'sda-settings', 'sda_xero', array( 'key' => 'xero_tenant_id',     'placeholder' => 'e.g. 12345678-aaaa-bbbb-cccc-000000000000' ) );
        add_settings_field( 'xero_account_code',  __( 'Xero Income Account Code', 'sda-token-ledger' ), array( __CLASS__, 'field_text' ), 'sda-settings', 'sda_xero', array( 'key' => 'xero_account_code', 'placeholder' => 'e.g. 200' ) );
        add_settings_field( 'xero_currency',      __( 'Currency Code', 'sda-token-ledger' ),        array( __CLASS__, 'field_text' ),     'sda-settings', 'sda_xero', array( 'key' => 'xero_currency',      'placeholder' => 'e.g. USD, NZD, AUD' ) );
    }

    // ---------------------------------------------------------------- Field Renderers

    public static function field_text( $args ) {
        $settings = get_option( 'sda_settings', array() );
        $key      = $args['key'];
        $val      = isset( $settings[ $key ] ) ? $settings[ $key ] : ( isset( $args['default'] ) ? $args['default'] : '' );
        printf(
            '<input type="text" name="sda_settings[%s]" id="sda_%s" value="%s" placeholder="%s" class="regular-text">',
            esc_attr( $key ),
            esc_attr( $key ),
            esc_attr( $val ),
            esc_attr( isset( $args['placeholder'] ) ? $args['placeholder'] : '' )
        );
    }

    public static function field_password( $args ) {
        $settings = get_option( 'sda_settings', array() );
        $key      = $args['key'];
        $val      = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
        printf(
            '<input type="password" name="sda_settings[%s]" id="sda_%s" value="%s" class="regular-text" autocomplete="new-password">',
            esc_attr( $key ),
            esc_attr( $key ),
            esc_attr( $val )
        );
        if ( $val ) {
            echo '<span class="sda-saved-indicator"> ✅ Saved</span>';
        }
    }

    public static function field_number( $args ) {
        $settings = get_option( 'sda_settings', array() );
        $key      = $args['key'];
        $val      = isset( $settings[ $key ] ) ? $settings[ $key ] : ( isset( $args['default'] ) ? $args['default'] : 0 );
        printf(
            '<input type="number" name="sda_settings[%s]" id="sda_%s" value="%s" min="%s" max="%s" class="small-text">',
            esc_attr( $key ),
            esc_attr( $key ),
            esc_attr( (string) $val ),
            esc_attr( (string) ( isset( $args['min'] ) ? $args['min'] : 0 ) ),
            esc_attr( (string) ( isset( $args['max'] ) ? $args['max'] : 9999999999 ) )
        );
    }

    public static function field_sdg_checkboxes( $args ) {
        $settings    = get_option( 'sda_settings', array() );
        $default_all = array_keys( SDA_SDGs::all() );
        $active_sdgs = isset( $settings['active_sdgs'] ) ? $settings['active_sdgs'] : $default_all;
        $active_sdgs = array_map( 'intval', (array) $active_sdgs );
        $sdgs        = SDA_SDGs::all();

        echo '<div class="sda-sdg-grid">';
        foreach ( $sdgs as $num => $sdg ) {
            $checked = in_array( $num, $active_sdgs, true ) ? 'checked' : '';
            printf(
                '<label class="sda-sdg-check" style="border-left:4px solid %s">
                    <input type="checkbox" name="sda_settings[active_sdgs][]" value="%d" %s>
                    %s <strong>SDG %d</strong><br><small>%s</small>
                </label>',
                esc_attr( $sdg['color'] ),
                $num,
                $checked,
                esc_html( $sdg['icon'] ),
                $num,
                esc_html( $sdg['name'] )
            );
        }
        echo '</div>';
    }

    public static function section_sdg_description() {
        echo '<p>' . esc_html__( 'Select which of the 17 UN Sustainable Development Goals are eligible for SDA → SDR conversion in your deployment. All are enabled by default.', 'sda-token-ledger' ) . '</p>';
    }

    public static function section_xero_description() {
        $status = SDA_Xero::get_status();

        // Status badge
        $badge_color = $status['connected'] ? '#46b450' : '#dc3232';
        $badge_text  = esc_html( $status['label'] );
        echo '<p>';
        printf(
            '<strong style="display:inline-block;padding:2px 10px;border-radius:3px;background:%s;color:#fff;font-size:12px;">%s</strong> <span class="description" style="margin-left:8px;">%s</span>',
            esc_attr( $badge_color ),
            $badge_text,
            esc_html( $status['details'] )
        );
        echo '</p>';

        // Action buttons
        echo '<p>';
        $settings_url = admin_url( 'admin.php?page=sda-settings' );

        if ( $status['connected'] ) {
            // Disconnect
            printf(
                '<a href="%s" class="button button-secondary" onclick="return confirm(\'%s\')">🔌 %s</a> &nbsp;',
                esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sda_xero_disconnect' ), 'sda_xero_disconnect' ) ),
                esc_js( __( 'Disconnect from Xero? Stored tokens will be deleted.', 'sda-token-ledger' ) ),
                esc_html__( 'Disconnect from Xero', 'sda-token-ledger' )
            );
        } elseif ( SDA_Xero::has_credentials() ) {
            // Connect
            printf(
                '<a href="%s" class="button button-primary">🔗 %s</a> &nbsp;',
                esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sda_xero_connect' ), 'sda_xero_connect' ) ),
                esc_html__( 'Connect to Xero', 'sda-token-ledger' )
            );
        } else {
            echo '<em class="description">' . esc_html__( 'Save your Client ID and Client Secret first, then come back to connect.', 'sda-token-ledger' ) . '</em>';
        }

        echo '</p>';

        echo '<p class="description">';
        printf(
            /* translators: %s: redirect URI */
            esc_html__( 'Register this Redirect URI in your Xero app: %s', 'sda-token-ledger' ),
            '<code>' . esc_html( SDA_Xero::get_redirect_uri() ) . '</code>'
        );
        echo '</p>';
    }

    public static function sanitize_settings( $input ) {
        $clean       = array();
        $text_fields = array( 'dao_address', 'network_name', 'rpc_main', 'rpc_side', 'token_symbol', 'sdr_symbol', 'xero_client_id', 'xero_tenant_id', 'xero_account_code', 'xero_currency' );
        $pass_fields = array( 'api_key', 'xero_client_secret' );
        $num_fields  = array( 'max_sdr_ratio', 'min_coin_proposal' );

        foreach ( $text_fields as $f ) {
            $clean[ $f ] = sanitize_text_field( isset( $input[ $f ] ) ? $input[ $f ] : '' );
        }
        foreach ( $pass_fields as $f ) {
            $existing    = get_option( 'sda_settings', array() );
            $existing    = isset( $existing[ $f ] ) ? $existing[ $f ] : '';
            $clean[ $f ] = ! empty( $input[ $f ] ) ? sanitize_text_field( $input[ $f ] ) : $existing;
        }
        foreach ( $num_fields as $f ) {
            $clean[ $f ] = absint( isset( $input[ $f ] ) ? $input[ $f ] : 0 );
        }

        // Active SDGs: array of ints 1–17 — PHP 7.2 compatible (no arrow functions)
        $raw               = isset( $input['active_sdgs'] ) ? $input['active_sdgs'] : array();
        $clean['active_sdgs'] = array();
        foreach ( array_map( 'intval', (array) $raw ) as $n ) {
            if ( $n >= 1 && $n <= 17 ) {
                $clean['active_sdgs'][] = $n;
            }
        }

        return $clean;
    }

    // ---------------------------------------------------------------- Admin Pages

    public static function page_dashboard() {
        global $wpdb;

        $total_sda = $wpdb->get_var( "SELECT SUM(amount) FROM {$wpdb->prefix}sda_ledger WHERE token_type='SDA'" );
        $total_sdr = $wpdb->get_var( "SELECT SUM(amount) FROM {$wpdb->prefix}sda_ledger WHERE token_type='SDR'" );
        $projects  = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sda_projects" );
        $verified  = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sda_contracts WHERE status='signed'" );

        $recent = $wpdb->get_results(
            "SELECT l.*, u.display_name
             FROM {$wpdb->prefix}sda_ledger l
             LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
             ORDER BY l.created_at DESC LIMIT 10"
        );

        include SDA_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }

    public static function page_ledger() {
        global $wpdb;

        $filter_user = isset( $_GET['uid'] )  ? absint( $_GET['uid'] )  : 0;
        $filter_type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';
        $filter_pid  = isset( $_GET['pid'] )  ? sanitize_text_field( $_GET['pid'] )  : '';

        $where = array( '1=1' );
        if ( $filter_user ) {
            $where[] = $wpdb->prepare( 'l.user_id = %d', $filter_user );
        }
        if ( in_array( $filter_type, array( 'SDA', 'SDR' ), true ) ) {
            $where[] = $wpdb->prepare( "l.token_type = %s", $filter_type );
        }
        if ( $filter_pid ) {
            $where[] = $wpdb->prepare( 'l.pid = %s', $filter_pid );
        }

        $per_page = 50;
        $paged    = max( 1, absint( isset( $_GET['paged'] ) ? $_GET['paged'] : 1 ) );
        $offset   = ( $paged - 1 ) * $per_page;
        $total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sda_ledger l WHERE " . implode( ' AND ', $where ) ); // phpcs:ignore
        $rows     = $wpdb->get_results( // phpcs:ignore
            "SELECT l.*, u.display_name, p.project_name
             FROM {$wpdb->prefix}sda_ledger l
             LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
             LEFT JOIN {$wpdb->prefix}sda_projects p ON p.pid = l.pid
             WHERE " . implode( ' AND ', $where ) . "
             ORDER BY l.created_at DESC LIMIT $per_page OFFSET $offset"
        );

        $users    = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );
        $projects = SDA_Token::get_projects();
        include SDA_PLUGIN_DIR . 'templates/admin-ledger.php';
    }

    public static function page_projects() {
        $projects = SDA_Token::get_projects();
        $sdgs     = SDA_SDGs::all();
        $notice   = '';
        if ( ! empty( $_GET['sda_notice'] ) ) {
            $notice = sanitize_text_field( urldecode( $_GET['sda_notice'] ) );
        }
        include SDA_PLUGIN_DIR . 'templates/admin-projects.php';
    }

    public static function page_contracts() {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT c.*, p.project_name FROM {$wpdb->prefix}sda_contracts c
             LEFT JOIN {$wpdb->prefix}sda_projects p ON p.pid = c.pid
             ORDER BY c.created_at DESC LIMIT 200"
        );
        $projects = SDA_Token::get_projects();
        include SDA_PLUGIN_DIR . 'templates/admin-contracts.php';
    }

    public static function page_verifiers() {
        global $wpdb;
        $verifiers = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sda_verifiers ORDER BY display_name ASC" );
        include SDA_PLUGIN_DIR . 'templates/admin-verifiers.php';
    }

    public static function page_settings() {
        include SDA_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    public static function page_setup() {
        $created_pages = get_option( 'sda_created_pages', array() );
        include SDA_PLUGIN_DIR . 'templates/admin-setup.php';
    }

    // ---------------------------------------------------------------- One-Click Setup Handler

    public static function handle_one_click_setup() {
        check_admin_referer( 'sda_one_click_setup' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        $page_defs = array(
            'ledger'   => array(
                'title'     => 'My Token Ledger',
                'content'   => "[sda_totals]\n\n[sda_ledger]",
                'option'    => 'ledger',
            ),
            'totals'   => array(
                'title'     => 'My Token Summary',
                'content'   => '[sda_totals]',
                'option'    => 'totals',
            ),
            'projects' => array(
                'title'     => 'SDA Approved Projects',
                'content'   => '[sda_projects]',
                'option'    => 'projects',
            ),
            'goals'    => array(
                'title'     => 'Sustainability Goals (SDGs)',
                'content'   => '[sda_sdg_goals]',
                'option'    => 'goals',
            ),
        );

        $existing = get_option( 'sda_created_pages', array() );
        $created  = 0;
        $skipped  = 0;

        foreach ( $page_defs as $key => $def ) {
            // If page already exists and is published, skip
            if ( ! empty( $existing[ $key ] ) && 'publish' === get_post_status( (int) $existing[ $key ] ) ) {
                $skipped++;
                continue;
            }

            $page_id = wp_insert_post( array(
                'post_title'   => $def['title'],
                'post_content' => $def['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => get_current_user_id(),
                'meta_input'   => array( '_sda_auto_page' => $key ),
            ) );

            if ( $page_id && ! is_wp_error( $page_id ) ) {
                $existing[ $key ] = $page_id;
                $created++;
            }
        }

        update_option( 'sda_created_pages', $existing );

        $notice = urlencode( "Setup complete. $created page(s) created, $skipped already existed." );
        wp_safe_redirect( admin_url( "admin.php?page=sda-setup&sda_notice=$notice" ) );
        exit;
    }

    // ---------------------------------------------------------------- Form Handlers

    public static function handle_issue_sda() {
        check_admin_referer( 'sda_issue_sda' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        $id = SDA_Token::issue_sda( array(
            'user_id'      => absint( isset( $_POST['user_id'] )      ? $_POST['user_id']      : 0 ),
            'pid'          => sanitize_text_field( isset( $_POST['pid'] )          ? $_POST['pid']          : '' ),
            'bid'          => sanitize_text_field( isset( $_POST['bid'] )          ? $_POST['bid']          : '' ),
            'amount'       => sanitize_text_field( isset( $_POST['amount'] )       ? $_POST['amount']       : '0' ),
            'sdg_goal'     => absint( isset( $_POST['sdg_goal'] )     ? $_POST['sdg_goal']     : 0 ),
            'tx_hash_side' => sanitize_text_field( isset( $_POST['tx_hash_side'] ) ? $_POST['tx_hash_side'] : '' ),
            'notes'        => sanitize_textarea_field( isset( $_POST['notes'] )    ? $_POST['notes']        : '' ),
        ) );

        $notice = $id ? urlencode( "SDA tokens issued. Ledger ID: $id" ) : urlencode( 'Error: Could not issue SDA. Check all required fields.' );
        wp_safe_redirect( admin_url( "admin.php?page=sda-ledger&sda_notice=$notice" ) );
        exit;
    }

    public static function handle_register_project() {
        check_admin_referer( 'sda_register_project' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        $raw_goals = isset( $_POST['sdg_goals'] ) ? (array) $_POST['sdg_goals'] : array();
        $sdg_goals_arr = array();
        foreach ( array_map( 'intval', $raw_goals ) as $n ) {
            if ( $n >= 1 && $n <= 17 ) {
                $sdg_goals_arr[] = $n;
            }
        }
        $sdg_goals = implode( ',', $sdg_goals_arr );

        $id = SDA_Token::register_project( array(
            'pid'           => sanitize_text_field( isset( $_POST['pid'] )           ? $_POST['pid']           : '' ),
            'bid'           => sanitize_text_field( isset( $_POST['bid'] )           ? $_POST['bid']           : '' ),
            'chain_type'    => sanitize_text_field( isset( $_POST['chain_type'] )    ? $_POST['chain_type']    : 'sidechain' ),
            'project_name'  => sanitize_text_field( isset( $_POST['project_name'] )  ? $_POST['project_name']  : '' ),
            'sdg_goals'     => $sdg_goals,
            'owner_user_id' => absint( isset( $_POST['owner_user_id'] ) ? $_POST['owner_user_id'] : 0 ),
            'notes'         => sanitize_textarea_field( isset( $_POST['notes'] ) ? $_POST['notes'] : '' ),
        ) );

        $notice = $id ? urlencode( 'Project registered successfully.' ) : urlencode( 'Error: Could not register project. Check that the PID is unique.' );
        wp_safe_redirect( admin_url( "admin.php?page=sda-projects&sda_notice=$notice" ) );
        exit;
    }

    public static function handle_approve_project() {
        check_admin_referer( 'sda_approve_project' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        global $wpdb;
        $pid = sanitize_text_field( isset( $_POST['pid'] ) ? $_POST['pid'] : '' );
        $wpdb->update(
            sda_table( 'projects' ),
            array(
                'dao_approved' => 1,
                'approved_at'  => current_time( 'mysql' ),
                'proposal_ref' => sanitize_text_field( isset( $_POST['proposal_ref'] ) ? $_POST['proposal_ref'] : '' ),
            ),
            array( 'pid' => $pid ),
            array( '%d', '%s', '%s' ),
            array( '%s' )
        );

        wp_safe_redirect( admin_url( 'admin.php?page=sda-projects&sda_notice=' . urlencode( "Project $pid approved by DAO." ) ) );
        exit;
    }

    public static function handle_add_verifier() {
        check_admin_referer( 'sda_add_verifier' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        global $wpdb;
        $raw_scope = isset( $_POST['sdg_scope'] ) ? (array) $_POST['sdg_scope'] : array();
        $scope_arr = array();
        foreach ( array_map( 'intval', $raw_scope ) as $n ) {
            if ( $n >= 1 && $n <= 17 ) {
                $scope_arr[] = $n;
            }
        }

        $wpdb->insert(
            sda_table( 'verifiers' ),
            array(
                'vid'          => sanitize_text_field( isset( $_POST['vid'] )          ? $_POST['vid']          : '' ),
                'display_name' => sanitize_text_field( isset( $_POST['display_name'] ) ? $_POST['display_name'] : '' ),
                'org_name'     => sanitize_text_field( isset( $_POST['org_name'] )     ? $_POST['org_name']     : '' ),
                'sdg_scope'    => implode( ',', $scope_arr ),
                'active'       => 1,
            ),
            array( '%s', '%s', '%s', '%s', '%d' )
        );

        wp_safe_redirect( admin_url( 'admin.php?page=sda-verifiers&sda_notice=' . urlencode( 'Verifier added.' ) ) );
        exit;
    }

    public static function handle_verify_convert() {
        check_admin_referer( 'sda_issue_sda' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        $sdr_id = SDA_Token::verify_and_convert(
            (int)   ( isset( $_POST['sda_ledger_id'] ) ? $_POST['sda_ledger_id'] : 0 ),
            sanitize_text_field( isset( $_POST['vid'] ) ? $_POST['vid'] : '' ),
            (int)   ( isset( $_POST['contract_id'] )   ? $_POST['contract_id']   : 0 ),
            (float) ( isset( $_POST['sdr_ratio'] )     ? $_POST['sdr_ratio']     : 0 ),
            sanitize_text_field( isset( $_POST['tx_hash_main'] ) ? $_POST['tx_hash_main'] : '' ),
            sanitize_textarea_field( isset( $_POST['proof_note'] ) ? $_POST['proof_note'] : '' )
        );

        $notice = $sdr_id
            ? urlencode( "SDA converted to SDR successfully. SDR Ledger ID: $sdr_id" )
            : urlencode( 'Error: Could not convert. Check the SDA Ledger ID, contract ID, and that the SDA has not already been converted.' );

        wp_safe_redirect( admin_url( "admin.php?page=sda-contracts&sda_notice=$notice" ) );
        exit;
    }

    // ---------------------------------------------------------------- Xero OAuth Handlers

    /**
     * Redirect the admin to the Xero OAuth authorization page.
     */
    public static function handle_xero_connect() {
        check_admin_referer( 'sda_xero_connect' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        $url = SDA_Xero::get_auth_url();

        if ( ! $url ) {
            wp_safe_redirect( admin_url( 'admin.php?page=sda-settings&sda_notice=' . urlencode( 'Error: Enter Xero Client ID and Client Secret before connecting.' ) ) );
            exit;
        }

        wp_redirect( $url );
        exit;
    }

    /**
     * Handle the OAuth callback from Xero and exchange the code for tokens.
     */
    public static function handle_xero_callback() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        $code  = isset( $_GET['code'] )  ? sanitize_text_field( $_GET['code'] )  : '';
        $state = isset( $_GET['state'] ) ? sanitize_text_field( $_GET['state'] ) : '';
        $error = isset( $_GET['error'] ) ? sanitize_text_field( $_GET['error'] ) : '';

        if ( $error ) {
            $notice = urlencode( 'Xero OAuth error: ' . $error );
            wp_safe_redirect( admin_url( 'admin.php?page=sda-settings&sda_notice=' . $notice ) );
            exit;
        }

        if ( ! $code ) {
            wp_safe_redirect( admin_url( 'admin.php?page=sda-settings&sda_notice=' . urlencode( 'Xero OAuth: no authorization code received.' ) ) );
            exit;
        }

        $result = SDA_Xero::handle_callback( $code, $state );

        if ( is_wp_error( $result ) ) {
            $notice = urlencode( 'Xero connection failed: ' . $result->get_error_message() );
        } else {
            $notice = urlencode( 'Xero connected successfully! SDR conversions will now be posted automatically.' );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=sda-settings&sda_notice=' . $notice ) );
        exit;
    }

    /**
     * Disconnect from Xero by clearing stored tokens.
     */
    public static function handle_xero_disconnect() {
        check_admin_referer( 'sda_xero_disconnect' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        SDA_Xero::clear_tokens();

        wp_safe_redirect( admin_url( 'admin.php?page=sda-settings&sda_notice=' . urlencode( 'Xero disconnected. Stored tokens have been deleted.' ) ) );
        exit;
    }

    /**
     * Backfill all past SDR conversions to Xero.
     */
    public static function handle_xero_backfill() {
        check_admin_referer( 'sda_xero_backfill' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        $result = SDA_Xero::backfill();

        $msg = sprintf(
            'Xero sync complete. %d synced, %d already synced (skipped).',
            (int) $result['synced'],
            (int) $result['skipped']
        );

        if ( ! empty( $result['errors'] ) ) {
            $msg .= ' Errors: ' . implode( '; ', $result['errors'] );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=sda-settings&sda_notice=' . urlencode( $msg ) ) );
        exit;
    }

    /**
     * Manually retry all pending Xero sync failures.
     */
    public static function handle_xero_retry_failures() {
        check_admin_referer( 'sda_xero_retry_failures' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        $result = SDA_Xero::retry_pending_failures();

        $msg = sprintf(
            'Xero retry complete. %d succeeded, %d still failing, %d skipped (max attempts reached).',
            (int) $result['succeeded'],
            (int) $result['failed'],
            (int) $result['skipped']
        );

        if ( ! empty( $result['errors'] ) ) {
            $msg .= ' Errors: ' . implode( '; ', $result['errors'] );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=sda-settings&sda_notice=' . urlencode( $msg ) ) );
        exit;
    }

    public static function handle_create_contract() {
        check_admin_referer( 'sda_create_contract' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        global $wpdb;
        $wpdb->insert(
            sda_table( 'contracts' ),
            array(
                'contract_address' => sanitize_text_field( isset( $_POST['contract_address'] ) ? $_POST['contract_address'] : '' ),
                'pid'              => sanitize_text_field( isset( $_POST['pid'] )              ? $_POST['pid']              : '' ),
                'vid'              => sanitize_text_field( isset( $_POST['vid'] )              ? $_POST['vid']              : '' ),
                'proof_ref'        => sanitize_text_field( isset( $_POST['proof_ref'] )        ? $_POST['proof_ref']        : '' ),
                'sda_total'        => sanitize_text_field( isset( $_POST['sda_total'] )        ? $_POST['sda_total']        : '0' ),
                'status'           => 'pending',
                'notes'            => sanitize_textarea_field( isset( $_POST['notes'] )        ? $_POST['notes']            : '' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        wp_safe_redirect( admin_url( 'admin.php?page=sda-contracts&sda_notice=' . urlencode( 'Smart contract registered.' ) ) );
        exit;
    }
}
