<?php

use Symfony\Component\Mime\Message;

$content = '';
$message = '';
$error = '';

// Debug-Modus
$debug = false; // Auf 'true' setzen, um Debug-Ausgaben zu aktivieren

// Get selected table and handle actions
$selectedTable = rex_get('table', 'string');
$action = rex_post('action', 'string');
$recordAction = rex_get('record_action', 'string');
$recordId = rex_get('record_id', 'int');
$editId = rex_request('edit_id', 'int', 0);
$addMode = rex_get('func') === 'add';
$searchData = rex_session('table_records_search', 'array');

// CSRF protection
$csrfToken = rex_csrf_token::factory('table_records');

// SQL instance
$sql = rex_sql::factory();
$sql->setDebug($debug);

// --- Functions ---

/**
 * Builds a WHERE clause based on search parameters.
 *
 * @param string $column The column to search in.
 * @param string $term The search term.
 * @param string $type The search type (exact, starts, ends, contains).
 * @param rex_sql $sqlInstance The rex_sql instance for escaping.
 *
 * @return array An associative array containing the WHERE clause and parameters.
 */
function buildWhereClause(string $column, string $term, string $type, rex_sql $sqlInstance): array
{
    $where = '';
    if (!$column || !$term) {
        return ['where' => '', 'params' => []];  // Return empty WHERE clause if column or term is missing
    }

    $column = '`' . rex_escape($column) . '`'; // Escape and quote the column name

    switch ($type) {
        case 'exact':
            $where = $column . ' = ' . $sqlInstance->escape($term);
            break;
        case 'starts':
            $where = $column . ' LIKE ' . $sqlInstance->escape($term . '%'); // Escape with wildcard
            break;
        case 'ends':
            $where = $column . ' LIKE ' . $sqlInstance->escape('%' . $term); // Escape with wildcard
            break;
        default: // contains
            $where = $column . ' LIKE ' . $sqlInstance->escape('%' . $term . '%'); // Escape with wildcard
    }

    return ['where' => $where, 'params' => []]; // No parameters needed anymore
}

/**
 * Generates a single record action button.
 *
 * @param string $url The URL for the action.
 * @param string $iconClass The Font Awesome icon class.
 * @param string $title The title of the button.
 * @param bool $confirmation Whether to show a confirmation dialog.
 * @param string $cssClass Additional CSS classes for the button.
 *
 * @return string The HTML for the action button.
 */
function getActionButton(string $url, string $iconClass, string $title, bool $confirmation = false, string $cssClass = ''): string
{
    $onclick = $confirmation ? 'return confirm(\'' . $confirmation . '\')' : '';
    return '<a href="' . $url . '" class="btn ' . $cssClass . ' btn-xs" title="' . $title . '" onclick="' . $onclick . '"><i class="rex-icon ' . $iconClass . '"></i></a>';
}

// --- ACTION HANDLER ---

if ($action && !$csrfToken->isValid()) {
    $error = rex_i18n::msg('csrf_token_invalid');
} elseif ($action) {
    try {
        switch ($action) {
            case 'search':
                $searchColumn = rex_post('search_column', 'string');
                $searchTerm = rex_post('search_term', 'string');
                $searchType = rex_post('search_type', 'string');

                // Save search params in session
                $searchData = [
                    'column' => $searchColumn,
                    'term' => $searchTerm,
                    'type' => $searchType
                ];
                rex_set_session('table_records_search', $searchData);

                // Build WHERE clause based on search type
                $whereData = buildWhereClause($searchColumn, $searchTerm, $searchType, $sql);  // Pass $sql
                $where = $whereData['where'];
                $params = $whereData['params'];

                if ($where) {
                    $sql->setQuery('SELECT COUNT(*) as count FROM ' . $selectedTable . ' WHERE ' . $where); // No parameters
                    $count = $sql->getValue('count');
                    $message = $count . ' Datensätze gefunden.';
                }
                break;

            case 'replace':
    $replaceColumn = rex_post('replace_column', 'string');
    $searchTerm = rex_post('search_term', 'string');
    $replaceTerm = rex_post('replace_term', 'string', ''); // Default to empty string if not set

    if ($replaceColumn && $searchTerm !== '') { // Changed condition to check if searchTerm is set (can be empty string)
        $sql->setQuery(
            'UPDATE ' . $selectedTable . '
             SET `' . rex_escape($replaceColumn) . '` = REPLACE(`' . rex_escape($replaceColumn) . '`, :search, :replace)',
            ['search' => $searchTerm, 'replace' => $replaceTerm]
        );
        $message = $sql->getRows() . ' Datensätze aktualisiert.';
    }
    break;

            case 'delete_results':
                $searchColumn = rex_post('search_column', 'string');
                $searchTerm = rex_post('search_term', 'string');
                $searchType = rex_post('search_type', 'string');

                // Build WHERE clause based on search type
                $whereData = buildWhereClause($searchColumn, $searchTerm, $searchType, $sql);  // Pass $sql
                $where = $whereData['where'];
                $params = $whereData['params'];

                if ($where) {
                    try {
                        // First count matching records
                        $sql->setQuery('SELECT COUNT(*) as count FROM ' . $selectedTable . ' WHERE ' . $where);  // No parameters
                        $count = $sql->getValue('count');

                        // Then delete
                        $sql->setQuery('DELETE FROM ' . $selectedTable . ' WHERE ' . $where); // No parameters
                        $message = $count . ' Datensätze gelöscht.';
                    } catch (rex_sql_exception $e) {
                        $error = $e->getMessage();
                    }
                }
                break;

            case 'truncate':
                $sql->setQuery('TRUNCATE TABLE ' . $selectedTable);
                $message = 'Tabelle wurde geleert.';
                break;

            case 'save':
            case 'create':
                $data = rex_post('data', 'array', []);

                if ($data) {
                    $sql->setTable($selectedTable);
                    if ($action === 'save') {
                        $sql->setWhere(['id' => rex_post('record_id', 'int')]);
                        $sql->setValues($data);
                        $sql->update();
                        $message = 'Datensatz gespeichert.';
                    } else {
                        $sql->setValues($data);
                        $sql->insert();
                        $message = 'Datensatz erstellt.';
                    }
                }
                break;
        }
    } catch (rex_sql_exception $e) {
        $error = $e->getMessage();
    }
}

// Handle single record actions
if ($recordAction && $recordId && $csrfToken->isValid()) {
    try {
        if ($recordAction === 'delete') {
            $sql->setQuery('DELETE FROM ' . $selectedTable . ' WHERE id = :id', ['id' => $recordId]);
            $message = 'Datensatz gelöscht.';
        }
    } catch (rex_sql_exception $e) {
        $error = $e->getMessage();
    }
}

// Handle search reset
if (rex_get('reset_search', 'bool')) {
    rex_unset_session('table_records_search');
    $searchData = []; // Reset $searchData as well
}

// Get all tables
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
    <input type="hidden" name="page" value="manage_sql/records">
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group">
                <label for="table">Tabelle</label>
                <select name="table" id="table" class="form-control" onchange="this.form.submit()">
                    <option value="">Bitte wählen...</option>';
foreach ($tables as $table) {
    $selected = ($selectedTable === $table) ? ' selected' : '';
    $formContent .= '<option value="' . $table . '"' . $selected . '>' . $table . '</option>';
}
$formContent .= '</select></div></div></div></form>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Tabelle auswählen');
$fragment->setVar('body', $formContent, false);
$content .= $fragment->parse('core/page/section.php');

if ($selectedTable) {
    echo '<pre>selectedTable: ' . $selectedTable . '</pre>'; // Debug Table Name
}

// --- ADDED: Reset Filter Button (Outside Accordion) ---
if ($searchData) {
    $resetUrl = rex_url::currentBackendPage(['table' => $selectedTable, 'reset_search' => 1]);
    $content .= '
        <div class="alert alert-info">
            Aktiver Filter: <strong>' . rex_escape($searchData['column']) . '</strong> ' . rex_escape($searchData['term']) . '
            <a href="' . $resetUrl . '" class="btn btn-default btn-xs pull-right ms-records-text-dark">
                <i class="rex-icon fa-times"></i> Filter zurücksetzen
            </a>
        </div>';
}

// Show edit/add form if requested
if ($editId || $addMode) {
    if ($editId) {
        $sql->setTable($selectedTable);
        $sql->setWhere(['id' => $editId]);
        $sql->select();
    }

    if (!$editId || $sql->getRows()) {
        $formAction = rex_url::currentBackendPage(['table' => $selectedTable]);
        $editForm = '
            <form action="' . $formAction . '" method="post">
                <input type="hidden" name="action" value="' . ($addMode ? 'create' : 'save') . '">
                ' . ($editId && !$addMode ? '<input type="hidden" name="record_id" value="' . $editId . '">' : '') . '
                ' . $csrfToken->getHiddenField();

        $columns = rex_sql::showColumns($selectedTable);
        foreach ($columns as $column) {
            if ($column['name'] === 'id') continue;

            $label = ucfirst(str_replace('_', ' ', $column['name']));
            $value = $editId ? $sql->getValue($column['name']) : '';
            $columnName = $column['name'];

            $editForm .= '<div class="form-group"><label>' . $label . '</label>';
            $inputAttributes = 'name="data[' . $columnName . ']" class="form-control"'; // Default input attributes

            // Different input types based on column type
            if (strpos($column['type'], 'text') !== false) {
                $editForm .= '<textarea ' . $inputAttributes . ' rows="3">' . rex_escape($value) . '</textarea>';
            } elseif (strpos($column['type'], 'datetime') !== false) {
                $dateTimeValue = ($value && $value != '0000-00-00 00:00:00') ? date('Y-m-d\TH:i', strtotime($value)) : '';
                $editForm .= '<input type="datetime-local" ' . $inputAttributes . ' value="' . $dateTimeValue . '">';
            } elseif (strpos($column['type'], 'date') !== false) {
                $dateValue = ($value && $value != '0000-00-00') ? date('Y-m-d', strtotime($value)) : '';
                $editForm .= '<input type="date" ' . $inputAttributes . ' value="' . $dateValue . '">';
            } elseif (strpos($column['type'], 'tinyint(1)') !== false) {
                $checked = $value ? ' checked' : '';
                $editForm .= '
                    <div class="checkbox">
                        <label>
                            <input type="hidden" name="data[' . $columnName . ']" value="0">
                            <input type="checkbox" ' . $inputAttributes . ' value="1"' . $checked . '>
                            ' . $label . '
                        </label>
                    </div>';
            } else {
                $editForm .= '<input type="text" ' . $inputAttributes . ' value="' . rex_escape($value) . '">';
            }
            $editForm .= '</div>';
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
        $content = $fragment->parse('core/page/section.php');
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
                    <div class="panel-body">';

        $formAction = rex_url::currentBackendPage(['table' => $selectedTable]);
        $actionContent .= '
                        <form action="' . $formAction . '" method="post">
                            <input type="hidden" name="action" value="search">
                            <div class="row">
                                <div class="col-sm-4">
                                    <select name="search_column" class="form-control" required>
                                        <option value="">Spalte wählen...</option>';
        foreach ($columnNames as $column) {
            $selected = (isset($searchData['column']) && $searchData['column'] === $column) ? ' selected' : '';
            $actionContent .= '<option value="' . $column . '"' . $selected . '>' . $column . '</option>';
        }
        $actionContent .= '
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <select name="search_type" class="form-control">
                                        <option value="contains"' . (isset($searchData['type']) && $searchData['type'] === 'contains' ? ' selected' : '') . '>Enthält</option>
                                        <option value="exact"' . (isset($searchData['type']) && $searchData['type'] === 'exact' ? ' selected' : '') . '>Exakt</option>
                                        <option value="starts"' . (isset($searchData['type']) && $searchData['type'] === 'starts' ? ' selected' : '') . '>Beginnt mit</option>
                                        <option value="ends"' . (isset($searchData['type']) && $searchData['type'] === 'ends' ? ' selected' : '') . '>Endet mit</option>
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <input type="text" name="search_term" class="form-control" required value="' . (isset($searchData['term']) ? rex_escape($searchData['term']) : '') . '">
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
                            ' . $csrfToken->getHiddenField() . '
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
                        <form action="' . $formAction . '" method="post">
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
                                        <input type="text" name="replace_term" class="form-control" placeholder="Ersetzen durch...">
                                        <span class="input-group-btn">
                                            <button type="submit" class="btn btn-primary" onclick="return confirm(\'Ersetzen wirklich durchführen?\')">
                                                <i class="rex-icon fa-exchange"></i>
                                            </button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            ' . $csrfToken->getHiddenField() . '
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
                        <form action="' . $formAction . '" method="post">
                            <input type="hidden" name="action" value="truncate">
                            <p class="alert alert-warning">
                                Diese Aktion löscht <strong>alle</strong> Datensätze aus der Tabelle. Dies kann nicht rückgängig gemacht werden!
                            </p>
                            <button type="submit" class="btn btn-danger" onclick="return confirm(\'Tabelle wirklich leeren?\')">
                                <i class="rex-icon fa-trash"></i> Tabelle leeren (TRUNCATE)
                            </button>
                            ' . $csrfToken->getHiddenField() . '
                        </form>
                    </div>
                </div>
            </div>
        </div>';

        $fragment = new rex_fragment();
        $fragment->setVar('title', 'Aktionen');
        $fragment->setVar('body', $actionContent, false);
        $content .= $fragment->parse('core/page/section.php');

        // Build base query for list
        $whereCondition = '';
        $params = [];

        // Apply search filter if exists
        if ($searchData) {
            $whereData = buildWhereClause($searchData['column'], $searchData['term'], $searchData['type'], $sql);  // Pass $sql
            $whereCondition = ' WHERE ' . $whereData['where'];
            $params = $whereData['params'];
        }

        $query = 'SELECT * FROM ' . $selectedTable . $whereCondition . ' ORDER BY id DESC';

        echo '<pre>Final Query: ' . $query . '</pre>'; // Debug Query

        try {
            $list = rex_list::factory($query);
            // dump($list); // Debug list object, remove in production!

            // Add actions column
            $list->addColumn('_actions', '', -1, ['<th class="rex-table-action">Aktionen</th>', '<td class="rex-table-action">###VALUE###</td>']);
            $list->setColumnPosition('_actions', 0);

            $list->setColumnFormat('_actions', 'custom', function ($params) use ($selectedTable, $csrfToken) {
                $id = $params['list']->getValue('id');

                $editUrl = rex_url::currentBackendPage(['table' => $selectedTable, 'edit_id' => $id]);
                $copyUrl = rex_url::currentBackendPage(['table' => $selectedTable, 'func' => 'add', 'id' => $id]);
                $deleteUrl = rex_url::currentBackendPage(['table' => $selectedTable, 'record_action' => 'delete', 'record_id' => $id]) . '&' . $csrfToken->getUrlParams();

                $editButton = getActionButton($editUrl, 'fa-edit', 'Bearbeiten', false, 'btn-edit');
                $copyButton = getActionButton($copyUrl, 'fa-copy', 'Kopieren');
                $deleteButton = getActionButton($deleteUrl, 'fa-trash', 'Löschen', 'Wirklich löschen?', 'btn-delete');

                return '<div class="btn-group">' . $editButton . $copyButton . $deleteButton . '</div>';
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
            $fragment->setVar('title', 'Datensätze' . ($searchData ? ' (gefiltert)' : ''));
            $fragment->setVar('content', $tableContent, false);
            $content .= $fragment->parse('core/page/section.php');

            // Add "New Record" button if not editing
            $addUrl = rex_url::currentBackendPage(['table' => $selectedTable, 'func' => 'add']);
            $content .= '
                <div class="panel panel-default">
                    <div class="panel-body">
                        <a href="' . $addUrl . '" class="btn btn-save">
                            <i class="rex-icon fa-plus"></i> Neuer Datensatz
                        </a>
                    </div>
                </div>';
        } catch (Exception $e) {
            $error = $e->getMessage();
            $content .= rex_view::error($error);
        }
    }
}

// Add custom CSS for mobile optimization and fixed action column
$content .= '
<style>
    .ms-records-text-dark {
        color: #000 !important; /* Or any dark color */
    }
    .ms-records-table-wrapper {
        position: relative;
        margin-bottom: 0;
        border: 0;
    }
    .ms-records-table-wrapper table {
        margin-bottom: 0;
    }
    .ms-records-table-wrapper th:first-child,
    .ms-records-table-wrapper td:first-child {
        position: sticky;
        left: 0;
        background: #fff;
        z-index: 1;
        border-right: 2px solid #eee;
    }
    .ms-records-checkbox {
        margin-top: 7px;
    }
    @media screen and (max-width: 768px) {
        .ms-records-table-responsive > .ms-records-table > tbody > tr > td {
            white-space: normal;
        }
        .ms-records-table td[data-title]:before {
            content: attr(data-title);
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .ms-records-btn-group {
            display: flex;
            justify-content: flex-start;
        }
        .ms-records-panel-title {
            font-size: 14px;
        }
        .ms-records-input-group {
            margin-top: 10px;
        }
    }
</style>';

echo $content;
