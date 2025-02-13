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

// Build SQL Query
$listQuery = 'SELECT 
        t.table_name, 
        t.table_rows,
        t.create_time,
        t.update_time,
        CASE WHEN t.table_name IN ("'.implode('","', $coreTables).'") THEN 1 ELSE 0 END as is_core
    FROM 
        information_schema.tables t
    WHERE 
        t.table_schema = DATABASE()
        AND t.table_name LIKE "rex_%"
    ORDER BY 
        is_core DESC, table_name ASC';

$list = rex_list::factory($listQuery);

// Hide some columns by default
$list->removeColumn('create_time');
$list->removeColumn('update_time');
$list->removeColumn('is_core');

// Search settings
$list->setColumnParams('table_name', ['table' => '###table_name###']);
$list->addParam('start', rex_request('start', 'int', null));

// Format table name
$list->setColumnFormat('table_name', 'custom', function ($params) use ($coreTables) {
    $tableName = $params['value'];
    $isCore = in_array($tableName, $coreTables);
    
    return sprintf(
        '<div class="table-name %s">%s %s</div>',
        $isCore ? 'rex-core-table' : '',
        $tableName,
        $isCore ? '<span class="label label-default">Core</span>' : ''
    );
});

// Format row count
$list->setColumnFormat('table_rows', 'custom', function ($params) {
    return number_format((int)$params['value'], 0, ',', '.');
});

// Column labels
$list->setColumnLabel('table_name', rex_i18n::msg('table'));
$list->setColumnLabel('table_rows', rex_i18n::msg('rows'));
$list->setColumnLabel('create_time', rex_i18n::msg('created_at'));
$list->setColumnLabel('update_time', rex_i18n::msg('updated_at'));

// Sortable columns
$list->setColumnSortable('table_name');
$list->setColumnSortable('table_rows');
$list->setColumnSortable('create_time');
$list->setColumnSortable('update_time');

// Add show/hide columns button
$columnSelect = [];
$columnSelect['create_time'] = rex_i18n::msg('created_at');
$columnSelect['update_time'] = rex_i18n::msg('updated_at');

$toolbar = '
    <div class="btn-group btn-group-xs">
        <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
            <i class="rex-icon fa-th-list"></i>
            Spalten
            <span class="caret"></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-right">';

foreach ($columnSelect as $id => $title) {
    $toolbar .= '<li>
        <a href="'.rex_url::currentBackendPage(['list' => 'tables', 'func' => 'toggle_column', 'column' => $id]).'">
            <i class="rex-icon '.($list->getColumnStatus($id) ? 'fa-check-square-o' : 'fa-square-o').'"></i>
            '.$title.'
        </a>
    </li>';
}

$toolbar .= '
        </ul>
    </div>';

// Actions
$list->addColumn('actions', '<i class="rex-icon fa-cog"></i>', -1, ['<th class="rex-table-action">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
$list->setColumnParams('actions', ['page' => 'table_builder/edit', 'table' => '###table_name###']);
$list->addLinkAttribute('actions', 'class', 'btn btn-xs btn-default');
$list->setColumnFormat('actions', 'custom', function ($params) {
    $tableName = $params['list']->getValue('table_name');
    
    return '
    <div class="btn-group">
        <a href="'.rex_url::backendPage('table_builder/edit', ['table' => $tableName]).'" class="btn btn-edit btn-xs" title="Bearbeiten">
            <i class="rex-icon fa-edit"></i> Bearbeiten
        </a>
        <a href="'.rex_url::backendPage('table_builder/sql', ['table' => $tableName]).'" class="btn btn-default btn-xs" title="SQL anzeigen">
            <i class="rex-icon fa-code"></i> SQL
        </a>
    </div>';
});

// Add CSS for core tables
$content .= '
<style>
.rex-core-table {
    font-weight: bold;
}
.rex-core-table .label {
    margin-left: 5px;
    font-size: 10px;
    vertical-align: middle;
}
</style>';

// Add table toolbar
$fragment = new rex_fragment();
$fragment->setVar('title', 'Tabellen');
$fragment->setVar('options', $toolbar, false);
$fragment->setVar('content', $list->get(), false);
$content .= $fragment->parse('core/page/section.php');

// Add "Create Table" button at the bottom
$buttons = '
<a class="btn btn-save" href="'.rex_url::backendPage('table_builder/create').'">
    <i class="rex-icon fa-plus"></i> Neue Tabelle erstellen
</a>';

$fragment = new rex_fragment();
$fragment->setVar('content', $buttons, false);
$content .= $fragment->parse('core/page/section.php');

echo $content;
