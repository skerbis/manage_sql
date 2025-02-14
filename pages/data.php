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

$content .= '
<style>
.table-responsive {
    margin: 0 -15px;
    padding: 0 15px;
    overflow-x: auto;
}
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
</style>';

$formContent = '
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
                    <label> </label>
                    <div class="btn-group btn-group-justified">
                        <a class="btn btn-primary" onclick="this.closest(\'form\').submit()">
                            <i class="rex-icon fa-search"></i> Suchen
                        </a>
                        <a class="btn btn-warning" onclick="if(confirm(\'Werte wirklich ersetzen?\')) { this.closest(\'form\').querySelector(\'input[name=action]\').value=\'replace\'; this.closest(\'form\').submit(); }">
                            <i class="rex-icon fa-exchange"></i> Ersetzen
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <input type="hidden" name="action" value="search">
    </form>

    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-12">
            <div class="btn-toolbar">
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

$fragment = new rex_fragment();
$fragment->setVar('title', 'Tabellen-Verwaltung');
$fragment->setVar('body', $formContent, false);
$content .= $fragment->parse('core/page/section.php');

if ($message) {
    $content .= rex_view::success($message);
}
if ($error) {
    $content .= rex_view::error($error);
}

if ($selectedTable) {
    try {
        $sql = rex_sql::factory();
        $query = 'SELECT * FROM ' . $sql->escapeIdentifier($selectedTable);
        $countQuery = 'SELECT COUNT(*) FROM ' . $sql->escapeIdentifier($selectedTable); // Separate count query

        $whereClause = '';
        $params = [];

        if ($searchValue && $searchField) {
            $whereClause = ' WHERE ' . $sql->escapeIdentifier($searchField) . ' LIKE :search';
            $params = ['search' => '%' . $searchValue . '%'];
            $query .= $whereClause;
            $countQuery .= $whereClause;
        }

        $query .= ' ORDER BY id DESC';

        // Manually fetch the total count before creating rex_list
        $countSql = rex_sql::factory();
        $countSql->setQuery($countQuery, $params);
        $totalRows = (int) $countSql->getValue('COUNT(*)'); // Cast to integer

        $list = rex_list::factory($query, 50, 'table-'.$selectedTable, false, $totalRows); // Pass total rows

        if ($searchValue && $searchField) {
            $list->addParam('search', $searchValue);
            $list->addParam('field', $searchField);
            $list->addParam('table', $selectedTable);
        }

        $list->addTableAttribute('class', 'table-striped table-hover');

        $columns = rex_sql::showColumns($selectedTable);
        foreach ($columns as $column) {
            $columnName = $column['name'];
            $list->setColumnFormat($columnName, 'custom', function ($params) {
                $value = $params['value'];
                if (is_string($value) && strlen($value) > 100) {
                    $value = substr($value, 0, 100) . '...';
                }
                return rex_escape($value);
            });
        }

        $content .= '<div class="table-responsive">';
        $content .= $list->get();
        $content .= '</div>';

    } catch (Exception $e) {
        $content .= rex_view::error($e->getMessage());
    }
}

echo $content;
