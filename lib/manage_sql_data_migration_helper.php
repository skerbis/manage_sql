<?php

class manage_sql_data_migration_helper
{
    /** @var array<string, array<int, array{name: string, type: string, nullable: bool, autoIncrement: bool}>> */
    private array $tableColumnsCache = [];

    /** @var array<string, array<string, mixed>> */
    private array $lookupCache = [];

    /**
     * @return string[]
     */
    public function getRexTables(): array
    {
        $tables = rex_sql::factory()->getTablesAndViews();

        return array_values(array_filter($tables, static function ($table): bool {
            return str_starts_with((string) $table, 'rex_');
        }));
    }

    /**
     * @return array<int, array{name: string, type: string, nullable: bool, autoIncrement: bool}>
     */
    public function getTableColumns(string $table): array
    {
        if (isset($this->tableColumnsCache[$table])) {
            return $this->tableColumnsCache[$table];
        }

        $columns = rex_sql::showColumns($table);
        $result = [];

        foreach ($columns as $column) {
            $name = (string) ($column['name'] ?? '');
            $type = (string) ($column['type'] ?? '');
            $nullRaw = (string) ($column['null'] ?? ($column['Null'] ?? ''));
            $extra = strtolower((string) ($column['extra'] ?? ''));

            if ('' === $name) {
                continue;
            }

            $result[] = [
                'name' => $name,
                'type' => $type,
                'nullable' => 'yes' === strtolower($nullRaw),
                'autoIncrement' => str_contains($extra, 'auto_increment'),
            ];
        }

        $this->tableColumnsCache[$table] = $result;

        return $result;
    }

    /**
     * @param string[] $sourceFieldNames
     * @param string[] $targetFieldNames
     * @return array<string, array{mode: string, source: string, constant: string, transform: string, lookup_table: string, lookup_source: string, lookup_target: string}>
     */
    public function suggestAutoMapping(array $sourceFieldNames, array $targetFieldNames): array
    {
        $mapping = [];

        foreach ($targetFieldNames as $targetField) {
            $sourceField = in_array($targetField, $sourceFieldNames, true) ? $targetField : '';

            $mapping[$targetField] = [
                'mode' => '' !== $sourceField ? 'source' : 'constant',
                'source' => $sourceField,
                'constant' => '',
                'transform' => 'none',
                'lookup_table' => '',
                'lookup_source' => '',
                'lookup_target' => '',
            ];
        }

        return $mapping;
    }

    /**
     * @param array<string, array{mode?: string, source?: string, constant?: string, transform?: string, lookup_table?: string, lookup_source?: string, lookup_target?: string}> $mapping
     * @param array{duplicate_strategy?: string, match_field?: string, only_changes?: bool} $options
     * @return array{rows: array<int, array<string, mixed>>, summary: array{checked: int, valid: int, invalid: int, insert: int, update: int, skipped: int}}
     */
    public function preview(string $sourceTable, string $targetTable, array $mapping, int $limit = 50, array $options = []): array
    {
        $sourceRows = $this->fetchRows($sourceTable, $limit, 0);
        $targetColumns = $this->getWritableTargetColumns($targetTable);
        $targetColumnMap = $this->createColumnMap($targetColumns);

        $matchField = (string) ($options['match_field'] ?? '');
        $duplicateStrategy = (string) ($options['duplicate_strategy'] ?? 'insert');
        $onlyChanges = (bool) ($options['only_changes'] ?? false);

        $rows = [];
        $valid = 0;
        $invalid = 0;
        $insertCount = 0;
        $updateCount = 0;
        $skippedCount = 0;

        foreach ($sourceRows as $sourceRow) {
            $mappedRow = $this->mapRow($sourceRow, $mapping, $targetColumns);
            $errors = $this->validateMappedRow($mappedRow, $targetColumns);

            $decision = $this->decideAction(
                $targetTable,
                $mappedRow,
                $targetColumns,
                $targetColumnMap,
                $matchField,
                $duplicateStrategy,
                $onlyChanges
            );

            if ([] !== $decision['errors']) {
                $errors = array_merge($errors, $decision['errors']);
            }

            if ([] === $errors) {
                ++$valid;
            } else {
                ++$invalid;
            }

            if ('insert' === $decision['action']) {
                ++$insertCount;
            } elseif ('update' === $decision['action']) {
                ++$updateCount;
            } else {
                ++$skippedCount;
            }

            $rows[] = [
                'source' => $sourceRow,
                'mapped' => $mappedRow,
                'errors' => $errors,
                'action' => $decision['action'],
                'reason' => $decision['reason'],
            ];
        }

        return [
            'rows' => $rows,
            'summary' => [
                'checked' => count($sourceRows),
                'valid' => $valid,
                'invalid' => $invalid,
                'insert' => $insertCount,
                'update' => $updateCount,
                'skipped' => $skippedCount,
            ],
        ];
    }

    /**
     * @param array<string, array{mode?: string, source?: string, constant?: string, transform?: string, lookup_table?: string, lookup_source?: string, lookup_target?: string}> $mapping
     * @param array{duplicate_strategy?: string, match_field?: string, only_changes?: bool} $options
    * @return array{total:int, inserted:int, updated:int, failed:int, skipped:int, errors: array<int, string>}
     */
    public function migrate(string $sourceTable, string $targetTable, array $mapping, int $batchSize = 500, array $options = []): array
    {
        $batchSize = max(1, min(5000, $batchSize));

        $targetColumns = $this->getWritableTargetColumns($targetTable);
        $targetColumnMap = $this->createColumnMap($targetColumns);

        $matchField = (string) ($options['match_field'] ?? '');
        $duplicateStrategy = (string) ($options['duplicate_strategy'] ?? 'insert');
        $onlyChanges = (bool) ($options['only_changes'] ?? false);

        $total = (int) rex_sql::factory()->getValue(
            'SELECT COUNT(*) FROM `' . rex_escape($sourceTable) . '`'
        );

        $inserted = 0;
        $updated = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $rows = $this->fetchRows($sourceTable, $batchSize, $offset);

            foreach ($rows as $sourceRow) {
                $mappedRow = $this->mapRow($sourceRow, $mapping, $targetColumns);
                $rowErrors = $this->validateMappedRow($mappedRow, $targetColumns);

                $decision = $this->decideAction(
                    $targetTable,
                    $mappedRow,
                    $targetColumns,
                    $targetColumnMap,
                    $matchField,
                    $duplicateStrategy,
                    $onlyChanges
                );

                if ([] !== $decision['errors']) {
                    $rowErrors = array_merge($rowErrors, $decision['errors']);
                }

                if ([] !== $rowErrors) {
                    ++$skipped;
                    $errors[] = 'Validierung: ' . implode('; ', $rowErrors);
                    continue;
                }

                if ('skip' === $decision['action']) {
                    ++$skipped;
                    continue;
                }

                try {
                    $sql = rex_sql::factory();
                    $sql->setTable($targetTable);

                    if ('update' === $decision['action'] && '' !== $decision['matchField']) {
                        $sql->setWhere([$decision['matchField'] => $decision['matchValue']]);
                        $sql->setValues($mappedRow);
                        $sql->update();
                        ++$updated;
                    } else {
                        $sql->setValues($mappedRow);
                        $sql->insert();
                        ++$inserted;
                    }
                } catch (rex_sql_exception $e) {
                    ++$failed;
                    $errors[] = $e->getMessage();
                }
            }
        }

        return [
            'total' => $total,
            'inserted' => $inserted,
            'updated' => $updated,
            'failed' => $failed,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 50),
        ];
    }

    /**
     * @param array<int, array{name: string, type: string, nullable: bool, autoIncrement: bool}> $targetColumns
    * @param array<string, array{mode?: string, source?: string, constant?: string, transform?: string, lookup_table?: string, lookup_source?: string, lookup_target?: string}> $mapping
     * @param array<string, mixed> $sourceRow
     * @return array<string, mixed>
     */
    private function mapRow(array $sourceRow, array $mapping, array $targetColumns): array
    {
        $mapped = [];

        foreach ($targetColumns as $targetColumn) {
            $targetField = $targetColumn['name'];
            $rule = $mapping[$targetField] ?? [];

            $mode = (string) ($rule['mode'] ?? 'source');
            $sourceField = (string) ($rule['source'] ?? '');
            $constant = (string) ($rule['constant'] ?? '');
            $transform = (string) ($rule['transform'] ?? 'none');
            $lookupTable = (string) ($rule['lookup_table'] ?? '');
            $lookupSource = (string) ($rule['lookup_source'] ?? '');
            $lookupTarget = (string) ($rule['lookup_target'] ?? '');

            $value = null;
            if ('constant' === $mode) {
                $value = $constant;
            } elseif ('lookup' === $mode) {
                $lookupValue = '' !== $sourceField && array_key_exists($sourceField, $sourceRow) ? $sourceRow[$sourceField] : null;
                $value = $this->resolveLookupValue($lookupTable, $lookupSource, $lookupTarget, $lookupValue);
            } elseif ('' !== $sourceField && array_key_exists($sourceField, $sourceRow)) {
                $value = $sourceRow[$sourceField];
            }

            $mapped[$targetField] = $this->applyTransform($value, $transform);
        }

        return $mapped;
    }

    /**
     * @return mixed
     */
    private function resolveLookupValue(string $table, string $sourceColumn, string $targetColumn, $lookupValue)
    {
        if ('' === $table || '' === $sourceColumn || '' === $targetColumn || null === $lookupValue || '' === $lookupValue) {
            return null;
        }

        if (!str_starts_with($table, 'rex_')) {
            return null;
        }

        $key = $table . '|' . $sourceColumn . '|' . $targetColumn;
        if (!isset($this->lookupCache[$key])) {
            try {
                $map = [];
                $sql = 'SELECT `' . rex_escape($sourceColumn) . '` AS src, `' . rex_escape($targetColumn) . '` AS dst FROM `' . rex_escape($table) . '`';
                $rows = rex_sql::factory()->getArray($sql);
                foreach ($rows as $row) {
                    $src = (string) ($row['src'] ?? '');
                    $map[$src] = $row['dst'] ?? null;
                }
                $this->lookupCache[$key] = $map;
            } catch (rex_sql_exception $e) {
                $this->lookupCache[$key] = [];
            }
        }

        $needle = (string) $lookupValue;

        return $this->lookupCache[$key][$needle] ?? null;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function applyTransform($value, string $transform)
    {
        if (!is_string($value)) {
            return $value;
        }

        return match ($transform) {
            'trim' => trim($value),
            'lower' => mb_strtolower($value),
            'upper' => mb_strtoupper($value),
            default => $value,
        };
    }

    /**
     * @param array<string, mixed> $mappedRow
     * @param array<int, array{name: string, type: string, nullable: bool, autoIncrement: bool}> $targetColumns
     * @return string[]
     */
    private function validateMappedRow(array $mappedRow, array $targetColumns): array
    {
        $errors = [];

        foreach ($targetColumns as $targetColumn) {
            $name = $targetColumn['name'];
            $nullable = $targetColumn['nullable'];
            $type = strtolower($targetColumn['type']);
            $value = $mappedRow[$name] ?? null;

            if (!$nullable && (null === $value || '' === $value)) {
                $errors[] = 'Pflichtfeld leer: ' . $name;
                continue;
            }

            if (null === $value || '' === $value) {
                continue;
            }

            if ((str_contains($type, 'int') || str_contains($type, 'decimal') || str_contains($type, 'float')) && !is_numeric((string) $value)) {
                $errors[] = 'Numerischer Typ erwartet bei ' . $name;
            }
        }

        return $errors;
    }

    /**
     * @param array<int, array{name: string, type: string, nullable: bool, autoIncrement: bool}> $targetColumns
     * @return array<string, array{name: string, type: string, nullable: bool, autoIncrement: bool}>
     */
    private function createColumnMap(array $targetColumns): array
    {
        $map = [];
        foreach ($targetColumns as $column) {
            $map[$column['name']] = $column;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $mappedRow
     * @param array<int, array{name: string, type: string, nullable: bool, autoIncrement: bool}> $targetColumns
     * @param array<string, array{name: string, type: string, nullable: bool, autoIncrement: bool}> $targetColumnMap
     * @return array{action: string, reason: string, errors: string[], matchField: string, matchValue: mixed}
     */
    private function decideAction(
        string $targetTable,
        array $mappedRow,
        array $targetColumns,
        array $targetColumnMap,
        string $matchField,
        string $duplicateStrategy,
        bool $onlyChanges
    ): array {
        $duplicateStrategy = in_array($duplicateStrategy, ['insert', 'skip', 'update'], true) ? $duplicateStrategy : 'insert';

        if ('' === $matchField) {
            return ['action' => 'insert', 'reason' => 'no_match_field', 'errors' => [], 'matchField' => '', 'matchValue' => null];
        }

        if (!isset($targetColumnMap[$matchField])) {
            return ['action' => 'insert', 'reason' => 'invalid_match_field', 'errors' => ['Ungueltiges Match-Feld.'], 'matchField' => '', 'matchValue' => null];
        }

        $matchValue = $mappedRow[$matchField] ?? null;
        if (null === $matchValue || '' === $matchValue) {
            return ['action' => 'insert', 'reason' => 'empty_match_value', 'errors' => ['Match-Feld ist leer.'], 'matchField' => $matchField, 'matchValue' => $matchValue];
        }

        $existingRow = $this->findExistingByMatchField($targetTable, $matchField, $matchValue);
        if (null === $existingRow) {
            return ['action' => 'insert', 'reason' => 'not_found', 'errors' => [], 'matchField' => $matchField, 'matchValue' => $matchValue];
        }

        if ($onlyChanges && $this->isSameAsExistingRow($mappedRow, $existingRow, $targetColumns)) {
            return ['action' => 'skip', 'reason' => 'unchanged', 'errors' => [], 'matchField' => $matchField, 'matchValue' => $matchValue];
        }

        if ('skip' === $duplicateStrategy) {
            return ['action' => 'skip', 'reason' => 'duplicate_skip', 'errors' => [], 'matchField' => $matchField, 'matchValue' => $matchValue];
        }

        if ('update' === $duplicateStrategy) {
            return ['action' => 'update', 'reason' => 'duplicate_update', 'errors' => [], 'matchField' => $matchField, 'matchValue' => $matchValue];
        }

        return ['action' => 'insert', 'reason' => 'duplicate_insert', 'errors' => [], 'matchField' => $matchField, 'matchValue' => $matchValue];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findExistingByMatchField(string $targetTable, string $matchField, $matchValue): ?array
    {
        try {
            $sql = rex_sql::factory();
            $query = 'SELECT * FROM `' . rex_escape($targetTable) . '` WHERE `' . rex_escape($matchField) . '` = :match_value LIMIT 1';
            $sql->setQuery($query, ['match_value' => $matchValue]);
            if ($sql->getRows() > 0) {
                return $sql->getRow();
            }
        } catch (rex_sql_exception $e) {
            return null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $mappedRow
     * @param array<string, mixed> $existingRow
     * @param array<int, array{name: string, type: string, nullable: bool, autoIncrement: bool}> $targetColumns
     */
    private function isSameAsExistingRow(array $mappedRow, array $existingRow, array $targetColumns): bool
    {
        foreach ($targetColumns as $column) {
            $name = $column['name'];
            $newValue = $mappedRow[$name] ?? null;
            $oldValue = $existingRow[$name] ?? null;

            if ((string) $newValue !== (string) $oldValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, array{name: string, type: string, nullable: bool, autoIncrement: bool}>
     */
    private function getWritableTargetColumns(string $targetTable): array
    {
        $columns = $this->getTableColumns($targetTable);

        return array_values(array_filter($columns, static function (array $column): bool {
            if ($column['autoIncrement']) {
                return false;
            }

            return 'id' !== $column['name'];
        }));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(string $sourceTable, int $limit, int $offset): array
    {
        $sql = 'SELECT * FROM `' . rex_escape($sourceTable) . '` LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        return rex_sql::factory()->getArray($sql);
    }
}
