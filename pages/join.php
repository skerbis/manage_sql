<?php
$content = '';
$error = '';
$message = '';

// Get all tables
$sql = rex_sql::factory();
$tablesQuery = 'SELECT `table_name` 
               FROM INFORMATION_SCHEMA.TABLES 
               WHERE `table_schema` = DATABASE() 
               AND `table_name` LIKE "rex_%"
               ORDER BY `table_name`';
$sql->setQuery($tablesQuery);
$tables = $sql->getArray();
$tables = array_column($tables, 'table_name');

// Get current state from session
$joins = rex_session('join_builder_joins', 'array', [
    [
        'left_table' => '',
        'left_column' => '',
        'right_table' => '',
        'right_column' => '',
        'type' => 'INNER JOIN'
    ]
]);

$selectedColumns = rex_session('join_builder_columns', 'array', []);

// Handle actions
$func = rex_request('func', 'string');
if ($func) {
    switch($func) {
        case 'add_join':
            $joins[] = [
                'left_table' => end($joins)['right_table'],
                'left_column' => '',
                'right_table' => '',
                'right_column' => '',
                'type' => 'INNER JOIN'
            ];
            rex_set_session('join_builder_joins', $joins);
            break;

        case 'remove_join':
            $index = rex_request('index', 'int');
            if (isset($joins[$index]) && count($joins) > 1) {
                unset($joins[$index]);
                $joins = array_values($joins); // Reindex array
                rex_set_session('join_builder_joins', $joins);
            }
            break;

        case 'select_table':
            $index = rex_request('index', 'int');
            $side = rex_request('side', 'string');
            $table = rex_request('table', 'string');
            
            if (isset($joins[$index])) {
                $joins[$index][$side . '_table'] = $table;
                $joins[$index][$side . '_column'] = '';
                rex_set_session('join_builder_joins', $joins);
            }
            break;

        case 'select_column':
            $index = rex_request('index', 'int');
            $side = rex_request('side', 'string');
            $column = rex_request('column', 'string');
            
            if (isset($joins[$index])) {
                $joins[$index][$side . '_column'] = $column;
                rex_set_session('join_builder_joins', $joins);
            }
            break;

        case 'select_type':
            $index = rex_request('index', 'int');
            $type = rex_request('type', 'string');
            
            if (isset($joins[$index])) {
                $joins[$index]['type'] = $type;
                rex_set_session('join_builder_joins', $joins);
            }
            break;

        case 'update_columns':
            $table = rex_request('table', 'string');
            $selectedCols = rex_request('columns', 'string');
            if ($table && $selectedCols) {
                $selectedColumns[$table] = json_decode($selectedCols) ?: [];
                rex_set_session('join_builder_columns', $selectedColumns);
            }
            break;

        case 'reset':
            rex_set_session('join_builder_joins', null);
            rex_set_session('join_builder_columns', null);
            break;

        case 'generate':
            // Build SELECT part
            $selectParts = [];
            foreach ($selectedColumns as $table => $columns) {
                foreach ($columns as $column) {
                    $selectParts[] = $table . '.' . $column;
                }
            }
            $selectClause = empty($selectParts) ? '*' : implode(', ', $selectParts);
            
            // Build JOIN part
            $joinClauses = [];
            $firstTable = '';
            $params = [];
            
            foreach ($joins as $join) {
                if (empty($join['left_table']) || empty($join['right_table']) || 
                    empty($join['left_column']) || empty($join['right_column'])) {
                    continue;
                }
                
                if (empty($firstTable)) {
                    $firstTable = $join['left_table'];
                }
                
                $joinClauses[] = sprintf(
                    '%s %s ON %s.%s = %s.%s',
                    $join['type'],
                    $join['right_table'],
                    $join['left_table'],
                    $join['left_column'],
                    $join['right_table'],
                    $join['right_column']
                );
            }
            
            if ($firstTable) {
                $generatedQuery = sprintf('SELECT %s FROM %s %s',
                    $selectClause,
                    $firstTable,
                    implode("\n", $joinClauses)
                );
                
                // Generate rex_sql code
                $generatedCode = '$sql = rex_sql::factory();
$sql->setDebug(false);
$sql->setQuery("
    ' . $generatedQuery . '
");';

                $message = 'Code wurde generiert.';
            } else {
                $error = 'Bitte mindestens einen validen JOIN definieren.';
            }
            break;
    }
}

// Show messages
if ($error) {
    $content .= rex_view::error($error);
}
if ($message) {
    $content .= rex_view::success($message);
}

// Add help modal
$helpFragment = new rex_fragment();
$content .= $helpFragment->parse('join_help_modal.php');

// Build JOIN UI
foreach ($joins as $index => $join) {
    $fragment = new rex_fragment();
    $fragment->setVar('index', $index);
    $fragment->setVar('join', $join);
    $fragment->setVar('tables', $tables);
    $fragment->setVar('columns', function($table) {
        return $table ? array_column(rex_sql::showColumns($table), 'name') : [];
    });
    $content .= $fragment->parse('join_row.php');
}

// Add/Remove buttons
$addUrl = rex_url::currentBackendPage(['func' => 'add_join']);
$resetUrl = rex_url::currentBackendPage(['func' => 'reset']);

$content .= '
<div class="btn-toolbar">
    <a class="btn btn-default" href="'.$addUrl.'"><i class="rex-icon fa-plus"></i> JOIN hinzufügen</a>
    <a class="btn btn-default" href="'.$resetUrl.'"><i class="rex-icon fa-times"></i> Zurücksetzen</a>
</div>';

// Column selection
$content .= '<h3 class="rex-form-aligned">Spaltenauswahl</h3>';

$usedTables = [];
foreach ($joins as $join) {
    if ($join['left_table']) $usedTables[] = $join['left_table'];
    if ($join['right_table']) $usedTables[] = $join['right_table'];
}
$usedTables = array_unique($usedTables);

foreach ($usedTables as $table) {
    $columns = array_column(rex_sql::showColumns($table), 'name');
    
    $fragment = new rex_fragment();
    $fragment->setVar('table', $table);
    $fragment->setVar('columns', $columns);
    $fragment->setVar('selectedColumns', $selectedColumns[$table] ?? []);
    $content .= $fragment->parse('column_selection.php');
}

// Generate button
$generateUrl = rex_url::currentBackendPage(['func' => 'generate']);
$content .= '
<div class="btn-toolbar">
    <a class="btn btn-save" href="'.$generateUrl.'"><i class="rex-icon fa-save"></i> Code generieren</a>
</div>';

// Show generated code if available
if (isset($generatedCode)) {
    $content .= '
    <div class="panel panel-default">
        <div class="panel-heading">
            <div class="panel-title"><i class="rex-icon fa-code"></i> Generierter Code</div>
        </div>
        <div class="panel-body">
            <pre class="rex-code">' . rex_escape($generatedCode) . '</pre>
            <button class="btn btn-default" onclick="copyToClipboard()">
                <i class="rex-icon fa-copy"></i> In Zwischenablage kopieren
            </button>
        </div>
    </div>
    <script>
    function copyToClipboard() {
        const code = document.querySelector(".rex-code").textContent;
        navigator.clipboard.writeText(code).then(() => {
            alert("Code wurde in die Zwischenablage kopiert!");
        });
    }
    </script>';
}

// Create page fragment
$fragment = new rex_fragment();
$fragment->setVar('title', 'JOIN Builder');
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
