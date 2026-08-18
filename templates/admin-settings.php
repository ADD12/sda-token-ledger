<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap sda-admin-wrap">
    <h1 class="sda-page-title">⚙️ SDG Settings &amp; Configuration</h1>

    <?php if ( ! empty( $_GET['sda_notice'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html( urldecode( sanitize_text_field( $_GET['sda_notice'] ) ) ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php
        settings_fields( 'sda_settings_group' );
        do_settings_sections( 'sda-settings' );
        submit_button( 'Save Settings' );
        ?>
    </form>

    <!-- Xero: Failed Syncs -->
    <?php
    $xero_failure_count = SDA_Xero::count_pending_failures();
    if ( $xero_failure_count > 0 ) :
        $xero_failures = SDA_Xero::get_pending_failures();
    ?>
    <div class="sda-panel" style="margin-top:24px;border-left:4px solid #dc3232">
        <h2>⚠️ Failed Xero Syncs
            <span style="display:inline-block;background:#dc3232;color:#fff;border-radius:10px;padding:2px 10px;font-size:13px;font-weight:700;margin-left:8px;"><?php echo esc_html( $xero_failure_count ); ?></span>
        </h2>
        <p><?php esc_html_e( 'The following SDA → SDR conversions could not be posted to Xero. A cron job retries them automatically every hour (max 3 attempts). You can also retry all now.', 'sda-token-ledger' ); ?></p>

        <table class="wp-list-table widefat striped" style="margin-bottom:12px">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'SDR #', 'sda-token-ledger' ); ?></th>
                    <th><?php esc_html_e( 'SDA #', 'sda-token-ledger' ); ?></th>
                    <th><?php esc_html_e( 'Ratio', 'sda-token-ledger' ); ?></th>
                    <th><?php esc_html_e( 'Attempts', 'sda-token-ledger' ); ?></th>
                    <th><?php esc_html_e( 'Last Attempted', 'sda-token-ledger' ); ?></th>
                    <th><?php esc_html_e( 'Error', 'sda-token-ledger' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $xero_failures as $fail ) : ?>
                <tr>
                    <td><?php echo esc_html( $fail->sdr_id ); ?></td>
                    <td><?php echo esc_html( $fail->sda_id ); ?></td>
                    <td><?php echo esc_html( number_format( (float) $fail->sdr_ratio, 2 ) ); ?></td>
                    <td>
                        <?php echo esc_html( $fail->attempt_count ); ?> / <?php echo esc_html( SDA_Xero::MAX_RETRY_ATTEMPTS ); ?>
                        <?php if ( (int) $fail->attempt_count >= SDA_Xero::MAX_RETRY_ATTEMPTS ) : ?>
                            <span style="color:#dc3232;font-weight:600"> <?php esc_html_e( '(max reached)', 'sda-token-ledger' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $fail->attempted_at ); ?></td>
                    <td style="color:#dc3232;max-width:320px;word-break:break-word"><?php echo esc_html( $fail->error_message ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( SDA_Xero::is_connected() ) : ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="sda_xero_retry_failures">
            <?php wp_nonce_field( 'sda_xero_retry_failures' ); ?>
            <button type="submit" class="button button-primary">
                🔁 <?php esc_html_e( 'Retry All Failed Syncs Now', 'sda-token-ledger' ); ?>
            </button>
        </form>
        <?php else : ?>
        <p class="description"><?php esc_html_e( 'Connect to Xero above to enable one-click retry.', 'sda-token-ledger' ); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Xero: Sync Past Conversions -->
    <?php if ( SDA_Xero::is_connected() ) : ?>
    <div class="sda-panel" style="margin-top:24px;border-left:4px solid #46b450">
        <h2>🔄 Sync Past Conversions to Xero</h2>
        <p>Post all previous SDA → SDR conversions that have not yet been recorded in Xero. Already-synced entries are skipped automatically.</p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="sda_xero_backfill">
            <?php wp_nonce_field( 'sda_xero_backfill' ); ?>
            <button type="submit" class="button button-primary" onclick="return confirm('This will post all unsynced SDR conversions to Xero. Continue?')">
                📤 Sync Past Conversions
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- REST API Info -->
    <div class="sda-panel" style="margin-top:30px">
        <h2>🔌 REST API Reference</h2>
        <p>Use the <code>X-SDA-API-Key</code> header with the key configured above. Base URL:</p>
        <code><?php echo esc_url( rest_url( 'sda/v1/' ) ); ?></code>

        <table class="sda-info-table" style="margin-top:12px">
            <thead><tr><th>Method</th><th>Endpoint</th><th>Purpose</th></tr></thead>
            <tbody>
                <tr><td>POST</td><td><code>/sda/v1/tokens/issue</code></td><td>Issue SDA tokens (sidechain)</td></tr>
                <tr><td>POST</td><td><code>/sda/v1/tokens/verify</code></td><td>Verify SDA → mint SDR</td></tr>
                <tr><td>GET</td><td><code>/sda/v1/ledger/{user_id}</code></td><td>Fetch user token ledger</td></tr>
                <tr><td>GET</td><td><code>/sda/v1/projects</code></td><td>List DAO-approved projects</td></tr>
                <tr><td>GET</td><td><code>/sda/v1/projects/{pid}</code></td><td>Single project detail</td></tr>
                <tr><td>POST</td><td><code>/sda/v1/contracts</code></td><td>Register a smart contract</td></tr>
                <tr><td>GET</td><td><code>/sda/v1/verifiers</code></td><td>List active VIDs</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Shortcode Reference -->
    <div class="sda-panel">
        <h2>📋 Shortcodes</h2>
        <table class="sda-info-table">
            <thead><tr><th>Shortcode</th><th>Description</th></tr></thead>
            <tbody>
                <tr>
                    <td><code>[sda_ledger]</code></td>
                    <td>Full token ledger for the logged-in user. Optional: <code>type="SDA"</code>, <code>type="SDR"</code>, <code>pid="PID"</code>, <code>limit="50"</code></td>
                </tr>
                <tr>
                    <td><code>[sda_totals]</code></td>
                    <td>Quick summary card showing SDA and SDR balances for the logged-in user. Shows coin-proposal eligibility if they hold ≥1M SDA.</td>
                </tr>
                <tr>
                    <td><code>[sda_projects]</code></td>
                    <td>Table of all DAO-approved projects with BID, SDG goals, and token totals.</td>
                </tr>
                <tr>
                    <td><code>[sda_sdg_goals]</code></td>
                    <td>Grid display of all 17 UN SDGs, highlighting which are active / SDR-eligible in this deployment.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Glossary -->
    <div class="sda-panel">
        <h2>📖 Glossary</h2>
        <table class="sda-info-table">
            <tbody>
                <tr><th>SDA</th><td>Sustainable Development Award — issued on the sidechain (offline), awarded for tracking proof of production against UN SDGs.</td></tr>
                <tr><th>SDR</th><td>Sustainable Development Reward — minted on the main chain after proof of production is verified and a smart contract is signed by a VID. Up to 10 SDR are issued per SDA.</td></tr>
                <tr><th>PID</th><td>Project ID — unique identifier for a sustainability project (e.g. shellfish farming operation).</td></tr>
                <tr><th>BID / B-lan</th><td>Blockchain ID — the sidechain or main-chain address / B-lan identifier where the project's tokens operate.</td></tr>
                <tr><th>VID</th><td>Verifier ID — a wallet address or DID belonging to an authorised SDR Verifier (e.g. a food processor or sustainability auditor).</td></tr>
                <tr><th>101DAO</th><td>The governing DAO that approves token holders who wish to propose their own coin (requires ≥1M SDA). First approval: AngelSharks.net.</td></tr>
                <tr><th>SDG</th><td>UN Sustainable Development Goal — all 17 goals are eligible for SDA / SDR rewards.</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Xero Integration Notes -->
    <div class="sda-panel sda-panel-info">
        <h2>📊 Xero Integration — Setup Guide</h2>
        <ol>
            <li>Create a Xero App at <a href="https://developer.xero.com/app/manage" target="_blank">developer.xero.com/app/manage</a> (OAuth 2.0, Web App type).</li>
            <li>Add this exact Redirect URI to your Xero app: <code><?php echo esc_html( SDA_Xero::get_redirect_uri() ); ?></code></li>
            <li>Copy the <strong>Client ID</strong> and <strong>Client Secret</strong> into the Xero section above and click <em>Save Settings</em>.</li>
            <li>Enter your <strong>Xero Tenant / Org ID</strong> (found in your Xero organisation settings or API connections page).</li>
            <li>Set the <strong>Income Account Code</strong> to the Xero account where SDR earnings should be posted (e.g. <code>200</code> for Sales).</li>
            <li>Click <strong>Connect to Xero</strong> to complete OAuth authorisation.</li>
            <li>Each SDA → SDR conversion will now be automatically posted as an Accounts Receivable invoice in Xero, tagged with the project name and SDG category.</li>
        </ol>
    </div>
</div>
