<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap sda-admin-wrap">
    <h1 class="sda-page-title">📜 Smart Contracts</h1>

    <?php if ( ! empty( $_GET['sda_notice'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( urldecode( $_GET['sda_notice'] ) ); ?></p></div>
    <?php endif; ?>

    <div class="sda-panel">
        <h2>➕ Register Smart Contract</h2>
        <p class="description">Register a main-chain smart contract that will be signed by a SDR Verifier (VID) to convert SDA → SDR tokens.</p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'sda_create_contract' ); ?>
            <input type="hidden" name="action" value="sda_create_contract">
            <div class="sda-form-grid">
                <div class="sda-form-field">
                    <label>Contract Address (Main-chain) <span class="required">*</span></label>
                    <input type="text" name="contract_address" required class="regular-text" placeholder="0x…">
                </div>
                <div class="sda-form-field">
                    <label>Project (PID) <span class="required">*</span></label>
                    <select name="pid" required>
                        <option value="">— select —</option>
                        <?php foreach ( $projects as $p ) : ?>
                            <option value="<?php echo esc_attr( $p->pid ); ?>"><?php echo esc_html( $p->project_name ); ?> (<?php echo esc_html( $p->pid ); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sda-form-field">
                    <label>Verifier ID (VID) <span class="required">*</span></label>
                    <input type="text" name="vid" required class="regular-text" placeholder="Verifier wallet address or DID">
                </div>
                <div class="sda-form-field">
                    <label>Total SDA Covered <span class="required">*</span></label>
                    <input type="text" name="sda_total" required class="regular-text" placeholder="e.g. 7500000000">
                </div>
                <div class="sda-form-field">
                    <label>Proof Reference (IPFS CID / URL)</label>
                    <input type="text" name="proof_ref" class="regular-text" placeholder="ipfs://Qm… or https://…">
                </div>
                <div class="sda-form-field sda-form-full">
                    <label>Notes</label>
                    <textarea name="notes" rows="2" class="large-text"></textarea>
                </div>
            </div>
            <p><button type="submit" class="button button-primary">Register Contract</button></p>
        </form>
    </div>

    <div class="sda-panel">
        <h2>All Smart Contracts</h2>
        <?php if ( empty( $rows ) ) : ?>
            <p>No contracts registered yet.</p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped sda-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Contract Address</th>
                        <th>Project</th>
                        <th>VID (Verifier)</th>
                        <th>SDA Covered</th>
                        <th>SDR Issued</th>
                        <th>Status</th>
                        <th>Proof</th>
                        <th>Signed At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $c ) : ?>
                        <tr>
                            <td><?php echo esc_html( $c->id ); ?></td>
                            <td><code class="sda-address" title="<?php echo esc_attr( $c->contract_address ); ?>"><?php echo esc_html( substr( $c->contract_address, 0, 14 ) . '…' ); ?></code></td>
                            <td>
                                <strong><?php echo esc_html( $c->project_name ?? '' ); ?></strong><br>
                                <code><?php echo esc_html( $c->pid ); ?></code>
                            </td>
                            <td><code class="sda-address" title="<?php echo esc_attr( $c->vid ); ?>"><?php echo esc_html( substr( $c->vid, 0, 12 ) . '…' ); ?></code></td>
                            <td><?php echo esc_html( number_format( (float) $c->sda_total, 0 ) ); ?></td>
                            <td><strong><?php echo esc_html( number_format( (float) $c->sdr_issued, 4 ) ); ?></strong></td>
                            <td>
                                <span class="sda-status-pill sda-status-pill-<?php echo esc_attr( $c->status ); ?>">
                                    <?php echo esc_html( ucfirst( $c->status ) ); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ( $c->proof_ref ) : ?>
                                    <a href="<?php echo esc_url( $c->proof_ref ); ?>" target="_blank" rel="noopener">View Proof ↗</a>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo $c->signed_at ? esc_html( date( 'Y-m-d', strtotime( $c->signed_at ) ) ) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Verify & Convert Form -->
    <div class="sda-panel">
        <h2>🔐 Verify SDA → Mint SDR (Manual)</h2>
        <p class="description">After a smart contract is signed on the main chain, use this form to record the conversion and mint SDR tokens into the ledger.</p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'sda_issue_sda' ); ?>
            <input type="hidden" name="action" value="sda_verify_convert">
            <div class="sda-form-grid">
                <div class="sda-form-field">
                    <label>SDA Ledger ID <span class="required">*</span></label>
                    <input type="number" name="sda_ledger_id" required class="small-text" placeholder="Ledger row ID">
                </div>
                <div class="sda-form-field">
                    <label>Contract ID <span class="required">*</span></label>
                    <select name="contract_id" required>
                        <option value="">— select contract —</option>
                        <?php foreach ( $rows as $c ) : ?>
                            <option value="<?php echo esc_attr( $c->id ); ?>">#<?php echo esc_html( $c->id ); ?> — <?php echo esc_html( $c->project_name ?? $c->pid ); ?> (<?php echo esc_html( substr( $c->contract_address, 0, 10 ) ); ?>…)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sda-form-field">
                    <label>VID (Verifier Address) <span class="required">*</span></label>
                    <input type="text" name="vid" required class="regular-text" placeholder="0x…">
                </div>
                <div class="sda-form-field">
                    <label>SDR Ratio (SDR per 1 SDA, max 10) <span class="required">*</span></label>
                    <input type="number" name="sdr_ratio" required class="small-text" min="0.01" max="10" step="0.01" placeholder="e.g. 10">
                </div>
                <div class="sda-form-field">
                    <label>Main-chain TX Hash <span class="required">*</span></label>
                    <input type="text" name="tx_hash_main" required class="regular-text" placeholder="0x…">
                </div>
                <div class="sda-form-field sda-form-full">
                    <label>Proof Note</label>
                    <input type="text" name="proof_note" class="large-text" placeholder="IPFS CID or verification notes">
                </div>
            </div>
            <p><button type="submit" class="button button-primary">✅ Convert to SDR</button></p>
        </form>
    </div>
</div>
