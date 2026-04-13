<?php defined( 'ABSPATH' ) || exit;
$all_fields = OEMM_Settings::all_fields();
$active     = OEMM_Settings::get_fields();
?>
<div class="wrap oemm-wrap">
    <h1>ÖMM Export</h1>
    <p>Exportiere die Startliste als CSV — mit individuell wählbaren Feldern. Jeder Export kann unterschiedliche Felder enthalten.</p>

    <div id="oemm-notice" class="notice" style="display:none;"></div>

    <!-- Export-Profile (vordefinierte Kombinationen) -->
    <div class="oemm-export-profiles">
        <h3>Schnell-Profile</h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button class="button oemm-profile-btn" data-profile="startliste"
                    data-fields="startnumber,billing_first_name,billing_last_name,geschlecht,geburtsdatum,billing_phone,billing_address_1,billing_postcode,billing_city,billing_country,shirt_size,order_id,order_status">
                🏁 Startliste (Basis)
            </button>
            <button class="button oemm-profile-btn" data-profile="druckerei"
                    data-fields="startnumber,token_paper,billing_first_name,billing_last_name,geschlecht,billing_company,billing_address_1,billing_postcode,billing_city,billing_country">
                🖨️ Druckerei / Serienbrief
            </button>
            <button class="button oemm-profile-btn" data-profile="besenwagen"
                    data-fields="startnumber,billing_first_name,billing_last_name,billing_phone,shirt_size,geburtsdatum,billing_city,billing_country">
                🚌 Besenwagen-Team
            </button>
            <button class="button oemm-profile-btn" data-profile="kontakt"
                    data-fields="startnumber,billing_first_name,billing_last_name,customer_email,billing_phone">
                📱 Kontaktliste
            </button>
            <button class="button oemm-profile-btn" data-profile="full"
                    data-fields="<?php echo esc_attr( implode( ',', array_keys( $all_fields ) ) ); ?>">
                📋 Alle Felder
            </button>
        </div>
    </div>

    <hr>

    <form id="oemm-export-form">
        <h3>Felder auswählen</h3>

        <div style="display:flex;gap:8px;margin-bottom:12px">
            <button type="button" id="oemm-export-select-all"  class="button button-small">Alle</button>
            <button type="button" id="oemm-export-select-none" class="button button-small">Keine</button>
        </div>

        <div class="oemm-fields-grid">
            <?php foreach ( $all_fields as $key => $label ) : ?>
            <label class="oemm-field-checkbox">
                <input type="checkbox"
                       name="export_fields[]"
                       class="oemm-export-field"
                       value="<?php echo esc_attr( $key ); ?>"
                       <?php checked( ! empty( $active[ $key ] ), true ); ?> />
                <?php echo esc_html( $label ); ?>
                <small style="color:#999;display:block;font-size:10px"><?php echo esc_html( $key ); ?></small>
            </label>
            <?php endforeach; ?>
        </div>

        <hr>

        <table class="form-table" style="max-width:600px">
            <tr>
                <th>Dateiname</th>
                <td>
                    <input type="text" id="oemm-export-name" value="startliste-oemm-2026" style="width:300px" />
                    <span style="color:#666">.csv</span>
                </td>
            </tr>
        </table>

        <p>
            <button type="submit" id="oemm-btn-export-csv" class="button button-primary button-large">
                📥 CSV herunterladen
            </button>
        </p>
    </form>

    <hr>

    <h2>QR-Codes für Druckerei</h2>
    <p>Exportiert eine CSV mit allen Teilnehmern + QR-Code Bild-URLs und Ziel-URLs. Perfekt für Serienbrief + Druckerei.</p>
    <p>Die <strong>QR_Bild_URL</strong> in der CSV kann die Druckerei direkt als Bildreferenz im Serienbrief verwenden.</p>

    <div style="display:flex;gap:12px;margin-top:12px">
        <button id="oemm-btn-export-qr-paper" class="button button-secondary button-large">
            📄 QR-Codes Zettel (Druckerei)
        </button>
        <button id="oemm-btn-export-qr-app" class="button button-secondary button-large">
            📱 QR-Codes App
        </button>
    </div>
    <p style="color:#666;font-size:12px;margin-top:8px">
        Spalten: Startnummer, Vorname, Nachname, Adresse, PLZ, Ort, Land, Token, QR-Ziel-URL, QR-Bild-URL (400px)
    </p>
</div>
