<?php
$content = '';
$message = '';
$error = '';

// Get selected table
$selectedTable = rex_get('table', 'string');
$func = rex_get('func', 'string');
$recordId = rex_get('id', 'int', 0);

// Get all tables
$sql = rex_sql::factory();
$tables = array_filter($sql->getTablesAndViews(), function($table) {
    return str_starts_with($table, 'rex_');
});

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
$formContent .= '</select></div></div></div></form>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Tabelle auswählen');
$fragment->setVar('body', $formContent, false);
$content .= $fragment->parse('core/page/section.php');

// Only proceed if table is selected
if ($selectedTable) {
    $columns = rex_sql::showColumns($selectedTable);

    // Handle form submissions
    $csrf = rex_csrf_token::factory('table_records');
    
    if (rex_request_method() === 'post' && $csrf->isValid()) {
        try {
            $sql = rex_sql::factory();
            
            switch ($func) {
                case 'add':
                    $sql->setTable($selectedTable);
                    foreach (rex_post('data', 'array', []) as $key => $value) {
                        $sql->setValue($key, $value);
                    }
                    $sql->insert();
                    $message = 'Datensatz wurde erstellt.';
                    rex_response::sendRedirect(rex_url::currentBackendPage(['table' => $selectedTable]));
                    break;

                case 'edit':
                    $sql->setTable($selectedTable);
                    foreach (rex_post('data', 'array', []) as $key => $value) {
                        $sql->setValue($key, $value);
                    }
                    $sql->setWhere(['id' => $recordId]);
                    $sql->update();
                    $message = 'Datensatz wurde gespeichert.';
                    rex_response::sendRedirect(rex_url::currentBackendPage(['table' => $selectedTable]));
                    break;

                case 'delete':
                    $sql->setTable($selectedTable);
                    $sql->setWhere(['id' => $recordId]);
                    $sql->delete();
                    $message = 'Datensatz wurde gelöscht.';
                    rex_response::sendRedirect(rex_url::currentBackendPage(['table' => $selectedTable]));
                    break;

                case 'search':
                    // search handled in list generation
                    break;

                case 'replace':
                    $column = rex_post('replace_column', 'string');
                    $search = rex_post('search_term', 'string');
                    $replace = rex_post('replace_term', 'string');
                    
                    if ($column && $search !== '') {
                        $sql->setQuery(
                            'UPDATE ' . $sql->escapeIdentifier($selectedTable) . 
                            ' SET ' . $sql->escapeIdentifier($column) . ' = REPLACE(' .
                            $sql->escapeIdentifier($column) . ', :search, :replace)',
                            ['search' => $search, 'replace' => $replace]
                        );
                        $message = $sql->getRows() . ' Datensätze wurden aktualisiert.';
                        rex_response::sendRedirect(rex_url::currentBackendPage(['table' => $selectedTable]));
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

    // Show edit/add form OR list view
    if (in_array($func, ['edit', 'add'])) {
        // Load record data for editing
        $data = [];
        if ($func === 'edit' && $recordId) {
            $sql = rex_sql::factory();
            $sql->setQuery('SELECT * FROM ' . $sql->escapeIdentifier($selectedTable) . ' WHERE id = :id', ['id' => $recordId]);
            if ($sql->getRows()) {
                $data = $sql->getRow();
            }
        }

        // Build form
        $form = '
        <form action="' . rex_url::currentBackendPage() . '" method="post">
            <input type="hidden" name="table" value="' . $selectedTable . '">
            <input type="hidden" name="func" value="' . $func . '">
            ' . ($recordId ? '<input type="hidden" name="id" value="' . $recordId . '">' : '') . '
            ' . $csrf->getHiddenField();

        foreach ($columns as $column) {
            if ($column['name'] === 'id') {
                continue;
            }

            $label = ucfirst(str_replace('_', ' ', $column['name']));
            $value = $data[$column['name']] ?? '';

            if (str_contains($column['type'], 'text')) {
                $form .= '
                <div class="form-group">
                    <label>' . $label . '</label>
                    <textarea name="data[' . $column['name'] . ']" class="form-control" rows="3">' . rex_escape($value) . '</textarea>
                </div>';
            } else {
                $form .= '
                <div class="form-group">
                    <label>' . $label . '</label>
                    <input type="text" name="data[' . $column['name'] . ']" value="' . rex_escape($value) . '" class="form-control">
                </div>';
            }
        }

        $form .= '
            <button type="submit" class="btn btn-save">' . ($func === 'edit' ? 'Speichern' : 'Erstellen') . '</button>
            <a class="btn btn-default" href="' . rex_url::currentBackendPage(['table' => $selectedTable]) . '">Abbrechen</a>
        </form>';

        $fragment = new rex_fragment();
        $fragment->setVar('title', $func === 'edit' ? 'Datensatz bearbeiten' : 'Neuer Datensatz');
        $fragment->setVar('body', $form, false);
        $content .= $fragment->parse('core/page/section.php');

    } else {
        // Show tools panel (search, replace)
        $toolsContent = '
        <div class="row">
            <div class="col-sm-6">
                <form action="' . rex_url::currentBackendPage() . '" method="get" class="form-horizontal">
                    <input type="hidden" name="page" value="table_builder/records">
                    <input type="hidden" name="table" value="' . $selectedTable . '">
                    <input type="hidden" name="func" value="search">
                    
                    <div class="panel panel-default">
                        <div class="panel-heading"><i class="rex-icon fa-search"></i> Suchen</div>
                        <div class="panel-body">
                            <div class="form-group">
                                <label class="col-sm-3 control-label">Spalte</label>
                                <div class="col-sm-9">
                                    <select name="search_column" class="form-control">
                                        <option value="">Bitte wählen...</option>';
        foreach ($columns as $column) {
            $toolsContent .= '<option value="' . $column['name'] . '">' . $column['name'] . '</option>';
        }
        $toolsContent .= '
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">Suchbegriff</label>
                                <div class="col-sm-9">
                                    <div class="input-group">
                                        <input type="text" name="search_term" class="form-control">
                                        <span class="input-group-btn">
                                            <button type="submit" class="btn btn-primary"><i class="rex-icon fa-search"></i> Suchen</button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="col-sm-6">
                <form action="' . rex_url::currentBackendPage() . '" method="post" class="form-horizontal">
                    <input type="hidden" name="table" value="' . $selectedTable . '">
                    <input type="hidden" name="func" value="replace">
                    ' . $csrf->getHiddenField() . '
                    
                    <div class="panel panel-default">
                        <div class="panel-heading"><i class="rex-icon fa-exchange"></i> Ersetzen</div>
                        <div class="panel-body">
                            <div class="form-group">
                                <label class="col-sm-3 control-label">Spalte</label>
                                <div class="col-sm-9">
                                    <select name="replace_column" class="form-control">
                                        <option value="">Bitte wählen...</option>';
        foreach ($columns as $column) {
            $toolsContent .= '<option value="' . $column['name'] . '">' . $column['name'] . '</option>';
        }
        $toolsContent .= '
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">Suchen nach</label>
                                <div class="col-sm-9">
                                    <input type="text" name="search_term" class="form-control">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">Ersetzen durch</label>
                                <div class="col-sm-9">
                                    <div class="input-group">
                                        <input type="text" name="replace_term" class="form-control">
                                        <span class="input-group-btn">
                                            <button type="submit" class="btn btn-primary" onclick="return confirm(\'Wirklich ersetzen?\')">
                                                <i class="rex-icon fa-exchange"></i> Ersetzen
                                            </button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>';

        $fragment = new rex_fragment();
        $fragment->setVar('title', 'Werkzeuge');
        $fragment->setVar('body', $toolsContent, false);
        $content .= $fragment->parse('core/page/section.php');

        // Build list query
        $query = 'SELECT * FROM ' . $sql->escapeIdentifier($selectedTable);
        $params = [];

        if ($func === 'search') {
            $searchColumn = rex_get('search_column');
            $searchTerm = rex_get('search_term');
            if ($searchColumn && $searchTerm) {
                $query .= ' WHERE ' . $sql->escapeIdentifier($searchColumn) . ' LIKE :term';
                $params['term'] = '%' . $searchTerm . '%';
            }
        }

        $query .= ' ORDER BY id DESC';

        // Create and configure list
        $list = rex_list::factory($query, 30, '', $params);
        
        // Add action buttons at start
        $list->addColumn('actions', '', 0, ['<th class="rex-table-action">Aktionen</th>', '<td class="rex-table-action">###VALUE###</td>']);
        $list->setColumnFormat('actions', 'custom', function ($params) use ($selectedTable, $csrf) {
            $buttons = [];
            
            // Edit
            $buttons[] = '<a href="' . rex_url::currentBackendPage([
                'table' => $selectedTable,
                'func' => 'edit',
                'id' => $params['list']->getValue('id')
            ]) . '" class="btn btn-edit btn-xs" title="Bearbeiten"><i class="rex-icon fa-edit"></i></a>';
            
            // Delete
            $buttons[] = '<a href="' . rex_url::currentBackendPage([
                'table' => $selectedTable,
                'func' => 'delete',
                'id' => $params['list']->getValue('id')
            ]) . $csrf->getUrlParams() . '" 
                class="btn btn-delete btn-xs" 
                onclick="return confirm(\'Wirklich löschen?\')"
                title="Löschen"><i class="rex-icon fa-trash"></i></a>';
            
            return '<div class="btn-group">' . implode('', $buttons) . '</div>';
        });

        // Add wrapper for fixed action column
        $list->addTableAttribute('class', 'table-striped');
        $tableContent = '
        <div class="panel panel-default">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-sm-6">
                        <a href="' . rex_url::currentBackendPage([
                            'table' => $selectedTable,
                            'func' => 'add'
                        ]) . '" class="btn btn-save btn-xs"><i class="rex-icon fa-plus"></i> Neuer Datensatz</a>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <div class="table-wrapper">' . $list->get() . '</div>
                </div>
            </div>
        </div>
        <style>
            .table-wrapper {
                position: relative;
                margin: 0;
                padding: 0;
            }
            .table-wrapper table {
                margin-bottom: 0;
            }
            .table-wrapper th:first-child,
            .table-wrapper td:first-child {
                position: sticky;
                left: 0;
                background: #fff;
                z-index: 1;
                border-right: 2px solid #eee;
                min-width: 70px;
            }
            .table-wrapper th:first-child {
                background: #f3f6fb;
            }
            .rex-table-action {
                padding: 4px !important;
            }
            .btn-xs {
                height: 24px;
                width: 28px;
                padding: 3px 6px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .btn-group {
                display: flex;
                gap: 2px;
            }
        </style>';

        // Display table
        $fragment = new rex_fragment();
        $fragment->setVar('title', 'Datensätze' . (rex_get('search_term') ? ' - Suchergebnisse' : ''));
        $fragment->setVar('content', $tableContent, false);
        $content .= $fragment->parse('core/page/section.php');
    }
}

echo $content;
