<?php

class manage_sql
{
    private rex_sql_table $table;
    private string $name;
    private array $columns = [];
    private array $indexes = [];
    private array $foreignKeys = [];

    public function __construct(string $tableName)
    {
        $this->name = $tableName;
        $this->table = rex_sql_table::get($tableName);
    }

    public function addColumn(string $name, string $type, bool $nullable = true, ?string $default = null, ?string $extra = null, ?string $comment = null): self
    {
        $this->columns[$name] = new rex_sql_column(
            $name,
            $type,
            $nullable,
            $default,
            $extra,
            $comment
        );
        
        return $this;
    }

    public function removeColumn(string $name): self
    {
        unset($this->columns[$name]);
        return $this;
    }

    public function addIndex(string $name, array $columns, string $type = rex_sql_index::INDEX): self
    {
        $this->indexes[$name] = new rex_sql_index($name, $columns, $type);
        return $this;
    }

    public function addForeignKey(string $name, string $refTable, array $columns, string $onUpdate = rex_sql_foreign_key::RESTRICT, string $onDelete = rex_sql_foreign_key::RESTRICT): self
    {
        $this->foreignKeys[$name] = new rex_sql_foreign_key($name, $refTable, $columns, $onUpdate, $onDelete);
        return $this;
    }

    public function create(): bool
    {
        try {
            // Ensure table exists
            if (!$this->table->exists()) {
                // Add primary ID column automatically
                $this->table->ensurePrimaryIdColumn();
                
                // Add all defined columns
                foreach ($this->columns as $column) {
                    $this->table->ensureColumn($column);
                }
                
                // Add all defined indexes
                foreach ($this->indexes as $index) {
                    $this->table->ensureIndex($index);
                }
                
                // Add all defined foreign keys
                foreach ($this->foreignKeys as $foreignKey) {
                    $this->table->ensureForeignKey($foreignKey);
                }
                
                // Create the table
                $this->table->ensure();
                
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            // Log error and return false
            rex_logger::logException($e);
            return false;
        }
    }

    public function exists(): bool
    {
        return $this->table->exists();
    }

    public function getTable(): rex_sql_table
    {
        return $this->table;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public static function getCommonColumnTypes(): array
    {
        return [
            'varchar(255)' => 'Text (VARCHAR)',
            'text' => 'Langer Text (TEXT)',
            'int(10)' => 'Ganzzahl (INT)',
            'int(10) unsigned' => 'Positive Ganzzahl (UNSIGNED INT)',
            'decimal(10,2)' => 'Dezimalzahl (DECIMAL)',
            'datetime' => 'Datum & Uhrzeit (DATETIME)',
            'date' => 'Datum (DATE)',
            'time' => 'Uhrzeit (TIME)',
            'tinyint(1)' => 'Boolean (TINYINT)',
            'mediumtext' => 'Mittellanger Text (MEDIUMTEXT)',
            'longtext' => 'Sehr langer Text (LONGTEXT)',
        ];
    }

    public function exportSchema(): string
    {
        $dumper = new rex_sql_schema_dumper();
        return $dumper->dumpTable($this->table);
    }
}
