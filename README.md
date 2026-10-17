[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Symcon%20Version-8.1%20%3E-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Schnittcher/AnkerSolix/actions/workflows/style.yml/badge.svg)](https://github.com/Schnittcher/AnkerSolix/actions/workflows/style.yml)
[![Tests](https://github.com/Schnittcher/AnkerSolix/actions/workflows/tests.yml/badge.svg)](https://github.com/Schnittcher/AnkerSolix/actions/workflows/tests.yml)

# Anker Solix

Dieses Modul verbindet IP-Symcon mit der Anker Solix Cloud und stellt Echtzeit-Daten von Solarbanks, Smart Plugs, Smart Metern, Powerstations und Home-Power-Systemen als Symcon-Variablen bereit.

## Inhaltsverzeichnis

- [Anker Solix](#anker-solix)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Voraussetzungen](#1-voraussetzungen)
  - [2. Funktionsumfang](#2-funktionsumfang)
  - [3. Enthaltene Module](#3-enthaltene-module)
  - [4. Installation](#4-installation)
  - [5. Konfiguration in IP-Symcon](#5-konfiguration-in-ip-symcon)
  - [6. Spenden](#6-spenden)
  - [7. Lizenz](#7-lizenz)

## 1. Voraussetzungen

- IP-Symcon ab Version 8.1
- Anker-Konto mit eingerichteter Solix-Anlage
- PHP mit OpenSSL-Erweiterung (inkl. EC-Unterstützung)

## 2. Funktionsumfang

- Echtzeit-Messwerte von Solarbanks, Smart Plugs, Smart Metern, Powerstations und Home-Power-Systemen
- Automatische Geräteerkennung per Konfigurator
- Verschlüsselte Anmeldung an der Anker Cloud (ECDH + AES-256-CBC)
- Gerätetyp-spezifische Variablen werden automatisch angelegt

Die einzelnen Funktionen stehen bei den Instanzen unter "Enthaltene Module" und in der README des jeweiligen Moduls.

## 3. Enthaltene Module

- [Anker Solix IO](AnkerSolixIO/README.md)
  - Splitter-Modul, das die Verbindung zur Anker Solix Cloud herstellt und den Auth-Token an alle Kindmodule weitergibt.
- [Anker Solix Konfigurator](AnkerSolixConfigurator/README.md)
  - Liest alle Geräte des Anker-Kontos aus und ermöglicht das Anlegen von Device-Instanzen per Klick.
- [Anker Solix Device](AnkerSolixDevice/README.md)
  - Liest Echtzeit-Messwerte eines einzelnen Geräts zyklisch aus der Cloud und stellt sie als Symcon-Variablen bereit.

## 4. Installation

Die Installation erfolgt über den IP-Symcon Module Store.

## 5. Konfiguration in IP-Symcon

1. **Anker Solix Konfigurator** anlegen — IP-Symcon fragt automatisch, ob ein vorhandenes IO-Modul ausgewählt oder ein neues angelegt werden soll.
2. Im **Anker Solix IO** E-Mail-Adresse, Passwort und Land eintragen, dann „Verbindung testen" klicken.
3. Den **Konfigurator** öffnen — alle Geräte der Anlage werden aufgelistet. Gewünschtes Gerät auswählen und **Erstellen** klicken.

## 6. Spenden

Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 7. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
