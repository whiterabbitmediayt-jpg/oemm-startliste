<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap oemm-wrap">
    <h1>ÖMM Import — Einmalig (2026)</h1>

    <div class="notice notice-warning">
        <p><strong>⚠ Achtung:</strong> Dieser Import setzt bestehende Startnummern in der Datenbank — einmalig für den Übergang von der Excel-Tabelle zum Plugin.<br>
        Danach wird alles über das Plugin verwaltet. Nur für Admins zugänglich.</p>
    </div>

    <div id="oemm-notice" class="notice" style="display:none;"></div>

    <h2>JSON-Import</h2>
    <p>Format: <code>[{"customer_id": 123, "startnumber": 456}, ...]</code></p>
    <p>Die customer_id = WooCommerce Benutzer-ID (nicht Bestellnummer!)</p>

    <textarea id="oemm-import-data" rows="15" style="width:100%;font-family:monospace;font-size:12px"
              placeholder='[{"customer_id": 123, "startnumber": 1}, {"customer_id": 456, "startnumber": 2}]'></textarea>

    <p>
        <button id="oemm-btn-import" class="button button-primary button-large">
            📥 Import starten
        </button>
    </p>

    <hr>
    <h2>Wie customer_id finden?</h2>
    <p>In der Excel-Tabelle (v11) haben wir die WooCommerce Customer-IDs bereits.<br>
    Alternativ: WooCommerce → Kunden → ID in der URL beim Bearbeiten.</p>
    <p>Ich (Buzz) kann die Excel-Tabelle auch direkt konvertieren — einfach Bescheid geben.</p>
</div>
