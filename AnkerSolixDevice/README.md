[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Symcon%20Version-8.1%20%3E-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

# Anker Solix Device

Liest Echtzeit-Messwerte eines einzelnen Anker Solix Geräts zyklisch aus der Cloud aus und stellt sie als Symcon-Variablen bereit.

## Inhaltsverzeichnis

- [Anker Solix Device](#anker-solix-device)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Voraussetzungen](#1-voraussetzungen)
  - [2. Konfiguration](#2-konfiguration)
  - [3. Variablen](#3-variablen)
  - [4. PHP-Befehlsreferenz](#4-php-befehlsreferenz)
  - [5. Lizenz](#5-lizenz)

## 1. Voraussetzungen

- IP-Symcon ab Version 8.1
- Eingerichtetes **Anker Solix IO**-Modul als übergeordnete Instanz  
  *(wird beim Anlegen über den Konfigurator automatisch verbunden)*

## 2. Konfiguration

| Feld | Beschreibung |
|---|---|
| **Name** | Anzeigename des Geräts (vom Konfigurator gesetzt) |
| **Typ** | Gerätetyp — bestimmt welche Variablen angelegt werden |
| **Site-ID** | Interne Anlagen-ID (vom Konfigurator gesetzt) |
| **Seriennummer** | Seriennummer des Geräts (vom Konfigurator gesetzt) |
| **Intervall (Sekunden)** | Aktualisierungsintervall, kein Limit |

## 3. Variablen

Es werden nur die für den jeweiligen Gerätetyp relevanten Variablen angelegt.

### Solarbank / Home Power Station

| Ident | Name | Einheit |
|---|---|---|
| `SolarPower` | Solarleistung | W |
| `BatteryPower` | Batterieleistung | W (+ laden / − entladen) |
| `OutputPower` | Ausgangsleistung | W |
| `HomeLoad` | Hausverbrauch | W |
| `SOC` | Batterieladung | % |
| `BatteryEnergy` | Batterieenergie | kWh |
| `TotalEnergy` | Energie gesamt | kWh |

### Smart Plug / Smart Meter / Powerstation

| Ident | Name | Einheit |
|---|---|---|
| `Power` | Leistung | W |
| `Voltage` | Spannung | V |
| `Current` | Strom | A |
| `SwitchState` | Schalter | — (nur Smart Plug) |
| `TotalEnergy` | Energie gesamt | kWh |


## 4. PHP-Befehlsreferenz

**ANKERSOLIX_UpdateData(integer $InstanceID)**  
Löst eine sofortige Aktualisierung der Messwerte aus, unabhängig vom konfigurierten Intervall.

**ANKERSOLIX_DebugData(integer $InstanceID)**  
Schreibt die rohe API-Antwort (Scene und Device-Objekt) ins Symcon-Nachrichtenarchiv. Dient zur Diagnose bei fehlenden oder falschen Werten.

## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
