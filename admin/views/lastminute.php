<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap oemm-wrap">
    <h1>⚡ Last-Minute-Verkauf</h1>
    <p style="color:#666;max-width:600px">Kassiere vor Ort, leg hier den Teilnehmer an — er bekommt sofort seinen QR-Code + Magic Link.</p>

    <div id="oemm-lm-notice" class="notice" style="display:none;margin-bottom:16px"></div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;max-width:900px">

        <!-- FORMULAR -->
        <div>
            <table class="form-table" style="background:#fff;padding:16px;border:1px solid #ddd;border-radius:6px">
                <tr>
                    <th style="width:130px"><label for="lm-first">Vorname *</label></th>
                    <td><input type="text" id="lm-first" class="regular-text" placeholder="Max" required></td>
                </tr>
                <tr>
                    <th><label for="lm-last">Nachname *</label></th>
                    <td><input type="text" id="lm-last" class="regular-text" placeholder="Mustermann" required></td>
                </tr>
                <tr>
                    <th><label for="lm-email">E-Mail *</label></th>
                    <td><input type="email" id="lm-email" class="regular-text" placeholder="max@example.com" required></td>
                </tr>
                <tr>
                    <th><label for="lm-phone">Telefon</label></th>
                    <td><input type="tel" id="lm-phone" class="regular-text" placeholder="+43 660 ..."></td>
                </tr>
                <tr>
                    <th><label for="lm-snr">Startnummer</label></th>
                    <td><input type="text" id="lm-snr" class="small-text" placeholder="z.B. 672" style="width:100px">
                    <span style="color:#888;font-size:12px;margin-left:8px">leer lassen = später vergeben</span></td>
                </tr>
                <tr>
                    <th><label for="lm-amount">Betrag (€)</label></th>
                    <td><input type="number" id="lm-amount" class="small-text" value="149" min="0" step="0.01" style="width:100px"></td>
                </tr>
                <tr>
                    <th><label for="lm-product">Produkt</label></th>
                    <td>
                        <select id="lm-product" class="regular-text">
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="lm-sendemail">E-Mail senden</label></th>
                    <td><input type="checkbox" id="lm-sendemail" checked>
                    <span style="color:#666;font-size:12px"> Magic Link + Startnummer an Teilnehmer mailen</span></td>
                </tr>
                <tr>
                    <td colspan="2" style="padding-top:12px">
                        <button id="oemm-btn-lastminute" class="button button-primary button-large">
                            ⚡ Jetzt anlegen &amp; QR-Code generieren
                        </button>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ERGEBNIS -->
        <div id="lm-result" style="display:none">
            <div style="background:#fff;padding:20px;border:1px solid #ddd;border-radius:6px;text-align:center">
                <h3 id="lm-result-name" style="margin-top:0"></h3>
                <div id="lm-result-snr" style="font-size:48px;font-weight:bold;color:#0073aa;margin:8px 0"></div>
                <img id="lm-qr-img" src="" alt="QR Code" style="width:220px;height:220px;border:2px solid #0073aa;border-radius:8px;margin:12px 0">
                <div>
                    <a id="lm-magic-link" href="#" target="_blank" class="button button-secondary">🔗 Magic Link öffnen</a>
                    <button id="lm-copy-link" class="button" style="margin-left:8px">📋 Link kopieren</button>
                </div>
                <div id="lm-result-meta" style="margin-top:12px;color:#666;font-size:12px"></div>
                <div id="lm-firebase-status" style="margin-top:8px;font-size:13px"></div>
                <hr>
                <button id="lm-btn-new" class="button">➕ Nächster Teilnehmer</button>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    $('#oemm-btn-lastminute').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('⏳ Wird angelegt...');
        $('#oemm-lm-notice').hide();

        $.post(oemm_ajax.url, {
            action:       'oemm_lastminute_sale',
            nonce:        oemm_ajax.nonce,
            first_name:   $('#lm-first').val(),
            last_name:    $('#lm-last').val(),
            email:        $('#lm-email').val(),
            phone:        $('#lm-phone').val(),
            startnumber:  $('#lm-snr').val(),
            amount:       $('#lm-amount').val(),
            product_id:   $('#lm-product').val(),
            send_email:   $('#lm-sendemail').is(':checked') ? 1 : 0,
        }, function(resp) {
            btn.prop('disabled', false).text('⚡ Jetzt anlegen & QR-Code generieren');
            if (!resp.success) {
                $('#oemm-lm-notice')
                    .removeClass('notice-success').addClass('notice notice-error')
                    .html('<p><strong>Fehler:</strong> ' + resp.data + '</p>').show();
                return;
            }
            var d = resp.data;
            $('#lm-result-name').text($('#lm-first').val() + ' ' + $('#lm-last').val());
            $('#lm-result-snr').text(d.startnumber ? 'SNR ' + d.startnumber : '(keine SNR)');
            $('#lm-qr-img').attr('src', d.qr_url);
            $('#lm-magic-link').attr('href', d.magic_link).show();
            $('#lm-copy-link').data('link', d.magic_link);
            $('#lm-result-meta').html(
                'Order #' + d.order_id + ' | User #' + d.user_id + ' | Token: ' + d.token_app
            );
            $('#lm-firebase-status').html(
                d.firebase_ok
                    ? '<span style="color:green">🔥 Firebase: synchronisiert ✓</span>'
                    : '<span style="color:orange">⚠️ Firebase: nicht synchronisiert (kein Token/Order?)</span>'
            );
            $('#lm-result').show();
            $('html,body').animate({scrollTop: $('#lm-result').offset().top - 40}, 300);
        }).fail(function() {
            btn.prop('disabled', false).text('⚡ Jetzt anlegen & QR-Code generieren');
            $('#oemm-lm-notice')
                .addClass('notice notice-error')
                .html('<p>Server-Fehler. Bitte nochmal versuchen.</p>').show();
        });
    });

    $('#lm-copy-link').on('click', function() {
        var link = $(this).data('link');
        navigator.clipboard.writeText(link).then(function() {
            $('#lm-copy-link').text('✅ Kopiert!');
            setTimeout(function(){ $('#lm-copy-link').text('📋 Link kopieren'); }, 2000);
        });
    });

    $('#lm-btn-new').on('click', function() {
        $('#lm-result').hide();
        $('#lm-first,#lm-last,#lm-email,#lm-phone,#lm-snr').val('');
        $('#lm-amount').val('149');
        $('#lm-sendemail').prop('checked', true);
        $('#lm-first').focus();
    });
});
</script>
