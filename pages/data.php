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

// Get selected table
$selectedTable = rex_request('table', 'string', '');
$action = rex_request('action', 'string', '');

// Handle actions
if ($selectedTable && $action && rex_csrf_token::factory('table_action')->isValid()) {
    try {
        $sql = rex_sql::factory();
        if ($action === 'truncate') {
            $sql->setQuery('TRUNCATE TABLE ' . $sql->escapeIdentifier($selectedTable));
            $message = 'Tabelle wurde geleert';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Add CSS
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

if ($selectedTable) {
    $formContent .= '
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-12">
            <div class="btn-toolbar">
                <div class="btn-group">
                    <a href="index.php?page=yform/manager/data_edit&table_name='.$selectedTable.'&func=add" class="btn btn-success">
                        <i class="rex-icon fa-plus"></i> Neuer Datensatz
                    </a>
                </div>
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
        $sql = rex_sql::factory();
        
        // Create list
        $list = rex_list::factory('SELECT * FROM ' . $sql->escapeIdentifier($selectedTable) . ' ORDER BY id DESC');
        
        // Configure list appearance
        $list->addTableAttribute('class', 'table-striped table-hover');
        
        // Configure columns
        $columns = rex_sql::showColumns($selectedTable);
        foreach ($columns as $column) {
            $columnName = $column['name'];
            
            // Set column format for better readability
            $list->setColumnFormat($columnName, 'custom', function ($params) {
                $value = $params['value'];
                if (is_string($value) && strlen($value) > 100) {
                    $value = substr($value, 0, 100) . '...';
                }
                return rex_escape($value);
            });
        }

        // Add action column
        $list->addColumn('Aktionen', '<i class="rex-icon fa-edit"></i> Bearbeiten', -1, ['<th class="rex-table-action">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
        $list->setColumnParams('Aktionen', ['page' => 'yform/manager/data_edit', 'func' => 'edit', 'table_name' => $selectedTable, 'data_id' => '###id###']);
        
        // Wrap list in responsive container
        $content .= '<div class="table-responsive">';
        $content .= $list->get();
        $content .= '</div>';

    } catch (Exception $e) {
        $content .= rex_view::error($e->getMessage());
    }
}

echo $content;
