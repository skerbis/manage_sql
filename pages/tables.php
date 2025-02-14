<?php
$content = '';

// Core tables to be marked
$coreTables = [
    'rex_action',
    'rex_article',
    'rex_article_slice',
    'rex_clang',
    'rex_config',
    'rex_media',
    'rex_media_category',
    'rex_module',
    'rex_module_action',
    'rex_template',
    'rex_user',
    'rex_user_role',
];

// Get YForm tables
$yformTables = [];
if (rex_addon::get('yform')->isAvailable()) {
    $tables = rex_sql::factory()->getArray('SELECT table_name FROM rex_yform_table');
    $yformTables = array_column($tables, 'table_name');
}

// Build SQL Query
$listQuery = 'SELECT 
        t.table_name, 
        t.table_rows,
        t.create_time,
        t.update_time,
        CASE 
            WHEN t.table_name IN ("'.implode('","', $coreTables).'") THEN 1 
            ELSE 0 
        END as is_core
    FROM 
        information_schema.tables t
    WHERE 
        t.table_schema = DATABASE()
        AND t.table_name LIKE "rex_%"
    ORDER BY 
        is_core DESC, table_name ASC';

$list = rex_list::factory($listQuery);

// Format table name
$list->setColumnFormat('table_name', 'custom', function ($params) use ($coreTables, $yformTables) {
    $tableName = $params['value'];
    $isCore = in_array($tableName, $coreTables);
    $isYform = in_array($tableName, $yformTables);
    
    $labels = [];
    if ($isCore) {
        $labels[] = '<span class="label label-default">Core</span>';
    }
    if ($isYform) {
        $labels[] = '<span class="label label-warning">YForm</span>';
    }
    
    return sprintf(
        '<div class="table-name %s">%s %s</div>',
        $isCore ? 'rex-core-table' : '',
        $tableName,
        implode(' ', $labels)
    );
});

// Format row count
$list->setColumnFormat('table_rows', 'custom', function ($params) {
    return number_format((int)$params['value'], 0, ',', '.');
});

// Column labels
$list->setColumnLabel('table_name', 'Tabellenname');
$list->setColumnLabel('table_rows', 'Datensätze');
$list->setColumnLabel('create_time', 'Erstellt am');
$list->setColumnLabel('update_time', 'Geändert am');

// Actions
$list->addColumn('actions', '<i class="rex-icon fa-cog"></i>', -1, ['<th class="rex-table-action">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
$list->setColumnFormat('actions', 'custom', function ($params) use ($coreTables, $yformTables) {
    $tableName = $params['list']->getValue('table_name');
    $isCore = in_array($tableName, $coreTables);
    $isYform = in_array($tableName, $yformTables);
    
    // Wenn Core oder YForm Tabelle, dann keine Bearbeiten-Option
    if ($isCore || $isYform) {
        if ($isYform) {
            return '
            <div class="btn-group">
                <a href="'.rex_url::backendPage('yform/manager/data_edit', ['table_name' => $tableName]).'" class="btn btn-default btn-xs" title="In YForm öffnen">
                    <i class="rex-icon fa-yform"></i> YForm
                </a>
                <a href="'.rex_url::backendPage('manage_sql/sql', ['table' => $tableName]).'" class="btn btn-default btn-xs" title="SQL anzeigen">
                    <i class="rex-icon fa-code"></i> REX_SQL_TABLE
                </a>
            </div>';
        }
        return '
        <div class="btn-group">
            <a href="'.rex_url::backendPage('manage_sql/sql', ['table' => $tableName]).'" class="btn btn-default btn-xs" title="SQL anzeigen">
                <i class="rex-icon fa-code"></i> REX_SQL_TABLE
            </a>
        </div>';
    }
    
    return '
    <div class="btn-group">
        <a href="'.rex_url::backendPage('manage_sql/edit', ['table' => $tableName]).'" class="btn btn-edit btn-xs" title="Bearbeiten">
            <i class="rex-icon fa-edit"></i> Bearbeiten
        </a>
        <a href="'.rex_url::backendPage('manage_sql/sql', ['table' => $tableName]).'" class="btn btn-default btn-xs" title="SQL anzeigen">
            <i class="rex-icon fa-code"></i> REX_SQL_TABLE
        </a>
    </div>';
});

// Add CSS for core tables and labels
$content .= '
<style>
.rex-core-table {
    font-weight: bold;
}
.rex-core-table .label, .table-name .label {
    margin-left: 5px;
    font-size: 10px;
    vertical-align: middle;
}
.table > tbody > tr > td {
    vertical-align: middle;
}
.label-warning {
    background-color: #f0ad4e;
}
</style>';

// Add table section with create button
$fragment = new rex_fragment();
$fragment->setVar('title', 'Tabellen');
$fragment->setVar('content', $list->get(), false);
$content .= $fragment->parse('core/page/section.php');

// Add "Create Table" button at the bottom
$buttons = '
<a class="btn btn-save" href="'.rex_url::backendPage('manage_sql/create').'">
    <i class="rex-icon fa-plus"></i> Neue Tabelle erstellen
</a>';

$fragment = new rex_fragment();
$fragment->setVar('content', $buttons, false);
$content .= $fragment->parse('core/page/section.php');

echo $content;
