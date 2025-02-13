<?php
$content = '';

// Get all tables
$sql = rex_sql::factory();
$tables = $sql->getTablesAndViews();

// Filter for tables with rex_ prefix
$tables = array_filter($tables, function($table) {
    return str_starts_with($table, 'rex_');
});

// Create table list
$list = rex_list::factory('SELECT table_name, table_rows 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE()
    AND table_name LIKE "rex_%"');

$list->addTableAttribute('class', 'table-striped table-hover');

$list->setColumnLabel('table_name', 'Tabellenname');
$list->setColumnLabel('table_rows', 'Anzahl Datensätze');

$content .= $list->get();

$fragment = new rex_fragment();
$fragment->setVar('title', 'Tabellen');
$fragment->setVar('content', $content, false);
$content = $fragment->parse('core/page/section.php');

echo $content;
