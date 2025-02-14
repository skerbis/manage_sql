<?php
$content = '';
$message = '';
$error = '';

// Debug-Modus
$debug = false; // Auf 'true' setzen, um Debug-Ausgaben zu aktivieren

// Get selected table and handle actions
$selectedTable = rex_get('table', 'string');
$action = rex_post('action', 'string');

// CSRF protection - erstmal auskommentiert für Debug
// $csrfToken = rex_csrf_token::factory('table_records');

// Handle actions when form is submitted
if ($action /* && !$csrfToken->isValid() - entfernt für Debug */) {
    // if ($action && !$csrfToken->isValid()) { //CSRF wieder aktivieren
    //     $error = rex_i18n::msg('csrf_token_invalid');
    // }
   //  elseif ($action) {
        $sql = rex_sql::factory();
        $sql->setDebug(false);
    
    try {
        switch ($action) {
            case 'search':
                $searchColumn = rex_post('search_column', 'string');
                $searchTerm = rex_post('search_term', 'string');
                $searchType = rex_post('search_type', 'string');
                
                // Build WHERE clause based on search type
                $where = '';
                $params = [];
                if ($searchColumn && $searchTerm) {
                    switch ($searchType) {
                        case 'exact':
                            $where = $searchColumn . ' = :term';
                            $params['term'] = $searchTerm;
                            break;
                        case 'starts':
                            $where = $searchColumn . ' LIKE :term';
                            $params['term'] = $searchTerm . '%';
                            break;
                        case 'ends':
                            $where = $searchColumn . ' LIKE :term';
                            $params['term'] = '%' . $searchTerm;
                            break;
                        default: // contains
                            $where = $searchColumn . ' LIKE :term';
                            $params['term'] = '%' . $searchTerm . '%';
                    }
                    
                    $sql->setQuery('SELECT * FROM ' . $selectedTable . ' WHERE ' . $where, $params);
                    $message = count($sql->getArray()) . ' Datensätze gefunden.';
                }
                break;

            case 'replace':
                $replaceColumn = rex_post('replace_column', 'string');
                $searchTerm = rex_post('search_term', 'string');
                $replaceTerm = rex_post('replace_term', 'string');
                
                if ($replaceColumn && $searchTerm) {
                    $sql->setQuery(
                        'UPDATE ' . $selectedTable . ' 
                         SET ' . $replaceColumn . ' = REPLACE(' . $replaceColumn . ', :search, :replace)',
                        ['search' => $searchTerm, 'replace' => $replaceTerm]
                    );
                    $message = $sql->getRows() . ' Datensätze aktualisiert.';
                }
                break;

            case 'delete_results':
                $searchColumn = rex_post('search_column', 'string');
                $searchTerm = rex_post('search_term', 'string');
                $searchType = rex_post('search_type', 'string');

                if ($debug) {
                    echo '<pre>';
                    echo '<b>Debug Delete Results:</b><br>';
                    echo 'searchColumn: ';
                    var_dump($searchColumn);
                    echo 'searchTerm: ';
                    var_dump($searchTerm);
                    echo 'searchType: ';
                    var_dump($searchType);
                    echo '</pre>';
                }
                
                if ($searchColumn && $searchTerm) {
                    $where = '';
                    $params = [];
                    switch ($searchType) {
                        case 'exact':
                            $where = $searchColumn . ' = :term';
                            break;
                        case 'starts':
                            $where = $searchColumn . ' LIKE :term';
                            $searchTerm .= '%';
                            break;
                        case 'ends':
                            $where = $searchColumn . ' LIKE :term';
                            $searchTerm = '%' . $searchTerm;
                            break;
                        default: // contains
                            $where = $searchColumn . ' LIKE :term';
                            $searchTerm = '%' . $searchTerm . '%';
                    }

                    $params['term'] = $searchTerm;

                    if ($debug) {
                        echo '<pre>';
                        echo '<b>Debug Delete Results - Before SQL:</b><br>';
                        echo '$where: ';
                        var_dump($where);
                        echo '$params: ';
                        var_dump($params);
                        echo '</pre>';
                    }
                    
                    try {
                        $sql->setQuery('DELETE FROM ' . $selectedTable . ' WHERE ' . $where, $params);

                        if ($debug) {
                            echo '<pre>';
                            echo '<b>Debug Delete Results - After SQL:</b><br>';
                            echo '$sql->getRows(): ';
                            var_dump($sql->getRows());
                            echo '</pre>';
                        }

                        $message = $sql->getRows() . ' Datensätze gelöscht.';

                    } catch (rex_sql_exception $e) {
                        if ($debug) {
                            echo '<pre>';
                            echo '<b>Debug Delete Results - SQL Exception:</b><br>';
                            echo $e->getMessage();
                            echo '</pre>';
                        }
                        $error = $e->getMessage();
                    }
                }
                break;

            case 'truncate':
                $sql->setQuery('TRUNCATE TABLE ' . $selectedTable);
                $message = 'Tabelle wurde geleert.';
                break;
                
            case 'save':
                $data = rex_post('data', 'array', []);
                $recordId = rex_post('record_id', 'int');
                
                if ($data && $recordId) {
                    $sql->setTable($selectedTable);
                    $sql->setWhere(['id' => $recordId]);
                    $sql->setValues($data);
                    $sql->update();
                    $message = 'Datensatz gespeichert.';
                }
                break;
                
            case 'create':
                $data = rex_post('data', 'array', []);
                
                if ($data) {
                    $sql->setTable($selectedTable);
                    $sql->setValues($data);
                    $sql->insert();
                    $message = 'Datensatz erstellt.';
                }
                break;
        }
    } catch (rex_sql_exception $e) {
        $error = $e->getMessage();
    }
}

// Handle single record actions
$recordAction = rex_get('record_action', 'string');
$recordId = rex_get('record_id', 'int');

if ($recordAction && $recordId /*&& $csrfToken->isValid()*/) {
    // if ($recordAction && $recordId && $csrfToken->isValid()) { // CSRF wieder aktivieren

    $sql = rex_sql::factory();

    try {
        switch ($recordAction) {
            case 'delete':
                if ($debug) {
                    echo '<pre>';
                    echo '<b>Debug Single Record Delete:</b><br>';
                    echo 'recordId: ';
                    var_dump($recordId);
                    echo '</pre>';
                }
                $sql->setQuery('DELETE FROM ' . $selectedTable . ' WHERE id = :id', ['id' => $recordId]);
                if ($debug) {
                    echo '<pre>';
                    echo '<b>Debug Single Record Delete After SQL:</b><br>';
                    echo '$sql->getRows(): ';
                    var_dump($sql->getRows());
                    echo '</pre>';
                }
                $message = 'Datensatz gelöscht.';
                break;
        }
    } catch (rex_sql_exception $e) {
        $error = $e->getMessage();
    }
}

// Get all tables
$sql = rex_sql::factory();
$tables = $sql->getTablesAndViews();
$tables = array_filter($tables, function ($table) {
    return str_starts_with($table, 'rex_');
});

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
$formContent .= '</select></div></div></div></form>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Tabelle auswählen');
$fragment->setVar('body', $formContent, false);
$content .= $fragment->parse('core/page/section.php');

// Check if we're in edit mode
$editId = rex_request('edit_id', 'int', 0);
$addMode = rex_get('func') === 'add';

// Show edit/add form if requested
if ($editId || $addMode) {
    $sql = rex_sql::factory();

    if ($editId) {
        $sql->setTable($selectedTable);
        $sql->setWhere(['id' => $editId]);
        $sql->select();
    }

    if (!$editId || $sql->getRows()) {
        $editForm = '
            <form action="' . rex_url::currentBackendPage(['table' => $selectedTable]) . '" method="post">
                <input type="hidden" name="action" value="' . ($addMode ? 'create' : 'save') . '">
                ' . ($editId && !$addMode ? '<input type="hidden" name="record_id" value="' . $editId . '">' : '') . '
                ' /*. $csrfToken->getHiddenField()*/;

        $columns = rex_sql::showColumns($selectedTable);
        foreach ($columns as $column) {
            if ($column['name'] === 'id') continue;

            $label = ucfirst(str_replace('_', ' ', $column['name']));
            $value = $editId ? $sql->getValue($column['name']) : '';

            // Different input types based on column type
            if (strpos($column['type'], 'text') !== false) {
                $editForm .= '
                    <div class="form-group">
                        <label>' . $label . '</label>
                        <textarea name="data[' . $column['name'] . ']" class="form-control" rows="3">' . rex_escape($value) . '</textarea>
                    </div>';
            } elseif (strpos($column['type'], 'datetime') !== false) {
                $editForm .= '
                    <div class="form-group">
                        <label>' . $label . '</label>
                        <input type="datetime-local" name="data[' . $column['name'] . ']" value="' . ($value && $value != '0000-00-00 00:00:00' ? date('Y-m-d\TH:i', strtotime($value)) : '') . '" class="form-control">
                    </div>';
            } elseif (strpos($column['type'], 'date') !== false) {
                $editForm .= '
                    <div class="form-group">
                        <label>' . $label . '</label>
                        <input type="date" name="data[' . $column['name'] . ']" value="' . ($value && $value != '0000-00-00' ? date('Y-m-d', strtotime($value)) : '') . '" class="form-control">
                    </div>';
            } elseif (strpos($column['type'], 'tinyint(1)') !== false) {
                $editForm .= '
                    <div class="form-group">
                        <label class="checkbox">
                            <input type="hidden" name="data[' . $column['name'] . ']" value="0">
                            <input type="checkbox" name="data[' . $column['name'] . ']" value="1"' . ($value ? ' checked' : '') . '>
                            ' . $label . '
                        </label>
                    </div>';
            } else {
                $editForm .= '
                    <div class="form-group">
                        <label>' . $label . '</label>
                        <input type="text" name="data[' . $column['name'] . ']" value="' . rex_escape($value) . '" class="form-control">
                    </div>';
            }
        }

        $editForm .= '
                <div class="btn-toolbar">
                    <button type="submit" class="btn btn-save">' . ($addMode ? 'Erstellen' : 'Speichern') . '</button>
                    <a href="' . rex_url::currentBackendPage(['table' => $selectedTable]) . '" class="btn btn-default">Abbrechen</a>
                </div>
            </form>';

        $fragment = new rex_fragment();
        $fragment->setVar('title', $addMode ? 'Neuer Datensatz' : 'Datensatz bearbeiten');
        $fragment->setVar('body', $editForm, false);
        $content = $fragment->parse('core/page/section.php'); // Hier wird $content überschrieben!
    }
} else {

     // If table is selected and NOT in edit or add mode, show actions and list
    if ($selectedTable) {
        $columns = rex_sql::showColumns($selectedTable);
        $columnNames = array_column($columns, 'name');

        // Accordion for actions
        $actionContent = '
        <div class="panel-group" id="accordion" role="tablist">
            <div class="panel panel-default">
                <div class="panel-heading" role="tab" id="headingOne">
                    <h4 class="panel-title">
                        <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseSearch">
                            <i class="rex-icon fa-search"></i> Suchen
                        </a>
                    </h4>
                </div>
                <div id="collapseSearch" class="panel-collapse collapse">
                    <div class="panel-body">
                        <form action="' . rex_url::currentBackendPage(['table' => $selectedTable]) . '" method="post">
                            <input type="hidden" name="action" value="search">
                            <div class="row">
                                <div class="col-sm-4">
                                    <select name="search_column" class="form-control" required>
                                        <option value="">Spalte wählen...</option>';
        foreach ($columnNames as $column) {
            $actionContent .= '<option value="' . $column . '">' . $column . '</option>';
        }
        $actionContent .= '
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <select name="search_type" class="form-control">
                                        <option value="contains">Enthält</option>
                                        <option value="exact">Exakt</option>
                                        <option value="starts">Beginnt mit</option>
                                        <option value="ends">Endet mit</option>
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <input type="text" name="search_term" class="form-control" required>
                                        <span class="input-group-btn">
                                            <button type="submit" class="btn btn-primary"><i class="rex-icon fa-search"></i></button>
                                            <button type="submit" name="action" value="delete_results" class="btn btn-danger"
                                                onclick="return confirm(\'Gefundene Datensätze wirklich löschen?\')">
                                                <i class="rex-icon fa-trash"></i>
                                            </button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            ' /* . $csrfToken->getHiddenField() */ . '
                        </form>
                    </div>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading" role="tab" id="headingTwo">
                    <h4 class="panel-title">
                        <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseReplace">
                            <i class="rex-icon fa-exchange"></i> Ersetzen
                        </a>
                    </h4>
                </div>
                <div id="collapseReplace" class="panel-collapse collapse">
                    <div class="panel-body">
                        <form action="' . rex_url::currentBackendPage(['table' => $selectedTable]) . '" method="post">
                            <input type="hidden" name="action" value="replace">
                            <div class="row">
                                <div class="col-sm-4">
                                    <select name="replace_column" class="form-control" required>
                                        <option value="">Spalte wählen...</option>';
        foreach ($columnNames as $column) {
            $actionContent .= '<option value="' . $column . '">' . $column . '</option>';
        }
        $actionContent .= '
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <input type="text" name="search_term" class="form-control" placeholder="Suchen nach..." required>
                                </div>
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <input type="text" name="replace_term" class="form-control" placeholder="Ersetzen durch..." required>
                                        <span class="input-group-btn">
                                            <button type="submit" class="btn btn-primary" onclick="return confirm(\'Ersetzen wirklich durchführen?\')">
                                                <i class="rex-icon fa-exchange"></i>
                                            </button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            ' /* . $csrfToken->getHiddenField() */ . '
                        </form>
                    </div>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading" role="tab" id="headingThree">
                    <h4 class="panel-title">
                        <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseTruncate">
                            <i class="rex-icon fa-trash"></i> Tabelle leeren
                        </a>
                    </h4>
                </div>
                <div id="collapseTruncate" class="panel-collapse collapse">
                    <div class="panel-body">
                        <form action="' . rex_url::currentBackendPage(['table' => $selectedTable]) . '" method="post">
                            <input type="hidden" name="action" value="truncate">
                            <p class="alert alert-warning">
                                Diese Aktion löscht <strong>alle</strong> Datensätze aus der Tabelle. Dies kann nicht rückgängig gemacht werden!
                            </p>
                            <button type="submit" class="btn btn-danger" onclick="return confirm(\'Tabelle wirklich leeren?\')">
                                <i class="rex-icon fa-trash"></i> Tabelle leeren (TRUNCATE)
                            </button>
                            ' /* . $csrfToken->getHiddenField() */ . '
                        </form>
                    </div>
                </div>
            </div>
        </div>';

        $fragment = new rex_fragment();
        $fragment->setVar('title', 'Aktionen');
        $fragment->setVar('body', $actionContent, false);
        $content .= $fragment->parse('core/page/section.php');
        // Records list
        $listQuery = rex_sql::factory();
        $listQuery->setQuery('SELECT * FROM ' . $selectedTable . ' ORDER BY id DESC');
        $list = rex_list::factory('SELECT * FROM ' . $selectedTable . ' ORDER BY id DESC', 30);

        // Add actions column
        $list->addColumn('_actions', '', -1, ['<th class="rex-table-action">Aktionen</th>', '<td class="rex-table-action">###VALUE###</td>']);
        $list->setColumnPosition('_actions', 0);
        $list->setColumnFormat('_actions', 'custom', function ($params) use ($selectedTable, $csrfToken) {
            $editUrl = rex_url::currentBackendPage([
                'table' => $selectedTable,
                'edit_id' => $params['list']->getValue('id')
            ]);

            $copyUrl = rex_url::currentBackendPage([
                'table' => $selectedTable,
                'func' => 'add',
                'id' => $params['list']->getValue('id')
            ]);

            $deleteUrl = rex_url::currentBackendPage([
                'table' => $selectedTable,
                'record_action' => 'delete',
                'record_id' => $params['list']->getValue('id')
            ]) /*. '&' . $csrfToken->getUrlParams()*/;

            return '
            <div class="btn-group">
                <a href="' . $editUrl . '" class="btn btn-edit btn-xs" title="Bearbeiten">
                    <i class="rex-icon fa-edit"></i>
                </a>
                <a href="' . $copyUrl . '" class="btn btn-default btn-xs" title="Kopieren">
                    <i class="rex-icon fa-copy"></i>
                </a>
                <a href="' . $deleteUrl . '" class="btn btn-delete btn-xs" onclick="return confirm(\'Wirklich löschen?\')" title="Löschen">
                    <i class="rex-icon fa-trash"></i>
                </a>
            </div>';
        });

        // Format columns based on data type
        foreach ($columns as $column) {
            $name = $column['name'];
            if ($name === 'id') continue;

            // Truncate text fields if too long
            if (strpos($column['type'], 'text') !== false || strpos($column['type'], 'varchar') !== false) {
                $list->setColumnFormat($name, 'custom', function ($params) {
                    $value = $params['value'];
                    if ($value && strlen($value) > 100) {
                        $value = substr($value, 0, 100) . '...';
                    }
                    return rex_escape($value);
                });
            }

            // Format dates
            if (strpos($column['type'], 'datetime') !== false) {
                $list->setColumnFormat($name, 'custom', function ($params) {
                    $value = $params['value'];
                    if ($value && $value != '0000-00-00 00:00:00') {
                        return rex_formatter::strftime(strtotime($value), 'datetime');
                    }
                    return '';
                });
            }

            // Format boolean values
            if (strpos($column['type'], 'tinyint(1)') !== false) {
                $list->setColumnFormat($name, 'custom', function ($params) {
                    return $params['value'] ? '<span class="text-success">✓</span>' : '<span class="text-danger">✗</span>';
                });
            }
        }

        // Wrap table in custom wrapper div
        $list->addTableAttribute('class', 'table-striped');
        $tableContent = '<div class="table-responsive table-wrapper">' . $list->get() . '</div>';

        $fragment = new rex_fragment();
        $fragment->setVar('title', 'Datensätze');
        $fragment->setVar('content', $tableContent, false);
        $content .= $fragment->parse('core/page/section.php');

        // Add "New Record" button if not editing
        $content .= '
        <div class="panel panel-default">
            <div class="panel-body">
                <a href="' . rex_url::currentBackendPage(['table' => $selectedTable, 'func' => 'add']) . '" class="btn btn-save">
                    <i class="rex-icon fa-plus"></i> Neuer Datensatz
                </a>
            </div>
        </div>';

    }
}


    // Add custom CSS for mobile optimization and fixed action column
    $content .= '
    <style>
        .table-wrapper {
            position: relative;
            margin-bottom: 0;
            border: 0;
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
        }
        .checkbox {
            margin-top: 7px;
        }
        @media screen and (max-width: 768px) {
            .table-responsive > .table > tbody > tr > td {
                white-space: normal;
            }
            .table td[data-title]:before {
                content: attr(data-title);
                display: block;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .btn-group {
                display: flex;
                justify-content: flex-start;
            }
            .panel-title {
                font-size: 14px;
            }
            .input-group {
                margin-top: 10px;
            }
        }
    </style>';

echo $content;
