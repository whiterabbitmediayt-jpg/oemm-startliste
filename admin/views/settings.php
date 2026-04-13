<?php defined( 'ABSPATH' ) || exit;
$all_fields = OEMM_Settings::all_fields();
$active     = OEMM_Settings::get_fields();
?>
<div class="wrap oemm-wrap">
    <h1>ÖMM Einstellungen</h1>

    <div id="oemm-notice" class="notice" style="display:none;"></div>

    <form id="oemm-settings-form">
        <?php wp_nonce_field( 'oemm_admin', 'nonce' ); ?>

        <table class="form-table">
            <tr>
                <th>App-Button Status</th>
                <td>
                    <label>
                        <input type="checkbox" name="event_active" value="1"
                               <?php checked( OEMM_Settings::is_active(), true ); ?> />
                        <strong>App-Button aktiv (AN/AUS)</strong>
                    </label>
                    <p class="description">Wenn ausgeschaltet: Kein Button im Kundendashboard sichtbar — unabhängig von Startnummer.</p>
                </td>
            </tr>
            <tr>
                <th>Event-Jahr</th>
                <td>
                    <input type="number" name="event_year" value="<?php echo esc_attr( OEMM_Settings::get_event_year() ); ?>" min="2024" max="2040" />
                </td>
            </tr>
            <tr>
                <th>Produkt-IDs (Startnummer-Produkte)</th>
                <td>
                    <input type="text" name="product_ids" value="<?php echo esc_attr( get_option('oemm_product_ids','') ); ?>" style="width:400px" />
                    <p class="description">Komma-getrennte WooCommerce Produkt-IDs. Aktuell 2026: 7537, 8457, 8566<br>
                    Wird jedes Jahr neu definiert.</p>
                </td>
            </tr>
            <tr>
                <th>Startnummer beginnt bei</th>
                <td>
                    <input type="number" name="startnumber_start" value="<?php echo esc_attr( OEMM_Settings::get_startnumber_start() ); ?>" min="1" />
                </td>
            </tr>
            <tr>
                <th>App URL (Basis)</th>
                <td>
                    <input type="url" name="app_url" value="<?php echo esc_attr( OEMM_Settings::get_app_url() ); ?>" style="width:400px" />
                    <p class="description">Token wird direkt angehängt. z.B. https://moped-tracker.web.app/t/</p>
                </td>
            </tr>
            <tr>
                <th>API-Key (für Urban's App)</th>
                <td>
                    <input type="text" name="api_key" value="<?php echo esc_attr( get_option('oemm_api_key','') ); ?>" style="width:400px;font-family:monospace" />
                    <p class="description">Wird im Header <code>X-OEMM-Key</code> mitgeschickt für /checkpoint und /photo Endpoints.</p>
                </td>
            </tr>
            <tr>
                <th style="color:#c0392b">⚠️ Daten löschen</th>
                <td>
                    <label>
                        <input type="checkbox" name="delete_data_on_uninstall" value="1"
                               <?php checked( get_option('oemm_delete_data_on_uninstall','0'), '1' ); ?> />
                        <strong>Alle Daten beim Deinstallieren löschen</strong>
                    </label>
                    <p class="description" style="color:#c0392b">
                        ⚠️ Standard: <strong>AUS</strong> — Startnummern und Tokens bleiben bei Neu-Installation erhalten.<br>
                        Nur aktivieren wenn du wirklich alle Daten löschen willst (z.B. Jahreswechsel).
                    </p>
                </td>
            </tr>
        </table>

        <hr>
        <h2>Felder in der Startliste</h2>
        <p>Wähle welche Felder in der Admin-Startliste angezeigt werden sollen. Die Auswahl gilt auch als Standard für den Export.</p>

        <div style="display:flex;gap:8px;margin-bottom:12px">
            <button type="button" id="oemm-select-all" class="button button-small">Alle auswählen</button>
            <button type="button" id="oemm-select-none" class="button button-small">Alle abwählen</button>
            <button type="button" id="oemm-select-default" class="button button-small">Standard</button>
        </div>

        <div class="oemm-fields-grid">
            <?php foreach ( $all_fields as $key => $label ) : ?>
            <label class="oemm-field-checkbox">
                <input type="checkbox"
                       name="fields[<?php echo esc_attr( $key ); ?>]"
                       class="oemm-field-toggle"
                       value="1"
                       <?php checked( ! empty( $active[ $key ] ), true ); ?> />
                <?php echo esc_html( $label ); ?>
                <small style="color:#999;display:block;font-size:10px"><?php echo esc_html( $key ); ?></small>
            </label>
            <?php endforeach; ?>
        </div>

        <p class="submit">
            <button type="submit" id="oemm-btn-save-settings" class="button button-primary button-large">
                💾 Einstellungen speichern
            </button>
        </p>
    </form>
</div>
