<?php
class YormFormHelper 
{
    public static function generateFormCode($tableName)
    {
        $code = [];
        $code[] = '<?php';
        $code[] = '// Neuer Datensatz';
        $code[] = '$dataset = rex_yform_manager_dataset::create("'.$tableName.'");';
        $code[] = '';
        $code[] = '// YForm Objekt erstellen';
        $code[] = '$yform = $dataset->getForm();';
        $code[] = '';
        $code[] = '// Formular konfigurieren';
        $code[] = '$yform->setObjectparams(\'form_action\', rex_getUrl(REX_ARTICLE_ID));';
        $code[] = '$yform->setObjectparams(\'form_showformafterupdate\', false);';
        $code[] = '$yform->setObjectparams(\'main_id\', -1);';
        $code[] = '$yform->setObjectparams(\'getdata\', false);';
        $code[] = '';
        $code[] = '// Formular ausgeben';
        $code[] = 'echo $dataset->executeForm($yform);';
        
        return implode("\n", $code);
    }
    
    public static function generateEditCode($tableName)
    {
        $code = [];
        $code[] = '<?php';
        $code[] = '// Datensatz laden (ID z.B. über rex_get)';
        $code[] = '$dataset = rex_yform_manager_dataset::get(rex_get(\'id\', \'int\'), "'.$tableName.'");';
        $code[] = '';
        $code[] = 'if ($dataset) {';
        $code[] = '    // YForm Objekt erstellen';
        $code[] = '    $yform = $dataset->getForm();';
        $code[] = '';
        $code[] = '    // Formular konfigurieren';
        $code[] = '    $yform->setObjectparams(\'form_action\', rex_getUrl(REX_ARTICLE_ID));';
        $code[] = '    $yform->setObjectparams(\'form_showformafterupdate\', false);';
        $code[] = '';
        $code[] = '    // Formular ausgeben';
        $code[] = '    echo $dataset->executeForm($yform);';
        $code[] = '}';
        
        return implode("\n", $code);
    }
    
    public static function generateListCode($tableName)
    {
        $code = [];
        $code[] = '<?php';
        $code[] = '// Datensätze laden';
        $code[] = '$items = rex_yform_manager_dataset::query("'.$tableName.'")->find();';
        $code[] = '';
        $code[] = 'if ($items->count() > 0) {';
        $code[] = '    foreach ($items as $item) {';
        $code[] = '        // Datensatz bearbeiten Link';
        $code[] = '        $editUrl = rex_getUrl(REX_ARTICLE_ID, REX_CLANG_ID, [\'id\' => $item->getId()]);';
        $code[] = '        echo \'<a href="\'.$editUrl.\'">Bearbeiten</a>\';';
        $code[] = '    }';
        $code[] = '}';
        
        return implode("\n", $code);
    }
}
