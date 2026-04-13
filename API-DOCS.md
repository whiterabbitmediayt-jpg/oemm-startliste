# ÖMM Startliste — REST API Dokumentation v1

**Base URL:** `https://mopedmarathon.at/wp-json/oemm/v1`

---

## Endpoints

### GET `/status`
Gibt Plugin-Version, Event-Jahr und Aktiv-Status zurück.

**Authentifizierung:** Keine

**Response:**
```json
{
  "plugin": "ÖMM Startliste",
  "version": "1.0.0",
  "year": 2026,
  "active": true
}
```

---

### GET `/participant?token=TOKEN`
Gibt die Teilnehmerdaten für einen gültigen QR-Token zurück.
**Der Token IST die Authentifizierung.** Kein Login nötig.
Zählt automatisch den Scan-Counter (App vs. Papier).

**Authentifizierung:** Token im Query-Parameter

**Parameter:**
| Name  | Typ    | Pflicht | Beschreibung                  |
|-------|--------|---------|-------------------------------|
| token | string | ✓       | QR-Token aus dem QR-Code-Link |

**Erfolg (200):**
```json
{
  "startnumber": 42,
  "first_name":  "Max",
  "phone":       "+436601234567",
  "shirt_size":  "L",
  "order_id":    8779,
  "channel":     "app"
}
```
`channel` ist entweder `"app"` (digitaler QR) oder `"paper"` (Papier-Brief-QR).

**Fehler:**
```json
{ "error": "Token nicht gefunden." }       // 404
{ "error": "Token ungültig." }             // 400
{ "error": "Keine Startnummer zugewiesen." } // 404
{ "error": "Event nicht aktiv." }          // 403
```

---

### POST `/checkpoint`
Speichert eine Geofencing-Zwischenzeit für einen Teilnehmer.

**Authentifizierung:** Header `X-OEMM-Key: <api_key>`

**Body (JSON):**
```json
{
  "token":      "abc123...",
  "checkpoint": "penserjoch",
  "timestamp":  "2026-08-31T14:23:00Z"
}
```

**Response (200):** `{ "success": true }`

---

### POST `/photo`
Ordnet ein Foto einem Teilnehmer zu (für den Fotopoint).

**Authentifizierung:** Header `X-OEMM-Key: <api_key>`

**Body (JSON):**
```json
{
  "token":     "abc123...",
  "photo_url": "https://..."
}
```

**Response (200):** `{ "success": true, "count": 3 }`

---

## Token-Konzept

- Jeder Teilnehmer hat **2 Tokens**: `app` (für die App) und `paper` (für den Papier-Brief)
- Token = `SHA256(customer_id + channel + year + salt)` - stabil, ändert sich nie
- Link-Format: `https://moped-tracker.web.app/t/{TOKEN}`
- Beim Aufruf von `/participant?token=X` wird automatisch gezählt ob App oder Papier → Statistik

## Versionierung

Bei Breaking Changes wird der Namespace auf `oemm/v2` erhöht.
v1 bleibt weiterhin aktiv (rückwärtskompatibel) bis Urban's App migriert hat.

---

*Stand: ÖMM Plugin v1.0.0 — Kontakt: mopedmarathon.at*
