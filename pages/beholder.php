<?php
$content = '';
$message = '';
$error = '';

// Get all tables
$sql = rex_sql::factory();
$tables = $sql->getArray('SHOW TABLES');
$allTables = [];
foreach ($tables as $table) {
    $tableName = reset($table); // Erstes Element des Arrays
    if (str_starts_with($tableName, 'rex_')) {
        $allTables[] = $tableName;
    }
}

// Get selected table structure if a table is selected
$selectedTable = rex_request('table', 'string', '');
$searchValue = rex_request('search', 'string', '');
$searchField = rex_request('field', 'string', '');
$action = rex_request('action', 'string', '');

// Build table selection form
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

// Add forms to content
$fragment = new rex_fragment();
$fragment->setVar('title', 'Tabellen-Verwaltung');
$fragment->setVar('body', $formContent, false);
$content .= $fragment->parse('core/page/section.php');

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

        $list = rex_list::factory($query, 50, $selectedTable);

        // Spalten konfigurieren
        $columns = rex_sql::showColumns($selectedTable);
        foreach ($columns as $column) {
            $columnName = $column['name'];
            $list->setColumnLabel($columnName, $columnName);
        }

        // Show table actions
        $tableActions = '
        <div class="row" style="margin-bottom: 20px;">
            <div class="col-sm-12">
                <div class="btn-group">
                    <a class="btn btn-default" href="'.rex_url::currentBackendPage(['table' => $selectedTable, 'func' => 'add']).'">
                        <i class="rex-icon fa-plus"></i> Neuer Datensatz
                    </a>
                    <button type="button" class="btn btn-danger" onclick="truncateTable()">
                        <i class="rex-icon fa-trash"></i> Tabelle leeren
                    </button>
                </div>
            </div>
        </div>

        <script>
        function truncateTable() {
            if (confirm("Wirklich alle Datensätze löschen?")) {
                window.location.href = "'.rex_url::currentBackendPage([
                    'table' => $selectedTable,
                    'action' => 'truncate',
                    '_csrf_token' => rex_csrf_token::factory('table_truncate')->getValue()
                ]).'";
            }
        }
        </script>';

        $content .= $tableActions;

        // Show table content
        $content .= $list->get();

    } catch (Exception $e) {
        $content .= rex_view::error($e->getMessage());
    }
}

echo $content;
?>
