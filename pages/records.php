<?php
$content = '';
$message = '';
$error = '';

// Get selected table and handle actions
$selectedTable = rex_get('table', 'string');
$action = rex_post('action', 'string');

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
    
    // Move actions to the beginning
    $list->addColumn('actions', '', 0, ['<th class="rex-table-action">Aktionen</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnFormat('actions', 'custom', function ($params) use ($selectedTable) {
        $token = rex_csrf_token::factory('table_records');
        $editUrl = rex_url::backendPage('table_builder/records', [
            'table' => $selectedTable,
            'edit_id' => $params['list']->getValue('id')
        ]);
        $deleteUrl = rex_url::backendPage('table_builder/records', [
            'table' => $selectedTable,
            'record_action' => 'delete',
            'record_id' => $params['list']->getValue('id')
        ]) . '&' . $token->getUrlParams();
        
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

    // Add table responsive class
    $list->addTableAttribute('class', 'table-responsive');
    
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Datensätze');
    $fragment->setVar('content', $list->get(), false);
    $content .= $fragment->parse('core/page/section.php');

    // Add custom CSS for mobile optimization
    $content .= '
    <style>
        .table-responsive {
            border: 0;
            margin-bottom: 0;
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
}

echo $content;
