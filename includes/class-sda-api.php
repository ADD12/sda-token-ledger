<?php
/**
 * REST API endpoints for smart-contract integration.
 *
 * Base: /wp-json/sda/v1/
 *
 * Public routes (require API key header X-SDA-API-Key):
 *   POST /tokens/issue          – Issue SDA tokens
 *   POST /tokens/verify         – Verify SDA and mint SDR
 *   GET  /ledger/{user_id}      – Ledger for a user
 *   GET  /projects              – All approved projects
 *   GET  /projects/{pid}        – Single project detail
 *   POST /contracts             – Register a smart contract
 *   GET  /verifiers             – List active verifiers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SDA_API {

    const NAMESPACE = 'sda/v1';

    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/tokens/issue', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'issue_sda' ),
            'permission_callback' => array( __CLASS__, 'check_api_key' ),
            'args'                => array(
                'user_id'      => array( 'required' => true, 'type' => 'integer' ),
                'pid'          => array( 'required' => true, 'type' => 'string' ),
                'bid'          => array( 'required' => true, 'type' => 'string' ),
                'amount'       => array( 'required' => true, 'type' => 'string' ),
                'sdg_goal'     => array( 'required' => false, 'type' => 'integer', 'minimum' => 1, 'maximum' => 17 ),
                'tx_hash_side' => array( 'required' => false, 'type' => 'string' ),
                'notes'        => array( 'required' => false, 'type' => 'string' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/tokens/verify', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'verify_sda' ),
            'permission_callback' => array( __CLASS__, 'check_api_key' ),
            'args'                => array(
                'sda_ledger_id' => array( 'required' => true,  'type' => 'integer' ),
                'vid'           => array( 'required' => true,  'type' => 'string' ),
                'contract_id'   => array( 'required' => true,  'type' => 'integer' ),
                'sdr_ratio'     => array( 'required' => true,  'type' => 'number', 'minimum' => 0.01, 'maximum' => 10 ),
                'tx_hash_main'  => array( 'required' => true,  'type' => 'string' ),
                'proof_note'    => array( 'required' => false, 'type' => 'string' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/ledger/(?P<user_id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_ledger' ),
            'permission_callback' => array( __CLASS__, 'check_api_key' ),
            'args'                => array(
                'user_id'    => array( 'required' => true, 'type' => 'integer' ),
                'token_type' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'SDA', 'SDR' ) ),
                'pid'        => array( 'required' => false, 'type' => 'string' ),
                'status'     => array( 'required' => false, 'type' => 'string' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/projects', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_projects' ),
            'permission_callback' => array( __CLASS__, 'check_api_key' ),
        ) );

        register_rest_route( self::NAMESPACE, '/projects/(?P<pid>[a-zA-Z0-9_-]+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_project' ),
            'permission_callback' => array( __CLASS__, 'check_api_key' ),
        ) );

        register_rest_route( self::NAMESPACE, '/contracts', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'create_contract' ),
            'permission_callback' => array( __CLASS__, 'check_api_key' ),
            'args'                => array(
                'contract_address' => array( 'required' => true,  'type' => 'string' ),
                'pid'              => array( 'required' => true,  'type' => 'string' ),
                'vid'              => array( 'required' => true,  'type' => 'string' ),
                'proof_ref'        => array( 'required' => false, 'type' => 'string' ),
                'sda_total'        => array( 'required' => true,  'type' => 'string' ),
                'notes'            => array( 'required' => false, 'type' => 'string' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/verifiers', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_verifiers' ),
            'permission_callback' => array( __CLASS__, 'check_api_key' ),
        ) );
    }

    // ------------------------------------------------------------------ Handlers

    public static function issue_sda( WP_REST_Request $req ): WP_REST_Response {
        $id = SDA_Token::issue_sda( $req->get_params() );
        if ( ! $id ) {
            return new WP_REST_Response( array( 'error' => 'Failed to issue SDA. Check required fields and that the PID exists.' ), 400 );
        }
        return new WP_REST_Response( array( 'success' => true, 'ledger_id' => $id ), 201 );
    }

    public static function verify_sda( WP_REST_Request $req ): WP_REST_Response {
        $p      = $req->get_params();
        $sdr_id = SDA_Token::verify_and_convert(
            (int)   $p['sda_ledger_id'],
            (string)$p['vid'],
            (int)   $p['contract_id'],
            (float) $p['sdr_ratio'],
            (string)$p['tx_hash_main'],
            (string)( $p['proof_note'] ?? '' )
        );
        if ( ! $sdr_id ) {
            return new WP_REST_Response( array( 'error' => 'Verification failed. The SDA may already be converted, or inputs are invalid.' ), 400 );
        }
        return new WP_REST_Response( array( 'success' => true, 'sdr_ledger_id' => $sdr_id ), 200 );
    }

    public static function get_ledger( WP_REST_Request $req ): WP_REST_Response {
        $user_id = (int) $req->get_param( 'user_id' );
        $filters = array_filter( array(
            'token_type' => $req->get_param( 'token_type' ),
            'pid'        => $req->get_param( 'pid' ),
            'status'     => $req->get_param( 'status' ),
        ) );
        $rows    = SDA_Token::get_user_ledger( $user_id, $filters );
        $totals  = SDA_Token::get_user_totals( $user_id );
        return new WP_REST_Response( array(
            'user_id' => $user_id,
            'totals'  => $totals,
            'rows'    => $rows,
        ), 200 );
    }

    public static function get_projects( WP_REST_Request $req ): WP_REST_Response {
        $projects = SDA_Token::get_projects( array( 'dao_approved' => 1 ) );
        return new WP_REST_Response( $projects, 200 );
    }

    public static function get_project( WP_REST_Request $req ): WP_REST_Response {
        global $wpdb;
        $pid = sanitize_text_field( $req->get_param( 'pid' ) );
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sda_projects WHERE pid = %s", $pid
        ) );
        if ( ! $row ) {
            return new WP_REST_Response( array( 'error' => 'Project not found.' ), 404 );
        }
        return new WP_REST_Response( $row, 200 );
    }

    public static function create_contract( WP_REST_Request $req ): WP_REST_Response {
        global $wpdb;
        $p = $req->get_params();

        $wpdb->insert(
            sda_table( 'contracts' ),
            array(
                'contract_address' => sanitize_text_field( $p['contract_address'] ),
                'pid'              => sanitize_text_field( $p['pid'] ),
                'vid'              => sanitize_text_field( $p['vid'] ),
                'proof_ref'        => sanitize_text_field( $p['proof_ref'] ?? '' ),
                'sda_total'        => sanitize_text_field( $p['sda_total'] ),
                'status'           => 'pending',
                'notes'            => sanitize_textarea_field( $p['notes'] ?? '' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( ! $wpdb->insert_id ) {
            return new WP_REST_Response( array( 'error' => 'Failed to create contract record.' ), 500 );
        }

        return new WP_REST_Response( array( 'success' => true, 'contract_id' => (int) $wpdb->insert_id ), 201 );
    }

    public static function get_verifiers( WP_REST_Request $req ): WP_REST_Response {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}sda_verifiers WHERE active = 1 ORDER BY display_name ASC"
        );
        return new WP_REST_Response( $rows ?? array(), 200 );
    }

    // ---------------------------------------------------------------- Auth

    /**
     * Verify the X-SDA-API-Key header against the stored secret.
     * Also accepts WordPress admin users (for testing from the admin panel).
     */
    public static function check_api_key( WP_REST_Request $req ): bool {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        $settings = get_option( 'sda_settings', array() );
        $stored   = $settings['api_key'] ?? '';

        if ( empty( $stored ) ) {
            return false;
        }

        $provided = $req->get_header( 'X-SDA-API-Key' );
        return hash_equals( $stored, (string) $provided );
    }
}
