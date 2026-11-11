# Zibro Warenkorb Rabatt-Störer (Progress Bar) – `v0.5.1`

Minimalistisches WordPress / WooCommerce Plugin zur Anzeige eines verkaufsfördernden Fortschrittsbalkens (Progress Bar) im Stil von Shopify/Neosupps inkl. automatischer Hinterlegung des WooCommerce-Gutscheins **`blackweek26`** ab 400 € für **zibro-ofenshop.de** (PeakShops Theme).

- **Aktuelle Version:** `v0.5.1` (siehe [`CHANGELOG.md`](./CHANGELOG.md))
- **Hauptdatei:** [`zibro-cart-discount-bar.php`](./zibro-cart-discount-bar.php)
- **Installationsfertiges ZIP-Paket:** [`zibro-cart-discount-bar.zip`](./zibro-cart-discount-bar.zip) (sowie [`zibro-cart-discount-bar-v0.5.1.zip`](./zibro-cart-discount-bar-v0.5.1.zip))

---

## Features

1. **Reine, schlanke Progressbar-Visualisierung (Minimalistic Code):**
   - Liest den aktuellen Brutto-Warenkorbwert (`get_subtotal() + get_subtotal_tax()`) aus und zeigt dem Kunden visuell an, wie viel noch bis zum Schwellenwert (Standard: **400 €** für **10 % Rabatt** mit dem in WooCommerce hinterlegten Gutschein **`blackweek26`**) fehlt.
   - Sobald der Schwellenwert erreicht ist, wechselt die Anzeige automatisch in den grünen Erfolgsstatus.
   - Die eigentliche Gutschein-Logik bleibt zu 100 % nativ bei WooCommerce (`blackweek26`).

2. **Optimiert für Desktop & Mobile (Position 3 Standard):**
   - **Position 3 (Standard):** Sitzt direkt über der Warenkorb-Artikeltabelle in der linken Spalte (`woocommerce_before_cart_table`).
   - **Mobile-View-Optimierung (`< 768px`):** Beseitigt überflüssige Theme-Abstände im mobilen Warenkorb und stimmt die Progressbar sowie die darunterliegende Artikel-Card optisch sauber aufeinander ab.
   - **Echtzeit-Updates per AJAX:** Aktualisiert sich automatisch ohne Seiten-Reload bei Mengenänderungen im Warenkorb sowie im Header-Mini-Cart (`woocommerce_add_to_cart_fragments`).

3. **WooCommerce Backend-Einstellungen:**
   - Zu finden unter **WooCommerce → Einstellungen → Warenkorb-Störer (10%)**:
     - Progressbar aktivieren / deaktivieren
     - Platzierung wählen (*Position 3: Über Artikeltabelle*, *Position 2: Volle Boxed-Breite 12 Spalten*, *Position 1: Direkt unter der Hauptnavigation*)
     - Schwellenwert in `€` (Standard: `400`)
     - Rabatt-Anzeige in `%` (Standard: `10`)
     - Gutscheincode für Text-Platzhalter `{coupon}` (Standard: `blackweek26`)
     - Anzeige im Header Mini-Cart (`ja`/`nein`)
     - Balkenfarbe (Standard: `#1e8e3e`)
     - Frei anpassbare Texte vor/nach Erreichen des Schwellenwerts (Platzhalter: `{remaining}`, `{discount}`, `{threshold}`, `{saved}`, `{coupon}`)

---

## Installation / Update in WordPress

1. Lade die Datei **[`zibro-cart-discount-bar-v0.5.0.zip`](./zibro-cart-discount-bar-v0.5.0.zip)** aus diesem Repository herunter.
2. Öffne im WordPress-Backend **Plugins → Neues Plugin hinzufügen → Plugin hochladen**.
3. Wähle `zibro-cart-discount-bar-v0.5.0.zip` aus, klicke auf **Jetzt installieren** (bzw. **Aktuelles durch hochgeladenes ersetzen**) und anschließend auf **Plugin aktivieren**.
4. *(Optional)* Einstellungen prüfen unter **WooCommerce → Einstellungen → Warenkorb-Störer (10%)**.

---

## Deaktivieren des Störers

- **Temporär ausschalten (Einstellungen behalten):**
  Unter **WooCommerce → Einstellungen → Warenkorb-Störer (10%)** den Haken bei **„Progressbar aktivieren“** entfernen und speichern.
- **Komplett deaktivieren:**
  Unter **Plugins → Installierte Plugins** bei *„Zibro Warenkorb Rabatt-Störer (Progress Bar)“* auf **Deaktivieren** klicken.

---

## Lokale Live-Vorschau (`preview/`)

Im Ordner [`preview/`](./preview/) befindet sich eine interaktive Testumgebung basierend auf dem Warenkorb-DOM von `zibro-ofenshop.de`:
- [`preview/index.html`](./preview/index.html) im Browser öffnen, um **Mobile (`390px` Viewport)** und **Desktop** nebeneinander mit verschiedenen Warenkorbwerten (`1x`, `3x`, `5x` Artikel) zu testen.

---

## Versionshistorie

Alle Änderungen werden fortlaufend mit eindeutiger Unterversion in der [`CHANGELOG.md`](./CHANGELOG.md) dokumentiert:
- **`v0.5.1`** – Hotfix gegen Fatal Error (`Cannot redeclare class`) beim Darüber-Installieren/Aktivieren + Auto-Deaktivierung älterer Duplikate
- **`v0.5.0`** – Automatische Hinterlegung & Entfernung des WooCommerce-Gutscheins `blackweek26` ab 400 € (inkl. AJAX-Mengenupdate-Support für `simple-cart-update.js`)
- **`v0.4.2`** – Dokumentation (`README.md`) & GitHub-Repository-Release
- **`v0.4.1`** – Installationsfertiges WordPress-ZIP-Paket
- **`v0.4.0`** – Festlegung auf Position 3 (`cart_column`) & Mobile-View-Feinschliff inkl. echtem `390px` Viewport-Testframe
- **`v0.3.0`** – Verschlankung auf reine Progressbar-Visualisierung für den nativen WooCommerce-Gutschein `blackweek26`
- **`v0.2.0`** – Kompaktes UI/UX (weniger Weißraum) & Option für Platzierung unter der Hauptnavigation
- **`v0.1.0`** – Initiales Plugin & Live-Browser-Vorschau
