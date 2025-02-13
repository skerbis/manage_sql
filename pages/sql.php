<?php
$content = '';

// Get all tables
$sql = rex_sql::factory();
$tables = $sql->getTablesAndViews();

// Filter for rex_ tables
$tables = array_filter($tables, function($table) {
    return str_starts_with($table, 'rex_');
});

if ($table = rex_get('table', 'string')) {
    // Export single table
    $dumper = new rex_sql_schema_dumper();
    $schema = $dumper->dumpTable(rex_sql_table::get($table));
    
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'SQL Schema: ' . rex_escape($table));
    $fragment->setVar('body', '<pre class="rex-code">' . rex_escape($schema) . '</pre>', false);
    $content .= $fragment->parse('core/page/section.php');
    
    // Add back button
    $content .= '<a class="btn btn-default" href="'.rex_url::currentBackendPage().'"><i class="rex-icon fa-arrow-left"></i> Zurück zur Übersicht</a>';
} else {
    // Show table list
    $list = '<div class="table-responsive"><table class="table table-hover">
        <thead>
            <tr>
                <th>Tabellenname</th>
                <th class="rex-table-action">Aktionen</th>
            </tr>
        </thead>
        <tbody>';
        
    foreach ($tables as $tableName) {
        $list .= '<tr>
            <td>'.$tableName.'</td>
            <td class="rex-table-action">
                <div class="btn-group btn-group-xs">
                    <a href="'.rex_url::currentBackendPage(['table' => $tableName]).'" class="btn btn-default" title="SQL anzeigen">
                        <i class="rex-icon fa-code"></i> SQL Schema anzeigen
                    </a>
                    <a href="index.php?page=table_builder/edit&table='.$tableName.'" class="btn btn-edit" title="Tabelle bearbeiten">
                        <i class="rex-icon fa-edit"></i> Bearbeiten
                    </a>
                </div>
            </td>
        </tr>';
    }
    
    $list .= '</tbody></table></div>';

    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Verfügbare Tabellen');
    $fragment->setVar('content', $list, false);
    $content .= $fragment->parse('core/page/section.php');
}

echo $content;
