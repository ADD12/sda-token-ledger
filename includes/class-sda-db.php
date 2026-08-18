<?php
/**
 * Database installation and schema management.
 *
 * Tables created:
 *  wp_sda_ledger   – one row per SDA / SDR token event
 *  wp_sda_projects – project (PID) registry
 *  wp_sda_verifiers – approved VID verifier addresses
 *  wp_sda_contracts – smart-contract audit trail
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SDA_DB {

    // ------------------------------------------------------------------ install

    public static function install(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // ---- wp_sda_projects -----------------------------------------------
        dbDelta( "CREATE TABLE {$wpdb->prefix}sda_projects (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pid           VARCHAR(64)     NOT NULL,
            bid           VARCHAR(128)    NOT NULL COMMENT 'Blockchain / B-lan ID (sidechain address or chain identifier)',
            chain_type    ENUM('sidechain','mainchain') NOT NULL DEFAULT 'sidechain',
            project_name  VARCHAR(255)    NOT NULL,
            sdg_goals     VARCHAR(255)    NOT NULL DEFAULT '' COMMENT 'Comma-separated SDG numbers 1–17',
            dao_approved  TINYINT(1)      NOT NULL DEFAULT 0,
            approved_at   DATETIME        NULL,
            proposal_ref  VARCHAR(255)    NULL COMMENT 'DAO proposal reference / TX hash',
            total_sda     DECIMAL(30,8)   NOT NULL DEFAULT 0,
            total_sdr     DECIMAL(30,8)   NOT NULL DEFAULT 0,
            owner_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            notes         TEXT            NULL,
            created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY   uq_pid (pid),
            KEY          idx_bid (bid),
            KEY          idx_owner (owner_user_id)
        ) $charset;" );

        // ---- wp_sda_ledger -------------------------------------------------
        dbDelta( "CREATE TABLE {$wpdb->prefix}sda_ledger (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id         BIGINT UNSIGNED NOT NULL COMMENT 'WordPress user ID',
            pid             VARCHAR(64)     NOT NULL COMMENT 'Project ID',
            bid             VARCHAR(128)    NOT NULL COMMENT 'Blockchain / B-lan ID',
            token_type      ENUM('SDA','SDR') NOT NULL,
            amount          DECIMAL(30,8)   NOT NULL DEFAULT 0,
            status          ENUM('pending','sidechain','verified','converted','rejected') NOT NULL DEFAULT 'pending',
            sdg_goal        TINYINT UNSIGNED NULL COMMENT '1–17 UN SDG number',
            tx_hash_side    VARCHAR(128)    NULL COMMENT 'Sidechain transaction hash',
            tx_hash_main    VARCHAR(128)    NULL COMMENT 'Main-chain transaction hash after verification',
            vid             VARCHAR(128)    NULL COMMENT 'Verifier ID / verifier wallet address',
            contract_id     BIGINT UNSIGNED NULL COMMENT 'FK to sda_contracts',
            sda_parent_id   BIGINT UNSIGNED NULL COMMENT 'For SDR: the SDA ledger row that originated it',
            sdr_ratio       DECIMAL(5,2)    NULL COMMENT 'SDR tokens issued per 1 SDA (max 10)',
            verified_at     DATETIME        NULL,
            notes           TEXT            NULL,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY    (id),
            KEY            idx_user   (user_id),
            KEY            idx_pid    (pid),
            KEY            idx_bid    (bid),
            KEY            idx_status (status),
            KEY            idx_type   (token_type)
        ) $charset;" );

        // ---- wp_sda_verifiers ----------------------------------------------
        dbDelta( "CREATE TABLE {$wpdb->prefix}sda_verifiers (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            vid          VARCHAR(128)    NOT NULL COMMENT 'Verifier wallet address or DID',
            display_name VARCHAR(255)    NOT NULL DEFAULT '',
            org_name     VARCHAR(255)    NULL,
            sdg_scope    VARCHAR(255)    NOT NULL DEFAULT '' COMMENT 'Comma-separated SDG numbers this verifier covers',
            active       TINYINT(1)      NOT NULL DEFAULT 1,
            created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_vid (vid)
        ) $charset;" );

        // ---- wp_sda_contracts ----------------------------------------------
        dbDelta( "CREATE TABLE {$wpdb->prefix}sda_contracts (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_address VARCHAR(128)  NOT NULL COMMENT 'Main-chain smart contract address',
            pid             VARCHAR(64)    NOT NULL,
            vid             VARCHAR(128)   NOT NULL,
            signed_at       DATETIME       NULL,
            proof_ref       VARCHAR(512)   NULL COMMENT 'IPFS CID or URL to proof-of-production document',
            status          ENUM('pending','signed','rejected') NOT NULL DEFAULT 'pending',
            sda_total       DECIMAL(30,8)  NOT NULL DEFAULT 0 COMMENT 'SDA tokens covered by this contract',
            sdr_issued      DECIMAL(30,8)  NOT NULL DEFAULT 0 COMMENT 'SDR tokens minted from this contract',
            chain_tx        VARCHAR(128)   NULL,
            notes           TEXT           NULL,
            created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_pid (pid),
            KEY idx_vid (vid),
            KEY idx_status (status)
        ) $charset;" );

        // ---- wp_sda_xero_failures ------------------------------------------
        dbDelta( "CREATE TABLE {$wpdb->prefix}sda_xero_failures (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sdr_id          BIGINT UNSIGNED NOT NULL COMMENT 'SDR ledger row ID',
            sda_id          BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Source SDA ledger row ID',
            sdr_ratio       DECIMAL(5,2)    NOT NULL DEFAULT 0 COMMENT 'Conversion ratio at time of failure',
            error_message   TEXT            NOT NULL COMMENT 'Error returned by Xero or WP_Error message',
            attempt_count   TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Total attempts made so far',
            attempted_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Time of most recent attempt',
            resolved        TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 = successfully retried, no longer pending',
            PRIMARY KEY (id),
            UNIQUE KEY uq_sdr_id (sdr_id),
            KEY idx_resolved (resolved),
            KEY idx_attempted_at (attempted_at)
        ) $charset;" );

        update_option( 'sda_db_version', SDA_VERSION );
    }

    // ---------------------------------------------------------------- deactivate

    public static function deactivate(): void {
        // Tables are preserved on deactivation – only removed on explicit uninstall.
    }

    // --------------------------------------------------------------- uninstall
    // Called from uninstall.php (not registered here to avoid loading full plugin on uninstall)
    public static function uninstall(): void {
        global $wpdb;
        $tables = array( 'sda_xero_failures', 'sda_contracts', 'sda_verifiers', 'sda_ledger', 'sda_projects' );
        foreach ( $tables as $t ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$t}" ); // phpcs:ignore
        }
        delete_option( 'sda_db_version' );
        delete_option( 'sda_settings' );
        wp_clear_scheduled_hook( 'sda_xero_retry_failures' );
    }
}
