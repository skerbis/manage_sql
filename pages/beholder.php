<?php
$content = '';
$message = '';
$error = '';

// Get YForm tables
$yformTables = [];
if (rex_addon::get('yform')->isAvailable()) {
    $tables = rex_sql::factory()->getArray('SELECT table_name FROM rex_yform_table');
    $yformTables = array_column($tables, 'table_name');
}

// Get selected table structure if a table is selected
$selectedTable = rex_request('table', 'string', '');
$searchValue = rex_request('search', 'string', '');
$searchField = rex_request('field', 'string', '');
$action = rex_request('action', 'string', '');

// Handle actions
if ($selectedTable && $action) {
    try {
        $sql = rex_sql::factory();
        switch ($action) {
            case 'truncate':
                if (rex_csrf_token::factory('table_truncate')->isValid()) {
                    $sql->setQuery('TRUNCATE TABLE ' . $sql->escapeIdentifier($selectedTable));
                    $message = 'Tabelle wurde geleert';
                }
                break;
            case 'delete_filtered':
                if ($searchValue && $searchField && rex_csrf_token::factory('delete_filtered')->isValid()) {
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
<form id="table-select" action="'.rex_url::currentBackendPage().'" method="get">
    <input type="hidden" name="page" value="table_builder/data">
    <div class="row">
        <div class="col-sm-4">
            <div class="form-group">
                <label for="table">YForm Tabelle</label>
                <select name="table" id="table" class="form-control" onchange="this.form.submit()">
                    <option value="">Bitte wählen...</option>';
foreach ($yformTables as $table) {
    $formContent .= '<option value="'.$table.'"'.($selectedTable === $table ? ' selected' : '').'>'.$table.'</option>';
}
$formContent .= '
                </select>
            </div>
        </div>
    </div>
</form>';

// Search and Actions form
if ($selectedTable) {
    $columns = rex_sql::showColumns($selectedTable);
    $formContent .= '
    <form action="'.rex_url::currentBackendPage().'" method="get" class="form-horizontal">
        <input type="hidden" name="page" value="table_builder/data">
        <input type="hidden" name="table" value="'.$selectedTable.'">
        <div class="row">
            <div class="col-sm-4">
                <div class="form-group">
                    <label for="field">Suchfeld</label>
                    <select name="field" id="field" class="form-control">
                        <option value="">Alle Felder</option>';
    foreach ($columns as $column) {
        $formContent .= '<option value="'.$column['name'].'"'.($searchField === $column['name'] ? ' selected' : '').'>'.$column['name'].'</option>';
    }
    $formContent .= '
                    </select>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-group">
                    <label for="search">Suchwert</label>
                    <input type="text" name="search" id="search" class="form-control" value="'.rex_escape($searchValue).'">
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary form-control">Suchen</button>
                </div>
            </div>
        </div>
    </form>

    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-12">
            <form action="'.rex_url::currentBackendPage().'" method="get" class="pull-right" style="margin-left: 10px;">
                <input type="hidden" name="page" value="table_builder/data">
                <input type="hidden" name="table" value="'.$selectedTable.'">
                <input type="hidden" name="action" value="truncate">
                '.rex_csrf_token::factory('table_truncate')->getHiddenField().'
                <button type="submit" class="btn btn-danger" onclick="return confirm(\'Wirklich alle Datensätze löschen?\')">
                    <i class="rex-icon fa-trash"></i> Tabelle leeren
                </button>
            </form>';

    if ($searchValue) {
        $formContent .= '
            <form action="'.rex_url::currentBackendPage().'" method="get" class="pull-right">
                <input type="hidden" name="page" value="table_builder/data">
                <input type="hidden" name="table" value="'.$selectedTable.'">
                <input type="hidden" name="field" value="'.$searchField.'">
                <input type="hidden" name="search" value="'.$searchValue.'">
                <input type="hidden" name="action" value="delete_filtered">
                '.rex_csrf_token::factory('delete_filtered')->getHiddenField().'
                <button type="submit" class="btn btn-warning" onclick="return confirm(\'Gefilterte Datensätze wirklich löschen?\')">
                    <i class="rex-icon fa-trash"></i> Gefilterte Datensätze löschen
                </button>
            </form>';
    }

    $formContent .= '    
        </div>
    </div>';
}

// Show messages if any
if ($message) {
    $content .= rex_view::success($message);
}
if ($error) {
    $content .= rex_view::error($error);
}

// Add forms to content
$fragment = new rex_fragment();
$fragment->setVar('title', 'Tabellen-Verwaltung');
$fragment->setVar('body', $formContent, false);
$content .= $fragment->parse('core/page/section.php');

// Show table data if table is selected
if ($selectedTable) {
    // Build query
    $sql = rex_sql::factory();
    $query = 'SELECT * FROM ' . $sql->escapeIdentifier($selectedTable);
    $params = [];

    if ($searchValue && $searchField) {
        $query .= ' WHERE ' . $sql->escapeIdentifier($searchField) . ' LIKE :search';
        $params['search'] = '%' . $searchValue . '%';
    }

    $list = rex_list::factory($query, 50, 'data', false, $params);

    // Add action column
    $list->addColumn('Aktion', '<i class="rex-icon fa-edit"></i> Bearbeiten', -1, ['<th>###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams('Aktion', ['page' => 'yform/manager/data_edit', 'table_name' => $selectedTable, 'data_id' => '###id###', 'func' => 'edit']);

    $content .= $list->get();
}

echo $content;
