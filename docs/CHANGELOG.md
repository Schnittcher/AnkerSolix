# Changelog

## 28.10.2026 - Version 1.2

### Neu
- Anker Solix X1 (Home Energy System) wird jetzt im Konfigurator erkannt und angezeigt.
- Neue API-Endpunkte `charging_hes_svc/get_hes_dev_info` und `get_system_running_info` für X1-Sites.

## 19.10.2026 - Version 1.1

### Fixes
- EC-Schlüsselgenerierung auf Windows behoben: temporäre minimale `openssl.cnf` wird zur Laufzeit erzeugt, kein Suchen nach installierten Pfaden mehr.

## 17.10.2026 - Version 1.0

### Neu
- Unterstützung für Solarbank, Smart Plug, Smart Meter, Powerstation und Home Power Station.
- Konfigurator erkennt alle Geräte eines Anker-Kontos automatisch und legt Device-Instanzen per Klick an.
- Gerätetyp-spezifische Variablen werden beim Anlegen automatisch angelegt und nicht benötigte entfernt.
- EC-Schlüsselgenerierung sucht `openssl.cnf` dynamisch — funktioniert auf Windows und Linux ohne manuelle Konfiguration.
