<?php
$message = '';
$error = '';

// Handle form submission
if (rex_post('createtable', 'boolean')) {
    $tableName = rex_post('table_name', 'string');
    $columns = rex_post('columns', 'array');
    
    // Validate table name
    if (empty($tableName)) {
        $error = 'Bitte geben Sie einen Tabellennamen ein.';
    } else {
        // Add prefix if not present
        if (!str_starts_with($tableName, 'rex_')) {
            $tableName = 'rex_' . $tableName;
        }
        
        try {
            $builder = new manage_sql($tableName);
            
            if ($builder->exists()) {
                $error = 'Die Tabelle existiert bereits.';
            } else {
                // Add columns
                foreach ($columns as $column) {
                    if (!empty($column['name']) && !empty($column['type'])) {
                        $builder->addColumn(
                            $column['name'],
                            $column['type'],
                            (bool) ($column['nullable'] ?? false),
                            $column['default'] ?? null,
                            $column['extra'] ?? null,
                            $column['comment'] ?? null
                        );
                    }
                }
                
                // Create table
                if ($builder->create()) {
                    $message = 'Tabelle wurde erfolgreich erstellt.';
                    
                    // Generate and show SQL schema
                    $schema = $builder->exportSchema();
                    rex_set_session('manage_sql_schema', $schema);
                    
                    // Redirect to success page
                    rex_response::sendRedirect(rex_url::currentBackendPage(['info' => 'table_created']));
                } else {
                    $error = 'Fehler beim Erstellen der Tabelle.';
                }
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Show message from redirect
if (rex_get('info') == 'table_created') {
    $message = 'Tabelle wurde erfolgreich erstellt.';
    $schema = rex_session('manage_sql_schema', 'string');
}

// Generate form
$content = '';

if ($error) {
    $content .= rex_view::error($error);
}

if ($message) {
    $content .= rex_view::success($message);
}

// Column template for JavaScript
$columnTemplate = '
<div class="column-row">
    <div class="row">
        <div class="col-sm-3">
            <div class="form-group">
                <input type="text" name="columns[{{index}}][name]" value="" class="form-control" placeholder="Spaltenname" />
            </div>
        </div>
        <div class="col-sm-3">
            <div class="form-group">
                <select name="columns[{{index}}][type]" class="form-control">';
foreach (manage_sql::getCommonColumnTypes() as $type => $label) {
    $columnTemplate .= '<option value="' . $type . '">' . $label . '</option>';
}
$columnTemplate .= '
                </select>
            </div>
        </div>
        <div class="col-sm-2">
            <div class="form-group">
                <input type="text" name="columns[{{index}}][default]" value="" class="form-control" placeholder="Standardwert" />
            </div>
        </div>
        <div class="col-sm-2">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="columns[{{index}}][nullable]" value="1" checked> Nullable
                </label>
            </div>
        </div>
        <div class="col-sm-2">
            <button type="button" class="btn btn-delete" onclick="removeColumn(this)">
                <i class="rex-icon fa-minus-circle"></i>
            </button>
        </div>
    </div>
</div>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', 'Neue Tabelle erstellen');
$fragment->setVar('body', '
    <form action="' . rex_url::currentBackendPage() . '" method="post">
        <div class="panel panel-default">
            <div class="panel-body">
                <div class="form-group">
                    <label for="table_name">Tabellenname:</label>
                    <input type="text" id="table_name" name="table_name" class="form-control" />
                    <p class="help-block">"rex_" wird automatisch vorangestellt, falls nicht angegeben.</p>
                </div>
                
                <div class="form-group" id="columns-container">
                    <!-- Column rows will be added here -->
                </div>
                
                <button type="button" class="btn btn-default" onclick="addColumn()">
                    <i class="rex-icon fa-plus"></i> Spalte hinzufügen
                </button>
            </div>
            
            <footer class="panel-footer">
                <div class="rex-form-panel-footer">
                    <button type="submit" name="createtable" value="1" class="btn btn-save">
                        Tabelle erstellen
                    </button>
                </div>
            </footer>
        </div>
    </form>

    <script type="text/template" id="column-template">
    ' . $columnTemplate . '
    </script>

    <script>
    let columnIndex = 0;

    function addColumn() {
        const container = document.getElementById("columns-container");
        const template = document.getElementById("column-template").innerHTML;
        
        // Replace placeholder index
        const html = template.replace(/{{index}}/g, columnIndex++);
        
        // Create temporary element to convert string to DOM
        const temp = document.createElement("div");
        temp.innerHTML = html;
        
        // Add new column row
        container.appendChild(temp.firstElementChild);
    }

    function removeColumn(button) {
        const row = button.closest(".column-row");
        row.remove();
    }

    // Add first column row
    addColumn();
    </script>
', false);

$content .= $fragment->parse('core/page/section.php');

// Show schema if available
if (isset($schema)) {
    echo rex_view::info('<p><strong>SQL Schema:</strong></p><pre>' . rex_escape($schema) . '</pre>');
}

echo $content;
