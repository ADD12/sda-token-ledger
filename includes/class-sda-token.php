<?php
/**
 * Core token business logic.
 *
 * Compatible: PHP 7.2+  (no union types, no bcmath required)
 *
 * Handles:
 *  – Issuing SDA tokens (sidechain)
 *  – Converting SDA → SDR after smart-contract verification
 *  – Ledger queries
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SDA_Token {

    const MAX_SDR_RATIO    = 10;       // 1 SDA can produce at most 10 SDR
    const MIN_COIN_TOKENS  = 1000000;  // 1 M tokens required to propose own coin

    // ----------------------------------------------------------------- Issue SDA

    /**
     * Issue SDA tokens to a user on the sidechain.
     *
     * @param array $args {
     *   @type int    $user_id
     *   @type string $pid
     *   @type string $bid
     *   @type string $amount
     *   @type int    $sdg_goal   (optional)
     *   @type string $tx_hash_side (optional)
     *   @type string $notes      (optional)
     * }
     * @return int|false  New ledger row ID, or false on failure.
     */
    public static function issue_sda( $args ) {
        global $wpdb;

        $user_id  = absint( isset( $args['user_id'] )      ? $args['user_id']      : 0 );
        $pid      = sanitize_text_field( isset( $args['pid'] ) ? $args['pid'] : '' );
        $bid      = sanitize_text_field( isset( $args['bid'] ) ? $args['bid'] : '' );
        $amount   = (string) ( isset( $args['amount'] ) ? $args['amount'] : '0' );
        $sdg_goal = isset( $args['sdg_goal'] ) && $args['sdg_goal'] ? absint( $args['sdg_goal'] ) : null;

        if ( ! $user_id || ! $pid || ! $bid || (float) $amount <= 0 ) {
            return false;
        }

        $inserted = $wpdb->insert(
            sda_table( 'ledger' ),
            array(
                'user_id'      => $user_id,
                'pid'          => $pid,
                'bid'          => $bid,
                'token_type'   => 'SDA',
                'amount'       => $amount,
                'status'       => 'sidechain',
                'sdg_goal'     => $sdg_goal,
                'tx_hash_side' => sanitize_text_field( isset( $args['tx_hash_side'] ) ? $args['tx_hash_side'] : '' ),
                'notes'        => sanitize_textarea_field( isset( $args['notes'] ) ? $args['notes'] : '' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );

        if ( ! $inserted ) {
            return false;
        }

        $ledger_id = (int) $wpdb->insert_id;

        // Update project totals
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}sda_projects
                 SET total_sda = total_sda + %s, updated_at = NOW()
                 WHERE pid = %s",
                $amount,
                $pid
            )
        );

        do_action( 'sda_issued', $ledger_id, $args );

        return $ledger_id;
    }

    // ----------------------------------------------------------------- Verify & Convert

    /**
     * Mark an SDA as verified and mint SDR tokens.
     *
     * @param int    $sda_ledger_id  Ledger row for the source SDA.
     * @param string $vid            Verifier ID / wallet address.
     * @param int    $contract_id    FK to sda_contracts row.
     * @param float  $sdr_ratio      How many SDR per 1 SDA (max 10).
     * @param string $tx_hash_main   Main-chain TX hash confirming the contract.
     * @param string $proof_note     Optional note / IPFS ref.
     * @return int|false  SDR ledger row ID, or false on failure.
     */
    public static function verify_and_convert( $sda_ledger_id, $vid, $contract_id, $sdr_ratio, $tx_hash_main, $proof_note = '' ) {
        global $wpdb;

        // Clamp ratio
        $sdr_ratio = min( (float) $sdr_ratio, (float) self::MAX_SDR_RATIO );
        if ( $sdr_ratio <= 0 ) {
            return false;
        }

        // Load source SDA row
        $sda = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sda_ledger WHERE id = %d AND token_type = 'SDA'",
                (int) $sda_ledger_id
            )
        );

        if ( ! $sda || in_array( $sda->status, array( 'verified', 'converted', 'rejected' ), true ) ) {
            return false;
        }

        // Safe multiplication without bcmath
        $sdr_amount = self::multiply_tokens( $sda->amount, $sdr_ratio );

        // Mark SDA as converted
        $wpdb->update(
            sda_table( 'ledger' ),
            array(
                'status'       => 'converted',
                'vid'          => sanitize_text_field( $vid ),
                'contract_id'  => (int) $contract_id,
                'tx_hash_main' => sanitize_text_field( $tx_hash_main ),
                'verified_at'  => current_time( 'mysql' ),
            ),
            array( 'id' => (int) $sda_ledger_id ),
            array( '%s', '%s', '%d', '%s', '%s' ),
            array( '%d' )
        );

        // Create SDR ledger row
        $wpdb->insert(
            sda_table( 'ledger' ),
            array(
                'user_id'       => (int) $sda->user_id,
                'pid'           => $sda->pid,
                'bid'           => $sda->bid,
                'token_type'    => 'SDR',
                'amount'        => $sdr_amount,
                'status'        => 'verified',
                'sdg_goal'      => $sda->sdg_goal,
                'vid'           => sanitize_text_field( $vid ),
                'contract_id'   => (int) $contract_id,
                'sda_parent_id' => (int) $sda_ledger_id,
                'sdr_ratio'     => $sdr_ratio,
                'tx_hash_main'  => sanitize_text_field( $tx_hash_main ),
                'verified_at'   => current_time( 'mysql' ),
                'notes'         => sanitize_textarea_field( $proof_note ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%f', '%s', '%s', '%s' )
        );

        if ( ! $wpdb->insert_id ) {
            return false;
        }

        $sdr_id = (int) $wpdb->insert_id;

        // Update contract
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}sda_contracts
                 SET sdr_issued = sdr_issued + %s, status = 'signed', updated_at = NOW()
                 WHERE id = %d",
                $sdr_amount,
                (int) $contract_id
            )
        );

        // Update project totals
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}sda_projects
                 SET total_sdr = total_sdr + %s, updated_at = NOW()
                 WHERE pid = %s",
                $sdr_amount,
                $sda->pid
            )
        );

        do_action( 'sda_converted_to_sdr', $sdr_id, (int) $sda_ledger_id, $sdr_ratio );

        return $sdr_id;
    }

    // ----------------------------------------------------------------- Queries

    /**
     * Get all ledger rows for a user, optionally filtered.
     *
     * @param int   $user_id
     * @param array $filters
     * @return array
     */
    public static function get_user_ledger( $user_id, $filters = array() ) {
        global $wpdb;

        $where = array( $wpdb->prepare( 'l.user_id = %d', (int) $user_id ) );

        if ( ! empty( $filters['token_type'] ) ) {
            $where[] = $wpdb->prepare( "l.token_type = %s", $filters['token_type'] );
        }
        if ( ! empty( $filters['pid'] ) ) {
            $where[] = $wpdb->prepare( 'l.pid = %s', $filters['pid'] );
        }
        if ( ! empty( $filters['status'] ) ) {
            $where[] = $wpdb->prepare( "l.status = %s", $filters['status'] );
        }
        if ( ! empty( $filters['sdg_goal'] ) ) {
            $where[] = $wpdb->prepare( 'l.sdg_goal = %d', (int) $filters['sdg_goal'] );
        }

        $sql = "SELECT l.*, p.project_name, p.chain_type, p.dao_approved
                FROM {$wpdb->prefix}sda_ledger l
                LEFT JOIN {$wpdb->prefix}sda_projects p ON p.pid = l.pid
                WHERE " . implode( ' AND ', $where ) . "
                ORDER BY l.created_at DESC";

        $results = $wpdb->get_results( $sql ); // phpcs:ignore
        return $results ? $results : array();
    }

    /**
     * Aggregate totals for a user.
     *
     * @param int $user_id
     * @return array
     */
    public static function get_user_totals( $user_id ) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT token_type, status, SUM(amount) AS total
                 FROM {$wpdb->prefix}sda_ledger
                 WHERE user_id = %d
                 GROUP BY token_type, status",
                (int) $user_id
            )
        );

        $out = array(
            'sda_sidechain' => '0',
            'sda_verified'  => '0',
            'sda_converted' => '0',
            'sdr_verified'  => '0',
        );

        if ( $rows ) {
            foreach ( $rows as $row ) {
                if ( 'SDA' === $row->token_type && 'sidechain' === $row->status ) {
                    $out['sda_sidechain'] = $row->total;
                } elseif ( 'SDA' === $row->token_type && 'verified' === $row->status ) {
                    $out['sda_verified'] = $row->total;
                } elseif ( 'SDA' === $row->token_type && 'converted' === $row->status ) {
                    $out['sda_converted'] = $row->total;
                } elseif ( 'SDR' === $row->token_type && 'verified' === $row->status ) {
                    $out['sdr_verified'] = $row->total;
                }
            }
        }

        return $out;
    }

    /**
     * Get all projects, optionally filtered.
     *
     * @param array $filters
     * @return array
     */
    public static function get_projects( $filters = array() ) {
        global $wpdb;

        $where = array( '1=1' );

        if ( isset( $filters['dao_approved'] ) ) {
            $where[] = $wpdb->prepare( 'dao_approved = %d', (int) $filters['dao_approved'] );
        }
        if ( ! empty( $filters['owner_user_id'] ) ) {
            $where[] = $wpdb->prepare( 'owner_user_id = %d', (int) $filters['owner_user_id'] );
        }

        $sql = "SELECT * FROM {$wpdb->prefix}sda_projects WHERE " . implode( ' AND ', $where ) . " ORDER BY created_at DESC";

        $results = $wpdb->get_results( $sql ); // phpcs:ignore
        return $results ? $results : array();
    }

    /**
     * Register a new project.
     *
     * @param array $args
     * @return int|false
     */
    public static function register_project( $args ) {
        global $wpdb;

        $required = array( 'pid', 'bid', 'project_name', 'owner_user_id' );
        foreach ( $required as $field ) {
            if ( empty( $args[ $field ] ) ) {
                return false;
            }
        }

        $chain_type = ( isset( $args['chain_type'] ) && 'mainchain' === $args['chain_type'] ) ? 'mainchain' : 'sidechain';

        $result = $wpdb->insert(
            sda_table( 'projects' ),
            array(
                'pid'           => sanitize_text_field( $args['pid'] ),
                'bid'           => sanitize_text_field( $args['bid'] ),
                'chain_type'    => $chain_type,
                'project_name'  => sanitize_text_field( $args['project_name'] ),
                'sdg_goals'     => sanitize_text_field( isset( $args['sdg_goals'] ) ? $args['sdg_goals'] : '' ),
                'dao_approved'  => 0,
                'owner_user_id' => absint( $args['owner_user_id'] ),
                'notes'         => sanitize_textarea_field( isset( $args['notes'] ) ? $args['notes'] : '' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
        );

        if ( ! $result ) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Check if a user qualifies to propose their own coin (>= 1M SDA).
     *
     * @param int $user_id
     * @return bool
     */
    public static function can_propose_coin( $user_id ) {
        global $wpdb;

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount) FROM {$wpdb->prefix}sda_ledger
                 WHERE user_id = %d AND token_type = 'SDA'
                 AND status IN ('sidechain','verified','converted')",
                (int) $user_id
            )
        );

        return (float) $total >= (float) self::MIN_COIN_TOKENS;
    }

    // ----------------------------------------------------------------- Helpers

    /**
     * Multiply a token amount by a ratio, returning an 8-decimal string.
     * Uses bcmul if available, otherwise falls back to float arithmetic.
     *
     * @param string|float $amount
     * @param float        $ratio
     * @return string
     */
    public static function multiply_tokens( $amount, $ratio ) {
        if ( function_exists( 'bcmul' ) ) {
            return bcmul( (string) $amount, (string) $ratio, 8 );
        }
        return number_format( (float) $amount * (float) $ratio, 8, '.', '' );
    }
}
