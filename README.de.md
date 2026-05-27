# manage_sql fuer REDAXO 5

manage_sql ist ein Backend-Addon fuer die Arbeit mit Datenbankstruktur,
SQL-Abfragen, YORM-Code-Generierung und Datenmigration direkt in REDAXO.

Schwerpunkt in Version `1.0.0`:
- praktische Tabellen- und Query-Werkzeuge
- sicherere Backend-Aktionen (CSRF/Method-Checks in zentralen Bereichen)
- interaktiver Datenmigrations-Helper mit Test-Run

## Funktionsumfang

### Tabellen- und Schema-Werkzeuge
- Auflistung und Analyse von `rex_` Tabellen
- Erstellen neuer Tabellen mit gaengigen Spaltentypen
- Bearbeiten bestehender Tabellenstrukturen
- Schema-Export (`rex_sql_table` / Schema-Dumper)

### Query Builder
- Erstellen von `SELECT` und `COUNT` Queries mit Bedingungen
- Optionaler Testlauf direkt im Backend
- Generierung kopierbarer `rex_sql` Code-Snippets

### JOIN Builder
- Interaktive JOIN-Definition ueber mehrere Tabellen
- Wahl von Join-Typ und ausgewaehlten Spalten
- SQL/rex_sql Code-Generierung
- Query-Vorschau zur schnellen Pruefung

### View Builder
- Testen eigener SQL-Queries
- Erstellen und Loeschen von SQL-Views (`rex_view_*`)

### Data Manager
- Datensaetze anzeigen
- Datensaetze erstellen/bearbeiten/loeschen
- Suchen, Ersetzen und Truncate-Aktionen
- Spaltentyp-basierte Formatierung in der Uebersicht

### YORM Generator
- Modell-Boilerplate aus YForm-Tabellen erzeugen
- Formular-/Listen-/Query-Beispiele fuer schnellere Integration

### Datenmigrations-Helper (neu)
- Mapping-UI von Quelltabelle auf Zieltabelle
- Mapping-Modi pro Zielfeld:
	- Quellfeld
	- Konstanter Wert
	- Lookup-Mapping
- Transformationen: `none`, `trim`, `lower`, `upper`
- Auto-Mapping nach Feldname
- Test-Run-Vorschau (50 Zeilen) mit Validierung und Aktionshinweisen
- Migration mit konfigurierbarer Batchgroesse
- Duplikatstrategie:
	- insert
	- skip
	- update
- Optional: nur geaenderte Zeilen anwenden

## Voraussetzungen

- PHP `>=8.1`
- REDAXO `>=5.18.1`
- YForm `>=4.0.0`

## Installation

1. Addon `manage_sql` ueber den REDAXO-Installer installieren.
2. Addon aktivieren.
3. Mit Admin-Rechten im Backend nutzen.

## Backend-Seiten

- Tabellen
- Neue Tabelle
- Query Builder
- YORM Generator
- REX SQLTable
- View Builder
- JOIN Builder
- Data Manager
- Datenmigration

## Hinweise

- Das Addon richtet sich an erfahrene Backend-Nutzer.
- Migrationen immer zuerst auf einer Staging-Umgebung pruefen.
- Bei grossen Datenmengen immer zuerst Test-Run und dann Batch-Migration.

## Bekannte Grenzen (1.0.0)

- Der Datenmigrations-Helper ist aktuell als pragmatischer MVP umgesetzt.
- Erweiterte Transformations-Pipelines und Profilspeicherung sind fuer kommende Versionen geplant.

## Support

- Repository: https://github.com/skerbis/manage_sql

## Lizenz

- MIT, siehe `LICENSE`.

## Autor

- Thomas Skerbis
