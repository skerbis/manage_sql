<?php
$content = '';
$error = '';
$message = '';

// Get query from session if exists
$query = rex_session('view_builder_query', 'string', '');
$viewName = rex_session('view_builder_name', 'string', '');

// Get all existing views
$sql = rex_sql::factory();
$viewsQuery = 'SELECT table_name FROM information_schema.views 
               WHERE table_schema = DATABASE() 
               AND table_name LIKE "rex_view_%"
               ORDER BY table_name';
$sql->setQuery($viewsQuery);
$existingViews = $sql->getArray();

// Handle actions
$func = rex_request('func', 'string');
if ($func) {
    switch($func) {
        case 'test':
            $query = rex_post('query', 'string');
            $viewName = rex_post('view_name', 'string');
            
            // Save in session
            rex_set_session('view_builder_query', $query);
            rex_set_session('view_builder_name', $viewName);
            
            if (!$query) {
                $error = 'Bitte geben Sie eine Query ein.';
                break;
            }
            
            try {
                // Test query
                $list = rex_list::factory($query);
                $list->addTableAttribute('class', 'table-striped table-hover');
                
                // Show test result
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
                
                $message = 'Query wurde erfolgreich getestet.';
            } catch (rex_sql_exception $e) {
                $error = 'Fehler beim Testen der Query: ' . $e->getMessage();
            }
            break;
            
        case 'create':
            $query = rex_post('query', 'string');
            $viewName = rex_post('view_name', 'string');
            
            if (!$query || !$viewName) {
                $error = 'Bitte geben Sie Query und View-Namen ein.';
                break;
            }
            
            // Check view name format
            if (!preg_match('/^rex_view_[a-zA-Z0-9_]+$/', $viewName)) {
                $error = 'Der View-Name muss mit "rex_view_" beginnen und darf nur Buchstaben, Zahlen und Unterstriche enthalten.';
                break;
            }
            
            try {
                // Test query first
                $sql->setQuery($query);
                
                // Create view
                $createViewQuery = 'CREATE OR REPLACE VIEW ' . $viewName . ' AS ' . $query;
                $sql->setQuery($createViewQuery);
                
                $message = 'View wurde erfolgreich erstellt.';
                
                // Clear session
                rex_set_session('view_builder_query', '');
                rex_set_session('view_builder_name', '');
                
                // Redirect to prevent refresh-recreation
                header('Location: ' . rex_url::currentBackendPage(['info' => 'view_created']));
                exit;
                
            } catch (rex_sql_exception $e) {
                $error = 'Fehler beim Erstellen der View: ' . $e->getMessage();
            }
            break;
            
        case 'delete':
            $viewName = rex_get('view_name', 'string');
            
            if (!$viewName) {
                $error = 'Kein View-Name angegeben.';
                break;
            }
            
            try {
                $sql->setQuery('DROP VIEW ' . $viewName);
                $message = 'View wurde erfolgreich gelöscht.';
            } catch (rex_sql_exception $e) {
                $error = 'Fehler beim Löschen der View: ' . $e->getMessage();
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
if (rex_get('info') === 'view_created') {
    $content .= rex_view::success('View wurde erfolgreich erstellt.');
}

// Existing views list
if ($existingViews) {
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Vorhandene Views');
    
    $viewsList = '<div class="table-responsive"><table class="table table-hover">
        <thead>
            <tr>
                <th>View Name</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>';
        
    foreach ($existingViews as $view) {
        $viewsList .= '<tr>
            <td>' . $view['table_name'] . '</td>
            <td>
                <a href="' . rex_url::currentBackendPage(['func' => 'delete', 'view_name' => $view['table_name']]) . '" 
                   class="btn btn-delete" onclick="return confirm(\'View wirklich löschen?\')">
                    <i class="rex-icon fa-trash"></i> Löschen
                </a>
            </td>
        </tr>';
    }
    
    $viewsList .= '</tbody></table></div>';
    
    $fragment->setVar('content', $viewsList, false);
    $content .= $fragment->parse('core/page/section.php');
}

// View creation form
$formContent = '
<form action="' . rex_url::currentBackendPage() . '" method="post">
    <fieldset>
        <div class="panel panel-edit">
            <header class="panel-heading"><div class="panel-title">View erstellen</div></header>
            <div class="panel-body">
                <div class="form-group">
                    <label for="view_name">View Name:</label>
                    <input type="text" id="view_name" name="view_name" value="' . rex_escape($viewName) . '" 
                           class="form-control" placeholder="rex_view_..." />
                    <p class="help-block">Der Name muss mit "rex_view_" beginnen.</p>
                </div>
                
                <div class="form-group">
                    <label for="query">SQL Query:</label>
                    <textarea id="query" name="query" rows="10" class="form-control">' . rex_escape($query) . '</textarea>
                    <p class="help-block">Geben Sie hier Ihre SELECT Query ein.</p>
                </div>
                
                <div class="btn-toolbar">
                    <button type="submit" name="func" value="test" class="btn btn-primary">
                        <i class="rex-icon fa-play"></i> Query testen
                    </button>
                    ' . ($testResult ? '
                    <button type="submit" name="func" value="create" class="btn btn-save">
                        <i class="rex-icon fa-save"></i> View erstellen
                    </button>
                    ' : '') . '
                </div>
            </div>
        </div>
    </fieldset>
</form>';

// Show test result if available
if (isset($testResult)) {
    $formContent .= $testResult;
}

$fragment = new rex_fragment();
$fragment->setVar('title', 'View Builder');
$fragment->setVar('body', $formContent, false);
$content .= $fragment->parse('core/page/section.php');

echo $content;
