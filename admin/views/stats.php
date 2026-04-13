<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap oemm-wrap">
    <h1>ÖMM Statistik — Digital vs. Papier</h1>

    <?php
    $total        = (int) ( $stats->total ?? 0 );
    $app_users    = (int) ( $stats->app_users ?? 0 );
    $paper_users  = (int) ( $stats->paper_users ?? 0 );
    $app_scans    = (int) ( $stats->total_app_scans ?? 0 );
    $paper_scans  = (int) ( $stats->total_paper_scans ?? 0 );
    $pct_app      = $total > 0 ? round( $app_users / $total * 100, 1 ) : 0;
    $pct_paper    = $total > 0 ? round( $paper_users / $total * 100, 1 ) : 0;
    ?>

    <div class="oemm-stats-grid">
        <div class="oemm-stat-card">
            <div class="oemm-stat-number"><?php echo $total; ?></div>
            <div class="oemm-stat-label">Teilnehmer gesamt</div>
        </div>
        <div class="oemm-stat-card oemm-stat-digital">
            <div class="oemm-stat-number"><?php echo $app_users; ?></div>
            <div class="oemm-stat-label">📱 App-Nutzer</div>
            <div class="oemm-stat-pct"><?php echo $pct_app; ?>%</div>
            <div class="oemm-stat-sub"><?php echo $app_scans; ?> Scans gesamt</div>
        </div>
        <div class="oemm-stat-card oemm-stat-paper">
            <div class="oemm-stat-number"><?php echo $paper_users; ?></div>
            <div class="oemm-stat-label">📄 Papier-Nutzer</div>
            <div class="oemm-stat-pct"><?php echo $pct_paper; ?>%</div>
            <div class="oemm-stat-sub"><?php echo $paper_scans; ?> Scans gesamt</div>
        </div>
    </div>

    <!-- Fortschrittsbalken: Digital First -->
    <div class="oemm-progress-section">
        <h2>Digital First Fortschritt 📈</h2>
        <div class="oemm-progress-bar-wrap">
            <div class="oemm-progress-bar" style="width:<?php echo $pct_app; ?>%">
                <?php echo $pct_app; ?>% digital
            </div>
        </div>
        <p style="color:#666">Ziel: Möglichst viele Teilnehmer nutzen die App statt dem Papier-Brief.</p>
    </div>

    <!-- Detailansicht: wer hat welchen QR gescannt -->
    <h2>Alle Teilnehmer — Scan-Uebersicht</h2>
    <?php
    global $wpdb;
    $table = $wpdb->prefix . 'oemm_participants';
    $year  = OEMM_Settings::get_event_year();
    $rows  = $wpdb->get_results( $wpdb->prepare(
        "SELECT customer_id, startnumber, scan_count_app, scan_count_paper FROM {$table}
         WHERE event_year = %d ORDER BY startnumber ASC",
        $year
    ) );
    ?>
    <table class="wp-list-table widefat fixed striped" style="max-width:600px">
        <thead>
            <tr>
                <th>Startnr.</th>
                <th>Name</th>
                <th>📱 App-Scans</th>
                <th>📄 Papier-Scans</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $rows as $row ) :
                $wc = new WC_Customer( (int) $row->customer_id );
            ?>
            <tr>
                <td><?php echo esc_html( $row->startnumber ?? '—' ); ?></td>
                <td><?php echo esc_html( $wc->get_billing_first_name() . ' ' . $wc->get_billing_last_name() ); ?></td>
                <td style="text-align:center">
                    <?php if ( $row->scan_count_app > 0 ) : ?>
                        <strong style="color:green"><?php echo $row->scan_count_app; ?> ✓</strong>
                    <?php else : ?>
                        <span style="color:#aaa">0</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center">
                    <?php if ( $row->scan_count_paper > 0 ) : ?>
                        <strong><?php echo $row->scan_count_paper; ?> ✓</strong>
                    <?php else : ?>
                        <span style="color:#aaa">0</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
