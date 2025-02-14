<?php
$content = '';
$message = '';
$error = '';

// Get all tables
$sql = rex_sql::factory();
$tables = $sql->getArray('SHOW TABLES');
$allTables = [];
foreach ($tables as $table) {
    $tableName = reset($table);
    if (str_starts_with($tableName, 'rex_')) {
        $allTables[] = $tableName;
    }
}

// Get selected table structure if a table is selected
$selectedTable = rex_request('table', 'string', '');
$searchValue = rex_request('search', 'string', '');
$searchField = rex_request('field', 'string', '');
$replaceValue = rex_request('replace', 'string', '');
$action = rex_request('action', 'string', '');

// Handle actions
if ($selectedTable && $action && rex_csrf_token::factory('table_action')->isValid()) {
    try {
        $sql = rex_sql::factory();
        switch ($action) {
            case 'truncate':
                $sql->setQuery('TRUNCATE TABLE ' . $sql->escapeIdentifier($selectedTable));
                $message = 'Tabelle wurde geleert';
                break;
            case 'replace':
                if ($searchValue && $searchField && $replaceValue) {
                    $sql->setQuery(
                        'UPDATE ' . $sql->escapeIdentifier($selectedTable) . 
                        ' SET ' . $sql->escapeIdentifier($searchField) . ' = REPLACE(' . 
                        $sql->escapeIdentifier($searchField) . ', :search, :replace)',
                        ['search' => $searchValue, 'replace' => $replaceValue]
                    );
                    $message = $sql->getRows() . ' Datensätze wurden aktualisiert';
                }
                break;
            case 'delete_found':
                if ($searchValue && $searchField) {
                    $sql->setQuery(
                        'DELETE FROM ' . $sql->escapeIdentifier($selectedTable) . 
                        ' WHERE ' . $sql->escapeIdentifier($searchField) . ' LIKE :search',
                        ['search' => '%' . $searchValue . '%']
                    );
                    $message = $sql->getRows() . ' Datensätze wurden gelöscht';
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Build table selection form
$formContent = '
<style>
.rex-table td {
    max-width: 250px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rex-table td:hover {
    white-space: normal;
    word-break: break-word;
}
</style>
<form id="table-select" method="get">
    <input type="hidden" name="page" value="table_builder/data">
    <div class="row">
        <div class="col-sm-4">
            <div class="form-group">
                <label for="table">Tabelle</label>
                <select name="table" id="table" class="form-control" onchange="this.form.submit()">
                    <option value="">Bitte wählen...</option>';
foreach ($allTables as $table) {
    $formContent .= '<option value="'.$table.'"'.($selectedTable === $table ? ' selected' : '').'>'.$table.'</option>';
}
$formContent .= '
                </select>
            </div>
        </div>
    </div>
</form>';

if ($selectedTable) {
    $columns = rex_sql::showColumns($selectedTable);
    $formContent .= '
    <form method="get" class="form-horizontal">
        <input type="hidden" name="page" value="table_builder/data">
        <input type="hidden" name="table" value="'.$selectedTable.'">
        '.rex_csrf_token::factory('table_action')->getHiddenField().'
        <div class="row">
            <div class="col-sm-3">
                <div class="form-group">
                    <label>Spalte</label>
                    <select name="field" class="form-control">
                        <option value="">Bitte wählen...</option>';
    foreach ($columns as $column) {
        $formContent .= '<option value="'.$column['name'].'"'.($searchField === $column['name'] ? ' selected' : '').'>'.$column['name'].'</option>';
    }
    $formContent .= '
                    </select>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    <label>Suchen nach</label>
                    <input type="text" name="search" class="form-control" value="'.rex_escape($searchValue).'">
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    <label>Ersetzen durch</label>
                    <input type="text" name="replace" class="form-control" value="'.rex_escape($replaceValue).'">
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div class="btn-group btn-group-full-width">
                        <button type="submit" class="btn btn-primary" name="action" value="search">
                            <i class="rex-icon fa-search"></i> Suchen
                        </button>
                        <button type="submit" class="btn btn-warning" name="action" value="replace" onclick="return confirm(\'Wirklich ersetzen?\')">
                            <i class="rex-icon fa-exchange"></i> Ersetzen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-12">
            <div class="btn-toolbar">
                <div class="btn-group">
                    <a href="index.php?page=yform/manager/data_edit&table_name='.$selectedTable.'&func=add" class="btn btn-success">
                        <i class="rex-icon fa-plus"></i> Neuer Datensatz
                    </a>
                </div>';

    if ($searchValue && $searchField) {
        $formContent .= '
                <div class="btn-group">
                    <form method="get" class="pull-right" style="display:inline-block">
                        <input type="hidden" name="page" value="table_builder/data">
                        <input type="hidden" name="table" value="'.$selectedTable.'">
                        <input type="hidden" name="field" value="'.$searchField.'">
                        <input type="hidden" name="search" value="'.$searchValue.'">
                        <input type="hidden" name="action" value="delete_found">
                        '.rex_csrf_token::factory('table_action')->getHiddenField().'
                        <button type="submit" class="btn btn-warning" onclick="return confirm(\'Gefundene Datensätze wirklich löschen?\')">
                            <i class="rex-icon fa-trash"></i> Gefundene löschen
                        </button>
                    </form>
                </div>';
    }

    $formContent .= '
                <div class="btn-group pull-right">
                    <form method="get" style="display:inline-block">
                        <input type="hidden" name="page" value="table_builder/data">
                        <input type="hidden" name="table" value="'.$selectedTable.'">
                        <input type="hidden" name="action" value="truncate">
                        '.rex_csrf_token::factory('table_action')->getHiddenField().'
                        <button type="submit" class="btn btn-danger" onclick="return confirm(\'Wirklich alle Datensätze löschen?\')">
                            <i class="rex-icon fa-trash"></i> Tabelle leeren
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>';
}

// Add forms to content
$fragment = new rex_fragment();
$fragment->setVar('title', 'Tabellen-Verwaltung');
$fragment->setVar('body', $formContent, false);
$content .= $fragment->parse('core/page/section.php');

// Show messages if any
if ($message) {
    $content .= rex_view::success($message);
}
if ($error) {
    $content .= rex_view::error($error);
}

// Show table data if table is selected
if ($selectedTable) {
    try {
        // Build query
        $sql = rex_sql::factory();
        $query = 'SELECT * FROM ' . $sql->escapeIdentifier($selectedTable);
        $params = [];

        if ($searchValue && $searchField) {
            $query .= ' WHERE ' . $sql->escapeIdentifier($searchField) . ' LIKE :search';
            $params['search'] = '%' . $searchValue . '%';
        }

        $query .= ' ORDER BY id DESC';

        $list = rex_list::factory($query, 50, $selectedTable, false, $params);

        // Spalten konfigurieren
        $columns = rex_sql::showColumns($selectedTable);
        foreach ($columns as $column) {
            $columnName = $column['name'];
            // Werte kürzen und formatieren
            $list->setColumnFormat($columnName, 'custom', function ($params) {
                $value = $params['value'];
                if (is_string($value)) {
                    if (strlen($value) > 100) {
                        $value = substr($value, 0, 100) . '...';
                    }
                    $value = rex_escape($value);
                }
                return $value;
            });
        }

        // Action column
        $list->addColumn('Aktionen', '<i class="rex-icon fa-edit"></i> Bearbeiten', -1, ['<th class="rex-table-action">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
        $list->setColumnParams('Aktionen', ['page' => 'yform/manager/data_edit', 'func' => 'edit', 'table_name' => $selectedTable, 'data_id' => '###id###']);

        // Show table content
        $content .= $list->get();

    } catch (Exception $e) {
        $content .= rex_view::error($e->getMessage());
    }
}

echo $content;
