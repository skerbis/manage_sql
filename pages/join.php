<?php
$content = '';
$error = '';
$message = '';
$csrfToken = rex_csrf_token::factory('manage_sql_join_builder');

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

$joinTypes = ['INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'FULL JOIN'];

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

/**
 * @return string[]
 */
function getTableColumns(string $table, array $tables): array
{
    if (!in_array($table, $tables, true)) {
        return [];
    }

    return array_column(rex_sql::showColumns($table), 'name');
}

// Handle actions
$requestMethod = strtolower(rex_request_method());
$func = 'post' === $requestMethod ? rex_post('func', 'string') : rex_get('func', 'string');
if ($func) {
    $mutatingFuncs = ['add_join', 'remove_join', 'select_table', 'select_column', 'select_type', 'update_columns', 'reset'];
    if (in_array($func, $mutatingFuncs, true)) {
        if ('post' !== $requestMethod) {
            $error = 'Ungültige Request-Methode.';
        } elseif (!$csrfToken->isValid()) {
            $error = rex_i18n::msg('csrf_token_invalid');
        }
    }
}

if ($func && '' === $error) {
    switch($func) {
        case 'add_join':
            $lastJoin = end($joins);
            $joins[] = [
                'left_table' => is_array($lastJoin) ? (string) ($lastJoin['right_table'] ?? '') : '',
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
            
            if (isset($joins[$index]) && in_array($side, ['left', 'right'], true) && in_array($table, $tables, true)) {
                $joins[$index][$side . '_table'] = $table;
                $joins[$index][$side . '_column'] = '';
                rex_set_session('join_builder_joins', $joins);
            }
            break;

        case 'select_column':
            $index = rex_request('index', 'int');
            $side = rex_request('side', 'string');
            $column = rex_request('column', 'string');
            
            if (isset($joins[$index]) && in_array($side, ['left', 'right'], true)) {
                $table = (string) ($joins[$index][$side . '_table'] ?? '');
                $allowedColumns = getTableColumns($table, $tables);
                if (!in_array($column, $allowedColumns, true)) {
                    break;
                }

                $joins[$index][$side . '_column'] = $column;
                rex_set_session('join_builder_joins', $joins);
            }
            break;

        case 'select_type':
            $index = rex_request('index', 'int');
            $type = rex_request('type', 'string');
            
            if (isset($joins[$index]) && in_array($type, $joinTypes, true)) {
                $joins[$index]['type'] = $type;
                rex_set_session('join_builder_joins', $joins);
            }
            break;

        case 'update_columns':
            $table = rex_request('table', 'string');
            $selectedCols = rex_request('columns', 'string');
            if ($table && $selectedCols && in_array($table, $tables, true)) {
                $decodedColumns = json_decode($selectedCols, true);
                $allowedColumns = getTableColumns($table, $tables);
                $selectedColumns[$table] = is_array($decodedColumns)
                    ? array_values(array_filter($decodedColumns, static fn ($col) => is_string($col) && in_array($col, $allowedColumns, true)))
                    : [];
                rex_set_session('join_builder_columns', $selectedColumns);
            }
            break;

        case 'reset':
            rex_set_session('join_builder_joins', null);
            rex_set_session('join_builder_columns', null);
            break;

        case 'test_query':
            $sql = rex_sql::factory();
            try {
                // Dieselbe Query-Generierung wie im 'generate' case
                $selectParts = [];
                foreach ($selectedColumns as $table => $columns) {
                    foreach ($columns as $column) {
                        $selectParts[] = $table . '.' . $column;
                    }
                }
                // Wenn keine Spalten ausgewählt sind, alle Spalten mit Tabellen-Prefix auswählen
                if (empty($selectParts)) {
                    $selectParts = [];
                    foreach ($joins as $join) {
                        if (!empty($join['left_table'])) {
                            $columns = rex_sql::showColumns($join['left_table']);
                            foreach ($columns as $column) {
                                $selectParts[] = $join['left_table'] . '.' . $column['name'] . ' AS ' . $join['left_table'] . '_' . $column['name'];
                            }
                        }
                        if (!empty($join['right_table'])) {
                            $columns = rex_sql::showColumns($join['right_table']);
                            foreach ($columns as $column) {
                                $selectParts[] = $join['right_table'] . '.' . $column['name'] . ' AS ' . $join['right_table'] . '_' . $column['name'];
                            }
                        }
                    }
                }
                $selectClause = implode(', ', array_unique($selectParts));
                
                $joinClauses = [];
                $firstTable = '';
                
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
                    $query = sprintf('SELECT %s FROM %s %s',
                        $selectClause,
                        $firstTable,
                        implode("\n", $joinClauses)
                    );

                    // Query ausführen und Ergebnis anzeigen
                    $list = rex_list::factory($query);
                    $list->addTableAttribute('class', 'table-striped table-hover');
                    
                    // Test-Ergebnis anzeigen
                    $fragment = new rex_fragment();
                    $fragment->setVar('title', 'Query Test Ergebnis');
                    $fragment->setVar('content', '
                        <div class="panel-body">
                            <p><strong>Ausgeführte Query:</strong></p>
                            <pre>' . rex_escape($query) . '</pre>
                            <hr>
                            <div class="table-responsive">
                                ' . $list->get() . '
                            </div>
                        </div>', false);
                    $testResult = $fragment->parse('core/page/section.php');
                    
                    $message = 'Query wurde erfolgreich ausgeführt.';
                }
            } catch (rex_sql_exception $e) {
                $error = 'Fehler beim Ausführen der Query: ' . $e->getMessage();
            }
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
    $fragment->setVar('columns', function($table) use ($tables) {
        return getTableColumns((string) $table, $tables);
    });
    $fragment->setVar('csrfParams', $csrfToken->getUrlParams());
    $content .= $fragment->parse('join_row.php');
}

// Add/Remove buttons
$actionUrl = rex_url::currentBackendPage();

$content .= '
<div class="btn-toolbar">
    <form action="'.$actionUrl.'" method="post" style="display:inline-block; margin:0 8px 0 0;">
        <input type="hidden" name="func" value="add_join">
        '.$csrfToken->getHiddenField().'
        <button type="submit" class="btn btn-default"><i class="rex-icon fa-plus"></i> JOIN hinzufügen</button>
    </form>
    <form action="'.$actionUrl.'" method="post" style="display:inline-block; margin:0;">
        <input type="hidden" name="func" value="reset">
        '.$csrfToken->getHiddenField().'
        <button type="submit" class="btn btn-default"><i class="rex-icon fa-times"></i> Zurücksetzen</button>
    </form>
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
    if (!in_array($table, $tables, true)) {
        continue;
    }

    $columns = getTableColumns($table, $tables);
    
    $fragment = new rex_fragment();
    $fragment->setVar('table', $table);
    $fragment->setVar('columns', $columns);
    $fragment->setVar('selectedColumns', $selectedColumns[$table] ?? []);
    $fragment->setVar('csrfParams', $csrfToken->getUrlParams());
    $content .= $fragment->parse('column_selection.php');
}

// Generate/Test buttons
$generateUrl = rex_url::currentBackendPage(['func' => 'generate']);
$testUrl = rex_url::currentBackendPage(['func' => 'test_query']);
$content .= '
<div class="btn-toolbar">
    <a class="btn btn-save" href="'.$generateUrl.'"><i class="rex-icon fa-save"></i> Code generieren</a>
    <a class="btn btn-primary" href="'.$testUrl.'"><i class="rex-icon fa-play"></i> Query testen</a>
</div>';

// Show test result if available
if (isset($testResult)) {
    $content .= $testResult;
}

// Show generated code if available
if (isset($generatedCode)) {
    $content .= '
    <div class="panel panel-default">
        <div class="panel-heading">
            <div class="panel-title"><i class="rex-icon fa-code"></i> Generierter Code</div>
        </div>
        <div class="panel-body">
            <pre class="rex-code">' . rex_escape($generatedCode) . '</pre>
            <button class="btn btn-default" onclick="manageSqlCopyToClipboard(\'.rex-code\', \'Code wurde in die Zwischenablage kopiert!\')">
                <i class="rex-icon fa-copy"></i> In Zwischenablage kopieren
            </button>
        </div>
    </div>';
}

// Create page fragment
$fragment = new rex_fragment();
$fragment->setVar('title', 'JOIN Builder');
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
