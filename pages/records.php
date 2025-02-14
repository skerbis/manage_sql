<?php
$content = '';
$message = '';
$error = '';

// Get all tables
$sql = rex_sql::factory();
$tables = $sql->getTablesAndViews();
$tables = array_filter($tables, function($table) {
    return str_starts_with($table, 'rex_');
});

// Get selected table
$selectedTable = rex_get('table', 'string');
$action = rex_post('action', 'string');

// Handle actions
if ($action && $selectedTable) {
    try {
        $sql = rex_sql::factory();
        
        switch($action) {
            case 'truncate':
                if (rex_csrf_token::factory('table_records')->isValid()) {
                    $sql->setQuery('TRUNCATE TABLE ' . $sql->escapeIdentifier($selectedTable));
                    $message = 'Tabelle wurde geleert.';
                }
                break;
                
            case 'search':
                $searchColumn = rex_post('search_column', 'string');
                $searchTerm = rex_post('search_term', 'string');
                $searchType = rex_post('search_type', 'string', 'contains');
                
                if ($searchColumn && $searchTerm) {
                    $where = '';
                    $params = [];
                    
                    switch($searchType) {
                        case 'exact':
                            $where = $sql->escapeIdentifier($searchColumn) . ' = :term';
                            $params['term'] = $searchTerm;
                            break;
                        case 'starts':
                            $where = $sql->escapeIdentifier($searchColumn) . ' LIKE :term';
                            $params['term'] = $searchTerm . '%';
                            break;
                        case 'ends':
                            $where = $sql->escapeIdentifier($searchColumn) . ' LIKE :term';
                            $params['term'] = '%' . $searchTerm;
                            break;
                        default: // contains
                            $where = $sql->escapeIdentifier($searchColumn) . ' LIKE :term';
                            $params['term'] = '%' . $searchTerm . '%';
                    }
                    
                    $query = 'SELECT * FROM ' . $sql->escapeIdentifier($selectedTable) . ' WHERE ' . $where;
                    $sql->setQuery($query, $params);
                    
                    if (0 === $sql->getRows()) {
                        $message = 'Keine Ergebnisse gefunden.';
                    }
                }
                break;
                
            case 'replace':
                if (rex_csrf_token::factory('table_records')->isValid()) {
                    $replaceColumn = rex_post('replace_column', 'string');
                    $searchTerm = rex_post('search_term', 'string');
                    $replaceTerm = rex_post('replace_term', 'string');
                    
                    if ($replaceColumn && $searchTerm) {
                        $query = 'UPDATE ' . $sql->escapeIdentifier($selectedTable) . 
                                ' SET ' . $sql->escapeIdentifier($replaceColumn) . ' = REPLACE(' .
                                $sql->escapeIdentifier($replaceColumn) . ', :search, :replace)';
                        
                        $sql->setQuery($query, [
                            'search' => $searchTerm,
                            'replace' => $replaceTerm
                        ]);
                        
                        $message = 'Ersetzen wurde durchgeführt. ' . $sql->getRows() . ' Datensätze betroffen.';
                    }
                }
                break;
                
            case 'delete_results':
                if (rex_csrf_token::factory('table_records')->isValid()) {
                    $searchColumn = rex_post('search_column', 'string');
                    $searchTerm = rex_post('search_term', 'string');
                    $searchType = rex_post('search_type', 'string', 'contains');
                    
                    if ($searchColumn && $searchTerm) {
                        $where = '';
                        $params = [];
                        
                        switch($searchType) {
                            case 'exact':
                                $where = $sql->escapeIdentifier($searchColumn) . ' = :term';
                                $params['term'] = $searchTerm;
                                break;
                            case 'starts':
                                $where = $sql->escapeIdentifier($searchColumn) . ' LIKE :term';
                                $params['term'] = $searchTerm . '%';
                                break;
                            case 'ends':
                                $where = $sql->escapeIdentifier($searchColumn) . ' LIKE :term';
                                $params['term'] = '%' . $searchTerm;
                                break;
                            default: // contains
                                $where = $sql->escapeIdentifier($searchColumn) . ' LIKE :term';
                                $params['term'] = '%' . $searchTerm . '%';
                        }
                        
                        $query = 'DELETE FROM ' . $sql->escapeIdentifier($selectedTable) . ' WHERE ' . $where;
                        $sql->setQuery($query, $params);
                        
                        $message = $sql->getRows() . ' Datensätze wurden gelöscht.';
                    }
                }
                break;
        }
    } catch (rex_sql_exception $e) {
        $error = $e->getMessage();
    }
}

// Show messages
if ($error) {
    $content .= rex_view::error($error);
}
if ($message) {
    $content .= rex_view::success($message);
}

// Table selection form
$formContent = '
<form id="table-select" action="' . rex_url::currentBackendPage() . '" method="get">
    <input type="hidden" name="page" value="table_builder/records">
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group">
                <label for="table">Tabelle</label>
                <select name="table" id="table" class="form-control" onchange="this.form.submit()">
                    <option value="">Bitte wählen...</option>';
                    
foreach ($tables as $table) {
    $formContent .= '<option value="' . $table . '"' . ($selectedTable === $table ? ' selected' : '') . '>' . $table . '</option>';
}

$formContent .= '
                </select>
            </div>
        </div>
    </div>
</form>';

// Add table selection to content
$fragment = new rex_fragment();
$fragment->setVar('title', 'Tabelle auswählen');
$fragment->setVar('body', $formContent, false);
$content .= $fragment->parse('core/page/section.php');

// If table is selected, show actions
if ($selectedTable) {
    $columns = rex_sql::showColumns($selectedTable);
    $columnNames = array_column($columns, 'name');
    
    // Search form
    $searchForm = '
    <form action="' . rex_url::currentBackendPage(['table' => $selectedTable]) . '" method="post" class="form-horizontal">
        <input type="hidden" name="action" value="search">
        
        <div class="form-group">
            <label class="col-sm-2 control-label">Spalte</label>
            <div class="col-sm-10">
                <select name="search_column" class="form-control" required>
                    <option value="">Bitte wählen...</option>';
    
    foreach ($columnNames as $column) {
        $searchForm .= '<option value="' . $column . '">' . $column . '</option>';
    }
    
    $searchForm .= '
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label class="col-sm-2 control-label">Suchtyp</label>
            <div class="col-sm-10">
                <select name="search_type" class="form-control">
                    <option value="contains">Enthält</option>
                    <option value="exact">Exakt</option>
                    <option value="starts">Beginnt mit</option>
                    <option value="ends">Endet mit</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label class="col-sm-2 control-label">Suchbegriff</label>
            <div class="col-sm-10">
                <input type="text" name="search_term" class="form-control" required>
            </div>
        </div>
        
        <div class="form-group">
            <div class="col-sm-10 col-sm-offset-2">
                <button type="submit" class="btn btn-primary">Suchen</button>
                <button type="submit" class="btn btn-danger" name="action" value="delete_results" 
                    onclick="return confirm(\'Gefundene Datensätze wirklich löschen?\')">
                    Suchergebnisse löschen
                </button>
                ' . rex_csrf_token::factory('table_records')->getHiddenField() . '
            </div>
        </div>
    </form>';
    
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Suchen');
    $fragment->setVar('body', $searchForm, false);
    $content .= $fragment->parse('core/page/section.php');
    
    // Replace form
    $replaceForm = '
    <form action="' . rex_url::currentBackendPage(['table' => $selectedTable]) . '" method="post" class="form-horizontal">
        <input type="hidden" name="action" value="replace">
        
        <div class="form-group">
            <label class="col-sm-2 control-label">Spalte</label>
            <div class="col-sm-10">
                <select name="replace_column" class="form-control" required>
                    <option value="">Bitte wählen...</option>';
    
    foreach ($columnNames as $column) {
        $replaceForm .= '<option value="' . $column . '">' . $column . '</option>';
    }
    
    $replaceForm .= '
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label class="col-sm-2 control-label">Suchen nach</label>
            <div class="col-sm-10">
                <input type="text" name="search_term" class="form-control" required>
            </div>
        </div>
        
        <div class="form-group">
            <label class="col-sm-2 control-label">Ersetzen durch</label>
            <div class="col-sm-10">
                <input type="text" name="replace_term" class="form-control" required>
            </div>
        </div>
        
        <div class="form-group">
            <div class="col-sm-10 col-sm-offset-2">
                <button type="submit" class="btn btn-primary" 
                    onclick="return confirm(\'Ersetzen wirklich durchführen?\')">
                    Ersetzen
                </button>
                ' . rex_csrf_token::factory('table_records')->getHiddenField() . '
            </div>
        </div>
    </form>';
    
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Ersetzen');
    $fragment->setVar('body', $replaceForm, false);
    $content .= $fragment->parse('core/page/section.php');
    
    // Truncate form
    $truncateForm = '
    <form action="' . rex_url::currentBackendPage(['table' => $selectedTable]) . '" method="post">
        <input type="hidden" name="action" value="truncate">
        <p class="alert alert-warning">
            Diese Aktion löscht <strong>alle</strong> Datensätze aus der Tabelle. Dies kann nicht rückgängig gemacht werden!
        </p>
        <button type="submit" class="btn btn-danger" onclick="return confirm(\'Tabelle wirklich leeren?\')">
            Tabelle leeren (TRUNCATE)
        </button>
        ' . rex_csrf_token::factory('table_records')->getHiddenField() . '
    </form>';
    
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Tabelle leeren');
    $fragment->setVar('body', $truncateForm, false);
    $content .= $fragment->parse('core/page/section.php');
    
    // Show results if search was performed
    if ('search' === $action && isset($sql) && $sql->getRows() > 0) {
        $resultTable = '<div class="table-responsive"><table class="table table-hover">
            <thead>
                <tr>';
        
        foreach ($columnNames as $column) {
            $resultTable .= '<th>' . rex_escape($column) . '</th>';
        }
        
        $resultTable .= '
                </tr>
            </thead>
            <tbody>';
            
        foreach ($sql->getArray() as $row) {
            $resultTable .= '<tr>';
            foreach ($columnNames as $column) {
                $resultTable .= '<td>' . rex_escape($row[$column]) . '</td>';
            }
            $resultTable .= '</tr>';
        }
        
        $resultTable .= '
            </tbody>
        </table></div>';
        
        $fragment = new rex_fragment();
        $fragment->setVar('title', 'Suchergebnisse (' . $sql->getRows() . ' Datensätze)');
        $fragment->setVar('body', $resultTable, false);
        $content .= $fragment->parse('core/page/section.php');
    }
}

echo $content;
