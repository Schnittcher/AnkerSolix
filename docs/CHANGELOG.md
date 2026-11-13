# Changelog

## 13.11.2026 - Version 1.4

### Fixes
- Batterieenergie bei Solarbank mit Erweiterungsakkus (BP1600) wird jetzt korrekt berechnet: Das API-Feld `sub_package_num` gibt die Anzahl der Zusatzakkus an, die nun zur nominalen Kapazität addiert werden.

## 07.11.2026 - Version 1.3

### Neu
- X1-Instanz legt jetzt automatisch Variablen an: Verbindungsstatus, Betriebsstatus und Gesamtenergie.
- Batterie-Variablen (SOC, Energie, Leistung) werden für X1-Instanzen angelegt und über `get_system_running_info` befüllt.

### Fixes
- Warning "No parent instance was configured" behoben: Timer und Bild-Abruf starten jetzt nur noch, wenn ein aktives IO-Modul verbunden ist.

## 28.10.2026 - Version 1.2

### Neu
- **Anker Solix X1 wird jetzt unterstützt!** Der Konfigurator erkennt X1-Systeme (Home Energy System) automatisch und listet das Hauptgerät sowie alle verbundenen Batteriemodule auf. Voraussetzung: Modul aktualisieren, dann den Konfigurator neu öffnen.

### Fixes
- Der Dokumentationslink der Instanzen führt jetzt zur Dokumentation auf deinsmarthome.info.

## 19.10.2026 - Version 1.1

### Fixes
- EC-Schlüsselgenerierung auf Windows behoben: Das Modul erzeugt jetzt zur Laufzeit eine minimale temporäre Konfigurationsdatei für OpenSSL — kein Suchen nach installierten Pfaden mehr, funktioniert auf allen Windows-Systemen unabhängig von der PHP-Installation.

## 17.10.2026 - Version 1.0

### Neu
- Unterstützung für Solarbank, Smart Plug, Smart Meter, Powerstation und Home Power Station.
- Konfigurator erkennt alle Geräte eines Anker-Kontos automatisch und legt Device-Instanzen per Klick an.
- Gerätetyp-spezifische Variablen werden beim Anlegen automatisch angelegt und nicht benötigte entfernt.
- EC-Schlüsselgenerierung sucht `openssl.cnf` dynamisch — funktioniert auf Windows und Linux ohne manuelle Konfiguration.
