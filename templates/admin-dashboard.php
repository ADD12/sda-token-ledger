<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap sda-admin-wrap">
    <h1 class="sda-page-title">🌱 SDA Token Ledger — Dashboard <span class="sda-version-badge">v<?php echo esc_html( SDA_VERSION ); ?></span></h1>

    <?php if ( ! empty( $_GET['sda_notice'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( urldecode( $_GET['sda_notice'] ) ); ?></p></div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="sda-kpi-row">
        <div class="sda-kpi-card" style="border-top-color:#0A97D9">
            <div class="sda-kpi-icon">🌿</div>
            <div class="sda-kpi-value"><?php echo esc_html( number_format( (float) $total_sda, 0 ) ); ?></div>
            <div class="sda-kpi-label">Total SDA Issued</div>
        </div>
        <div class="sda-kpi-card" style="border-top-color:#56C02B">
            <div class="sda-kpi-icon">⭐</div>
            <div class="sda-kpi-value"><?php echo esc_html( number_format( (float) $total_sdr, 0 ) ); ?></div>
            <div class="sda-kpi-label">Total SDR Awarded</div>
        </div>
        <div class="sda-kpi-card" style="border-top-color:#3F7E44">
            <div class="sda-kpi-icon">📁</div>
            <div class="sda-kpi-value"><?php echo esc_html( number_format( (int) $projects, 0 ) ); ?></div>
            <div class="sda-kpi-label">Registered Projects</div>
        </div>
        <div class="sda-kpi-card" style="border-top-color:#DDA63A">
            <div class="sda-kpi-icon">📜</div>
            <div class="sda-kpi-value"><?php echo esc_html( number_format( (int) $verified, 0 ) ); ?></div>
            <div class="sda-kpi-label">Verified Contracts</div>
        </div>
        <div class="sda-kpi-card" style="border-top-color:#9B59B6">
            <div class="sda-kpi-icon">🎁</div>
            <div class="sda-kpi-value"><?php echo esc_html( number_format( (int) $genesis_count, 0 ) ); ?></div>
            <div class="sda-kpi-label"><a href="<?php echo esc_url( admin_url( 'admin.php?page=sda-genesis' ) ); ?>" style="color:inherit;text-decoration:none">Genesis Members</a></div>
        </div>
    </div>

    <!-- Quick Issue SDA Form -->
    <div class="sda-panel">
        <h2>⚡ Quick Issue SDA Tokens</h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'sda_issue_sda' ); ?>
            <input type="hidden" name="action" value="sda_issue_sda">
            <div class="sda-form-grid">
                <div class="sda-form-field">
                    <label for="qi-user">WordPress User</label>
                    <select name="user_id" id="qi-user" required>
                        <option value="">— select —</option>
                        <?php foreach ( get_users() as $u ) : ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>"><?php echo esc_html( $u->display_name ); ?> (#<?php echo $u->ID; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sda-form-field">
                    <label for="qi-pid">Project ID (PID)</label>
                    <select name="pid" id="qi-pid" required>
                        <option value="">— select —</option>
                        <?php
                        global $wpdb;
                        $all_projs = $wpdb->get_results( "SELECT pid, project_name, bid FROM {$wpdb->prefix}sda_projects ORDER BY project_name" );
                        foreach ( $all_projs as $p ) :
                        ?>
                            <option value="<?php echo esc_attr( $p->pid ); ?>" data-bid="<?php echo esc_attr( $p->bid ); ?>">
                                <?php echo esc_html( $p->project_name ); ?> (<?php echo esc_html( $p->pid ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sda-form-field">
                    <label for="qi-bid">B-lan / BID (auto-fills from project)</label>
                    <input type="text" name="bid" id="qi-bid" class="regular-text" placeholder="0x… or sidechain address" required>
                </div>
                <div class="sda-form-field">
                    <label for="qi-amount">Amount (SDA)</label>
                    <input type="text" name="amount" id="qi-amount" class="regular-text" placeholder="e.g. 7500000000" required>
                </div>
                <div class="sda-form-field">
                    <label for="qi-sdg">SDG Goal (1–17)</label>
                    <select name="sdg_goal" id="qi-sdg">
                        <option value="">— none —</option>
                        <?php foreach ( SDA_SDGs::all() as $n => $sdg ) : ?>
                            <option value="<?php echo $n; ?>"><?php echo esc_html( "SDG $n: {$sdg['name']}" ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sda-form-field">
                    <label for="qi-tx">Sidechain TX Hash</label>
                    <input type="text" name="tx_hash_side" id="qi-tx" class="regular-text" placeholder="0x…">
                </div>
                <div class="sda-form-field sda-form-full">
                    <label for="qi-notes">Notes</label>
                    <textarea name="notes" id="qi-notes" rows="2" class="large-text"></textarea>
                </div>
            </div>
            <p><button type="submit" class="button button-primary">Issue SDA Tokens</button></p>
        </form>
    </div>

    <!-- Recent Activity -->
    <div class="sda-panel">
        <h2>📋 Recent Token Activity</h2>
        <?php if ( empty( $recent ) ) : ?>
            <p>No activity yet.</p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped sda-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>PID</th>
                        <th>BID (truncated)</th>
                        <th>SDG</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( date( 'Y-m-d H:i', strtotime( $row->created_at ) ) ); ?></td>
                            <td><?php echo esc_html( $row->display_name ?? "User #{$row->user_id}" ); ?></td>
                            <td><span class="sda-type-badge sda-type-<?php echo esc_attr( strtolower( $row->token_type ) ); ?>"><?php echo esc_html( $row->token_type ); ?></span></td>
                            <td><strong><?php echo esc_html( number_format( (float) $row->amount, 2 ) ); ?></strong></td>
                            <td><code><?php echo esc_html( $row->pid ); ?></code></td>
                            <td><code><?php echo esc_html( substr( $row->bid, 0, 12 ) . '…' ); ?></code></td>
                            <td><?php echo $row->sdg_goal ? SDA_SDGs::badge( (int) $row->sdg_goal ) : '—'; ?></td>
                            <td><span class="sda-status-pill sda-status-pill-<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( ucfirst( $row->status ) ); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=sda-ledger' ) ); ?>" class="button">View Full Ledger →</a></p>
    </div>

    <!-- AngelSharks Pioneer -->
    <div class="sda-panel sda-pioneer-panel">
        <h2>🦈 Pioneer Project: AngelSharks.net</h2>
        <p><strong>First 101DAO Approved Project</strong> — 7.5 billion SDA tokens tracking <em>Proof of Production of Shellfish</em> to remove carbon from our oceans via natural biomineralisation.</p>
        <table class="sda-info-table">
            <tr><th>Token Supply</th><td>7,500,000,000 SDA</td></tr>
            <tr><th>SDG Alignment</th><td><?php echo SDA_SDGs::badge( 14 ); ?> <?php echo SDA_SDGs::badge( 13 ); ?> <?php echo SDA_SDGs::badge( 2 ); ?></td></tr>
            <tr><th>Conversion</th><td>SDA → SDR (up to 10 SDR per SDA) upon food-processor verification</td></tr>
            <tr><th>Chain Model</th><td>Sidechain (offline) → Main-chain (verified smart contract)</td></tr>
        </table>
    </div>
</div>
