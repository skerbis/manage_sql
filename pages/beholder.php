<?php

/**
 * Redaxo-Addon: Tabellen-Daten-Manager
 * Seite: Liste/Bearbeiten/Löschen/Hinzufügen von Datensätzen
 */

$content = '';

// ------------------------------ Tabellenauswahl ------------------------------
$sql = rex_sql::factory();
$tables = $sql->getTablesAndViews();
$tables = array_filter($tables, function ($table) {
    return str_starts_with($table, 'rex_');
});

$selectedTable = rex_request('table', 'string', '');

$formContent = '<form action="' . rex_url::currentBackendPage() . '" method="get">
    <input type="hidden" name="page" value="table_data_manager">
    <div class="form-group">
        <label for="table">Tabelle auswählen:</label>
        <select class="form-control" id="table" name="table" onchange="this.form.submit();">';

$formContent .= '<option value="">Bitte wählen...</option>';
foreach ($tables as $table) {
    $selected = ($selectedTable === $table) ? ' selected' : '';
    $formContent .= '<option value="' . $table . '"' . $selected . '>' . $table . '</option>';
}
$formContent .= '</select>
    </div>
</form>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Tabellenauswahl');
$fragment->setVar('body', $formContent, false);
$content .= $fragment->parse('core/page/section.php');

// ------------------------------ Datensatzanzeige/Bearbeitung ------------------------------
if ($selectedTable) {
    $columns = rex_sql::showColumns($selectedTable);

    // ------ Aktionen: Hinzufügen, Löschen, Speichern ------
    $action = rex_request('action', 'string');
    $recordId = rex_request('record_id', 'int');

    if ($action === 'delete' && $recordId) {
        // DIREKTE WEITERLEITUNG NACH DEM LÖSCHEN
        $deleteSql = rex_sql::factory();
        $deleteSql->setTable($selectedTable);
        $deleteSql->setWhere('id = :id', ['id' => $recordId]); //Sicherstellen, dass die ID existiert!

        try {
            $deleteSql->delete();
            echo rex_view::success('Datensatz gelöscht.');
            echo '<script>window.location.href = "' . rex_url::currentBackendPage(['table' => $selectedTable]) . '";</script>'; //Weiterleitung
            exit;
        } catch (rex_sql_exception $e) {
            echo rex_view::error('Fehler beim Löschen: ' . $e->getMessage());
        }
    }

    // ------ Filter-Formular ------
    $filterForm = '<form action="' . rex_url::currentBackendPage() . '" method="get">
        <input type="hidden" name="page" value="table_data_manager">
        <input type="hidden" name="table" value="' . rex_escape($selectedTable) . '">';

    foreach ($columns as $column) {
        $filterValue = rex_request('filter', 'array')[$column['name']] ?? '';
        $filterForm .= '<div class="form-group">';
        $filterForm .= '<label for="filter_' . $column['name'] . '">' . $column['name'] . ':</label>';

        // Bestimme den Feldtyp basierend auf dem Spaltentyp
        if (stripos($column['type'], 'enum') !== false || stripos($column['type'], 'set') !== false) {
            // Extrahiere die Enum-/Set-Werte aus dem Typ
            preg_match('/\((.*?)\)/', $column['type'], $matches);
            $values = explode(',', str_replace("'", '', $matches[1])); // Werte extrahieren und bereinigen

            $filterForm .= '<select class="form-control" id="filter_' . $column['name'] . '" name="filter[' . $column['name'] . ']">';
            $filterForm .= '<option value="">Alle</option>';
            foreach ($values as $value) {
                $selected = ($filterValue === trim($value)) ? ' selected' : '';
                $filterForm .= '<option value="' . rex_escape(trim($value)) . '"' . $selected . '>' . rex_escape(trim($value)) . '</option>';
            }
            $filterForm .= '</select>';
        } else {
            $filterForm .= '<input type="text" class="form-control" id="filter_' . $column['name'] . '" name="filter[' . $column['name'] . ']" value="' . rex_escape($filterValue) . '">';
        }
        $filterForm .= '</div>';
    }

    $filterForm .= '<button type="submit" class="btn btn-primary">Filtern</button>
     <a href="' . rex_url::currentBackendPage(['table' => $selectedTable]) . '" class="btn btn-default">Zurücksetzen</a>
    </form>';

    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Filter');
    $fragment->setVar('body', $filterForm, false);
    $content .= $fragment->parse('core/page/section.php');

    // ------ Daten abrufen und anzeigen ------
    $sql = rex_sql::factory();
    $query = 'SELECT * FROM ' . $selectedTable;
    $whereClauses = [];
    $params = [];

    // Filterbedingungen auswerten
    $filterValues = rex_request('filter', 'array');
    if (!empty($filterValues)) {
        foreach ($filterValues as $column => $value) {
            if ($value !== '') {
                $whereClauses[] = $column . ' LIKE :' . $column;
                $params[$column] = '%' . $value . '%'; // Beispiel: LIKE-Filter
            }
        }
    }

    if (!empty($whereClauses)) {
        $query .= ' WHERE ' . implode(' AND ', $whereClauses);
    }

    $sql->setQuery($query, $params);
    $data = $sql->getArray();

    // ------ Formular zum Hinzufügen ------
    $addForm = '<form action="' . rex_url::currentBackendPage() . '" method="post">
        <input type="hidden" name="page" value="table_data_manager">
        <input type="hidden" name="table" value="' . rex_escape($selectedTable) . '">
        <input type="hidden" name="action" value="add">';

    foreach ($columns as $column) {
        $addForm .= '<div class="form-group">';
        $addForm .= '<label for="add_' . $column['name'] . '">' . $column['name'] . ':</label>';
        $addForm .= '<input type="text" class="form-control" id="add_' . $column['name'] . '" name="add[' . $column['name'] . ']" value="">'; //Kein escape nötig
        $addForm .= '</div>';
    }

    $addForm .= '<button type="submit" class="btn btn-success">Hinzufügen</button>
    </form>';

    // ------ Tabelle ausgeben ------
    if (count($data) > 0) {
        $table = '<table class="table table-striped">';
        $table .= '<thead><tr>';
        foreach ($columns as $column) {
            $table .= '<th>' . $column['name'] . '</th>';
        }
        $table .= '<th>Aktionen</th></tr></thead>';
        $table .= '<tbody>';

        foreach ($data as $row) {
            $table .= '<tr>';

            // Inlines bearbeiten
            $table .= '<form action="' . rex_url::currentBackendPage() . '" method="post">';
            $table .= '<input type="hidden" name="page" value="table_data_manager">';
            $table .= '<input type="hidden" name="table" value="' . rex_escape($selectedTable) . '">';
            $table .= '<input type="hidden" name="action" value="edit">';
            $table .= '<input type="hidden" name="record_id" value="' . $row['id'] . '">';

            foreach ($columns as $column) {
                $table .= '<td><input type="text" class="form-control" name="edit[' . $column['name'] . ']" value="' . rex_escape($row[$column['name']]) . '"></td>';
            }

            $table .= '<td>
              <button type="submit" class="btn btn-xs btn-success">Speichern</button>
              <a href="' . rex_url::currentBackendPage(['table' => $selectedTable, 'action' => 'delete', 'record_id' => $row['id']]) . '" class="btn btn-xs btn-danger" onclick="return confirm(\'Wirklich löschen?\');">Löschen</a>
            </td>';

            $table .= '</form></tr>';
        }
        $table .= '</tbody></table>';

        // Ausgabe
        $content .= '<h2>Datensätze</h2>';
        $content .= $table;
        $content .= '<h2>Datensatz hinzufügen</h2>'; // Überschrift
        $content .= $addForm; // Formular

    } else {
        $content .= rex_view::info('Keine Datensätze gefunden.');
    }

    // ------ Hinzufügen ------
    if ($action === 'add') {
        $addValues = rex_post('add', 'array');

        $addSql = rex_sql::factory();
        $addSql->setTable($selectedTable);

        foreach ($addValues as $column => $value) {
            $addSql->setValue($column, $value);
        }

        try {
            $addSql->insert();
            echo rex_view::success('Datensatz hinzugefügt.');
            echo '<script>window.location.href = "' . rex_url::currentBackendPage(['table' => $selectedTable]) . '";</script>'; //Weiterleitung
            exit;
        } catch (rex_sql_exception $e) {
            echo rex_view::error('Fehler beim Hinzufügen: ' . $e->getMessage());
        }
    }

    // ------ Bearbeiten ------
    if ($action === 'edit' && $recordId) {
        $editValues = rex_post('edit', 'array');

        $updateSql = rex_sql::factory();
        $updateSql->setTable($selectedTable);
        $updateSql->setWhere('id = :id', ['id' => $recordId]);

        foreach ($editValues as $column => $value) {
            $updateSql->setValue($column, $value);
        }

        try {
            $updateSql->update();
            echo rex_view::success('Datensatz aktualisiert.');
            echo '<script>window.location.href = "' . rex_url::currentBackendPage(['table' => $selectedTable]) . '";</script>'; //Weiterleitung
            exit;

        } catch (rex_sql_exception $e) {
            echo rex_view::error('Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Datensätze');
    $fragment->setVar('body', $content, false);
    // Ausgabe hier
     echo '<div class="container-fluid">';
     echo $content;
     echo '</div>';
    }
?>
