<?php
$content = '';
$message = '';
$error = '';

// Get selected table and handle actions
$selectedTable = rex_get('table', 'string');
$action = rex_post('action', 'string');
$recordAction = rex_post('record_action', 'string');
$recordId = rex_get('record_id', 'int');
$editId = rex_get('edit_id', 'int', 0); // Initialize $editId

// Get all tables
$sql = rex_sql::factory();
$tables = $sql->getTablesAndViews();
$tables = array_filter($tables, function($table) {
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

// If table is selected
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
                        ' . rex_csrf_token::factory('table_records')->getHiddenField() . '
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
                        ' . rex_csrf_token::factory('table_records')->getHiddenField() . '
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
                        ' . rex_csrf_token::factory('table_records')->getHiddenField() . '
                    </form>
                </div>
            </div>
        </div>
    </div>';

    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Aktionen');
    $fragment->setVar('body', $actionContent, false);
    $content .= $fragment->parse('core/page/section.php');

    // Add responsive table wrapper and classes
    $list = rex_list::factory('SELECT * FROM ' . $selectedTable . ' ORDER BY id DESC', 30);

    // Add actions column first
    $list->addColumn('_actions', '', -1, ['<th class="rex-table-action">Aktionen</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnPosition('_actions', 0);
    $list->setColumnFormat('_actions', 'custom', function ($params) use ($selectedTable) {
        $id = $params['list']->getValue('id'); // Get the ID of the current record
        $token = rex_csrf_token::factory('table_records');

        $editUrl = rex_url::backendPage('table_builder/records', [
            'table' => $selectedTable,
            'edit_id' => $id
        ]);

        // Generate the delete URL correctly, including the CSRF token.
        $deleteUrl = rex_url::currentBackendPage([ // Use currentBackendPage to preserve other GET params
            'table' => $selectedTable,
            'record_action' => 'delete',
            'record_id' => $id,
            ...$token->getUrlParamsAsArray(), // Use getUrlParamsAsArray to merge token params
        ]);

        // Debugging: Output the generated URLs and token validity
        dump('Edit URL: ' . $editUrl);
        dump('Delete URL: ' . $deleteUrl);
        dump('Is CSRF Token Valid (in URL Generation): ' . $token->isValid()); //Check during generation

        return '
        <div class="btn-group">
            <a href="' . $editUrl . '" class="btn btn-edit btn-xs" title="Bearbeiten">
                <i class="rex-icon fa-edit"></i>
            </a>
            <a href="' . $deleteUrl . '" class="btn btn-delete btn-xs" onclick="return confirm(\'Wirklich löschen?\')" title="Löschen">
                <i class="rex-icon fa-trash"></i>
            </a>
        </div>';
    });

    // Wrap table in custom wrapper div
    $list->addTableAttribute('class', 'table-striped');
    $tableContent = '<div class="table-wrapper">' . $list->get() . '</div>';

    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Datensätze');
    $fragment->setVar('content', $tableContent, false);
    $content .= $fragment->parse('core/page/section.php');

    // Add "New Record" button if not editing
    if (!$editId) {
        $content .= '
        <div class="panel panel-default">
            <div class="panel-body">
                <a href="' . rex_url::currentBackendPage(['table' => $selectedTable, 'func' => 'add']) . '" class="btn btn-save">
                    <i class="rex-icon fa-plus"></i> Neuer Datensatz
                </a>
            </div>
        </div>';
    }

    // Show edit/add form if requested
    if ($editId || rex_get('func') === 'add') {
        $sql = rex_sql::factory();

        if ($editId) {
            $sql->setTable($selectedTable);
            $sql->setWhere(['id' => $editId]);
            $sql->select();
        }

        if (!$editId || $sql->getRows()) {
            $editForm = '
            <form action="' . rex_url::currentBackendPage(['table' => $selectedTable]) . '" method="post">
                <input type="hidden" name="record_action" value="' . ($editId ? 'save' : 'create') . '">
                ' . ($editId ? '<input type="hidden" name="record_id" value="' . $editId . '">' : '') . '
                ' . rex_csrf_token::factory('table_records')->getHiddenField();

            foreach ($columns as $column) {
                if ($column['name'] === 'id') continue;

                $label = ucfirst(str_replace('_', ' ', $column['name']));
                $value = $editId ? $sql->getValue($column['name']) : '';

                if (str_contains($column['type'], 'text')) {
                    $editForm .= '
                    <div class="form-group">
                        <label>' . $label . '</label>
                        <textarea name="data[' . $column['name'] . ']" class="form-control" rows="3">' . rex_escape($value) . '</textarea>
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
                <button type="submit" class="btn btn-save">' . ($editId ? 'Speichern' : 'Erstellen') . '</button>
                <a href="' . rex_url::currentBackendPage(['table' => $selectedTable]) . '" class="btn btn-default">Abbrechen</a>
            </form>';

            $fragment = new rex_fragment();
            $fragment->setVar('title', $editId ? 'Datensatz bearbeiten' : 'Neuer Datensatz');
            $fragment->setVar('body', $editForm, false);
            $content .= $fragment->parse('core/page/section.php');
        }
    } else {
    }

    // Add custom CSS for mobile optimization and fixed action column
    $content .= '
    <style>
        .table-wrapper {
            position: relative;
            overflow-x: auto;
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

    // ----- ACTIONS (Save/Create/Delete/Search/Replace/Truncate) -----

    $token = rex_csrf_token::factory('table_records');

    // ** 1. SAVE/CREATE **
    if (in_array($recordAction, ['save', 'create']) && $token->isValid()) {
        $data = rex_post('data', 'array');

        $sql = rex_sql::factory();
        $sql->setTable($selectedTable);

        foreach ($columns as $column) {
            if ($column['name'] === 'id') continue; // ID wird nicht gesetzt/verändert
            $sql->setValue($column['name'], $data[$column['name']] ?? null);  //Null-Coalescing Operator, falls Wert fehlt
        }

        if ($recordAction === 'save') {
            $sql->setWhere(['id' => $recordId]);
            try {
                $sql->update();
                $message = 'Datensatz erfolgreich gespeichert.';
            } catch (Exception $e) {
                $error = 'Fehler beim Speichern des Datensatzes: ' . $e->getMessage();
            }

        } elseif ($recordAction === 'create') {
            try {
                $sql->insert();
                $message = 'Datensatz erfolgreich erstellt.';
            } catch (Exception $e) {
                $error = 'Fehler beim Erstellen des Datensatzes: 'e->getMessage();
            }
        }
        // Redirect, um das Formular zu leeren und die Änderungen anzuzeigen
        echo rex_view::success($message);
        echo rex_view::error($error);

        echo '<script>
                 window.location.href = "' . rex_url::currentBackendPage(['table' => $selectedTable]) . '";
              </script>';
        exit(); //Wichtig, um weiteren Output zu verhindern
    }

    // ** 2. DELETE **
    if ($recordAction === 'delete' && rex_csrf_token::factory('table_records')->isValid()) {  //Revalidate!
        $recordId = rex_get('record_id', 'int'); // Get record_id safely

        $sql = rex_sql::factory();
        $sql->setTable($selectedTable);
        $sql->setWhere(['id' => $recordId]);

        try {
            $sql->delete();
            $message = 'Datensatz erfolgreich gelöscht.';
        } catch (Exception $e) {
            $error = 'Fehler beim Löschen des Datensatzes: ' . $e->getMessage();
        }

        echo rex_view::success($message);
        echo rex_view::error($error);

        echo '<script>
                 window.location.href = "' . rex_url::currentBackendPage(['table' => $selectedTable]) . '";
              </script>';
        exit(); //Wichtig, um weiteren Output zu verhindern
    }  else {
            dump("CSRF Token invalid or recordAction is not delete!");
    }

    // ** 3. SEARCH **
    if ($action === 'search' && $token->isValid()) {
        $searchColumn = rex_post('search_column', 'string');
        $searchTerm = rex_post('search_term', 'string');
        $searchType = rex_post('search_type', 'string');

        $sql = rex_sql::factory();
        $sql->setTable($selectedTable);
        $searchTerm = $sql->escape($searchTerm);

        switch ($searchType) {
            case 'contains':
                $whereClause = "`$searchColumn` LIKE '%$searchTerm%'";
                break;
            case 'exact':
                $whereClause = "`$searchColumn` = '$searchTerm'";
                break;
            case 'starts':
                $whereClause = "`$searchColumn` LIKE '$searchTerm%'";
                break;
            case 'ends':
                $whereClause = "`$searchColumn` LIKE '%$searchTerm'";
                break;
            default:
                $whereClause = "`$searchColumn` LIKE '%$searchTerm%'"; // Default: Contains
        }

        $sql->setWhere($whereClause);

        //Anzeige der Suchergebnisse
        $list = rex_list::factory($sql->getQuery(), 30);  //Query wird direkt übergeben

        // Add actions column first
        $list->addColumn('_actions', '', -1, ['<th class="rex-table-action">Aktionen</th>', '<td class="rex-table-action">###VALUE###</td>']);
        $list->setColumnPosition('_actions', 0);
        $list->setColumnFormat('_actions', 'custom', function ($params) use ($selectedTable) {
            $id = $params['list']->getValue('id'); // Get the ID of the current record
            $token = rex_csrf_token::factory('table_records');

            $editUrl = rex_url::backendPage('table_builder/records', [
                'table' => $selectedTable,
                'edit_id' => $id
            ]);

            // Generate the delete URL correctly, including the CSRF token.
            $deleteUrl = rex_url::currentBackendPage([ // Use currentBackendPage to preserve other GET params
                'table' => $selectedTable,
                'record_action' => 'delete',
                'record_id' => $id,
                ...$token->getUrlParamsAsArray(), // Use getUrlParamsAsArray to merge token params
            ]);

            return '
            <div class="btn-group">
                <a href="' . $editUrl . '" class="btn btn-edit btn-xs" title="Bearbeiten">
                    <i class="rex-icon fa-edit"></i>
                </a>
                <a href="' . $deleteUrl . '" class="btn btn-delete btn-xs" onclick="return confirm(\'Wirklich löschen?\')" title="Löschen">
                    <i class="rex-icon fa-trash"></i>
                </a>
            </div>';
        });

        // Wrap table in custom wrapper div
        $list->addTableAttribute('class', 'table-striped');
        $tableContent = '<div class="table-wrapper">' . $list->get() . '</div>';

        $fragment = new rex_fragment();
        $fragment->setVar('title', 'Suchergebnisse');
        $fragment->setVar('content', $tableContent, false);
        $content = $fragment->parse('core/page/section.php');

        echo $content;
    }

    // ** 4. DELETE RESULTS **
    if ($action === 'delete_results' && $token->isValid()) {
        $searchColumn = rex_post('search_column', 'string');
        $searchTerm = rex_post('search_term', 'string');
        $searchType = rex_post('search_type', 'string');

        $sql = rex_sql::factory();
        $sql->setTable($selectedTable);
        $searchTerm = $sql->escape($searchTerm);

        switch ($searchType) {
            case 'contains':
                $whereClause = "`$searchColumn` LIKE '%$searchTerm%'";
                break;
            case 'exact':
                $whereClause = "`$searchColumn` = '$searchTerm'";
                break;
            case 'starts':
                $whereClause = "`$searchColumn` LIKE '$searchTerm%'";
                break;
            case 'ends':
                $whereClause = "`$searchColumn` LIKE '%$searchTerm'";
                break;
            default:
                $whereClause = "`$searchColumn` LIKE '%$searchTerm%'"; // Default: Contains
        }

        try {
            // Delete all records that match the search criteria
            $sql->setQuery("DELETE FROM `$selectedTable` WHERE " . $whereClause);
            $message = 'Gefundene Datensätze erfolgreich gelöscht.';
        } catch (Exception $e) {
            $error = 'Fehler beim Löschen der Datensätze: ' . $e->getMessage();
        }
        echo rex_view::success($message);
        echo rex_view::error($error);

        echo '<script>
                 window.location.href = "' . rex_url::currentBackendPage(['table' => $selectedTable]) . '";
              </script>';
        exit(); //Wichtig, um weiteren Output zu verhindern
    }

    // ** 5. REPLACE **
    if ($action === 'replace' && $token->isValid()) {
        $replaceColumn = rex_post('replace_column', 'string');
        $searchTerm = rex_post('search_term', 'string');
        $replaceTerm = rex_post('replace_term', 'string');

        $sql = rex_sql::factory();
        $sql->setTable($selectedTable);
        $searchTerm = $sql->escape($searchTerm);
        $replaceTerm = $sql->escape($replaceTerm);

        $sql->setQuery("UPDATE `$selectedTable` SET `$replaceColumn` = REPLACE(`$replaceColumn`, '$searchTerm', '$replaceTerm')");

        $message = 'Ersetzen erfolgreich durchgeführt.';
        echo rex_view::success($message);

        echo '<script>
                 window.location.href = "' . rex_url::currentBackendPage(['table' => $selectedTable]) . '";
              </script>';
        exit();
    }

    // ** 6. TRUNCATE **
    if ($action === 'truncate' && $token->isValid()) {
        $sql = rex_sql::factory();
        $sql->setTable($selectedTable);
        try {
            $sql->setQuery('TRUNCATE TABLE `' . $selectedTable . '`');
            $message = 'Tabelle erfolgreich geleert.';
        } catch (Exception $e) {
            $error = 'Fehler beim Leeren der Tabelle: ' . $e->getMessage();
        }
        echo rex_view::success($message);
        echo rex_view::error($error);

        echo '<script>
                 window.location.href = "' . rex_url::currentBackendPage(['table' => $selectedTable]) . '";
              </script>';
        exit();
    }

    if ($message) {
        echo rex_view::success($message);
    }
    if ($error) {
        echo rex_view::error($error);
    }
}
echo $content;
