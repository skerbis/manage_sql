# Table Builder für REDAXO 5

Das Table Builder Addon ermöglicht die einfache Erstellung und Verwaltung von Datenbanktabellen direkt im REDAXO-Backend.

## Features

- Visuelle Erstellung von Datenbanktabellen
- Automatische Erstellung von id-Spalten (auto increment)
- Unterstützung der gängigsten MySQL-Spaltentypen
- Automatische Generierung von rex_sql_table Code
- SQL Schema Export

## Installation

1. Das Addon-Verzeichnis `table_builder` in `/redaxo/src/addons/` erstellen
2. Die Addon-Dateien in das Verzeichnis kopieren
3. Das Addon im REDAXO-Backend unter "Installer" installieren und aktivieren

## Systemvoraussetzungen

* PHP 8.1 oder höher
* REDAXO 5.18.1 oder höher

## Verwendung

### Neue Tabelle erstellen

1. Im REDAXO-Backend zu "Table Builder" > "Neue Tabelle" navigieren
2. Einen Tabellennamen eingeben (das Prefix "rex_" wird automatisch hinzugefügt)
3. Über "Spalte hinzufügen" beliebig viele Spalten anlegen
4. Für jede Spalte:
   - Namen festlegen
   - Typ auswählen
   - Optional: Standardwert definieren
   - Optional: "Nullable" aktivieren/deaktivieren
5. "Tabelle erstellen" klicken
6. Nach erfolgreicher Erstellung wird der generierte rex_sql_table Code angezeigt

### Tabellenübersicht

Unter "Table Builder" > "Tabellen" werden alle vorhandenen Tabellen mit Prefix "rex_" angezeigt.

### SQL Export

Unter "Table Builder" > "SQL Export" kann das Schema bestehender Tabellen als rex_sql_table Code exportiert werden.

## Verfügbare Spaltentypen

- VARCHAR(255) - Für Texte bis 255 Zeichen
- TEXT - Für längere Texte
- INT(10) - Für Ganzzahlen
- INT(10) UNSIGNED - Für positive Ganzzahlen
- DECIMAL(10,2) - Für Dezimalzahlen
- DATETIME - Für Datum mit Uhrzeit
- DATE - Für Datum
- TIME - Für Uhrzeit
- TINYINT(1) - Für Boolean-Werte
- MEDIUMTEXT - Für mittellange Texte
- LONGTEXT - Für sehr lange Texte

## Support & Bugs

Bitte Bugs und Feature-Requests im GitHub Repository melden:
https://github.com/your/repo

## Lizenz

MIT License

## Autor

YOUR NAME

## Credits

- [REDAXO](https://redaxo.org)
- Entwickelt mit rex_sql_table von [Gregor Harlan](https://github.com/gharlan)
