# Changelog

## 19.10.2026 - Version 1.1

### Fixes
- EC-Schlüsselgenerierung auf Windows behoben: temporäre minimale `openssl.cnf` wird zur Laufzeit erzeugt, kein Suchen nach installierten Pfaden mehr.

## 17.10.2026 - Version 1.0

### Neu
- Unterstützung für Solarbank, Smart Plug, Smart Meter, Powerstation und Home Power Station.
- Konfigurator erkennt alle Geräte eines Anker-Kontos automatisch und legt Device-Instanzen per Klick an.
- Gerätetyp-spezifische Variablen werden beim Anlegen automatisch angelegt und nicht benötigte entfernt.
- EC-Schlüsselgenerierung sucht `openssl.cnf` dynamisch — funktioniert auf Windows und Linux ohne manuelle Konfiguration.
