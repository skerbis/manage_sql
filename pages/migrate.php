<?php

if (!class_exists('manage_sql_data_migration_helper')) {
    require_once rex_path::addon('manage_sql', 'lib/manage_sql_data_migration_helper.php');
}

$helper = new manage_sql_data_migration_helper();
$csrfToken = rex_csrf_token::factory('manage_sql_data_migrate');

$content = '';
$error = '';
$message = '';
$previewData = null;
$migrateResult = null;

$tables = $helper->getRexTables();

$sourceTable = rex_request('source_table', 'string', '');
$targetTable = rex_request('target_table', 'string', '');
$batchSize = rex_post('batch_size', 'int', 500);
$mapping = rex_post('mapping', 'array', []);
$duplicateStrategy = rex_post('duplicate_strategy', 'string', 'insert');
$matchField = rex_post('match_field', 'string', '');
$onlyChanges = rex_post('only_changes', 'int', 0) === 1;
$action = rex_post('action', 'string', '');

if ('' !== $sourceTable && !in_array($sourceTable, $tables, true)) {
    $sourceTable = '';
}
if ('' !== $targetTable && !in_array($targetTable, $tables, true)) {
    $targetTable = '';
}

$sourceColumns = '' !== $sourceTable ? $helper->getTableColumns($sourceTable) : [];
$targetColumns = '' !== $targetTable ? $helper->getTableColumns($targetTable) : [];

$sourceFieldNames = array_map(static fn (array $c): string => $c['name'], $sourceColumns);
$writableTargetColumns = array_values(array_filter($targetColumns, static fn (array $c): bool => !$c['autoIncrement'] && 'id' !== $c['name']));
$targetFieldNames = array_map(static fn (array $c): string => $c['name'], $writableTargetColumns);
$targetAllFieldNames = array_map(static fn (array $c): string => $c['name'], $targetColumns);

$options = [
    'duplicate_strategy' => in_array($duplicateStrategy, ['insert', 'skip', 'update'], true) ? $duplicateStrategy : 'insert',
    'match_field' => in_array($matchField, $targetAllFieldNames, true) ? $matchField : '',
    'only_changes' => $onlyChanges,
];

if ('post' === strtolower(rex_request_method()) && '' !== $action) {
    if (!$csrfToken->isValid()) {
        $error = rex_i18n::msg('csrf_token_invalid');
    } elseif ('' === $sourceTable || '' === $targetTable) {
        $error = 'Bitte Quell- und Zieltabelle wählen.';
    } elseif ($sourceTable === $targetTable) {
        $error = 'Quelle und Ziel dürfen nicht identisch sein.';
    } else {
        if ('auto_map' === $action) {
            $mapping = $helper->suggestAutoMapping($sourceFieldNames, $targetFieldNames);
            $message = 'Auto-Mapping wurde erstellt.';
        } elseif ('preview' === $action) {
            $previewData = $helper->preview($sourceTable, $targetTable, $mapping, 50, $options);
            $message = 'Test-Run wurde ausgeführt.';
        } elseif ('migrate' === $action) {
            $migrateResult = $helper->migrate($sourceTable, $targetTable, $mapping, $batchSize, $options);
            $message = 'Migration abgeschlossen.';
        }
    }
}

if ('' !== $error) {
    $content .= rex_view::error($error);
}
if ('' !== $message) {
    $content .= rex_view::success($message);
}

$selectForm = '
<form action="' . rex_url::currentBackendPage() . '" method="get">
    <input type="hidden" name="page" value="manage_sql/migrate">
    <div class="row">
        <div class="col-sm-5">
            <div class="form-group">
                <label for="source_table">Quelltabelle</label>
                <select name="source_table" id="source_table" class="form-control" onchange="this.form.submit()">
                    <option value="">Bitte wählen...</option>';

foreach ($tables as $table) {
    $selected = $table === $sourceTable ? ' selected' : '';
    $selectForm .= '<option value="' . rex_escape($table) . '"' . $selected . '>' . rex_escape($table) . '</option>';
}

$selectForm .= '
                </select>
            </div>
        </div>
        <div class="col-sm-5">
            <div class="form-group">
                <label for="target_table">Zieltabelle</label>
                <select name="target_table" id="target_table" class="form-control" onchange="this.form.submit()">
                    <option value="">Bitte wählen...</option>';

foreach ($tables as $table) {
    $selected = $table === $targetTable ? ' selected' : '';
    $selectForm .= '<option value="' . rex_escape($table) . '"' . $selected . '>' . rex_escape($table) . '</option>';
}

$selectForm .= '
                </select>
            </div>
        </div>
        <div class="col-sm-2">
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-default btn-block">Laden</button>
            </div>
        </div>
    </div>
</form>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Datenmigrations-Helper');
$fragment->setVar('body', $selectForm, false);
$content .= $fragment->parse('core/page/section.php');

if ('' !== $sourceTable && '' !== $targetTable && $sourceTable !== $targetTable) {
    $mappingForm = '
<form action="' . rex_url::currentBackendPage(['source_table' => $sourceTable, 'target_table' => $targetTable]) . '" method="post">
    <input type="hidden" name="source_table" value="' . rex_escape($sourceTable) . '">
    <input type="hidden" name="target_table" value="' . rex_escape($targetTable) . '">
    ' . $csrfToken->getHiddenField() . '

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Zielspalte</th>
                    <th>Modus</th>
                    <th>Quelle</th>
                    <th>Konstante</th>
                    <th>Transform</th>
                    <th>Lookup Tabelle</th>
                    <th>Lookup Von</th>
                    <th>Lookup Nach</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($writableTargetColumns as $column) {
        $targetField = $column['name'];

        $rule = $mapping[$targetField] ?? [];
        $mode = (string) ($rule['mode'] ?? 'source');
        $sourceField = (string) ($rule['source'] ?? $targetField);
        $constant = (string) ($rule['constant'] ?? '');
        $transform = (string) ($rule['transform'] ?? 'none');
        $lookupTable = (string) ($rule['lookup_table'] ?? '');
        $lookupSource = (string) ($rule['lookup_source'] ?? '');
        $lookupTarget = (string) ($rule['lookup_target'] ?? '');

        $mappingForm .= '<tr>';
        $mappingForm .= '<td><strong>' . rex_escape($targetField) . '</strong><br><small>' . rex_escape($column['type']) . '</small></td>';

        $mappingForm .= '<td><select name="mapping[' . rex_escape($targetField) . '][mode]" class="form-control">';
        $mappingForm .= '<option value="source"' . ('source' === $mode ? ' selected' : '') . '>Quellfeld</option>';
        $mappingForm .= '<option value="constant"' . ('constant' === $mode ? ' selected' : '') . '>Konstante</option>';
        $mappingForm .= '<option value="lookup"' . ('lookup' === $mode ? ' selected' : '') . '>Lookup</option>';
        $mappingForm .= '</select></td>';

        $mappingForm .= '<td><select name="mapping[' . rex_escape($targetField) . '][source]" class="form-control">';
        $mappingForm .= '<option value="">-</option>';
        foreach ($sourceFieldNames as $sourceName) {
            $selected = $sourceName === $sourceField ? ' selected' : '';
            $mappingForm .= '<option value="' . rex_escape($sourceName) . '"' . $selected . '>' . rex_escape($sourceName) . '</option>';
        }
        $mappingForm .= '</select></td>';

        $mappingForm .= '<td><input type="text" name="mapping[' . rex_escape($targetField) . '][constant]" class="form-control" value="' . rex_escape($constant) . '"></td>';

        $mappingForm .= '<td><select name="mapping[' . rex_escape($targetField) . '][transform]" class="form-control">';
        $mappingForm .= '<option value="none"' . ('none' === $transform ? ' selected' : '') . '>Keine</option>';
        $mappingForm .= '<option value="trim"' . ('trim' === $transform ? ' selected' : '') . '>trim</option>';
        $mappingForm .= '<option value="lower"' . ('lower' === $transform ? ' selected' : '') . '>lowercase</option>';
        $mappingForm .= '<option value="upper"' . ('upper' === $transform ? ' selected' : '') . '>uppercase</option>';
        $mappingForm .= '</select></td>';

        $mappingForm .= '<td><select name="mapping[' . rex_escape($targetField) . '][lookup_table]" class="form-control">';
        $mappingForm .= '<option value="">-</option>';
        foreach ($tables as $lookupTableOption) {
            $selected = $lookupTableOption === $lookupTable ? ' selected' : '';
            $mappingForm .= '<option value="' . rex_escape($lookupTableOption) . '"' . $selected . '>' . rex_escape($lookupTableOption) . '</option>';
        }
        $mappingForm .= '</select></td>';

        $mappingForm .= '<td><input type="text" name="mapping[' . rex_escape($targetField) . '][lookup_source]" class="form-control" value="' . rex_escape($lookupSource) . '" placeholder="alte_spalte"></td>';
        $mappingForm .= '<td><input type="text" name="mapping[' . rex_escape($targetField) . '][lookup_target]" class="form-control" value="' . rex_escape($lookupTarget) . '" placeholder="neue_spalte"></td>';

        $mappingForm .= '</tr>';
    }

    $mappingForm .= '
            </tbody>
        </table>
    </div>

    <div class="row">
        <div class="col-sm-2">
            <label for="batch_size">Batchgröße</label>
            <input type="number" id="batch_size" name="batch_size" class="form-control" min="1" max="5000" value="' . (int) $batchSize . '">
        </div>
        <div class="col-sm-3">
            <label for="duplicate_strategy">Duplikatstrategie</label>
            <select id="duplicate_strategy" name="duplicate_strategy" class="form-control">
                <option value="insert"' . ('insert' === $options['duplicate_strategy'] ? ' selected' : '') . '>Immer Insert</option>
                <option value="skip"' . ('skip' === $options['duplicate_strategy'] ? ' selected' : '') . '>Bei Treffer überspringen</option>
                <option value="update"' . ('update' === $options['duplicate_strategy'] ? ' selected' : '') . '>Bei Treffer updaten</option>
            </select>
        </div>
        <div class="col-sm-3">
            <label for="match_field">Match-Feld im Ziel</label>
            <select id="match_field" name="match_field" class="form-control">
                <option value="">-</option>';

    foreach ($targetAllFieldNames as $targetMatchName) {
        $selected = $targetMatchName === $options['match_field'] ? ' selected' : '';
        $mappingForm .= '<option value="' . rex_escape($targetMatchName) . '"' . $selected . '>' . rex_escape($targetMatchName) . '</option>';
    }

    $mappingForm .= '
            </select>
        </div>
        <div class="col-sm-4">
            <label>&nbsp;</label>
            <div class="checkbox" style="margin-top: 0;">
                <label>
                    <input type="checkbox" name="only_changes" value="1"' . ($options['only_changes'] ? ' checked' : '') . '> Nur Unterschiede anwenden
                </label>
            </div>
        </div>
    </div>

    <div class="btn-toolbar" style="margin-top: 15px;">
        <button type="submit" name="action" value="auto_map" class="btn btn-default">
            <i class="rex-icon fa-magic"></i> Auto-Mapping
        </button>
        <button type="submit" name="action" value="preview" class="btn btn-primary">
            <i class="rex-icon fa-play"></i> Test-Run (50)
        </button>
        <button type="submit" name="action" value="migrate" class="btn btn-save" onclick="return confirm(\'Migration wirklich ausführen?\')">
            <i class="rex-icon fa-save"></i> Migration ausführen
        </button>
    </div>
</form>';

    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Feld-Mapping');
    $fragment->setVar('body', $mappingForm, false);
    $content .= $fragment->parse('core/page/section.php');
}

if (is_array($previewData)) {
    $summary = $previewData['summary'];
    $previewContent = '<p><strong>Geprüft:</strong> ' . (int) $summary['checked'] . ' | <strong>Gültig:</strong> ' . (int) $summary['valid'] . ' | <strong>Ungültig:</strong> ' . (int) $summary['invalid'] . '</p>';
    $previewContent .= '<p><strong>Insert:</strong> ' . (int) $summary['insert'] . ' | <strong>Update:</strong> ' . (int) $summary['update'] . ' | <strong>Skip:</strong> ' . (int) $summary['skipped'] . '</p>';

    $previewContent .= '<div class="table-responsive"><table class="table table-hover">';
    $previewContent .= '<thead><tr><th>#</th><th>Status</th><th>Aktion</th><th>Mapped Werte</th><th>Hinweise</th></tr></thead><tbody>';

    foreach ($previewData['rows'] as $index => $row) {
        $errors = $row['errors'];
        $status = [] === $errors ? '<span class="label label-success">OK</span>' : '<span class="label label-danger">Fehler</span>';
        $actionText = rex_escape((string) ($row['action'] ?? 'insert'));
        $reasonText = rex_escape((string) ($row['reason'] ?? ''));
        $mappedDump = '<pre style="margin:0; white-space:pre-wrap;">' . rex_escape(print_r($row['mapped'], true)) . '</pre>';
        $errorText = [] === $errors ? ($reasonText === '' ? '-' : $reasonText) : rex_escape(implode('; ', $errors));

        $previewContent .= '<tr>';
        $previewContent .= '<td>' . ((int) $index + 1) . '</td>';
        $previewContent .= '<td>' . $status . '</td>';
        $previewContent .= '<td>' . $actionText . '</td>';
        $previewContent .= '<td>' . $mappedDump . '</td>';
        $previewContent .= '<td>' . $errorText . '</td>';
        $previewContent .= '</tr>';
    }

    $previewContent .= '</tbody></table></div>';

    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Test-Run Ergebnis');
    $fragment->setVar('body', $previewContent, false);
    $content .= $fragment->parse('core/page/section.php');
}

if (is_array($migrateResult)) {
    $resultContent = '<p><strong>Total:</strong> ' . (int) $migrateResult['total'] . '</p>';
    $resultContent .= '<p><strong>Insert:</strong> ' . (int) $migrateResult['inserted'] . ' | <strong>Update:</strong> ' . (int) $migrateResult['updated'] . ' | <strong>Fehler:</strong> ' . (int) $migrateResult['failed'] . ' | <strong>Übersprungen:</strong> ' . (int) $migrateResult['skipped'] . '</p>';

    if ([] !== $migrateResult['errors']) {
        $resultContent .= '<div class="alert alert-warning"><strong>Fehlerauszug:</strong><ul>';
        foreach ($migrateResult['errors'] as $migrationError) {
            $resultContent .= '<li>' . rex_escape($migrationError) . '</li>';
        }
        $resultContent .= '</ul></div>';
    }

    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Migration Ergebnis');
    $fragment->setVar('body', $resultContent, false);
    $content .= $fragment->parse('core/page/section.php');
}

echo $content;
