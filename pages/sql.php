<?php
$content = '';

if ($table = rex_get('table', 'string')) {
    $dumper = new rex_sql_schema_dumper();
    $schema = $dumper->dumpTable(rex_sql_table::get($table));
    
    $content .= rex_view::info('<p><strong>SQL Schema für ' . rex_escape($table) . ':</strong></p><pre>' . rex_escape($schema) . '</pre>');
} else {
    $content .= rex_view::info('Bitte wählen Sie eine Tabelle aus der Übersicht.');
}

$fragment = new rex_fragment();
$fragment->setVar('title', 'SQL Export');
$fragment->setVar('content', $content, false);
echo $fragment->parse('core/page/section.php');
