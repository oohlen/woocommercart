# Changelog – Zibro Warenkorb Rabatt-Störer (`zibro-cart-discount-bar`)

Alle Änderungen durch Prompts werden fortlaufend mit einer eindeutigen Unterversion dokumentiert.

## [v0.5.0] – 2026-11-11
### Behoben & Hinzugefügt
- **Automatische Hinterlegung des WooCommerce-Gutscheins `blackweek26` ab 400 € wieder aktiviert**:
  - Standard-WooCommerce löst unter *Marketing → Gutscheine* angelegte Gutscheine nicht von selbst ein, solange sie nicht aktiv per Code dem Warenkorb hinzugefügt werden.
  - [`Zibro_Cart_Discount_Bar::sync_auto_coupon_before_totals()`](file:///Users/oleohlen/zibro-warenkorb/zibro-cart-discount-bar.php#L82-L112) und [`Zibro_Cart_Discount_Bar::sync_auto_coupon()`](file:///Users/oleohlen/zibro-warenkorb/zibro-cart-discount-bar.php#L118-L162) prüfen bei jedem Seitenaufruf (`wp`) sowie bei jedem AJAX-Warenkorb-Update (`simple-cart-update.js`) den Brutto-Warenwert und hinterlegen ab `400 €` automatisch den WooCommerce-Gutschein **`blackweek26`** (`$cart->apply_coupon('blackweek26')`). Fällt der Warenwert unter `400 €`, wird er automatisch wieder entfernt.
  - Neues Backend-Einstellungsfeld *„Gutschein automatisch anwenden“* (`zibro_cdb_auto_apply`, Standard: `ja`).

## [v0.4.2] – 2026-11-10
### Dokumentation & GitHub-Repository
- **Umfassende Dokumentation ([`README.md`](file:///Users/oleohlen/zibro-warenkorb/README.md)) hinzugefügt**:
  - Installationsanleitung, Backend-Konfiguration, Platzierungsoptionen, Deaktivierungswege und Versionsbezug (`v0.4.2`).
- **GitHub-Sync**:
  - Komplettes Projekt inkl. Plugin, Live-Browser-Vorschau (`preview/`) und aktuellem Release-Paket (`zibro-cart-discount-bar-v0.4.2.zip`) in `https://github.com/oohlen/woocommercart` gepusht.

## [v0.4.1] – 2026-11-09
### Release-Paket
- **Installationsfertiges WordPress-Plugin-Archiv ([`zibro-cart-discount-bar-v0.4.1.zip`](file:///Users/oleohlen/zibro-warenkorb/zibro-cart-discount-bar-v0.4.1.zip))**:
  - Saubere Ordnerstruktur `zibro-cart-discount-bar/zibro-cart-discount-bar.php` (`v0.4.1`) für den 1-Klick-Upload im WordPress-Backend unter *Plugins → Neues Plugin hinzufügen → Plugin hochladen*.

## [v0.4.0] – 2026-11-09
### Geändert & Optimiert
- **Festlegung auf Position 3 (`cart_column` – Direkt über der Warenkorb-Artikeltabelle)**:
  - Standard-Platzierung auf `cart_column` (`woocommerce_before_cart_table`) gesetzt, sodass die Progressbar passgenau über der linken Warenkorb-Tabelle sitzt.
- **Sauberes Mobile-View-Design (`< 768px`)**:
  - Die Progressbar in Position 3 und die darunterliegende Warenkorb-Tabelle sind auf Mobile optisch aufeinander abgestimmt (saubere weiße Card mit `8px` Radius über der abgerundeten Artikel-Box, sauber zentrierter Löschen-Button, `64x64px` Produkt-Thumbnail mit `6px` Radius und nebeneinanderliegendes Gutschein-Eingabefeld).
  - Live-Vorschau (`preview/index.html`) nutzt für die Mobile-Ansicht nun einen **echten `390px` Viewport-Frame (`<iframe>`)**, damit sämtliche nativen `@media (max-width: 767px)` Breakpoints von PeakShops und unserem Plugin 1:1 wie auf einem echten iPhone greifen.

## [v0.3.0] – 2026-11-09
### Vereinfacht & Optimiert
- **Reine, schlanke Progressbar-Visualisierung (ohne eigene Gutschein-Logik)** für den in WooCommerce angelegten Gutschein `blackweek26`.

## [v0.2.0] – 2026-11-09
### Geändert & Hinzugefügt
- **Kompakteres UI/UX für Desktop & Mobile**.

## [v0.1.0] – 2026-11-09
### Hinzugefügt
- **Initiales Release (`v0.1.0`)**.
