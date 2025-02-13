<?php

$content = '';

// Get all tables
$sql = rex_sql::factory();
$tables = $sql->getTablesAndViews();
$tables = array_filter($tables, function ($table) {
    return str_starts_with($table, 'rex_');
});

// Get selected table structure if a table is selected
$selectedTable = rex_request('table', 'string', '');
$columns = [];
if ($selectedTable && in_array($selectedTable, $tables)) {
    $columns = rex_sql::showColumns($selectedTable);
}

// Handle form submission
if (rex_post('generate', 'boolean') || rex_post('test_query', 'boolean')) {
    $queryType = rex_post('query_type', 'string');
    $selectedColumns = rex_post('columns', 'array', []);
    $whereColumns = rex_post('where', 'array', []);
    $orderBy = rex_post('orderby', 'array', []);
    $limit = rex_post('limit', 'int', 0);

    // Generate code
    $code = generateQueryCode($selectedTable, $queryType, $selectedColumns, $whereColumns, $orderBy, $limit);

    // Show generated code
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Generierter Code');
    $fragment->setVar('body', '
        <pre class="pre-scrollable"><code>' . rex_escape($code) . '</code></pre>
        <button class="btn btn-default" onclick="copyToClipboard()">
            <i class="rex-icon fa-copy"></i> In Zwischenablage kopieren
        </button>
        <script>
        function copyToClipboard() {
            const code = document.querySelector("pre code").textContent;
            navigator.clipboard.writeText(code).then(() => {
                alert("Code wurde in die Zwischenablage kopiert!");
            });
        }
        </script>
    ', false);
    $content .= $fragment->parse('core/page/section.php');

    // Test query if requested
    if (rex_post('test_query', 'boolean') && $queryType === 'select') {
        $sql = rex_sql::factory();
        try {
            // Build query based on the generated code
            $queryData = buildQuery($selectedTable, $queryType, $selectedColumns, $whereColumns, $orderBy, $limit);

            $query = $queryData['query'];
            $params = $queryData['params'];
            $sql->setQuery($query, $params);

            $result = $sql->getArray();
        } catch (rex_sql_exception $e) {
            $result = 'Fehler: ' . $e->getMessage();
        }

        // Show dump result
        $fragment = new rex_fragment();
        $fragment->setVar('title', 'Query Ergebnis');
        $fragment->setVar('body', dump($result), false);
        $content .= $fragment->parse('core/page/section.php');
    }
        // Test query if requested
    if (rex_post('test_query', 'boolean') && $queryType === 'count') {
        $sql = rex_sql::factory();
        try {
            // Build query based on the generated code
            $queryData = buildQuery($selectedTable, $queryType, $selectedColumns, $whereColumns, $orderBy, $limit);

            $query = $queryData['query'];
            $params = $queryData['params'];
            $sql->setQuery($query, $params);

            $result = $sql->getValue('count');
        } catch (rex_sql_exception $e) {
            $result = 'Fehler: ' . $e->getMessage();
        }

        // Show dump result
        $fragment = new rex_fragment();
        $fragment->setVar('title', 'Query Ergebnis');
        $fragment->setVar('body', '<pre class="pre-scrollable">' . rex_escape(print_r($result, true)) . '</pre>', false);
        $content .= $fragment->parse('core/page/section.php');
    }
}

// Build form
$formContent = '
<form id="querybuilder" action="' . rex_url::currentBackendPage() . '" method="get">
    <input type="hidden" name="page" value="table_builder/query">
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

if ($selectedTable && !empty($columns)) {
    $formContent .= '
    <form action="' . rex_url::currentBackendPage(['table' => $selectedTable]) . '" method="post">
        <div class="col-sm-6">
            <div class="form-group">
                <label for="query_type">Query Typ</label>
                <select name="query_type" id="query_type" class="form-control" onchange="toggleQueryOptions(this.value)">
                    <option value="select">SELECT</option>
                    <option value="count">COUNT</option>
                    <option value="insert">INSERT</option>
                    <option value="update">UPDATE</option>
                    <option value="delete">DELETE</option>
                </select>
            </div>
        </div>
    
        <div class="panel panel-default select-options">
            <div class="panel-heading">Spalten</div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-sm-12">
                        <label class="checkbox-inline" style="margin-bottom: 15px;">
                            <input type="checkbox" name="select_all" id="select_all" checked onclick="toggleAllColumns(this)">
                            <strong>Alle Spalten (*)</strong>
                        </label>
                    </div>
                </div>
                <div class="row" id="column-list">';
    
    foreach ($columns as $column) {
        $formContent .= '
                    <div class="col-sm-3">
                        <label class="checkbox-inline">
                            <input type="checkbox" name="columns[]" value="' . $column['name'] . '" checked class="column-checkbox">
                            ' . $column['name'] . '
                        </label>
                    </div>';
    }
    
    $formContent .= '
                </div>
            </div>
        </div>
        
        <div class="panel panel-default where-panel">
            <div class="panel-heading">WHERE Bedingungen</div>
            <div class="panel-body" id="where-conditions">
                <div class="where-row row">
                    <div class="col-sm-4">
                        <select name="where[column][]" class="form-control">
                            <option value="">Spalte wählen...</option>';
    foreach ($columns as $column) {
        $formContent .= '<option value="' . $column['name'] . '">' . $column['name'] . '</option>';
    }
    $formContent .= '
                        </select>
                    </div>
                    <div class="col-sm-3">
                        <select name="where[operator][]" class="form-control">
                            <option value="=">=</option>
                            <option value="!=">!=</option>
                            <option value=">">></option>
                            <option value=">=">>=</option>
                            <option value="<"><</option>
                            <option value="<="><=</option>
                            <option value="LIKE">LIKE</option>
                            <option value="IN">IN</option>
                            <option value="NOT IN">NOT IN</option>
                            <option value="IS NULL">IS NULL</option>
                            <option value="IS NOT NULL">IS NOT NULL</option>
                        </select>
                    </div>
                    <div class="col-sm-3">
                        <input type="text" name="where[value][]" class="form-control" placeholder="Wert">
                    </div>
                    <div class="col-sm-2">
                        <button type="button" class="btn btn-delete" onclick="removeWhereRow(this)">
                            <i class="rex-icon fa-minus-circle"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="panel-footer">
                <button type="button" class="btn btn-default" onclick="addWhereRow()">
                    <i class="rex-icon fa-plus"></i> Weitere Bedingung
                </button>
            </div>
        </div>
        
        <div class="panel panel-default select-options">
            <div class="panel-heading">ORDER BY</div>
            <div class="panel-body" id="orderby-conditions">
                <div class="orderby-row row">
                    <div class="col-sm-6">
                        <select name="orderby[column][]" class="form-control">
                            <option value="">Spalte wählen...</option>';
    foreach ($columns as $column) {
        $formContent .= '<option value="' . $column['name'] . '">' . $column['name'] . '</option>';
    }
    $formContent .= '
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <select name="orderby[direction][]" class="form-control">
                            <option value="ASC">Aufsteigend</option>
                            <option value="DESC">Absteigend</option>
                        </select>
                    </div>
                    <div class="col-sm-2">
                        <button type="button" class="btn btn-delete" onclick="removeOrderByRow(this)">
                            <i class="rex-icon fa-minus-circle"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="panel-footer">
                <button type="button" class="btn btn-default" onclick="addOrderByRow()">
                    <i class="rex-icon fa-plus"></i> Weitere Sortierung
                </button>
            </div>
        </div>
        
        <div class="form-group select-options">
            <label for="limit">LIMIT</label>
            <input type="number" name="limit" id="limit" class="form-control" min="0">
        </div>
        
        <div class="panel-footer">
            <button type="submit" name="generate" value="1" class="btn btn-save">Code generieren</button>
            <button type="submit" name="test_query" value="1" class="btn btn-primary">Query testen</button>
        </div>
    </form>';
}

$formContent .= '
<script>
function toggleQueryOptions(type) {
    const selectOptions = document.querySelectorAll(".select-options");
    const wherePanel = document.querySelector(".where-panel");
    
    selectOptions.forEach(el => {
        el.style.display = type === "select" ? "block" : "none";
    });
    
    wherePanel.style.display = ["select", "update", "delete"].includes(type) ? "block" : "none";
}

function toggleAllColumns(checkbox) {
    const columnCheckboxes = document.querySelectorAll(".column-checkbox");
    const columnList = document.getElementById("column-list");
    
    columnCheckboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    
    // Toggle visibility of individual columns
    columnList.style.display = checkbox.checked ? "none" : "flex";
}

function addWhereRow() {
    const container = document.getElementById("where-conditions");
    const template = container.querySelector(".where-row").cloneNode(true);
    // Reset input values
    template.querySelectorAll("select, input").forEach(el => el.value = "");
    container.appendChild(template);
}

function removeWhereRow(button) {
    const row = button.closest(".where-row");
    if (document.querySelectorAll(".where-row").length > 1) {
        row.remove();
    }
}

function addOrderByRow() {
    const container = document.getElementById("orderby-conditions");
    const template = container.querySelector(".orderby-row").cloneNode(true);
    // Reset select values
    template.querySelectorAll("select").forEach(el => el.value = "");
    container.appendChild(template);
}

function removeOrderByRow(button) {
    const row = button.closest(".orderby-row");
    if (document.querySelectorAll(".orderby-row").length > 1) {
        row.remove();
    }
}

// Initial state
document.addEventListener("DOMContentLoaded", function() {
    const selectAll = document.getElementById("select_all");
    if (selectAll) {
        const columnList = document.getElementById("column-list");
        columnList.style.display = selectAll.checked ? "none" : "flex";
        
        // Add event listeners to individual checkboxes
        const columnCheckboxes = document.querySelectorAll(".column-checkbox");
        columnCheckboxes.forEach(cb => {
            cb.addEventListener("change", function() {
                const allChecked = Array.from(columnCheckboxes).every(cb => cb.checked);
                document.getElementById("select_all").checked = allChecked;
            });
        });
        
        // Initial query type options
        const queryType = document.getElementById("query_type");
        if (queryType) {
            toggleQueryOptions(queryType.value);
        }
    }
});
</script>';

// Add form to content
$fragment = new rex_fragment();
$fragment->setVar('title', 'Query Builder');
$fragment->setVar('body', $formContent, false);
$content .= $fragment->parse('core/page/section.php');

echo $content;

/**
 * Generate rex_sql code
 */
function generateQueryCode($table, $queryType, $columns, $where, $orderBy, $limit)
{
    $code = [];
    $code[] = '// Rex SQL Query für ' . $table;
    $code[] = '$sql = rex_sql::factory();';

    switch ($queryType) {
        case 'select':
            $queryData = buildQuery($table, $queryType, $columns, $where, $orderBy, $limit);

            $query = $queryData['query'];
            $params = $queryData['params'];

            $code[] = '// Query ausführen';
            $code[] = '$sql->setQuery("' . $query . '", ' . (empty($params) ? '[]' : var_export($params, true)) . ');';
            $code[] = '';
            $code[] = '// Beispiele für Datenzugriff:';
            $code[] = '// Alle Datensätze als Array';
            $code[] = '$data = $sql->getArray();';
            $code[] = '';
            $code[] = '// Einzelner Datensatz';
            $code[] = '$sql->getRow();';
            $code[] = '$value = $sql->getValue("spaltenname");';
            break;
         case 'count':
            $queryData = buildQuery($table, $queryType, $columns, $where, $orderBy, $limit);

            $query = $queryData['query'];
            $params = $queryData['params'];

            $code[] = '// Query ausführen';
            $code[] = '$sql->setQuery("' . $query . '", ' . (empty($params) ? '[]' : var_export($params, true)) . ');';
            $code[] = '';
            $code[] = '// Beispiele für Datenzugriff:';
            $code[] = '// Wert';
            $code[] = '$value = $sql->getValue();';
            break;

        case 'insert':
            $code[] = '$sql->setTable("' . $table . '");';
            $code[] = '';
            $code[] = '// Werte setzen';
            if ($columns) {
                foreach ($columns as $column) {
                    $code[] = '$sql->setValue("' . $column . '", $' . $column . '); // Wert für ' . $column;
                }
            } else {
                $code[] = '// Beispiel:';
                $code[] = '// $sql->setValue("name", $name);';
                $code[] = '// $sql->setValue("description", $description);';
            }
            $code[] = '';
            $code[] = 'try {';
            $code[] = '    $sql->insert();';
            $code[] = '    $lastId = $sql->getLastId(); // ID des neuen Datensatzes';
            $code[] = '} catch (rex_sql_exception $e) {';
            $code[] = '    // Fehlerbehandlung';
            $code[] = '    echo $e->getMessage();';
            $code[] = '}';
            break;

        case 'update':
            $code[] = '$sql->setTable("' . $table . '");';
            $code[] = '';
            $code[] = '// Werte setzen';
            if ($columns) {
                foreach ($columns as $column) {
                    $code[] = '$sql->setValue("' . $column . '", $' . $column . '); // Wert für ' . $column;
                }
            } else {
                $code[] = '// Beispiel:';
                $code[] = '// $sql->setValue("name", $name);';
                $code[] = '// $sql->setValue("description", $description);';
            }

            // WHERE conditions for update
            if (!empty($where['column'])) {
                $whereConditions = [];
                $whereParams = [];
                foreach ($where['column'] as $i => $column) {
                    if ($column && isset($where['operator'][$i])) {
                        $operator = $where['operator'][$i];
                        if (in_array($operator, ['IS NULL', 'IS NOT NULL'])) {
                            $whereConditions[] = $column . ' ' . $operator;
                        } else {
                            $paramName = 'where_' . $i;
                            $whereConditions[] = $column . ' ' . $operator . ' :' . $paramName;
                            $whereParams[$paramName] = $where['value'][$i];
                        }
                    }
                }
                if ($whereConditions) {
                    $code[] = '';
                    $code[] = '// WHERE Bedingung setzen';
                    $code[] = '$sql->setWhere("' . implode(' AND ', $whereConditions) . '", ' . var_export($whereParams, true) . ');';
                }
            } else {
                $code[] = '';
                $code[] = '// WHERE Bedingung nicht vergessen!';
                $code[] = '// $sql->setWhere("id = :id", ["id" => $id]);';
            }

            $code[] = '';
            $code[] = 'try {';
            $code[] = '    $sql->update();';
            $code[] = '    $affectedRows = $sql->getRows(); // Anzahl der aktualisierten Datensätze';
            $code[] = '} catch (rex_sql_exception $e) {';
            $code[] = '    // Fehlerbehandlung';
            $code[] = '    echo $e->getMessage();';
            $code[] = '}';
            break;

        case 'delete':
            $code[] = '$sql->setTable("' . $table . '");';

            // WHERE conditions for delete
            if (!empty($where['column'])) {
                $whereConditions = [];
                $whereParams = [];
                foreach ($where['column'] as $i => $column) {
                    if ($column && isset($where['operator'][$i])) {
                        $operator = $where['operator'][$i];
                        if (in_array($operator, ['IS NULL', 'IS NOT NULL'])) {
                            $whereConditions[] = $column . ' ' . $operator;
                        } else {
                            $paramName = 'where_' . $i;
                            $whereConditions[] = $column . ' ' . $operator . ' :' . $paramName;
                            $whereParams[$paramName] = $where['value'][$i];
                        }
                    }
                }
                if ($whereConditions) {
                    $code[] = '';
                    $code[] = '// WHERE Bedingung setzen';
                    $code[] = '$sql->setWhere("' . implode(' AND ', $whereConditions) . '", ' . var_export($whereParams, true) . ');';
                }
            } else {
                $code[] = '';
                $code[] = '// WHERE Bedingung nicht vergessen!';
                $code[] = '// $sql->setWhere("id = :id", ["id" => $id]);';
            }

            $code[] = '';
            $code[] = 'try {';
            $code[] = '    $sql->delete();';
            $code[] = '    $affectedRows = $sql->getRows(); // Anzahl der gelöschten Datensätze';
            $code[] = '} catch (rex_sql_exception $e) {';
            $code[] = '    // Fehlerbehandlung';
            $code[] = '    echo $e->getMessage();';
            $code[] = '}';
            break;
    }

    return implode("\n", $code);
}


/**
 * Build query and params
 */
function buildQuery($table, $queryType, $columns, $where, $orderBy, $limit) {
    $params = [];
    
    switch ($queryType) {
        case 'select':
            // Handle SELECT columns
            $selectAll = rex_post('select_all', 'boolean');
            $columnList = $selectAll ? '*' : ($columns ? implode(', ', $columns) : '*');
            
            // Build WHERE conditions
            $conditions = [];
            if (!empty($where['column'])) {
                foreach ($where['column'] as $i => $column) {
                    if ($column && isset($where['operator'][$i])) {
                        $operator = $where['operator'][$i];
                        if (in_array($operator, ['IS NULL', 'IS NOT NULL'])) {
                            $conditions[] = $column . ' ' . $operator;
                        } else {
                            $paramName = 'where_' . $i;
                            $conditions[] = $column . ' ' . $operator . ' :' . $paramName;
                            $params[$paramName] = $where['value'][$i];
                        }
                    }
                }
            }
            
            // Build ORDER BY
            $orderByParts = [];
            if (!empty($orderBy['column'])) {
                foreach ($orderBy['column'] as $i => $column) {
                    if ($column) {
                        $orderByParts[] = $column . ' ' . ($orderBy['direction'][$i] ?? 'ASC');
                    }
                }
            }
            
            // Build query
            $query = 'SELECT ' . $columnList . "\n";
            $query .= 'FROM ' . $table;
            
            if ($conditions) {
                $query .= "\nWHERE " . implode(' AND ', $conditions);
            }
            
            if ($orderByParts) {
                $query .= "\nORDER BY " . implode(', ', $orderByParts);
            }
            
            if ($limit > 0) {
                $query .= "\nLIMIT " . $limit;
            }
            
            return ['query' => $query, 'params' => $params];
             case 'count':
            // Build WHERE conditions
            $conditions = [];
            if (!empty($where['column'])) {
                foreach ($where['column'] as $i => $column) {
                    if ($column && isset($where['operator'][$i])) {
                        $operator = $where['operator'][$i];
                        if (in_array($operator, ['IS NULL', 'IS NOT NULL'])) {
                            $conditions[] = $column . ' ' . $operator;
                        } else {
                            $paramName = 'where_' . $i;
                            $conditions[] = $column . ' ' . $operator . ' :' . $paramName;
                            $params[$paramName] = $where['value'][$i];
                        }
                    }
                }
            }
            
            $query = 'SELECT COUNT(*) as count FROM ' . $table;
            if ($conditions) {
                $query .= "\nWHERE " . implode(' AND ', $conditions);
            }
            
            return ['query' => $query, 'params' => $params];
            
        default:
            return ['query' => '', 'params' => []];
    }
}
