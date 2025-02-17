<?php
$content = '';
$message = '';
$error = '';

$tableName = rex_get('table', 'string');
if (!$tableName) {
    echo rex_view::error('Keine Tabelle ausgewählt');
    return;
}

$table = rex_sql_table::get($tableName);
if (!$table->exists()) {
    echo rex_view::error('Tabelle existiert nicht');
    return;
}

// Handle form submission
if (rex_post('updatetable', 'boolean')) {
    $columns = rex_post('columns', 'array');
    
    try {
        foreach ($columns as $columnName => $column) {
            if (isset($column['delete']) && $column['delete']) {
                // Delete column
                $table->removeColumn($columnName);
                continue;
            }
            
            // Check if column should be renamed
            $newName = $column['new_name'] ?? '';
            if (!empty($newName) && $newName !== $columnName) {
                // Check if new name already exists
                if ($table->hasColumn($newName)) {
                    throw new rex_exception(sprintf('Eine Spalte mit dem Namen "%s" existiert bereits.', $newName));
                }
                // Rename column
                $table->renameColumn($columnName, $newName);
                $columnName = $newName;
            }
            
            // Update column and position
            $after = null;
            if (isset($column['after'])) {
                $after = $column['after'] === 'FIRST' ? rex_sql_table::FIRST : $column['after'];
            }
            
            $table->ensureColumn(new rex_sql_column(
                $columnName,
                $column['type'],
                (bool)($column['nullable'] ?? false),
                $column['default'] ?? null,
                $column['extra'] ?? null,
                $column['comment'] ?? null
            ), $after);
        }
        
        // Add new column if data exists
        if ($newColumn = rex_post('new_column', 'array')) {
            if (!empty($newColumn['name']) && !empty($newColumn['type'])) {
                $table->ensureColumn(new rex_sql_column(
                    $newColumn['name'],
                    $newColumn['type'],
                    (bool)($newColumn['nullable'] ?? false),
                    $newColumn['default'] ?? null,
                    $newColumn['extra'] ?? null,
                    $newColumn['comment'] ?? null
                ));
            }
        }
        
        // Save changes
        $table->ensure();
        
        $message = 'Tabelle wurde erfolgreich aktualisiert.';
        
        // Regenerate table object to get fresh data
        $table = rex_sql_table::get($tableName);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Show messages
if ($error) {
    $content .= rex_view::error($error);
}
if ($message) {
    $content .= rex_view::success($message);
}

// Build form
$formContent = '';

// Existing columns
$columns = $table->getColumns();
foreach ($columns as $column) {
    $name = $column->getName();
    if ($name === 'id') continue; // Skip id column
    
    $formContent .= '<div class="column-row panel panel-default">
        <div class="panel-heading">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="panel-title">'.$name.'</h3>
                </div>
                <div class="col-sm-6 text-right">
                    <label class="checkbox-inline text-danger">
                        <input type="checkbox" name="columns['.$name.'][delete]" value="1"> Löschen
                    </label>
                </div>
            </div>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group">
                        <label>Neuer Name:</label>
                        <input type="text" name="columns['.$name.'][new_name]" value="'.$name.'" class="form-control" placeholder="Spalte umbenennen">
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        <label>Typ:</label>
                        <select name="columns['.$name.'][type]" class="form-control">';
                        
foreach (rex_table_builder::getCommonColumnTypes() as $type => $label) {
    $formContent .= '<option value="'.$type.'"'.($column->getType() === $type ? ' selected' : '').'>'.$label.'</option>';
}

$formContent .= '</select>
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="form-group">
                        <label>Standardwert:</label>
                        <input type="text" name="columns['.$name.'][default]" value="'.$column->getDefault().'" class="form-control">
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="form-group">
                        <label>Extra:</label>
                        <input type="text" name="columns['.$name.'][extra]" value="'.$column->getExtra().'" class="form-control">
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="columns['.$name.'][nullable]" value="1"'.($column->isNullable() ? ' checked' : '').'>
                            Nullable
                        </label>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        <label>Position:</label>
                        <select name="columns['.$name.'][after]" class="form-control">
                            <option value="FIRST">Am Anfang</option>';
                            
foreach ($columns as $otherColumn) {
    $otherName = $otherColumn->getName();
    if ($otherName !== $name && $otherName !== 'id') {
        $formContent .= '<option value="'.$otherName.'">'.$otherName.'</option>';
    }
}

$formContent .= '</select>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

// New column form
$newColumnForm = '<div class="column-row panel panel-info">
    <div class="panel-heading">
        <h3 class="panel-title">Neue Spalte hinzufügen</h3>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-sm-3">
                <div class="form-group">
                    <label>Name:</label>
                    <input type="text" name="new_column[name]" class="form-control" placeholder="Spaltenname">
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    <label>Typ:</label>
                    <select name="new_column[type]" class="form-control">';
foreach (rex_table_builder::getCommonColumnTypes() as $type => $label) {
    $newColumnForm .= '<option value="'.$type.'">'.$label.'</option>';
}
$newColumnForm .= '</select>
                </div>
            </div>
            <div class="col-sm-2">
                <div class="form-group">
                    <label>Standardwert:</label>
                    <input type="text" name="new_column[default]" class="form-control" placeholder="Standardwert">
                </div>
            </div>
            <div class="col-sm-2">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="new_column[nullable]" value="1" checked>
                        Nullable
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>';

// Complete form
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', 'Tabelle bearbeiten: ' . $tableName);
$fragment->setVar('body', '
    <form action="'.rex_url::currentBackendPage(['table' => $tableName]).'" method="post">
        '.$formContent.'
        '.$newColumnForm.'
        <div class="panel-footer">
            <div class="rex-form-panel-footer">
                <div class="btn-toolbar">
                    <button type="submit" name="updatetable" value="1" class="btn btn-save">Speichern</button>
                    <a class="btn btn-default" href="'.rex_url::backendPage('table_builder/tables').'">Abbrechen</a>
                </div>
            </div>
        </div>
    </form>', false);

$content .= $fragment->parse('core/page/section.php');

echo $content;
