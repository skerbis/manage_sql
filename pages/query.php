<?php
$content = '';

// Get all tables
$sql = rex_sql::factory();
$tables = $sql->getTablesAndViews();
$tables = array_filter($tables, function($table) {
    return str_starts_with($table, 'rex_');
});

// Get selected table structure if a table is selected
$selectedTable = rex_get('table', 'string');
$columns = [];
if ($selectedTable && in_array($selectedTable, $tables)) {
    $columns = rex_sql::showColumns($selectedTable);
}

// Handle form submission
if (rex_post('generate', 'boolean')) {
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
        <pre class="pre-scrollable"><code>'.rex_escape($code).'</code></pre>
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
}

// Build form
$formContent = '
<form action="'.rex_url::currentBackendPage().'" method="post">
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group">
                <label for="table">Tabelle</label>
                <select name="table" id="table" class="form-control" onchange="this.form.submit()">
                    <option value="">Bitte wählen...</option>';
foreach ($tables as $table) {
    $formContent .= '<option value="'.$table.'"'.($selectedTable === $table ? ' selected' : '').'>'.$table.'</option>';
}
$formContent .= '
                </select>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                <label for="query_type">Query Typ</label>
                <select name="query_type" id="query_type" class="form-control">
                    <option value="select">SELECT</option>
                    <option value="count">COUNT</option>
                    <option value="insert">INSERT</option>
                    <option value="update">UPDATE</option>
                    <option value="delete">DELETE</option>
                </select>
            </div>
        </div>
    </div>';

if ($selectedTable && !empty($columns)) {
    $formContent .= '
    <div class="panel panel-default">
        <div class="panel-heading">Spalten</div>
        <div class="panel-body">
            <div class="row">';
    
    foreach ($columns as $column) {
        $formContent .= '
            <div class="col-sm-3">
                <label class="checkbox-inline">
                    <input type="checkbox" name="columns[]" value="'.$column['name'].'" checked>
                    '.$column['name'].'
                </label>
            </div>';
    }
    
    $formContent .= '
            </div>
        </div>
    </div>
    
    <div class="panel panel-default">
        <div class="panel-heading">WHERE Bedingungen</div>
        <div class="panel-body" id="where-conditions">
            <div class="where-row row">
                <div class="col-sm-4">
                    <select name="where[column][]" class="form-control">
                        <option value="">Spalte wählen...</option>';
    foreach ($columns as $column) {
        $formContent .= '<option value="'.$column['name'].'">'.$column['name'].'</option>';
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
    
    <div class="panel panel-default">
        <div class="panel-heading">ORDER BY</div>
        <div class="panel-body" id="orderby-conditions">
            <div class="orderby-row row">
                <div class="col-sm-6">
                    <select name="orderby[column][]" class="form-control">
                        <option value="">Spalte wählen...</option>';
    foreach ($columns as $column) {
        $formContent .= '<option value="'.$column['name'].'">'.$column['name'].'</option>';
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
    
    <div class="form-group">
        <label for="limit">LIMIT</label>
        <input type="number" name="limit" id="limit" class="form-control" min="0">
    </div>
    
    <div class="panel-footer">
        <button type="submit" name="generate" value="1" class="btn btn-save">Code generieren</button>
    </div>';
}

$formContent .= '</form>

<script>
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
function generateQueryCode($table, $queryType, $columns, $where, $orderBy, $limit) {
    $code = [];
    $code[] = '$sql = rex_sql::factory();';
    
    switch ($queryType) {
        case 'select':
            $conditions = [];
            $params = [];
            
            // Build WHERE conditions
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
            $query = 'SELECT ' . ($columns ? implode(', ', $columns) : '*') . "\n";
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
            
            $code[] = '$sql->setQuery("' . $query . '", ' . (empty($params) ? '[]' : var_export($params, true)) . ');';
            break;
            
        case 'count':
            $code[] = '$sql->setQuery("SELECT COUNT(*) as count FROM ' . $table . '");';
            $code[] = '$count = $sql->getValue("count");';
            break;
            
        case 'insert':
            $code[] = '// Werte setzen';
            foreach ($columns as $column) {
                $code[] = '$sql->setValue("' . $column . '", $value);';
            }
            $code[] = '$sql->setTable("' . $table . '");';
            $code[] = '$sql->insert();';
            break;
            
        case 'update':
            $code[] = '// Werte setzen';
            foreach ($columns as $column) {
                $code[] = '$sql->setValue("' . $column . '", $value);';
            }
            $code[] = '$sql->setTable("' . $table . '");';
            $code[] = '// WHERE Bedingung nicht vergessen!';
            $code[] = '$sql->setWhere("id = :id", ["id" => $id]);';
            $code[] = '$sql->update();';
            break;
            
        case 'delete':
            $code[] = '$sql->setTable("' . $table . '");';
            $code[] = '// WHERE Bedingung nicht vergessen!';
            $code[] = '$sql->setWhere("id = :id", ["id" => $id]);';
            $code[] = '$sql->delete();';
            break;
    }
    
    return implode("\n", $code);
}
