<?php
/**
 * Frontend shortcodes.
 *
 * [sda_ledger]                 – Current user's token ledger table
 * [sda_totals]                 – Quick summary card (SDA / SDR balances)
 * [sda_projects]               – Table of approved projects
 * [sda_sdg_goals]              – All 17 SDG goal cards
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SDA_Shortcodes {

    public static function init(): void {
        add_shortcode( 'sda_ledger',    array( __CLASS__, 'sc_ledger' ) );
        add_shortcode( 'sda_totals',    array( __CLASS__, 'sc_totals' ) );
        add_shortcode( 'sda_projects',  array( __CLASS__, 'sc_projects' ) );
        add_shortcode( 'sda_sdg_goals', array( __CLASS__, 'sc_sdg_goals' ) );
    }

    // ---------------------------------------------------------------- [sda_ledger]

    public static function sc_ledger( array $atts ): string {
        if ( ! is_user_logged_in() ) {
            return '<p class="sda-notice">' . esc_html__( 'Please log in to view your token ledger.', 'sda-token-ledger' ) . '</p>';
        }

        $atts = shortcode_atts( array(
            'type'   => '',   // SDA | SDR | (blank = all)
            'pid'    => '',
            'limit'  => 50,
        ), $atts, 'sda_ledger' );

        $user_id = get_current_user_id();
        $filters = array_filter( array(
            'token_type' => strtoupper( $atts['type'] ),
            'pid'        => sanitize_text_field( $atts['pid'] ),
        ) );

        $rows    = SDA_Token::get_user_ledger( $user_id, $filters );
        $totals  = SDA_Token::get_user_totals( $user_id );
        $settings = get_option( 'sda_settings', array() );
        $sda_sym = $settings['token_symbol'] ?? 'SDA';
        $sdr_sym = $settings['sdr_symbol']   ?? 'SDR';

        ob_start();
        ?>
        <div class="sda-ledger-wrap">
            <div class="sda-summary-row">
                <div class="sda-summary-card sda-card-blue">
                    <span class="sda-card-label">🌿 <?php echo esc_html( $sda_sym ); ?> (Sidechain)</span>
                    <span class="sda-card-value"><?php echo esc_html( number_format( (float) $totals['sda_sidechain'], 2 ) ); ?></span>
                </div>
                <div class="sda-summary-card sda-card-teal">
                    <span class="sda-card-label">🔄 <?php echo esc_html( $sda_sym ); ?> (Converted)</span>
                    <span class="sda-card-value"><?php echo esc_html( number_format( (float) $totals['sda_converted'], 2 ) ); ?></span>
                </div>
                <div class="sda-summary-card sda-card-green">
                    <span class="sda-card-label">⭐ <?php echo esc_html( $sdr_sym ); ?> (Rewards)</span>
                    <span class="sda-card-value"><?php echo esc_html( number_format( (float) $totals['sdr_verified'], 2 ) ); ?></span>
                </div>
            </div>

            <?php if ( empty( $rows ) ) : ?>
                <p class="sda-notice"><?php esc_html_e( 'No token records found.', 'sda-token-ledger' ); ?></p>
            <?php else : ?>
                <div class="sda-table-wrap">
                    <table class="sda-ledger-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Date', 'sda-token-ledger' ); ?></th>
                                <th><?php esc_html_e( 'Type', 'sda-token-ledger' ); ?></th>
                                <th><?php esc_html_e( 'Amount', 'sda-token-ledger' ); ?></th>
                                <th><?php esc_html_e( 'Project (PID)', 'sda-token-ledger' ); ?></th>
                                <th><?php esc_html_e( 'B-lan / BID', 'sda-token-ledger' ); ?></th>
                                <th><?php esc_html_e( 'SDG', 'sda-token-ledger' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'sda-token-ledger' ); ?></th>
                                <th><?php esc_html_e( 'Verifier (VID)', 'sda-token-ledger' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( array_slice( $rows, 0, (int) $atts['limit'] ) as $row ) : ?>
                                <tr class="sda-status-<?php echo esc_attr( $row->status ); ?>">
                                    <td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $row->created_at ) ) ); ?></td>
                                    <td>
                                        <span class="sda-type-badge sda-type-<?php echo esc_attr( strtolower( $row->token_type ) ); ?>">
                                            <?php echo esc_html( $row->token_type ); ?>
                                        </span>
                                    </td>
                                    <td class="sda-amount"><?php echo esc_html( number_format( (float) $row->amount, 2 ) ); ?></td>
                                    <td>
                                        <span class="sda-pid"><?php echo esc_html( $row->pid ); ?></span>
                                        <?php if ( $row->project_name ) : ?>
                                            <br><small><?php echo esc_html( $row->project_name ); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="sda-bid">
                                        <?php if ( $row->bid ) : ?>
                                            <code class="sda-address"><?php echo esc_html( self::truncate_hash( $row->bid ) ); ?></code>
                                            <span class="sda-chain-badge"><?php echo esc_html( $row->chain_type ?? 'sidechain' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $row->sdg_goal ? SDA_SDGs::badge( (int) $row->sdg_goal ) : '—'; // phpcs:ignore ?>
                                    </td>
                                    <td>
                                        <span class="sda-status-pill sda-status-pill-<?php echo esc_attr( $row->status ); ?>">
                                            <?php echo esc_html( ucfirst( $row->status ) ); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ( $row->vid ) : ?>
                                            <code class="sda-address"><?php echo esc_html( self::truncate_hash( $row->vid ) ); ?></code>
                                        <?php else : ?>
                                            <span class="sda-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // ---------------------------------------------------------------- [sda_totals]

    public static function sc_totals( array $atts ): string {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        $user_id  = get_current_user_id();
        $totals   = SDA_Token::get_user_totals( $user_id );
        $settings = get_option( 'sda_settings', array() );
        $sda_sym  = $settings['token_symbol'] ?? 'SDA';
        $sdr_sym  = $settings['sdr_symbol']   ?? 'SDR';
        $can_coin = SDA_Token::can_propose_coin( $user_id );

        ob_start();
        ?>
        <div class="sda-totals-widget">
            <h3><?php esc_html_e( 'My Token Summary', 'sda-token-ledger' ); ?></h3>
            <table class="sda-totals-table">
                <tr>
                    <td>🌿 <?php echo esc_html( $sda_sym ); ?> on Sidechain</td>
                    <td><strong><?php echo esc_html( number_format( (float) $totals['sda_sidechain'], 4 ) ); ?></strong></td>
                </tr>
                <tr>
                    <td>✅ <?php echo esc_html( $sda_sym ); ?> Converted to <?php echo esc_html( $sdr_sym ); ?></td>
                    <td><strong><?php echo esc_html( number_format( (float) $totals['sda_converted'], 4 ) ); ?></strong></td>
                </tr>
                <tr class="sda-highlight">
                    <td>⭐ <?php echo esc_html( $sdr_sym ); ?> Rewards Earned</td>
                    <td><strong><?php echo esc_html( number_format( (float) $totals['sdr_verified'], 4 ) ); ?></strong></td>
                </tr>
            </table>
            <?php if ( $can_coin ) : ?>
                <p class="sda-coin-eligible">🏅 <?php esc_html_e( 'You hold 1M+ SDA — you are eligible to propose your own coin via the 101DAO!', 'sda-token-ledger' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // ---------------------------------------------------------------- [sda_projects]

    public static function sc_projects( array $atts ): string {
        $projects = SDA_Token::get_projects( array( 'dao_approved' => 1 ) );
        if ( empty( $projects ) ) {
            return '<p class="sda-notice">' . esc_html__( 'No approved projects found.', 'sda-token-ledger' ) . '</p>';
        }

        ob_start();
        ?>
        <div class="sda-projects-wrap">
            <table class="sda-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Project', 'sda-token-ledger' ); ?></th>
                        <th><?php esc_html_e( 'PID', 'sda-token-ledger' ); ?></th>
                        <th><?php esc_html_e( 'B-lan / BID', 'sda-token-ledger' ); ?></th>
                        <th><?php esc_html_e( 'SDGs', 'sda-token-ledger' ); ?></th>
                        <th><?php esc_html_e( 'Total SDA', 'sda-token-ledger' ); ?></th>
                        <th><?php esc_html_e( 'Total SDR', 'sda-token-ledger' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $projects as $p ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $p->project_name ); ?></strong></td>
                            <td><code><?php echo esc_html( $p->pid ); ?></code></td>
                            <td><code class="sda-address"><?php echo esc_html( self::truncate_hash( $p->bid ) ); ?></code></td>
                            <td><?php echo SDA_SDGs::badges( $p->sdg_goals ); // phpcs:ignore ?></td>
                            <td><?php echo esc_html( number_format( (float) $p->total_sda, 2 ) ); ?></td>
                            <td><?php echo esc_html( number_format( (float) $p->total_sdr, 2 ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    // ---------------------------------------------------------------- [sda_sdg_goals]

    public static function sc_sdg_goals( array $atts ): string {
        $settings    = get_option( 'sda_settings', array() );
        $active_sdgs = array_map( 'intval', (array) ( $settings['active_sdgs'] ?? array_keys( SDA_SDGs::all() ) ) );
        $sdgs        = SDA_SDGs::all();

        ob_start();
        ?>
        <div class="sda-sdg-grid-public">
            <?php foreach ( $sdgs as $num => $sdg ) :
                $active = in_array( $num, $active_sdgs, true );
            ?>
                <div class="sda-sdg-card <?php echo $active ? 'sda-sdg-active' : 'sda-sdg-inactive'; ?>"
                     style="border-top:4px solid <?php echo esc_attr( $sdg['color'] ); ?>">
                    <div class="sda-sdg-number" style="color:<?php echo esc_attr( $sdg['color'] ); ?>"><?php echo $num; ?></div>
                    <div class="sda-sdg-icon"><?php echo esc_html( $sdg['icon'] ); ?></div>
                    <div class="sda-sdg-name"><?php echo esc_html( $sdg['name'] ); ?></div>
                    <div class="sda-sdg-desc"><?php echo esc_html( $sdg['short'] ); ?></div>
                    <?php if ( $active ) : ?>
                        <div class="sda-sdg-badge-eligible">✅ SDR Eligible</div>
                    <?php else : ?>
                        <div class="sda-sdg-badge-inactive">⏸ Inactive</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // ---------------------------------------------------------------- Helpers

    /** Truncate a long hash/address for display. */
    private static function truncate_hash( string $hash, int $chars = 10 ): string {
        if ( strlen( $hash ) <= $chars * 2 + 3 ) {
            return $hash;
        }
        return substr( $hash, 0, $chars ) . '…' . substr( $hash, -$chars );
    }
}
