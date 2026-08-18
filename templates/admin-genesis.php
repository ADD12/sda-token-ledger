<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap sda-admin-wrap">
    <h1 class="sda-page-title">🎁 Genesis Grants — 101DAO SDA Genesis Server</h1>

    <?php if ( ! empty( $_GET['sda_notice'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html( urldecode( sanitize_text_field( $_GET['sda_notice'] ) ) ); ?></p>
        </div>
    <?php endif; ?>
    <?php if ( ! empty( $_GET['sda_error'] ) ) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html( urldecode( sanitize_text_field( $_GET['sda_error'] ) ) ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( ! SDA_Genesis::code_is_set() ) : ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e( 'Genesis Invite Code not configured.', 'sda-token-ledger' ); ?></strong>
                <?php esc_html_e( 'Set a Genesis Invite Code in', 'sda-token-ledger' ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sda-settings' ) ); ?>"><?php esc_html_e( 'SDG Settings → Genesis Bonus', 'sda-token-ledger' ); ?></a>
                <?php esc_html_e( 'to enable automatic awards on registration.', 'sda-token-ledger' ); ?>
            </p>
        </div>
    <?php else : ?>
        <div class="notice notice-info inline">
            <p>
                🔑 <?php esc_html_e( 'Invite code is set.', 'sda-token-ledger' ); ?>
                &nbsp;💰 <?php printf(
                    esc_html__( 'Bonus amount: %s SDA per new account.', 'sda-token-ledger' ),
                    '<strong>' . esc_html( number_format( SDA_Genesis::get_amount(), 0 ) ) . '</strong>'
                ); ?>
                &nbsp;📋 <?php printf(
                    esc_html__( 'Project ID: %s', 'sda-token-ledger' ),
                    '<code>' . esc_html( SDA_Genesis::get_pid() ) . '</code>'
                ); ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Stats row -->
    <div class="sda-kpi-row" style="margin-top:20px">
        <div class="sda-kpi-card" style="border-top-color:#9B59B6">
            <div class="sda-kpi-icon">🌱</div>
            <div class="sda-kpi-value"><?php echo esc_html( number_format( $genesis_count ) ); ?></div>
            <div class="sda-kpi-label">Genesis Members</div>
        </div>
        <div class="sda-kpi-card" style="border-top-color:#0A97D9">
            <div class="sda-kpi-icon">💰</div>
            <div class="sda-kpi-value"><?php echo esc_html( number_format( $genesis_count * SDA_Genesis::get_amount(), 0 ) ); ?></div>
            <div class="sda-kpi-label">Total SDA Granted</div>
        </div>
        <div class="sda-kpi-card" style="border-top-color:#3F7E44">
            <div class="sda-kpi-icon">⚙️</div>
            <div class="sda-kpi-value"><?php echo esc_html( number_format( SDA_Genesis::get_amount(), 0 ) ); ?></div>
            <div class="sda-kpi-label">SDA per New Member</div>
        </div>
    </div>

    <!-- Manual grant panel -->
    <div class="sda-panel">
        <h2>⚡ Manually Grant Genesis Bonus</h2>
        <p class="description">
            <?php esc_html_e( 'Use this to award the genesis bonus to an existing user who registered before the code was set, or who joined via a direct invite. Each user can only receive the bonus once.', 'sda-token-ledger' ); ?>
        </p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'sda_grant_genesis' ); ?>
            <input type="hidden" name="action" value="sda_grant_genesis">
            <div class="sda-form-grid">
                <div class="sda-form-field">
                    <label for="genesis-user"><?php esc_html_e( 'WordPress User', 'sda-token-ledger' ); ?></label>
                    <select name="user_id" id="genesis-user" required>
                        <option value="">— <?php esc_html_e( 'select user', 'sda-token-ledger' ); ?> —</option>
                        <?php foreach ( get_users( array( 'orderby' => 'display_name' ) ) as $u ) :
                            $already = SDA_Genesis::has_been_awarded( $u->ID );
                        ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>"
                                <?php if ( $already ) echo 'disabled style="color:#999"'; ?>>
                                <?php echo esc_html( $u->display_name ); ?> (#<?php echo $u->ID; ?>)
                                <?php if ( $already ) echo ' — ' . esc_html__( 'already granted', 'sda-token-ledger' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <p>
                <button type="submit" class="button button-primary">
                    🎁 <?php printf(
                        esc_html__( 'Grant %s SDA Genesis Bonus', 'sda-token-ledger' ),
                        esc_html( number_format( SDA_Genesis::get_amount(), 0 ) )
                    ); ?>
                </button>
            </p>
        </form>
    </div>

    <!-- Grant history -->
    <div class="sda-panel">
        <h2>📋 Genesis Grant History</h2>
        <?php if ( empty( $grants ) ) : ?>
            <p><?php esc_html_e( 'No genesis bonuses awarded yet. They appear here as new members join using the invite code.', 'sda-token-ledger' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped sda-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Date Granted', 'sda-token-ledger' ); ?></th>
                        <th><?php esc_html_e( 'User', 'sda-token-ledger' ); ?></th>
                        <th><?php esc_html_e( 'Email', 'sda-token-ledger' ); ?></th>
                        <th><?php esc_html_e( 'Amount', 'sda-token-ledger' ); ?></th>
                        <th><?php esc_html_e( 'Ledger ID', 'sda-token-ledger' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $grants as $g ) : ?>
                        <tr>
                            <td><?php echo esc_html( $g->granted_date ? date( 'Y-m-d H:i', strtotime( $g->granted_date ) ) : '—' ); ?></td>
                            <td>
                                <strong><?php echo esc_html( $g->display_name ); ?></strong>
                                <span style="color:#888"> #<?php echo esc_html( $g->user_id ); ?></span>
                            </td>
                            <td><?php echo esc_html( $g->user_email ); ?></td>
                            <td><strong style="color:#3F7E44"><?php echo esc_html( number_format( SDA_Genesis::get_amount(), 0 ) ); ?> SDA</strong></td>
                            <td>
                                <?php if ( $g->ledger_id ) : ?>
                                    <code>#<?php echo esc_html( $g->ledger_id ); ?></code>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- How it works -->
    <div class="sda-panel" style="background:#f9f9f9">
        <h2>ℹ️ How Genesis Grants Work</h2>
        <ol style="line-height:2">
            <li><?php esc_html_e( 'You set a secret Genesis Invite Code in SDG Settings → Genesis Bonus.', 'sda-token-ledger' ); ?></li>
            <li><?php esc_html_e( 'Share that code privately with members of the 101DAO SDA Genesis server (e.g. pin it in Discord).', 'sda-token-ledger' ); ?></li>
            <li><?php esc_html_e( 'When someone registers on your WordPress site and enters the code, they automatically receive the bonus SDA — no admin action needed.', 'sda-token-ledger' ); ?></li>
            <li><?php esc_html_e( 'Each user can only receive the bonus once. The code field does not appear at all if no code is configured.', 'sda-token-ledger' ); ?></li>
            <li><?php esc_html_e( 'You can also grant the bonus manually above for users who registered before the code was set.', 'sda-token-ledger' ); ?></li>
        </ol>
    </div>
</div>
