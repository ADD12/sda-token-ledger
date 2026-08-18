<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap sda-admin-wrap">
    <h1 class="sda-page-title">📒 Token Ledger</h1>

    <?php if ( ! empty( $_GET['sda_notice'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( urldecode( $_GET['sda_notice'] ) ); ?></p></div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="get" class="sda-filter-bar">
        <input type="hidden" name="page" value="sda-ledger">
        <label>User:
            <select name="uid">
                <option value="">All Users</option>
                <?php foreach ( $users as $u ) : ?>
                    <option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $filter_user, $u->ID ); ?>>
                        <?php echo esc_html( $u->display_name ); ?> (#<?php echo $u->ID; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Type:
            <select name="type">
                <option value="">All</option>
                <option value="SDA" <?php selected( $filter_type, 'SDA' ); ?>>SDA</option>
                <option value="SDR" <?php selected( $filter_type, 'SDR' ); ?>>SDR</option>
            </select>
        </label>
        <label>Project (PID):
            <select name="pid">
                <option value="">All Projects</option>
                <?php foreach ( $projects as $p ) : ?>
                    <option value="<?php echo esc_attr( $p->pid ); ?>" <?php selected( $filter_pid, $p->pid ); ?>>
                        <?php echo esc_html( $p->project_name ); ?> (<?php echo esc_html( $p->pid ); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="button">Filter</button>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sda-ledger' ) ); ?>" class="button">Reset</a>
        <span class="sda-record-count"><?php echo esc_html( number_format( $total ) ); ?> records</span>
    </form>

    <?php if ( empty( $rows ) ) : ?>
        <p>No ledger entries match your filter.</p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped sda-table">
            <thead>
                <tr>
                    <th style="width:40px">ID</th>
                    <th>Date</th>
                    <th>User</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Project / PID</th>
                    <th>B-lan / BID</th>
                    <th>SDG</th>
                    <th>Status</th>
                    <th>VID / Verifier</th>
                    <th>TX (Sidechain)</th>
                    <th>TX (Main-chain)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $rows as $row ) : ?>
                    <tr class="sda-status-<?php echo esc_attr( $row->status ); ?>">
                        <td><small><?php echo esc_html( $row->id ); ?></small></td>
                        <td><small><?php echo esc_html( date( 'Y-m-d H:i', strtotime( $row->created_at ) ) ); ?></small></td>
                        <td><?php echo esc_html( $row->display_name ?? "#{$row->user_id}" ); ?></td>
                        <td><span class="sda-type-badge sda-type-<?php echo esc_attr( strtolower( $row->token_type ) ); ?>"><?php echo esc_html( $row->token_type ); ?></span></td>
                        <td class="sda-amount"><strong><?php echo esc_html( number_format( (float) $row->amount, 4 ) ); ?></strong></td>
                        <td>
                            <code><?php echo esc_html( $row->pid ); ?></code>
                            <?php if ( $row->project_name ) : ?>
                                <br><small><?php echo esc_html( $row->project_name ); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="sda-chain-badge <?php echo esc_attr( $row->chain_type ?? 'sidechain' ); ?>">
                                <?php echo esc_html( $row->chain_type ?? 'sidechain' ); ?>
                            </span><br>
                            <code class="sda-address" title="<?php echo esc_attr( $row->bid ); ?>">
                                <?php echo esc_html( substr( $row->bid, 0, 14 ) . ( strlen( $row->bid ) > 14 ? '…' : '' ) ); ?>
                            </code>
                        </td>
                        <td><?php echo $row->sdg_goal ? SDA_SDGs::badge( (int) $row->sdg_goal ) : '—'; ?></td>
                        <td><span class="sda-status-pill sda-status-pill-<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( ucfirst( $row->status ) ); ?></span></td>
                        <td><code class="sda-address" title="<?php echo esc_attr( $row->vid ?? '' ); ?>"><?php echo $row->vid ? esc_html( substr( $row->vid, 0, 10 ) . '…' ) : '—'; ?></code></td>
                        <td><code class="sda-address"><?php echo $row->tx_hash_side ? esc_html( substr( $row->tx_hash_side, 0, 10 ) . '…' ) : '—'; ?></code></td>
                        <td><code class="sda-address"><?php echo $row->tx_hash_main ? esc_html( substr( $row->tx_hash_main, 0, 10 ) . '…' ) : '—'; ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php
        $pages = ceil( $total / $per_page );
        if ( $pages > 1 ) :
            $base_url = admin_url( 'admin.php?page=sda-ledger&uid=' . $filter_user . '&type=' . $filter_type . '&pid=' . $filter_pid );
        ?>
            <div class="sda-pagination">
                <?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
                    <a href="<?php echo esc_url( $base_url . '&paged=' . $i ); ?>"
                       class="button <?php echo $i === $paged ? 'button-primary' : ''; ?>">
                        <?php echo esc_html( $i ); ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
