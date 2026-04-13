<?php defined( 'ABSPATH' ) || exit; ?>
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
        <input type="text" id="oemm-search" placeholder="Suche nach Name, Firma, E-Mail, Startnummer..." style="width:340px">
        <span id="oemm-search-count" style="color:#666;margin-left:10px"></span>
    </div>

    <div class="oemm-table-wrapper">
        <table id="oemm-startliste-table" class="wp-list-table widefat striped oemm-sortable">
            <thead>
                <tr>
                    <th class="oemm-th-sort" data-col="0" data-type="num" style="width:80px;cursor:pointer">
                        Startnr. <span class="oemm-sort-icon">↕</span>
                    </th>
                    <th class="oemm-th-sort" data-col="1" style="cursor:pointer">
                        Vorname <span class="oemm-sort-icon">↕</span>
                    </th>
                    <th class="oemm-th-sort" data-col="2" style="cursor:pointer">
                        Nachname <span class="oemm-sort-icon">↕</span>
                    </th>
                    <th class="oemm-th-sort" data-col="3" style="cursor:pointer">
                        Firma <span class="oemm-sort-icon">↕</span>
                    </th>
                    <th class="oemm-th-sort" data-col="4" style="cursor:pointer">
                        Telefon <span class="oemm-sort-icon">↕</span>
                    </th>
                    <th class="oemm-th-sort" data-col="5" style="cursor:pointer">
                        T-Shirt <span class="oemm-sort-icon">↕</span>
                    </th>
                    <th class="oemm-th-sort" data-col="6" style="cursor:pointer">
                        Produkt <span class="oemm-sort-icon">↕</span>
                    </th>
                    <th style="width:100px">Speichern</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $participants ) ) : ?>
                    <tr><td colspan="9">Keine Teilnehmer gefunden. Bitte Produkt-IDs in den Einstellungen prüfen.</td></tr>
                <?php endif; ?>

                <?php foreach ( $participants as $p ) :
                    $first    = $p['billing_first_name'] ?? '';
                    $last     = $p['billing_last_name']  ?? '';
                    $firma    = $p['billing_company']    ?? '';
                    $email    = $p['customer_email']     ?? '';
                    $phone    = $p['billing_phone']      ?? '';
                    $shirt    = $p['shirt_size']         ?? '';
                    $produkt  = $p['product_name']       ?? '';
                    $order_id = $p['order_id']           ?? '';
                    $sn       = $p['startnumber']        ?? '';
                    $cid      = $p['customer_id'];

                    $search_text = strtolower( implode( ' ', [ $first, $last, $firma, $email, $sn ] ) );
                ?>
                <tr data-customer-id="<?php echo esc_attr( $cid ); ?>"
                    data-search="<?php echo esc_attr( $search_text ); ?>">

                    <!-- Startnummer -->
                    <td data-val="<?php echo esc_attr( $sn ); ?>">
                        <input type="text"
                               class="oemm-startnumber-input"
                               value="<?php echo esc_attr( $sn ); ?>"
                               style="width:60px"
                               data-customer-id="<?php echo esc_attr( $cid ); ?>"
                        />
                    </td>

                    <!-- Vorname — klickbar zur Bestellung -->
                    <td data-val="<?php echo esc_attr( $first ); ?>">
                        <?php if ( $order_id ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ); ?>" target="_blank" title="Bestellung #<?php echo esc_attr( $order_id ); ?> öffnen">
                                <?php echo esc_html( $first ); ?>
                            </a>
                        <?php else : ?>
                            <?php echo esc_html( $first ); ?>
                        <?php endif; ?>
                    </td>

                    <!-- Nachname — klickbar zur Bestellung -->
                    <td data-val="<?php echo esc_attr( $last ); ?>">
                        <?php if ( $order_id ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ); ?>" target="_blank" title="Bestellung #<?php echo esc_attr( $order_id ); ?> öffnen">
                                <?php echo esc_html( $last ); ?>
                            </a>
                        <?php else : ?>
                            <?php echo esc_html( $last ); ?>
                        <?php endif; ?>
                    </td>

                    <!-- Firma -->
                    <td data-val="<?php echo esc_attr( $firma ); ?>"><?php echo esc_html( $firma ); ?></td>

                    <!-- Telefon -->
                    <td data-val="<?php echo esc_attr( $phone ); ?>"><?php echo esc_html( $phone ); ?></td>

                    <!-- T-Shirt -->
                    <td data-val="<?php echo esc_attr( $shirt ); ?>"><?php echo esc_html( $shirt ); ?></td>

                    <!-- Produkt -->
                    <td data-val="<?php echo esc_attr( $produkt ); ?>"><?php echo esc_html( $produkt ); ?></td>

                    <!-- Speichern -->
                    <td>
                        <button class="oemm-btn-save-startnumber button button-small button-primary"
                                data-customer-id="<?php echo esc_attr( $cid ); ?>"
                                title="Startnummer speichern">✓ Speichern</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="oemm-footer-info">
        <strong><?php echo count( $participants ); ?></strong> Teilnehmer |
        Startnummern vergeben: <strong><?php echo count( array_filter( $participants, fn($p) => ! is_null( $p['startnumber'] ) ) ); ?></strong> |
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=oemm-settings' ) ); ?>">Einstellungen</a>
    </p>
</div>
