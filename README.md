[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Symcon%20Version-8.1%20%3E-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

# Anker Solix

Dieses Modul verbindet IP-Symcon mit der Anker Solix Cloud und stellt Echtzeit-Daten von Solarbanks, Smart Plugs, Smart Metern, Powerstations und Home-Power-Systemen als Symcon-Variablen bereit.

## Inhaltsverzeichnis

- [Anker Solix](#anker-solix)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Voraussetzungen](#1-voraussetzungen)
  - [2. Enthaltene Module](#2-enthaltene-module)
  - [3. Funktionsweise](#3-funktionsweise)
  - [4. Einrichtung](#4-einrichtung)
  - [5. Spenden](#5-spenden)
  - [6. Lizenz](#6-lizenz)

## 1. Voraussetzungen

- IP-Symcon ab Version 8.1
- Anker-Konto mit eingerichteter Solix-Anlage
- PHP mit OpenSSL-Erweiterung (inkl. EC-Unterstützung)

## 2. Enthaltene Module

| Modul | Typ | Beschreibung |
|---|---|---|
| [Anker Solix IO](AnkerSolixIO/README.md) | Splitter | Verwaltet Zugangsdaten und API-Kommunikation |
| [Anker Solix Konfigurator](AnkerSolixConfigurator/README.md) | Konfigurator | Erkennt alle Geräte der Anlage automatisch |
| [Anker Solix Device](AnkerSolixDevice/README.md) | Gerät | Liest Messwerte eines einzelnen Geräts aus |

## 3. Funktionsweise

```
IP-Symcon
    │
    ├── Anker Solix IO          ← Authentifizierung, Token-Verwaltung
    │       │
    │       ├── Anker Solix Konfigurator   ← Gerätesuche
    │       ├── Anker Solix Device         ← Solarbank E1600
    │       └── Anker Solix Device         ← Smart Plug
    │
    └── (weitere IO-Instanzen für weitere Konten möglich)
```

Das **IO-Modul** übernimmt die verschlüsselte Anmeldung an der Anker Cloud (ECDH-Schlüsselaustausch + AES-256-CBC) und stellt den Auth-Token über den Symcon-Datenfluss allen Kindmodulen zur Verfügung.

Der **Konfigurator** liest alle verfügbaren Sites und Geräte aus und ermöglicht das Anlegen von Device-Instanzen per Klick.

Das **Device-Modul** fragt die aktuellen Messwerte zyklisch ab und legt die passenden Variablen je nach Gerätetyp automatisch an.

## 4. Einrichtung

1. **Anker Solix Konfigurator** anlegen  
   → IP-Symcon fragt automatisch, ob ein vorhandenes IO-Modul ausgewählt oder ein neues angelegt werden soll

2. Im **Anker Solix IO** E-Mail-Adresse, Passwort und Land eintragen  
   → „Verbindung testen" klicken — bei Erfolg wird der Status grün

3. Den **Konfigurator** öffnen — alle Geräte der Anlage werden automatisch aufgelistet  
   → Gewünschtes Gerät auswählen → **Erstellen**  
   → Die Device-Instanz wird mit Site-ID, Seriennummer und Gerätetyp vorbefüllt angelegt und startet sofort mit dem Abrufen der Daten

## 5. Spenden

Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EK4JRP87XLSHW" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a> <a href="https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share" target="_blank">Amazon Wunschzettel</a>

## 7. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
