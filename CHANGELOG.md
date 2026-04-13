# Changelog — ÖMM Startliste

Alle wichtigen Änderungen werden hier dokumentiert.
Format: [Semantisches Versioning](https://semver.org/) — MAJOR.MINOR.PATCH

---

## [1.2.1] — 2026-04-13

### Fix
- Auto-Updater Test: GitHub → WordPress Update-Mechanismus verifiziert

---

## [1.2.0] — 2026-04-13

### Neu
- **GitHub Auto-Updater:** Plugin aktualisiert sich direkt aus dem WP-Admin (Plugins → "Aktualisieren")
- GitHub Token in Einstellungen hinterlegen → ab dann 1-Klick-Updates
- Repo: whiterabbitmediayt-jpg/oemm-startliste (privat)

---

## [1.1.1] — 2026-04-13

### Geändert
- Hersteller auf **Manuel Ribis GmbH** geändert
- Daten bleiben bei Deinstallation/Neu-Installation standardmäßig erhalten (kein Datenverlust)
- Neuer Schalter in Einstellungen: "Daten beim Deinstallieren löschen" (Standard: AUS)
- `uninstall.php` hinzugefügt für kontrollierten Datenlösch-Prozess
- API-Key Feld in Einstellungen für Urban's App-Authentifizierung

---

## [1.1.0] — 2026-04-13

### Neu
- **Vollständige Feldabdeckung:** Alle 28 Felder aus der Startliste v11 verfügbar
  (Startnummer, Name, Geschlecht, Geburtsdatum, Adresse, Bestelldaten, Attribution, Tokens etc.)
- **Export-Funktion:** CSV-Download mit frei wählbaren Feldern
- **Export-Profile:** Schnell-Profile für Startliste, Druckerei/Serienbrief, Besenwagen-Team, Kontaktliste
- **QR-Code Export für Druckerei:** CSV mit Token + QR-Bild-URL (400px) für Serienbrief
- **Suchfeld** in der Startliste (Name, E-Mail, Startnummer)
- **QR-Modal** statt Inline-Vorschau — sauberer und platzsparender
- **Felder-Grid** im Settings mit Alle/Keine/Standard Buttons
- **Export-Untermenü** im Admin

### Geändert
- Alle Texte mit korrekten Umlauten (ä, ö, ü, ß)
- Startliste zeigt jetzt dynamisch nur die in den Einstellungen aktivierten Felder
- Feldnamen konsistent mit Startliste v11 (billing_first_name statt first_name etc.)

### Technisch
- `OEMM_Settings::all_fields()` — zentrale Definition aller verfügbaren Felder
- Participant::get() liefert jetzt alle 28+ Felder inkl. Attribution, Geschlecht, Bestelldetails
- Export via direktem Form-POST (kein AJAX) für saubere Datei-Downloads
- UTF-8 BOM im CSV-Export für korrekte Excel-Darstellung von Umlauten

---

## [1.0.0] — 2026-04-13

### Neu
- Erstes Release
- Grundstruktur: DB-Tabelle, Token-System, QR-Codes
- Admin-Startliste mit Startnummer-Vergabe (händisch + automatisch auffüllen)
- ON/OFF Schalter für App-Button
- WooCommerce Dashboard-Button für Kunden mit Startnummer
- REST API v1 für Urban's App (`/oemm/v1/participant`, `/status`, `/checkpoint`, `/photo`)
- Import-Seite für einmaligen Excel-Import (2026)
- Statistik-Seite: App vs. Papier mit Scan-Countern
- API-Dokumentation für Urban (API-DOCS.md)
