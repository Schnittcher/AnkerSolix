# Changelog

## 28.10.2026 - Version 1.2

### Neu
- **Anker Solix X1 wird jetzt unterstützt!** Der Konfigurator erkennt X1-Systeme (Home Energy System) automatisch und listet das Hauptgerät sowie alle verbundenen Batteriemodule auf. Voraussetzung: Modul aktualisieren, dann den Konfigurator neu öffnen.

## 19.10.2026 - Version 1.1

### Fixes
- EC-Schlüsselgenerierung auf Windows behoben: Das Modul erzeugt jetzt zur Laufzeit eine minimale temporäre Konfigurationsdatei für OpenSSL — kein Suchen nach installierten Pfaden mehr, funktioniert auf allen Windows-Systemen unabhängig von der PHP-Installation.

## 17.10.2026 - Version 1.0

### Neu
- Unterstützung für Solarbank, Smart Plug, Smart Meter, Powerstation und Home Power Station.
- Konfigurator erkennt alle Geräte eines Anker-Kontos automatisch und legt Device-Instanzen per Klick an.
- Gerätetyp-spezifische Variablen werden beim Anlegen automatisch angelegt und nicht benötigte entfernt.
- EC-Schlüsselgenerierung sucht `openssl.cnf` dynamisch — funktioniert auf Windows und Linux ohne manuelle Konfiguration.
