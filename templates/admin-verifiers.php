<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap sda-admin-wrap">
    <h1 class="sda-page-title">🔐 SDR Verifiers (VIDs)</h1>

    <?php if ( ! empty( $_GET['sda_notice'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( urldecode( $_GET['sda_notice'] ) ); ?></p></div>
    <?php endif; ?>

    <div class="sda-panel">
        <h2>➕ Add Verifier</h2>
        <p class="description">
            A SDR Verifier (VID) is an organisation or wallet address authorised to sign smart contracts that convert SDA tokens into SDR tokens.
            Food processors, government bodies, and certified sustainability auditors can act as VIDs.
        </p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'sda_add_verifier' ); ?>
            <input type="hidden" name="action" value="sda_add_verifier">
            <div class="sda-form-grid">
                <div class="sda-form-field">
                    <label>Verifier ID (VID) <span class="required">*</span></label>
                    <input type="text" name="vid" required class="regular-text" placeholder="0x… wallet address or DID">
                    <p class="description">The unique on-chain identifier for this verifier.</p>
                </div>
                <div class="sda-form-field">
                    <label>Display Name <span class="required">*</span></label>
                    <input type="text" name="display_name" required class="regular-text" placeholder="e.g. Pacific Shellfish Processors Ltd">
                </div>
                <div class="sda-form-field">
                    <label>Organisation</label>
                    <input type="text" name="org_name" class="regular-text" placeholder="e.g. AngelSharks Verification Authority">
                </div>
                <div class="sda-form-field sda-form-full">
                    <label>SDG Scope (goals this verifier is authorised for)</label>
                    <div class="sda-sdg-check-wrap">
                        <?php foreach ( SDA_SDGs::all() as $n => $sdg ) : ?>
                            <label class="sda-sdg-inline" style="border-left:3px solid <?php echo esc_attr( $sdg['color'] ); ?>">
                                <input type="checkbox" name="sdg_scope[]" value="<?php echo $n; ?>">
                                <?php echo esc_html( $sdg['icon'] ); ?> SDG <?php echo $n; ?>: <?php echo esc_html( $sdg['name'] ); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <p><button type="submit" class="button button-primary">Add Verifier</button></p>
        </form>
    </div>

    <div class="sda-panel">
        <h2>Registered Verifiers</h2>
        <?php if ( empty( $verifiers ) ) : ?>
            <p>No verifiers registered yet. Add the first SDR Verifier above.</p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped sda-table">
                <thead>
                    <tr>
                        <th>Display Name</th>
                        <th>Organisation</th>
                        <th>VID Address</th>
                        <th>SDG Scope</th>
                        <th>Status</th>
                        <th>Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $verifiers as $v ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $v->display_name ); ?></strong></td>
                            <td><?php echo esc_html( $v->org_name ?: '—' ); ?></td>
                            <td>
                                <code class="sda-address" title="<?php echo esc_attr( $v->vid ); ?>">
                                    <?php echo esc_html( substr( $v->vid, 0, 20 ) . ( strlen( $v->vid ) > 20 ? '…' : '' ) ); ?>
                                </code>
                            </td>
                            <td><?php echo SDA_SDGs::badges( $v->sdg_scope ); ?></td>
                            <td>
                                <?php if ( $v->active ) : ?>
                                    <span class="sda-approved">✅ Active</span>
                                <?php else : ?>
                                    <span class="sda-pending">⏸ Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( date( 'Y-m-d', strtotime( $v->created_at ) ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
