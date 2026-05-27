# manage_sql for REDAXO 5

manage_sql is a backend addon for working with database structure, SQL queries,
YORM code generation and data migration directly in REDAXO.

Current focus in `1.0.0`:
- practical table and query tools
- safer backend actions (CSRF/method checks in key areas)
- interactive data migration helper with test-run

## Features

### Table and schema tools
- list and inspect `rex_` tables
- create tables with common column types
- edit existing table columns
- export table schema (`rex_sql_table` / schema dumper)

### Query builder
- build `SELECT` and `COUNT` queries with conditions
- optional test-run directly in backend
- generate ready-to-copy `rex_sql` code snippets

### JOIN builder
- interactive join definition across multiple tables
- choose join type and selected columns
- generate SQL/rex_sql snippets
- run query preview for fast feedback

### View builder
- test custom SQL queries
- create and remove SQL views (`rex_view_*`)

### Records manager
- browse table rows
- create/edit/delete records
- search, replace, and truncate actions
- column-based formatting for overview tables

### YORM generator
- generate model boilerplate from YForm tables
- generate form/list/query examples for faster integration

### Data migration helper (new)
- source table -> target table mapping UI
- mapping modes per target field:
    - source field
    - constant value
    - lookup mapping
- transforms: `none`, `trim`, `lower`, `upper`
- auto-mapping by field name
- test-run preview (50 rows) with validation and action hints
- migration run with batch size
- duplicate strategy:
    - insert
    - skip
    - update
- optional: apply only changed rows

## Requirements

- PHP `>=8.1`
- REDAXO `>=5.18.1`
- YForm `>=4.0.0`

## Installation

1. Install addon `manage_sql` via REDAXO installer.
2. Activate the addon.
3. Use backend with admin permissions.

## Backend pages

- Tables
- Neue Tabelle
- Query Builder
- YORM Generator
- REX SQLTable
- View Builder
- JOIN Builder
- Data Manager
- Datenmigration

## Notes

- The addon is intended for experienced backend users.
- Always run migrations on staging first.
- For large datasets, use batch migration and test-run before execute.

## Known limitations (1.0.0)

- Migration helper currently targets practical MVP workflows.
- Advanced transform pipelines and profile persistence are planned for next releases.

## Support

- Repository: https://github.com/skerbis/manage_sql

## License

- MIT, see `LICENSE`.

## Author

- Thomas Skerbis
