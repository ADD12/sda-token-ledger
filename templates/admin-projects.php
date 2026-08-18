<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap sda-admin-wrap">
    <h1 class="sda-page-title">📁 Projects (PIDs / BIDs)</h1>

    <?php if ( ! empty( $notice ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
    <?php endif; ?>

    <!-- Register Project -->
    <div class="sda-panel">
        <h2>➕ Register New Project</h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'sda_register_project' ); ?>
            <input type="hidden" name="action" value="sda_register_project">
            <div class="sda-form-grid">
                <div class="sda-form-field">
                    <label>Project ID (PID) <span class="required">*</span></label>
                    <input type="text" name="pid" required class="regular-text" placeholder="e.g. ANGELSHARKS-001">
                    <p class="description">Unique identifier for this project.</p>
                </div>
                <div class="sda-form-field">
                    <label>B-lan / BID (Blockchain ID) <span class="required">*</span></label>
                    <input type="text" name="bid" required class="regular-text" placeholder="0x… sidechain address or chain ID">
                    <p class="description">The sidechain address or B-lan identifier where SDA tokens operate.</p>
                </div>
                <div class="sda-form-field">
                    <label>Project Name <span class="required">*</span></label>
                    <input type="text" name="project_name" required class="regular-text" placeholder="e.g. AngelSharks Shellfish Carbon Removal">
                </div>
                <div class="sda-form-field">
                    <label>Chain Type</label>
                    <select name="chain_type">
                        <option value="sidechain">Sidechain (offline, awaiting main-chain verification)</option>
                        <option value="mainchain">Main-chain (live)</option>
                    </select>
                </div>
                <div class="sda-form-field">
                    <label>Owner (WordPress User) <span class="required">*</span></label>
                    <select name="owner_user_id" required>
                        <option value="">— select —</option>
                        <?php foreach ( get_users() as $u ) : ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>"><?php echo esc_html( $u->display_name ); ?> (#<?php echo $u->ID; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sda-form-field">
                    <label>SDG Goals (check all that apply)</label>
                    <div class="sda-sdg-check-wrap">
                        <?php foreach ( $sdgs as $n => $sdg ) : ?>
                            <label class="sda-sdg-inline" style="border-left:3px solid <?php echo esc_attr( $sdg['color'] ); ?>">
                                <input type="checkbox" name="sdg_goals[]" value="<?php echo $n; ?>">
                                <?php echo esc_html( $sdg['icon'] ); ?> SDG <?php echo $n; ?>: <?php echo esc_html( $sdg['name'] ); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="sda-form-field sda-form-full">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" class="large-text"></textarea>
                </div>
            </div>
            <p><button type="submit" class="button button-primary">Register Project</button></p>
        </form>
    </div>

    <!-- Projects Table -->
    <div class="sda-panel">
        <h2>All Projects</h2>
        <?php if ( empty( $projects ) ) : ?>
            <p>No projects registered yet.</p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped sda-table">
                <thead>
                    <tr>
                        <th>Project Name</th>
                        <th>PID</th>
                        <th>B-lan / BID</th>
                        <th>Chain</th>
                        <th>SDG Goals</th>
                        <th>DAO Approved</th>
                        <th>Total SDA</th>
                        <th>Total SDR</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $projects as $p ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $p->project_name ); ?></strong></td>
                            <td><code><?php echo esc_html( $p->pid ); ?></code></td>
                            <td>
                                <code class="sda-address" title="<?php echo esc_attr( $p->bid ); ?>">
                                    <?php echo esc_html( substr( $p->bid, 0, 16 ) . ( strlen( $p->bid ) > 16 ? '…' : '' ) ); ?>
                                </code>
                            </td>
                            <td><span class="sda-chain-badge <?php echo esc_attr( $p->chain_type ); ?>"><?php echo esc_html( $p->chain_type ); ?></span></td>
                            <td><?php echo SDA_SDGs::badges( $p->sdg_goals ); ?></td>
                            <td>
                                <?php if ( $p->dao_approved ) : ?>
                                    <span class="sda-approved">✅ Approved</span>
                                    <?php if ( $p->approved_at ) : ?>
                                        <br><small><?php echo esc_html( date( 'Y-m-d', strtotime( $p->approved_at ) ) ); ?></small>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="sda-pending">⏳ Pending</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( number_format( (float) $p->total_sda, 0 ) ); ?></td>
                            <td><?php echo esc_html( number_format( (float) $p->total_sdr, 0 ) ); ?></td>
                            <td>
                                <?php if ( ! $p->dao_approved ) : ?>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                                        <?php wp_nonce_field( 'sda_approve_project' ); ?>
                                        <input type="hidden" name="action" value="sda_approve_project">
                                        <input type="hidden" name="pid" value="<?php echo esc_attr( $p->pid ); ?>">
                                        <input type="text" name="proposal_ref" placeholder="DAO TX/Ref" class="small-text">
                                        <button type="submit" class="button button-small">✅ DAO Approve</button>
                                    </form>
                                <?php else : ?>
                                    <span class="sda-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
