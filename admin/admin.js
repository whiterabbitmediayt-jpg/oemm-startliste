jQuery(function($) {
    'use strict';

    // -------------------------------------------------------------------------
    // Hilfsfunktionen
    // -------------------------------------------------------------------------

    function showNotice(msg, type) {
        var $n = $('#oemm-notice');
        $n.removeClass('success error').addClass(type).html(msg).show();
        setTimeout(function() { $n.fadeOut(); }, 4000);
    }

    function ajaxPost(action, data, successMsg) {
        return $.post(oemm_ajax.url, $.extend({
            action: 'oemm_' + action,
            nonce: oemm_ajax.nonce
        }, data))
        .then(function(res) {
            if (res.success) {
                showNotice(res.data ? (res.data.message || successMsg) : successMsg, 'success');
            } else {
                showNotice('Fehler: ' + (res.data || 'Unbekannt'), 'error');
            }
            return res;
        })
        .fail(function() {
            showNotice('Server-Fehler. Bitte Seite neu laden.', 'error');
        });
    }

    // -------------------------------------------------------------------------
    // Startnummer speichern (einzeln per Klick auf ✓ Button)
    // -------------------------------------------------------------------------

    $(document).on('click', '.oemm-btn-save-startnumber', function() {
        var $btn = $(this);
        var cid  = $btn.data('customer-id');
        var $input = $('[data-customer-id="' + cid + '"].oemm-startnumber-input');
        var sn   = $input.val();

        $btn.prop('disabled', true).text('...');

        ajaxPost('set_startnumber', { customer_id: cid, startnumber: sn }, 'Startnummer gespeichert.')
        .always(function() {
            $btn.prop('disabled', false).text('✓');
        });
    });

    // Enter-Taste in Startnummer-Feld -> speichern
    $(document).on('keypress', '.oemm-startnumber-input', function(e) {
        if (e.which === 13) {
            var cid = $(this).data('customer-id');
            $('[data-customer-id="' + cid + '"].oemm-btn-save-startnumber').click();
        }
    });

    // -------------------------------------------------------------------------
    // Alle Startnummern auffüllen
    // -------------------------------------------------------------------------

    $('#oemm-btn-fill-startnumbers').on('click', function() {
        if (!confirm('Alle Teilnehmer ohne Startnummer werden jetzt fortlaufend befüllt. Bereits vergebene Nummern bleiben unverändert. Fortfahren?')) return;

        var $btn = $(this).prop('disabled', true).text('Wird befüllt...');

        ajaxPost('fill_startnumbers', {}, 'Startnummern aufgefüllt!')
        .then(function(res) {
            if (res.success) {
                setTimeout(function() { location.reload(); }, 1500);
            }
        })
        .always(function() {
            $btn.prop('disabled', false).text('▶ Startnummern auffüllen');
        });
    });

    // -------------------------------------------------------------------------
    // Alle Tokens generieren
    // -------------------------------------------------------------------------

    $('#oemm-btn-generate-tokens').on('click', function() {
        var $btn = $(this).prop('disabled', true).text('Wird generiert...');

        ajaxPost('generate_tokens', {}, 'Tokens generiert!')
        .always(function() {
            $btn.prop('disabled', false).text('🔑 Alle Tokens generieren');
        });
    });

    // -------------------------------------------------------------------------
    // Settings Form
    // -------------------------------------------------------------------------

    $('#oemm-settings-form').on('submit', function(e) {
        e.preventDefault();
        var data = $(this).serializeArray().reduce(function(obj, item) {
            // Felder-Checkboxen als Objekt aufbauen
            if (item.name.startsWith('fields[')) {
                var key = item.name.replace('fields[', '').replace(']', '');
                if (!obj.fields) obj.fields = {};
                obj.fields[key] = 1;
            } else {
                obj[item.name] = item.value;
            }
            return obj;
        }, {});

        // Alle bekannten Felder die NICHT gecheckt sind explizit auf 0 setzen
        var allFields = ['first_name','last_name','email','phone','address_1','postcode','city','country','geburtsdatum','shirt_size','order_id','order_status'];
        if (!data.fields) data.fields = {};
        allFields.forEach(function(f) {
            if (!data.fields[f]) data.fields[f] = 0;
        });

        // Als JSON-Strings für POST senden
        var postData = { action: 'oemm_save_settings', nonce: oemm_ajax.nonce };
        postData.event_year       = data.event_year;
        postData.product_ids      = data.product_ids;
        postData.startnumber_start = data.startnumber_start;
        postData.app_url          = data.app_url;
        if (data.event_active) postData.event_active = 1;
        postData.github_token              = data.github_token || '';
        postData.api_key                   = data.api_key || '';
        postData.firebase_credentials_path = data.firebase_credentials_path || '';
        if (data.delete_data_on_uninstall) postData.delete_data_on_uninstall = 1;

        // Felder als flache key-value
        allFields.forEach(function(f) {
            postData['fields[' + f + ']'] = data.fields[f] || 0;
        });

        $.post(oemm_ajax.url, postData)
        .then(function(res) {
            if (res.success) {
                showNotice('✅ Einstellungen gespeichert.', 'success');
            } else {
                showNotice('Fehler: ' + res.data, 'error');
            }
        });
    });

    // -------------------------------------------------------------------------
    // Update-Cache leeren
    // -------------------------------------------------------------------------

    $('#oemm-btn-clear-update-cache').on('click', function() {
        var $btn = $(this).prop('disabled', true).text('Wird geprüft...');
        $.post(oemm_ajax.url, {
            action: 'oemm_clear_update_cache',
            nonce: oemm_ajax.nonce
        }).then(function(res) {
            if (res.success) {
                showNotice('✅ Update-Cache geleert. Weiterleitung...', 'success');
                setTimeout(function() {
                    window.location.href = oemm_ajax.plugins_url;
                }, 1500);
            }
        }).fail(function(xhr) {
            showNotice('Fehler: ' + xhr.status + ' ' + xhr.statusText, 'error');
        }).always(function() {
            $btn.prop('disabled', false).text('🔄 Updates prüfen');
        });
    });

    // -------------------------------------------------------------------------
    // Suche in Startliste
    // -------------------------------------------------------------------------

    $('#oemm-search').on('input', function() {
        var q = $(this).val().toLowerCase().trim();
        var $rows = $('#oemm-startliste-table tbody tr');
        var visible = 0;
        $rows.each(function() {
            var text = $(this).data('search') || '';
            var show = !q || text.indexOf(q) !== -1;
            $(this).toggle(show);
            if (show) visible++;
        });
        $('#oemm-search-count').text(q ? visible + ' gefunden' : '');
    });

    // -------------------------------------------------------------------------
    // QR Modal
    // -------------------------------------------------------------------------

    $(document).on('click', '.oemm-btn-show-qr', function() {
        var token = $(this).data('token');
        var label = $(this).data('label') || 'QR Code';
        var appUrl = typeof oemm_ajax.app_url !== 'undefined' ? oemm_ajax.app_url : 'https://moped-tracker.web.app/t/';
        var qrImgUrl = 'https://quickchart.io/qr?text=' + encodeURIComponent(appUrl + token) + '&size=250&margin=1';

        $('#oemm-modal-title').text('QR Code — ' + label);
        $('#oemm-modal-img').html('<img src="' + qrImgUrl + '" width="250" height="250" />');
        $('#oemm-modal-token').text(token);
        $('#oemm-qr-modal').css('display', 'flex');
    });

    $('#oemm-modal-close, #oemm-qr-modal').on('click', function(e) {
        if (e.target === this) $('#oemm-qr-modal').hide();
    });

    // -------------------------------------------------------------------------
    // Settings: Alle / Keine / Standard
    // -------------------------------------------------------------------------

    $('#oemm-select-all').on('click', function() {
        $('.oemm-field-toggle').prop('checked', true);
    });
    $('#oemm-select-none').on('click', function() {
        $('.oemm-field-toggle').prop('checked', false);
    });
    var defaultFields = ['startnumber','billing_first_name','billing_last_name','geschlecht','geburtsdatum','customer_email','billing_phone','billing_address_1','billing_postcode','billing_city','billing_country','order_id','order_status','shirt_size','product_name'];
    $('#oemm-select-default').on('click', function() {
        $('.oemm-field-toggle').each(function() {
            var name = $(this).attr('name').replace('fields[','').replace(']','');
            $(this).prop('checked', defaultFields.indexOf(name) !== -1);
        });
    });

    // -------------------------------------------------------------------------
    // Export: Felder alle / keine
    // -------------------------------------------------------------------------

    $('#oemm-export-select-all').on('click', function() {
        $('.oemm-export-field').prop('checked', true);
    });
    $('#oemm-export-select-none').on('click', function() {
        $('.oemm-export-field').prop('checked', false);
    });

    // Export-Profile
    $('.oemm-profile-btn').on('click', function() {
        $('.oemm-profile-btn').removeClass('active');
        $(this).addClass('active');
        var fields = $(this).data('fields').split(',');
        $('.oemm-export-field').each(function() {
            $(this).prop('checked', fields.indexOf($(this).val()) !== -1);
        });
    });

    // CSV Export
    $('#oemm-export-form').on('submit', function(e) {
        e.preventDefault();
        var fields = [];
        $('.oemm-export-field:checked').each(function() { fields.push($(this).val()); });
        if (fields.length === 0) {
            showNotice('Bitte mindestens ein Feld auswählen.', 'error');
            return;
        }
        var exportName = $('#oemm-export-name').val() || 'startliste';

        // Formular-POST direkt (kein AJAX) für Datei-Download
        var $form = $('<form method="POST" action="' + oemm_ajax.url + '">');
        $form.append('<input type="hidden" name="action" value="oemm_export_csv">');
        $form.append('<input type="hidden" name="nonce" value="' + oemm_ajax.nonce + '">');
        $form.append('<input type="hidden" name="export_name" value="' + exportName + '">');
        fields.forEach(function(f) {
            $form.append('<input type="hidden" name="fields[]" value="' + f + '">');
        });
        $('body').append($form);
        $form.submit();
        $form.remove();
    });

    // QR Export (Druckerei)
    function exportQR(channel) {
        var $form = $('<form method="POST" action="' + oemm_ajax.url + '">');
        $form.append('<input type="hidden" name="action" value="oemm_export_qr_zip">');
        $form.append('<input type="hidden" name="nonce" value="' + oemm_ajax.nonce + '">');
        $form.append('<input type="hidden" name="channel" value="' + channel + '">');
        $('body').append($form);
        $form.submit();
        $form.remove();
    }
    $('#oemm-btn-export-qr-paper').on('click', function() { exportQR('paper'); });
    $('#oemm-btn-export-qr-app').on('click',   function() { exportQR('app');   });

    // -------------------------------------------------------------------------
    // Import
    // -------------------------------------------------------------------------

    $('#oemm-btn-import').on('click', function() {
        var data = $('#oemm-import-data').val().trim();
        if (!data) {
            showNotice('Bitte JSON-Daten einfuegen.', 'error');
            return;
        }

        try {
            var parsed = JSON.parse(data);
            if (!Array.isArray(parsed) || parsed.length === 0) throw new Error('Kein Array');
        } catch(e) {
            showNotice('Ungültige JSON-Daten: ' + e.message, 'error');
            return;
        }

        if (!confirm(parsed.length + ' Einträge importieren. Bestehende Startnummern werden überschrieben!')) return;

        var $btn = $(this).prop('disabled', true).text('Importiere...');

        ajaxPost('import_excel', { data: data }, 'Import abgeschlossen!')
        .always(function() {
            $btn.prop('disabled', false).text('📥 Import starten');
        });
    });

});

// -------------------------------------------------------------------------
// Tabellen-Sortierung
// -------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function() {
    var table = document.getElementById('oemm-startliste-table');
    if (!table) return;

    var sortState = { col: -1, asc: true };

    table.querySelectorAll('th.oemm-th-sort').forEach(function(th) {
        th.addEventListener('click', function() {
            var col  = parseInt(th.dataset.col);
            var type = th.dataset.type || 'str';
            var asc  = (sortState.col === col) ? !sortState.asc : true;
            sortState = { col: col, asc: asc };

            // Icons zurücksetzen
            table.querySelectorAll('th.oemm-th-sort .oemm-sort-icon').forEach(function(ic) {
                ic.textContent = '\u2195';
            });
            th.querySelector('.oemm-sort-icon').textContent = asc ? '\u2191' : '\u2193';

            var tbody = table.querySelector('tbody');
            var rows  = Array.from(tbody.querySelectorAll('tr[data-customer-id]'));

            rows.sort(function(a, b) {
                var tds = [a, b].map(function(r) {
                    var td = r.querySelectorAll('td')[col];
                    if (!td) return '';
                    // Bei Startnummer-Spalte: aktuellen Input-Wert lesen
                    var input = td.querySelector('input');
                    if (input) return input.value.trim();
                    return (td.dataset.val || td.textContent).trim();
                });

                if (type === 'num') {
                    // Leere Werte immer ans Ende
                    if (tds[0] === '' && tds[1] !== '') return 1;
                    if (tds[0] !== '' && tds[1] === '') return -1;
                    if (tds[0] === '' && tds[1] === '') return 0;
                    // Numerisch sortieren (parseInt entfernt führende Nullen für die Reihenfolge)
                    // '01' und '1' landen nebeneinander, '01' < '1' via String-Vergleich
                    var na = parseInt(tds[0], 10);
                    var nb = parseInt(tds[1], 10);
                    if (na !== nb) return asc ? na - nb : nb - na;
                    // Bei gleicher Zahl: String-Vergleich (01 vor 1)
                    return asc
                        ? tds[0].localeCompare(tds[1], 'de', {numeric: false})
                        : tds[1].localeCompare(tds[0], 'de', {numeric: false});
                } else {
                    return asc
                        ? tds[0].localeCompare(tds[1], 'de')
                        : tds[1].localeCompare(tds[0], 'de');
                }
            });

            rows.forEach(function(r) { tbody.appendChild(r); });
        });
    });
});
