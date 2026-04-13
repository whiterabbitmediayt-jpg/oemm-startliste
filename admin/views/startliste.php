<?php defined( 'ABSPATH' ) || exit;
$all_labels   = OEMM_Settings::all_fields();
$active_keys  = array_keys( array_filter( $fields ) );
?>
<div class="wrap oemm-wrap">
    <h1>ÖMM Startliste <?php echo esc_html( $year ); ?></h1>

    <div class="oemm-toolbar">
        <span class="oemm-status-badge <?php echo $is_active ? 'active' : 'inactive'; ?>">
            App-Button: <?php echo $is_active ? '🟢 AN' : '⚫ AUS'; ?>
        </span>
        <div class="oemm-toolbar-actions">
            <button id="oemm-btn-generate-tokens" class="button button-secondary">
                🔑 Alle Tokens generieren
            </button>
            <button id="oemm-btn-fill-startnumbers" class="button button-primary">
                ▶ Startnummern auffüllen
            </button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=oemm-export' ) ); ?>" class="button button-secondary">
                📥 Export
            </a>
            <button id="oemm-btn-clear-update-cache" class="button button-secondary" title="Update-Prüfung erzwingen">
                🔄 Updates prüfen
            </button>
        </div>
    </div>

    <div id="oemm-notice" class="notice" style="display:none;"></div>

    <!-- Suchfeld -->
    <div class="oemm-search-bar">
        <input type="text" id="oemm-search" placeholder="Suche nach Name, E-Mail, Startnummer..." style="width:300px">
        <span id="oemm-search-count" style="color:#666;margin-left:10px"></span>
    </div>

    <div class="oemm-table-wrapper">
        <table id="oemm-startliste-table" class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:70px">Startnr.</th>
                    <?php foreach ( $active_keys as $key ) :
                        if ( $key === 'startnumber' ) continue; // schon als erste Spalte
                        $label = $all_labels[ $key ] ?? $key;
                    ?>
                    <th><?php echo esc_html( $label ); ?></th>
                    <?php endforeach; ?>
                    <th style="width:60px">QR App</th>
                    <th style="width:60px">QR Zettel</th>
                    <th style="width:50px">✓</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $participants ) ) : ?>
                    <tr><td colspan="30">Keine Teilnehmer gefunden. Bitte Produkt-IDs in den Einstellungen prüfen.</td></tr>
                <?php endif; ?>
                <?php foreach ( $participants as $p ) :
                    $tokens      = OEMM_Token::get_or_create( (int) $p['customer_id'] );
                    $search_text = strtolower( implode( ' ', array(
                        $p['billing_first_name'] ?? '',
                        $p['billing_last_name']  ?? '',
                        $p['customer_email']     ?? '',
                        $p['startnumber']        ?? '',
                    ) ) );
                ?>
                <tr data-customer-id="<?php echo esc_attr( $p['customer_id'] ); ?>"
                    data-search="<?php echo esc_attr( $search_text ); ?>">

                    <!-- Startnummer (immer erste Spalte, editierbar) -->
                    <td>
                        <input type="number"
                               class="oemm-startnumber-input"
                               value="<?php echo esc_attr( $p['startnumber'] ?? '' ); ?>"
                               min="1"
                               style="width:58px"
                               data-customer-id="<?php echo esc_attr( $p['customer_id'] ); ?>"
                        />
                    </td>

                    <!-- Dynamische Spalten -->
                    <?php foreach ( $active_keys as $key ) :
                        if ( $key === 'startnumber' ) continue;
                        $val = $p[ $key ] ?? '';
                    ?>
                    <td><?php
                        if ( $key === 'order_id' && $val ) {
                            echo '<a href="' . esc_url( admin_url( 'post.php?post=' . $val . '&action=edit' ) ) . '" target="_blank">#' . esc_html( $val ) . '</a>';
                        } elseif ( $key === 'order_status' && $val ) {
                            echo '<span class="oemm-order-status status-' . esc_attr( $val ) . '">' . esc_html( $val ) . '</span>';
                        } elseif ( $key === 'token_app' || $key === 'token_paper' ) {
                            $tok = $key === 'token_app' ? $tokens['app'] : $tokens['paper'];
                            echo $tok ? '<code class="oemm-token">' . esc_html( $tok ) . '</code>' : '<em>—</em>';
                        } elseif ( $key === 'order_total' || $key === 'order_subtotal' || $key === 'line_total' ) {
                            echo $val !== null && $val !== '' ? esc_html( number_format( (float)$val, 2, ',', '.' ) ) . ' €' : '';
                        } else {
                            echo esc_html( $val );
                        }
                    ?></td>
                    <?php endforeach; ?>

                    <!-- QR App -->
                    <td>
                        <?php if ( $tokens['app'] ) : ?>
                        <button class="oemm-btn-show-qr button button-small"
                                data-token="<?php echo esc_attr( $tokens['app'] ); ?>"
                                data-label="App">QR</button>
                        <?php else : ?><em>—</em><?php endif; ?>
                    </td>

                    <!-- QR Zettel -->
                    <td>
                        <?php if ( $tokens['paper'] ) : ?>
                        <button class="oemm-btn-show-qr button button-small"
                                data-token="<?php echo esc_attr( $tokens['paper'] ); ?>"
                                data-label="Zettel">QR</button>
                        <?php else : ?><em>—</em><?php endif; ?>
                    </td>

                    <!-- Speichern -->
                    <td>
                        <button class="oemm-btn-save-startnumber button button-small button-primary"
                                data-customer-id="<?php echo esc_attr( $p['customer_id'] ); ?>">✓</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="oemm-footer-info">
        <strong><?php echo count( $participants ); ?></strong> Teilnehmer |
        Startnummern vergeben: <strong><?php echo count( array_filter( $participants, fn($p) => ! is_null( $p['startnumber'] ) ) ); ?></strong> |
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=oemm-settings' ) ); ?>">Angezeigte Felder anpassen</a>
    </p>
</div>

<!-- QR Modal -->
<div id="oemm-qr-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;display:none;align-items:center;justify-content:center">
    <div style="background:#fff;padding:24px;border-radius:8px;text-align:center;max-width:300px">
        <h3 id="oemm-modal-title">QR Code</h3>
        <div id="oemm-modal-img"></div>
        <p id="oemm-modal-token" style="font-size:11px;color:#666;word-break:break-all"></p>
        <button id="oemm-modal-close" class="button button-secondary" style="margin-top:8px">Schließen</button>
    </div>
</div>
