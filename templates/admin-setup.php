<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap sda-admin-wrap">
    <h1 class="sda-page-title">🚀 One-Click Page Setup</h1>

    <?php if ( ! empty( $_GET['sda_notice'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( urldecode( $_GET['sda_notice'] ) ); ?></p></div>
    <?php endif; ?>

    <!-- What this does -->
    <div class="sda-panel sda-panel-info">
        <h2>What does this do?</h2>
        <p>Clicking <strong>Run One-Click Setup</strong> automatically creates four published WordPress pages with the correct shortcodes already inserted. Each page will immediately appear in your WordPress sidebar menu (under <em>SDA Tokens</em>) with a <strong>🌐 View: …</strong> link so you can jump straight to the live page.</p>
        <p>You can re-run setup at any time — existing pages are never deleted or overwritten, only missing ones are created.</p>
    </div>

    <!-- Pages that will be created -->
    <div class="sda-panel">
        <h2>Pages that will be created</h2>
        <table class="sda-info-table">
            <thead>
                <tr>
                    <th>Page Title</th>
                    <th>Shortcode(s)</th>
                    <th>Purpose</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $page_map = array(
                    'ledger'   => array( 'title' => 'My Token Ledger',           'shortcodes' => '[sda_totals]  [sda_ledger]', 'purpose' => 'Shows logged-in user\'s full token history and balance summary.' ),
                    'totals'   => array( 'title' => 'My Token Summary',          'shortcodes' => '[sda_totals]',               'purpose' => 'Quick balance card with SDA / SDR totals and coin-eligibility notice.' ),
                    'projects' => array( 'title' => 'SDA Approved Projects',     'shortcodes' => '[sda_projects]',             'purpose' => 'Public table of all 101DAO-approved sustainability projects.' ),
                    'goals'    => array( 'title' => 'Sustainability Goals (SDGs)','shortcodes' => '[sda_sdg_goals]',            'purpose' => 'Grid of all 17 UN SDGs showing which are active for SDR conversion.' ),
                );
                foreach ( $page_map as $key => $def ) :
                    $page_id  = ! empty( $created_pages[ $key ] ) ? (int) $created_pages[ $key ] : 0;
                    $status   = $page_id ? get_post_status( $page_id ) : false;
                    $is_live  = ( 'publish' === $status );
                ?>
                    <tr>
                        <td><strong><?php echo esc_html( $def['title'] ); ?></strong></td>
                        <td><code><?php echo esc_html( $def['shortcodes'] ); ?></code></td>
                        <td><?php echo esc_html( $def['purpose'] ); ?></td>
                        <td>
                            <?php if ( $is_live ) : ?>
                                <span class="sda-approved">✅ Live</span>
                                &nbsp;
                                <a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>" target="_blank" class="button button-small">View ↗</a>
                                &nbsp;
                                <a href="<?php echo esc_url( get_edit_post_link( $page_id ) ); ?>" class="button button-small">Edit</a>
                            <?php elseif ( $page_id ) : ?>
                                <span class="sda-pending">⚠ Exists (<?php echo esc_html( $status ); ?>)</span>
                            <?php else : ?>
                                <span class="sda-muted">⏳ Not created yet</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px">
            <?php wp_nonce_field( 'sda_one_click_setup' ); ?>
            <input type="hidden" name="action" value="sda_one_click_setup">
            <button type="submit" class="button button-primary button-large">
                🚀 Run One-Click Setup
            </button>
            <span style="margin-left:12px;color:#666;font-size:13px">Creates any missing pages — existing pages are untouched.</span>
        </form>
    </div>

    <!-- Live page links (shown after setup) -->
    <?php if ( ! empty( $created_pages ) ) : ?>
    <div class="sda-panel">
        <h2>🌐 Your Live Pages</h2>
        <p>These pages are ready to add to your WordPress navigation menu (<em>Appearance → Menus</em>) or share with users.</p>
        <div class="sda-setup-links-grid">
            <?php foreach ( $page_map as $key => $def ) :
                $page_id = ! empty( $created_pages[ $key ] ) ? (int) $created_pages[ $key ] : 0;
                if ( ! $page_id ) continue;
                $url     = get_permalink( $page_id );
                $edit    = get_edit_post_link( $page_id );
            ?>
                <div class="sda-setup-link-card">
                    <div class="sda-setup-link-title"><?php echo esc_html( $def['title'] ); ?></div>
                    <div class="sda-setup-link-code"><code><?php echo esc_html( $def['shortcodes'] ); ?></code></div>
                    <div class="sda-setup-link-actions">
                        <a href="<?php echo esc_url( $url ); ?>" target="_blank" class="button button-primary button-small">🌐 View Page</a>
                        <a href="<?php echo esc_url( $edit ); ?>" class="button button-small">✏️ Edit</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Shortcode reference -->
    <div class="sda-panel">
        <h2>📋 Add Shortcodes Manually</h2>
        <p>You can also paste these shortcodes into any existing page or post:</p>
        <table class="sda-info-table">
            <thead><tr><th>Shortcode</th><th>Options</th><th>Description</th></tr></thead>
            <tbody>
                <tr>
                    <td><code>[sda_ledger]</code></td>
                    <td><code>type="SDA|SDR"</code> &nbsp;<code>pid="…"</code> &nbsp;<code>limit="50"</code></td>
                    <td>Full token history table for the logged-in user.</td>
                </tr>
                <tr>
                    <td><code>[sda_totals]</code></td>
                    <td>—</td>
                    <td>Balance card. Shows coin-proposal eligibility badge if user holds ≥1M SDA.</td>
                </tr>
                <tr>
                    <td><code>[sda_projects]</code></td>
                    <td>—</td>
                    <td>Table of all DAO-approved projects with BIDs, SDG badges, and token totals.</td>
                </tr>
                <tr>
                    <td><code>[sda_sdg_goals]</code></td>
                    <td>—</td>
                    <td>Grid of all 17 UN SDGs with active/inactive SDR eligibility status.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Navigation menu tip -->
    <div class="sda-panel sda-panel-info">
        <h2>💡 Add Pages to Your Navigation Menu</h2>
        <ol style="margin-left:20px;font-size:13px;line-height:1.8">
            <li>Go to <a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>">Appearance → Menus</a></li>
            <li>In the left panel, open <strong>Pages</strong> and tick the SDA pages</li>
            <li>Click <strong>Add to Menu</strong>, then <strong>Save Menu</strong></li>
        </ol>
        <a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>" class="button" style="margin-top:8px">Open Menu Editor →</a>
    </div>
</div>
