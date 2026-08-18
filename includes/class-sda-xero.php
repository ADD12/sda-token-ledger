<?php
/**
 * Xero Accounting Integration for SDA Token Ledger.
 *
 * Handles:
 *  - OAuth 2.0 authorization code flow (PKCE-optional; client-secret flow)
 *  - Token storage / refresh
 *  - Posting SDR conversion events as Xero invoices
 *  - Backfilling past unsynced conversions
 *
 * Compatible: PHP 7.2+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SDA_Xero {

    const AUTH_URL       = 'https://login.xero.com/identity/connect/authorize';
    const TOKEN_URL      = 'https://identity.xero.com/connect/token';
    const API_BASE       = 'https://api.xero.com/api.xro/2.0';
    const SCOPES         = 'offline_access accounting.transactions accounting.settings.read';
    const TOKENS_KEY     = 'sda_xero_tokens';
    const SYNCED_KEY     = 'sda_xero_synced_sdr_ids';
    const STATE_TRANSIENT = 'sda_xero_oauth_state';

    // ----------------------------------------------------------------- Credentials

    /**
     * Return stored Xero credentials (client ID, secret, tenant ID, account code).
     *
     * @return array  Keys: client_id, client_secret, tenant_id, account_code, currency.
     */
    public static function get_credentials() {
        $settings = get_option( 'sda_settings', array() );
        return array(
            'client_id'     => isset( $settings['xero_client_id'] )     ? trim( $settings['xero_client_id'] )     : '',
            'client_secret' => isset( $settings['xero_client_secret'] ) ? trim( $settings['xero_client_secret'] ) : '',
            'tenant_id'     => isset( $settings['xero_tenant_id'] )     ? trim( $settings['xero_tenant_id'] )     : '',
            'account_code'  => isset( $settings['xero_account_code'] )  ? trim( $settings['xero_account_code'] )  : '200',
            'currency'      => isset( $settings['xero_currency'] )       ? strtoupper( trim( $settings['xero_currency'] ) ) : 'USD',
        );
    }

    /**
     * Check whether the minimum credentials (client ID + secret) are configured.
     *
     * @return bool
     */
    public static function has_credentials() {
        $creds = self::get_credentials();
        return ! empty( $creds['client_id'] ) && ! empty( $creds['client_secret'] );
    }

    // ----------------------------------------------------------------- Token Storage

    /**
     * Return stored OAuth token data.
     *
     * @return array|false  Keys: access_token, refresh_token, expires_at, token_type.
     */
    public static function get_tokens() {
        return get_option( self::TOKENS_KEY, false );
    }

    /**
     * Persist OAuth tokens.
     *
     * @param array $data
     */
    private static function save_tokens( $data ) {
        update_option( self::TOKENS_KEY, $data, false );
    }

    /**
     * Delete stored tokens (disconnect).
     */
    public static function clear_tokens() {
        delete_option( self::TOKENS_KEY );
    }

    // ----------------------------------------------------------------- OAuth Flow

    /**
     * The redirect URI registered in the Xero app.
     *
     * @return string
     */
    public static function get_redirect_uri() {
        return admin_url( 'admin-post.php?action=sda_xero_callback' );
    }

    /**
     * Generate and return the Xero OAuth 2.0 authorization URL.
     * Saves a state token to a short-lived transient for CSRF protection.
     *
     * @return string|false  URL, or false if credentials are missing.
     */
    public static function get_auth_url() {
        if ( ! self::has_credentials() ) {
            return false;
        }

        $state = wp_generate_password( 32, false );
        set_transient( self::STATE_TRANSIENT, $state, 10 * MINUTE_IN_SECONDS );

        $creds = self::get_credentials();

        $params = array(
            'response_type' => 'code',
            'client_id'     => $creds['client_id'],
            'redirect_uri'  => self::get_redirect_uri(),
            'scope'         => self::SCOPES,
            'state'         => $state,
        );

        return self::AUTH_URL . '?' . http_build_query( $params );
    }

    /**
     * Exchange an authorization code for access + refresh tokens.
     *
     * @param string $code   Authorization code from Xero callback.
     * @param string $state  State value returned by Xero.
     * @return true|WP_Error
     */
    public static function handle_callback( $code, $state ) {
        // CSRF check
        $saved_state = get_transient( self::STATE_TRANSIENT );
        delete_transient( self::STATE_TRANSIENT );

        if ( ! $saved_state || ! hash_equals( (string) $saved_state, (string) $state ) ) {
            return new WP_Error( 'xero_state_mismatch', __( 'OAuth state mismatch. Please try connecting again.', 'sda-token-ledger' ) );
        }

        $creds = self::get_credentials();

        $response = wp_remote_post(
            self::TOKEN_URL,
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( $creds['client_id'] . ':' . $creds['client_secret'] ),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ),
                'body' => array(
                    'grant_type'   => 'authorization_code',
                    'code'         => $code,
                    'redirect_uri' => self::get_redirect_uri(),
                ),
            )
        );

        return self::process_token_response( $response );
    }

    /**
     * Refresh the access token using the stored refresh token.
     *
     * @return true|WP_Error
     */
    public static function refresh_access_token() {
        $tokens = self::get_tokens();
        if ( ! $tokens || empty( $tokens['refresh_token'] ) ) {
            return new WP_Error( 'xero_no_refresh_token', __( 'No Xero refresh token found. Please reconnect.', 'sda-token-ledger' ) );
        }

        $creds = self::get_credentials();

        $response = wp_remote_post(
            self::TOKEN_URL,
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( $creds['client_id'] . ':' . $creds['client_secret'] ),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ),
                'body' => array(
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $tokens['refresh_token'],
                ),
            )
        );

        return self::process_token_response( $response );
    }

    /**
     * Parse a token endpoint response and store the result.
     *
     * @param array|WP_Error $response
     * @return true|WP_Error
     */
    private static function process_token_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 || empty( $body['access_token'] ) ) {
            $msg = isset( $body['error_description'] ) ? $body['error_description'] : sprintf( __( 'Xero token request failed (HTTP %d).', 'sda-token-ledger' ), (int) $code );
            return new WP_Error( 'xero_token_error', $msg );
        }

        self::save_tokens( array(
            'access_token'  => $body['access_token'],
            'refresh_token' => isset( $body['refresh_token'] ) ? $body['refresh_token'] : '',
            'expires_at'    => time() + (int) ( isset( $body['expires_in'] ) ? $body['expires_in'] : 1800 ) - 60,
            'token_type'    => isset( $body['token_type'] ) ? $body['token_type'] : 'Bearer',
        ) );

        return true;
    }

    /**
     * Get a valid (non-expired) access token, refreshing if necessary.
     *
     * @return string|WP_Error
     */
    public static function get_valid_access_token() {
        $tokens = self::get_tokens();

        if ( ! $tokens || empty( $tokens['access_token'] ) ) {
            return new WP_Error( 'xero_not_connected', __( 'Xero is not connected. Please authenticate from SDG Settings.', 'sda-token-ledger' ) );
        }

        // Refresh if within 60 s of expiry (already offset by 60 s in save)
        if ( isset( $tokens['expires_at'] ) && time() >= (int) $tokens['expires_at'] ) {
            $result = self::refresh_access_token();
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            $tokens = self::get_tokens();
        }

        return $tokens['access_token'];
    }

    // ----------------------------------------------------------------- Connection Status

    /**
     * Returns true if the plugin has a stored access/refresh token.
     *
     * @return bool
     */
    public static function is_connected() {
        $tokens = self::get_tokens();
        return ! empty( $tokens['access_token'] );
    }

    /**
     * Return a human-readable status array.
     *
     * @return array  Keys: connected (bool), label (string), details (string).
     */
    public static function get_status() {
        if ( ! self::has_credentials() ) {
            return array(
                'connected' => false,
                'label'     => __( 'Not Configured', 'sda-token-ledger' ),
                'details'   => __( 'Enter a Xero Client ID and Client Secret in SDG Settings to get started.', 'sda-token-ledger' ),
            );
        }

        if ( ! self::is_connected() ) {
            return array(
                'connected' => false,
                'label'     => __( 'Disconnected', 'sda-token-ledger' ),
                'details'   => __( 'Credentials saved. Click "Connect to Xero" to complete OAuth authentication.', 'sda-token-ledger' ),
            );
        }

        $tokens  = self::get_tokens();
        $expires = isset( $tokens['expires_at'] ) ? (int) $tokens['expires_at'] : 0;
        $synced  = count( (array) get_option( self::SYNCED_KEY, array() ) );

        return array(
            'connected' => true,
            'label'     => __( 'Connected', 'sda-token-ledger' ),
            'details'   => sprintf(
                /* translators: 1: ISO-8601 expiry datetime, 2: number of synced entries */
                __( 'Access token valid (refreshes automatically). %2$d conversion(s) synced to Xero.', 'sda-token-ledger' ),
                gmdate( 'Y-m-d H:i', $expires ),
                $synced
            ),
        );
    }

    // ----------------------------------------------------------------- API Requests

    /**
     * Make an authenticated request to the Xero API.
     *
     * @param string      $method    HTTP method (GET, POST, PUT).
     * @param string      $endpoint  API path, e.g. 'Invoices'.
     * @param array|null  $body      JSON-encodable body for POST/PUT.
     * @return array|WP_Error  Decoded JSON response body, or WP_Error.
     */
    private static function api_request( $method, $endpoint, $body = null ) {
        $token = self::get_valid_access_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $creds = self::get_credentials();
        if ( empty( $creds['tenant_id'] ) ) {
            return new WP_Error( 'xero_no_tenant', __( 'Xero Tenant ID is not configured. Enter it in SDG Settings.', 'sda-token-ledger' ) );
        }

        $args = array(
            'method'  => strtoupper( $method ),
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Xero-Tenant-Id' => $creds['tenant_id'],
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ),
        );

        if ( null !== $body ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( self::API_BASE . '/' . ltrim( $endpoint, '/' ), $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code    = wp_remote_retrieve_response_code( $response );
        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            $msg = isset( $decoded['Detail'] ) ? $decoded['Detail'] : sprintf( __( 'Xero API error (HTTP %d).', 'sda-token-ledger' ), (int) $code );
            return new WP_Error( 'xero_api_error', $msg );
        }

        return is_array( $decoded ) ? $decoded : array();
    }

    // ----------------------------------------------------------------- Sync Logic

    // ----------------------------------------------------------------- Failure Logging

    const MAX_RETRY_ATTEMPTS = 3;

    /**
     * Record (or update) a failed Xero sync attempt in the failures table.
     *
     * @param int    $sdr_id
     * @param int    $sda_id
     * @param float  $ratio
     * @param string $error_message
     */
    public static function log_failure( $sdr_id, $sda_id, $ratio, $error_message ) {
        global $wpdb;

        $table = $wpdb->prefix . 'sda_xero_failures';

        $existing = $wpdb->get_row(
            $wpdb->prepare( "SELECT id, attempt_count FROM {$table} WHERE sdr_id = %d", (int) $sdr_id )
        );

        if ( $existing ) {
            $wpdb->update(
                $table,
                array(
                    'error_message' => $error_message,
                    'attempt_count' => (int) $existing->attempt_count + 1,
                    'attempted_at'  => current_time( 'mysql', true ),
                    'resolved'      => 0,
                ),
                array( 'sdr_id' => (int) $sdr_id ),
                array( '%s', '%d', '%s', '%d' ),
                array( '%d' )
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'sdr_id'        => (int) $sdr_id,
                    'sda_id'        => (int) $sda_id,
                    'sdr_ratio'     => (float) $ratio,
                    'error_message' => $error_message,
                    'attempt_count' => 1,
                    'attempted_at'  => current_time( 'mysql', true ),
                    'resolved'      => 0,
                ),
                array( '%d', '%d', '%f', '%s', '%d', '%s', '%d' )
            );
        }

        error_log( '[SDA Xero] Failure logged for SDR #' . (int) $sdr_id . ': ' . $error_message );
    }

    /**
     * Mark a failure record as resolved (successfully retried).
     *
     * @param int $sdr_id
     */
    private static function resolve_failure( $sdr_id ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'sda_xero_failures',
            array( 'resolved' => 1 ),
            array( 'sdr_id'   => (int) $sdr_id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    /**
     * Return all unresolved failure rows.
     *
     * @return array
     */
    public static function get_pending_failures() {
        global $wpdb;
        return (array) $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}sda_xero_failures WHERE resolved = 0 ORDER BY attempted_at ASC"
        );
    }

    /**
     * Return the count of unresolved failures.
     *
     * @return int
     */
    public static function count_pending_failures() {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sda_xero_failures WHERE resolved = 0"
        );
    }

    /**
     * Retry all pending failures that have not yet exceeded MAX_RETRY_ATTEMPTS.
     * Called by the hourly cron job and the admin one-click retry button.
     *
     * @return array  Keys: retried (int), succeeded (int), failed (int), skipped (int), errors (string[]).
     */
    public static function retry_pending_failures() {
        $results = array(
            'retried'   => 0,
            'succeeded' => 0,
            'failed'    => 0,
            'skipped'   => 0,
            'errors'    => array(),
        );

        if ( ! self::is_connected() ) {
            $results['errors'][] = __( 'Xero is not connected.', 'sda-token-ledger' );
            return $results;
        }

        $rows = self::get_pending_failures();

        foreach ( $rows as $row ) {
            if ( (int) $row->attempt_count >= self::MAX_RETRY_ATTEMPTS ) {
                $results['skipped']++;
                continue;
            }

            $results['retried']++;

            $res = self::post_conversion( (int) $row->sdr_id, (int) $row->sda_id, (float) $row->sdr_ratio );

            if ( is_wp_error( $res ) ) {
                self::log_failure( (int) $row->sdr_id, (int) $row->sda_id, (float) $row->sdr_ratio, $res->get_error_message() );
                $results['failed']++;
                $results['errors'][] = 'SDR #' . (int) $row->sdr_id . ': ' . $res->get_error_message();
            } elseif ( isset( $res['already_synced'] ) ) {
                // Already marked synced — treat as resolved
                self::resolve_failure( (int) $row->sdr_id );
                $results['succeeded']++;
            } else {
                self::resolve_failure( (int) $row->sdr_id );
                $results['succeeded']++;
            }

            usleep( 200000 ); // 200 ms — avoid rate limits
        }

        return $results;
    }

    /**
     * WP-Cron callback: retry pending Xero failures every hour.
     */
    public static function cron_retry_failures() {
        self::retry_pending_failures();
    }

    // ----------------------------------------------------------------- Conversion Hook

    /**
     * Hook registered on `sda_converted_to_sdr`.
     * Posts the conversion to Xero if the integration is connected.
     *
     * @param int   $sdr_id        SDR ledger row ID.
     * @param int   $sda_ledger_id Source SDA ledger row ID.
     * @param float $sdr_ratio     Conversion ratio.
     */
    public static function on_conversion( $sdr_id, $sda_ledger_id, $sdr_ratio ) {
        if ( ! self::is_connected() ) {
            return;
        }

        $result = self::post_conversion( (int) $sdr_id, (int) $sda_ledger_id, (float) $sdr_ratio );

        if ( is_wp_error( $result ) ) {
            self::log_failure( (int) $sdr_id, (int) $sda_ledger_id, (float) $sdr_ratio, $result->get_error_message() );
        }
    }

    /**
     * Post a single SDA → SDR conversion to Xero as an Accounts Receivable invoice.
     *
     * @param int   $sdr_id
     * @param int   $sda_id
     * @param float $ratio
     * @return array|WP_Error  Xero API response or error.
     */
    public static function post_conversion( $sdr_id, $sda_id, $ratio ) {
        if ( self::is_synced( $sdr_id ) ) {
            return array( 'already_synced' => true );
        }

        global $wpdb;

        // Load the SDR row (joined with project for name)
        $sdr = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT l.*, p.project_name
                 FROM {$wpdb->prefix}sda_ledger l
                 LEFT JOIN {$wpdb->prefix}sda_projects p ON p.pid = l.pid
                 WHERE l.id = %d AND l.token_type = 'SDR'",
                $sdr_id
            )
        );

        if ( ! $sdr ) {
            return new WP_Error( 'xero_sdr_not_found', sprintf( __( 'SDR ledger row #%d not found.', 'sda-token-ledger' ), $sdr_id ) );
        }

        $creds        = self::get_credentials();
        $settings     = get_option( 'sda_settings', array() );
        $sdr_symbol   = isset( $settings['sdr_symbol'] ) && $settings['sdr_symbol'] ? $settings['sdr_symbol'] : 'SDR';
        $project_name = $sdr->project_name ? $sdr->project_name : $sdr->pid;
        $sdg_label    = $sdr->sdg_goal ? 'SDG ' . (int) $sdr->sdg_goal : 'No SDG';
        $tx_hash      = $sdr->tx_hash_main ? $sdr->tx_hash_main : 'N/A';
        $date_str     = gmdate( 'Y-m-d', strtotime( $sdr->verified_at ? $sdr->verified_at : $sdr->created_at ) );
        $amount       = round( (float) $sdr->amount, 2 );

        $description = sprintf(
            /* translators: 1: SDR symbol, 2: project name, 3: SDG label, 4: ratio, 5: TX hash */
            '%1$s Conversion — %2$s | %3$s | Ratio: %4$s | TX: %5$s',
            $sdr_symbol,
            $project_name,
            $sdg_label,
            number_format( $ratio, 2 ),
            $tx_hash
        );

        $line_item = array(
            'Description' => $description,
            'Quantity'    => 1,
            'UnitAmount'  => $amount,
            'AccountCode' => $creds['account_code'] ? $creds['account_code'] : '200',
        );

        // Attach tracking category if SDG goal is set
        if ( $sdr->sdg_goal ) {
            $line_item['Tracking'] = array(
                array(
                    'Name'   => 'SDG',
                    'Option' => 'SDG ' . (int) $sdr->sdg_goal,
                ),
            );
        }

        $payload = array(
            'Invoices' => array(
                array(
                    'Type'          => 'ACCREC',
                    'Status'        => 'AUTHORISED',
                    'Date'          => $date_str,
                    'DueDate'       => $date_str,
                    'CurrencyCode'  => $creds['currency'] ? $creds['currency'] : 'USD',
                    'Reference'     => 'SDA#' . (int) $sda_id . '/SDR#' . $sdr_id,
                    'LineAmountTypes' => 'Exclusive',
                    'LineItems'     => array( $line_item ),
                    'Contact'       => array(
                        'Name' => $project_name,
                    ),
                ),
            ),
        );

        $result = self::api_request( 'POST', 'Invoices', $payload );

        if ( ! is_wp_error( $result ) ) {
            self::mark_synced( $sdr_id );
        }

        return $result;
    }

    /**
     * Backfill: post all SDR ledger rows that have not yet been synced to Xero.
     *
     * @return array  Keys: synced (int), skipped (int), errors (string[]).
     */
    public static function backfill() {
        global $wpdb;

        $results = array( 'synced' => 0, 'skipped' => 0, 'errors' => array() );

        if ( ! self::is_connected() ) {
            $results['errors'][] = __( 'Xero is not connected.', 'sda-token-ledger' );
            return $results;
        }

        $sdr_rows = $wpdb->get_results(
            "SELECT id, sda_parent_id, sdr_ratio FROM {$wpdb->prefix}sda_ledger
             WHERE token_type = 'SDR' AND status = 'verified'
             ORDER BY id ASC"
        );

        if ( ! $sdr_rows ) {
            return $results;
        }

        foreach ( $sdr_rows as $row ) {
            if ( self::is_synced( (int) $row->id ) ) {
                $results['skipped']++;
                continue;
            }

            $res = self::post_conversion( (int) $row->id, (int) $row->sda_parent_id, (float) $row->sdr_ratio );

            if ( is_wp_error( $res ) ) {
                $results['errors'][] = 'SDR #' . (int) $row->id . ': ' . $res->get_error_message();
            } elseif ( isset( $res['already_synced'] ) ) {
                $results['skipped']++;
            } else {
                $results['synced']++;
            }

            // Small delay to avoid Xero rate limits (60 calls/min)
            usleep( 200000 ); // 200 ms
        }

        return $results;
    }

    // ----------------------------------------------------------------- Sync Tracking

    /**
     * Mark an SDR ledger ID as successfully synced to Xero.
     *
     * @param int $sdr_id
     */
    private static function mark_synced( $sdr_id ) {
        $synced   = (array) get_option( self::SYNCED_KEY, array() );
        $synced[] = (int) $sdr_id;
        update_option( self::SYNCED_KEY, array_unique( $synced ), false );
    }

    /**
     * Check if an SDR ledger ID has already been synced.
     *
     * @param int $sdr_id
     * @return bool
     */
    private static function is_synced( $sdr_id ) {
        $synced = (array) get_option( self::SYNCED_KEY, array() );
        return in_array( (int) $sdr_id, $synced, true );
    }
}
