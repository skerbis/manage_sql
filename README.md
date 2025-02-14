# Table Builder für REDAXO 5

Table Builder ist ein REDAXO-AddOn zur vereinfachten Arbeit mit Datenbanktabellen, rex_sql und YOrm. Es unterstützt bei der korrekten Erstellung von Datenbankabfragen und der Generierung von YOrm-Modellen.

## Features

### Datenbank-Management
- Visuelle Erstellung und Bearbeitung von Datenbanktabellen
- Automatische Erstellung von korrekten Tabellenstrukturen inkl. Primärschlüssel
- Verwaltung von Spaltentypen, Indizes und Fremdschlüsseln

### Query Builder
- Visuelle Erstellung von rex_sql Queries
- Automatische Generierung von sicherem Code
- Unterstützung für komplexe WHERE-Bedingungen
- Live-Vorschau der generierten Abfragen
- Testmöglichkeit direkt im Backend

### YOrm Generator
- Automatische Generierung von YOrm-Model-Klassen
- Erstellung von Type-Hints für bessere IDE-Unterstützung
- Generierung von Getter-Methoden
- Beispielcode für CRUD-Operationen
- Relation-Handling

## Installation

1. Im REDAXO-Installer das AddOn "manage_sql" herunterladen
2. Installation und Aktivierung durchführen
3. Rechte für Administratoren setzen

## Systemvoraussetzungen

* PHP 8.1 oder höher
* REDAXO 5.18.1 oder höher
* YForm 4.0.0 oder höher

## Anwendungsbereiche

### Tabellenerstellung
- Strukturierte Anlage neuer Datenbanktabellen
- Verwaltung bestehender Tabellenstrukturen
- Export von Tabellendefinitionen als rex_sql_table Code

### Query-Erstellung
- Generierung sicherer Datenbankabfragen
- Unterstützung verschiedener Query-Typen (SELECT, INSERT, UPDATE, DELETE)
- Automatische Parameterbindung
- Beispiele für komplexe Abfragen

### YOrm-Integration
- Model-Generierung für YForm-Tabellen
- Erzeugen typsicherer Datenzugriffsmethoden
- Beispiele für Relation-Handling
- Generierung von Formular- und Listen-Code

## Code-Beispiele

### Tabellendefinition
```php
// Generierter Code für eine neue Tabelle
rex_sql_table::get(rex::getTable('example'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('title', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('description', 'text'))
    ->ensure();
```

### Query-Beispiel
```php
// Generierte sichere Abfrage
$sql = rex_sql::factory();
$sql->setQuery('SELECT * FROM rex_example WHERE status = :status', ['status' => 1]);
```

### YOrm-Model
```php
// Generiertes YOrm-Model
class Example extends rex_yform_manager_dataset
{
    public function getTitle(): string 
    {
        return $this->getValue('title');
    }
    
    public static function getAll(): rex_yform_manager_collection
    {
        return self::query()->find();
    }
}
```

## Unterstützung & Bugs

Fehler bitte im GitHub-Repository melden:
https://github.com/alexplusde/manage_sql

## Lizenz

MIT Lizenz, siehe [LICENSE.md](LICENSE.md)

## Autor
Thomas Skerbis

## Credits

- [REDAXO](https://redaxo.org)
- Entwickelt mit rex_sql_table von [Gregor Harlan](https://github.com/gharlan)
