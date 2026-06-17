[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Symcon%20Version-8.1%20%3E-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

# Anker Solix IO

Splitter-Modul, das die Verbindung zur Anker Solix Cloud herstellt und den Auth-Token über den Symcon-Datenfluss an alle Kindmodule (Konfigurator, Device) weitergibt.

## Inhaltsverzeichnis

- [Anker Solix IO](#anker-solix-io)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Voraussetzungen](#1-voraussetzungen)
  - [2. Konfiguration](#2-konfiguration)
  - [3. PHP-Befehlsreferenz](#3-php-befehlsreferenz)
  - [4. Lizenz](#4-lizenz)

## 1. Voraussetzungen

- IP-Symcon ab Version 8.1
- Anker-Konto mit eingerichteter Solix-Anlage
- PHP mit OpenSSL inkl. EC-Kurven-Unterstützung (`prime256v1`)

## 2. Konfiguration

| Feld | Beschreibung |
|---|---|
| **E-Mail Adresse** | E-Mail-Adresse des Anker-Kontos |
| **Passwort** | Passwort des Anker-Kontos |
| **Land** | Länderkürzel (z. B. `DE`, `AT`, `CH`) |

Nach dem Speichern der Zugangsdaten kann über **„Verbindung testen"** geprüft werden, ob die Anmeldung an der Anker Cloud erfolgreich ist. Bei Erfolg wechselt der Status auf grün.

Die Authentifizierung erfolgt über ECDH-Schlüsselaustausch (prime256v1) und AES-256-CBC-verschlüsseltes Passwort — entsprechend der offiziellen Anker-API. Der generierte Auth-Token wird automatisch erneuert.

## 3. PHP-Befehlsreferenz

**ANKERSOLIXIO_TestConnection(integer $InstanceID)**  
Löscht den gespeicherten Token und führt eine neue Anmeldung durch. Gibt eine Erfolgsmeldung oder den Fehlertext aus.

## 4. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
