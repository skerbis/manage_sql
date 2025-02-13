<?php
$content = '';

// Get YForm tables
$yformTables = [];
if (rex_addon::get('yform')->isAvailable()) {
    $tables = rex_sql::factory()->getArray('SELECT table_name FROM rex_yform_table');
    $yformTables = array_column($tables, 'table_name');
}

// Get selected table structure if a table is selected
$selectedTable = rex_request('table', 'string', '');
$columns = [];
if ($selectedTable && in_array($selectedTable, $yformTables)) {
    $columns = rex_sql::showColumns($selectedTable);
}

// Build form
$formContent = '
<form id="yormbuilder" action="'.rex_url::currentBackendPage().'" method="get">
    <input type="hidden" name="page" value="table_builder/yorm">
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group">
                <label for="table">YForm Tabelle</label>
                <select name="table" id="table" class="form-control" onchange="this.form.submit()">
                    <option value="">Bitte wählen...</option>';
foreach ($yformTables as $table) {
    $formContent .= '<option value="'.$table.'"'.($selectedTable === $table ? ' selected' : '').'>'.$table.'</option>';
}
$formContent .= '
                </select>
            </div>
        </div>
    </div>
</form>';

if ($selectedTable && !empty($columns)) {
    // Get YForm table definition
    $yformTable = rex_sql::factory()->getArray('SELECT * FROM rex_yform_table WHERE table_name = :table', ['table' => $selectedTable])[0];
    $fields = rex_sql::factory()->getArray('SELECT * FROM rex_yform_field WHERE table_name = :table ORDER BY prio', ['table' => $selectedTable]);
    
    // Generate model code
    $className = 'Rex' . str_replace(' ', '', ucwords(str_replace(['rex_', '_'], ['',' '], $selectedTable)));
    
    $modelCode = [];
    $modelCode[] = '<?php';
    $modelCode[] = '';
    $modelCode[] = 'class ' . $className . ' extends rex_yform_manager_dataset';
    $modelCode[] = '{';
    $modelCode[] = '    protected static $table_name = \'' . $selectedTable . '\';';
    $modelCode[] = '';
    
    // Generate getter methods for each field
    foreach ($fields as $field) {
        if ($field['type_id'] == 'value') {
            $name = $field['name'];
            $label = $field['label'] ?: $name;
            
            $methodName = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
            
            $typeMap = [
                'text' => 'string',
                'textarea' => 'string',
                'select' => 'string',
                'checkbox' => 'bool',
                'radio' => 'bool',
                'email' => 'string',
                'integer' => 'int',
                'float' => 'float',
                'decimal' => 'float',
                'date' => '?\\DateTime',
                'datetime' => '?\\DateTime',
                'time' => '?\\DateTime',
                'be_link' => 'int',
                'be_media' => 'string',
                'be_medialist' => 'string',
                'be_manager_relation' => 'rex_yform_manager_collection|null',
                'choice' => 'string',
            ];
            
            $type = $typeMap[$field['type_name']] ?? 'mixed';
            
            $modelCode[] = '    /**';
            $modelCode[] = '     * ' . $label;
            $modelCode[] = '     * @return ' . $type;
            $modelCode[] = '     */';
            
            if (in_array($field['type_name'], ['date', 'datetime', 'time'])) {
                $modelCode[] = '    public function ' . $methodName . '()';
                $modelCode[] = '    {';
                $modelCode[] = '        $value = $this->getValue(\'' . $name . '\');';
                $modelCode[] = '        return $value ? new \\DateTime($value) : null;';
                $modelCode[] = '    }';
            } elseif ($field['type_name'] === 'be_manager_relation') {
                $modelCode[] = '    public function ' . $methodName . '()';
                $modelCode[] = '    {';
                $modelCode[] = '        return $this->getRelatedCollection(\'' . $name . '\');';
                $modelCode[] = '    }';
            } else {
                $modelCode[] = '    public function ' . $methodName . '()';
                $modelCode[] = '    {';
                $modelCode[] = '        return $this->getValue(\'' . $name . '\');';
                $modelCode[] = '    }';
            }
            $modelCode[] = '';
        }
    }
    
    // Add standard methods
    $modelCode[] = '    /**';
    $modelCode[] = '     * @return rex_yform_manager_collection|' . $className . '[]';
    $modelCode[] = '     */';
    $modelCode[] = '    public static function getAll()';
    $modelCode[] = '    {';
    $modelCode[] = '        return self::query()->find();';
    $modelCode[] = '    }';
    $modelCode[] = '';
    $modelCode[] = '    /**';
    $modelCode[] = '     * @return ' . $className . '|null';
    $modelCode[] = '     */';
    $modelCode[] = '    public static function getById($id)';
    $modelCode[] = '    {';
    $modelCode[] = '        return self::get($id);';
    $modelCode[] = '    }';
    $modelCode[] = '';
    $modelCode[] = '}';
    
    // Show generated model code
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'YORM Model für ' . $selectedTable);
    $fragment->setVar('body', '
        <div class="alert alert-info">
            Speichern Sie diesen Code als <code>' . $className . '.php</code>
        </div>
        <pre id="model-code" class="pre-scrollable"><code class="php">'.rex_escape(implode("\n", $modelCode)).'</code></pre>
        <button class="btn btn-default" onclick="copyModelCode()">
            <i class="rex-icon fa-copy"></i> Model-Code kopieren
        </button>
        <script>
        function copyModelCode() {
            const code = document.querySelector("#model-code code").textContent;
            navigator.clipboard.writeText(code).then(() => {
                alert("Model-Code wurde in die Zwischenablage kopiert!");
            });
        }
        </script>
    ', false);
    $content .= $fragment->parse('core/page/section.php');
    
    // Generate form usage code
    $formCode = [];
    $formCode[] = '<?php';
    $formCode[] = '// Neuer Datensatz';
    $formCode[] = '$dataset = rex_yform_manager_dataset::create("'.$selectedTable.'");';
    $formCode[] = '';
    $formCode[] = '// YForm Objekt erstellen';
    $formCode[] = '$yform = $dataset->getForm();';
    $formCode[] = '';
    $formCode[] = '// Formular konfigurieren';
    $formCode[] = '$yform->setObjectparams(\'form_action\', rex_getUrl(REX_ARTICLE_ID));';
    $formCode[] = '$yform->setObjectparams(\'form_showformafterupdate\', false);';
    $formCode[] = '$yform->setObjectparams(\'main_id\', -1);';
    $formCode[] = '$yform->setObjectparams(\'getdata\', false);';
    $formCode[] = '';
    $formCode[] = '// Formular ausgeben';
    $formCode[] = 'echo $dataset->executeForm($yform);';
    
    // Generate edit code
    $editCode = [];
    $editCode[] = '<?php';
    $editCode[] = '// Datensatz laden (ID z.B. über rex_get)';
    $editCode[] = '$dataset = rex_yform_manager_dataset::get(rex_get(\'id\', \'int\'), "'.$selectedTable.'");';
    $editCode[] = '';
    $editCode[] = 'if ($dataset) {';
    $editCode[] = '    // YForm Objekt erstellen';
    $editCode[] = '    $yform = $dataset->getForm();';
    $editCode[] = '';
    $editCode[] = '    // Formular konfigurieren';
    $editCode[] = '    $yform->setObjectparams(\'form_action\', rex_getUrl(REX_ARTICLE_ID));';
    $editCode[] = '    $yform->setObjectparams(\'form_showformafterupdate\', false);';
    $editCode[] = '';
    $editCode[] = '    // Formular ausgeben';
    $editCode[] = '    echo $dataset->executeForm($yform);';
    $editCode[] = '}';
    
    // Generate list code
    $listCode = [];
    $listCode[] = '<?php';
    $listCode[] = '// Datensätze laden';
    $listCode[] = '$items = rex_yform_manager_dataset::query("'.$selectedTable.'")->find();';
    $listCode[] = '';
    $listCode[] = 'if ($items->count() > 0) {';
    $listCode[] = '    foreach ($items as $item) {';
    $listCode[] = '        // Datensatz bearbeiten Link';
    $listCode[] = '        $editUrl = rex_getUrl(REX_ARTICLE_ID, REX_CLANG_ID, [\'id\' => $item->getId()]);';
    $listCode[] = '        echo \'<a href="\'.$editUrl.\'">Bearbeiten</a>\';';
    $listCode[] = '    }';
    $listCode[] = '}';
    
    // Show form usage code
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'YORM Formular Code');
    $fragment->setVar('body', '
        <div class="nav-tabs">
            <ul class="nav nav-tabs">
                <li class="active"><a data-toggle="tab" href="#add">Datensatz anlegen</a></li>
                <li><a data-toggle="tab" href="#edit">Datensatz bearbeiten</a></li>
                <li><a data-toggle="tab" href="#list">Datensätze auflisten</a></li>
            </ul>
            <div class="tab-content">
                <div id="add" class="tab-pane active">
                    <pre class="pre-scrollable"><code class="php">'.rex_escape(implode("\n", $formCode)).'</code></pre>
                </div>
                <div id="edit" class="tab-pane">
                    <pre class="pre-scrollable"><code class="php">'.rex_escape(implode("\n", $editCode)).'</code></pre>
                </div>
                <div id="list" class="tab-pane">
                    <pre class="pre-scrollable"><code class="php">'.rex_escape(implode("\n", $listCode)).'</code></pre>
                </div>
            </div>
        </div>
        <button class="btn btn-default" onclick="copyFormCode()">
            <i class="rex-icon fa-copy"></i> Code in Zwischenablage kopieren
        </button>
        <script>
        function copyFormCode() {
            const activeTab = document.querySelector(".tab-pane.active");
            const code = activeTab.querySelector("code").textContent;
            navigator.clipboard.writeText(code).then(() => {
                alert("Code wurde in die Zwischenablage kopiert!");
            });
        }
        </script>
    ', false);
    $content .= $fragment->parse('core/page/section.php');
}

// Add form to content
$fragment = new rex_fragment();
$fragment->setVar('title', 'YORM Code Generator');
$fragment->setVar('body', $formContent, false);
$content .= $fragment->parse('core/page/section.php');



// Find relations in the fields
    $relations = [];
    foreach ($fields as $field) {
        if ($field['type_name'] === 'be_manager_relation') {
            $options = json_decode($field['options'], true) ?: [];
            $relations[] = [
                'name' => $field['name'],
                'label' => $field['label'],
                'type' => $options['type'] ?? '1',
                'table' => $options['table'] ?? '',
                'relationTable' => $options['relation_table'] ?? ''
            ];
        }
    }

    // Generate Query Examples
    $queryCode = [];
    $queryCode[] = '<?php';
    $queryCode[] = '// Basis-Queries';
    $queryCode[] = '$items = ' . $className . '::query()';
    $queryCode[] = '    ->where(\'status\', 1)                    // WHERE status = 1';
    $queryCode[] = '    ->whereNot(\'status\', 0)                // WHERE status != 0';
    $queryCode[] = '    ->whereNull(\'deleted_at\')              // WHERE deleted_at IS NULL';
    $queryCode[] = '    ->whereNotNull(\'created_at\')           // WHERE created_at IS NOT NULL';
    $queryCode[] = '    ->whereRaw(\'price > 100\')              // WHERE price > 100';
    $queryCode[] = '    ->orderBy(\'name\')                      // ORDER BY name ASC';
    $queryCode[] = '    ->orderBy(\'name\', \'desc\')            // ORDER BY name DESC';
    $queryCode[] = '    ->limit(10)                              // LIMIT 10';
    $queryCode[] = '    ->offset(20)                             // OFFSET 20';
    $queryCode[] = '    ->find();';
    $queryCode[] = '';

    // Multiple conditions
    $queryCode[] = '// Mehrere Bedingungen';
    $queryCode[] = '$items = ' . $className . '::query()';
    $queryCode[] = '    ->where(\'status\', 1)';
    $queryCode[] = '    ->where(function (rex_yform_manager_query $query) {';
    $queryCode[] = '        $query';
    $queryCode[] = '            ->where(\'type\', \'news\')';
    $queryCode[] = '            ->orWhere(\'type\', \'blog\');';
    $queryCode[] = '    })';
    $queryCode[] = '    ->find();';
    $queryCode[] = '';

    // Collection examples
    $queryCode[] = '// Collection Handling';
    $queryCode[] = '$items = ' . $className . '::query()->find();';
    $queryCode[] = '';
    $queryCode[] = '// Collection filtern';
    $queryCode[] = '$filtered = $items->filter(function (' . $className . ' $item) {';
    $queryCode[] = '    return $item->getStatus() == 1;';
    $queryCode[] = '});';
    $queryCode[] = '';
    $queryCode[] = '// Collection transformieren';
    $queryCode[] = '$transformed = $items->map(function (' . $className . ' $item) {';
    $queryCode[] = '    return [';
    $queryCode[] = '        \'id\' => $item->getId(),';
    $queryCode[] = '        \'name\' => $item->getName()';
    $queryCode[] = '    ];';
    $queryCode[] = '});';
    $queryCode[] = '';
    $queryCode[] = '// Collection Methoden';
    $queryCode[] = '$count = $items->count();          // Anzahl der Datensätze';
    $queryCode[] = '$first = $items->first();          // Erster Datensatz';
    $queryCode[] = '$last = $items->last();            // Letzter Datensatz';
    $queryCode[] = '$exists = $items->exists();        // Prüfen ob Datensätze existieren';
    $queryCode[] = '$isEmpty = $items->isEmpty();      // Prüfen ob keine Datensätze existieren';
    $queryCode[] = '$array = $items->toArray();        // Als Array ausgeben';
    $queryCode[] = '$ids = $items->getIds();           // Alle IDs als Array';
    $queryCode[] = '';

    if (!empty($relations)) {
        $queryCode[] = '// Relations-Beispiele';
        $queryCode[] = '';

        foreach ($relations as $relation) {
            $relatedClass = 'Rex' . str_replace(' ', '', ucwords(str_replace(['rex_', '_'], ['',' '], $relation['table'])));
            
            switch ($relation['type']) {
                case '1': // 1:1
                    $queryCode[] = '// 1:1 Relation für ' . $relation['label'];
                    $queryCode[] = '$item = ' . $className . '::query()->findId(1);';
                    $queryCode[] = '$related = $item->get' . ucfirst($relation['name']) . '(); // Einzelner ' . $relatedClass;
                    $queryCode[] = '';
                    break;

                case '2': // 1:n
                    $queryCode[] = '// 1:n Relation für ' . $relation['label'];
                    $queryCode[] = '$item = ' . $className . '::query()->findId(1);';
                    $queryCode[] = '$related = $item->get' . ucfirst($relation['name']) . '(); // Collection von ' . $relatedClass;
                    $queryCode[] = '';
                    $queryCode[] = '// Mit der Collection arbeiten';
                    $queryCode[] = 'foreach ($related as $relatedItem) {';
                    $queryCode[] = '    echo $relatedItem->getId();';
                    $queryCode[] = '}';
                    $queryCode[] = '';
                    break;

                case '4': // n:m
                    $queryCode[] = '// n:m Relation für ' . $relation['label'];
                    $queryCode[] = '$item = ' . $className . '::query()->findId(1);';
                    $queryCode[] = '$related = $item->get' . ucfirst($relation['name']) . '(); // Collection von ' . $relatedClass;
                    $queryCode[] = '';
                    $queryCode[] = '// Mit der Collection arbeiten';
                    $queryCode[] = 'foreach ($related as $relatedItem) {';
                    $queryCode[] = '    echo $relatedItem->getId();';
                    $queryCode[] = '}';
                    $queryCode[] = '';
                    $queryCode[] = '// Relation über Zwischentabelle ' . $relation['relationTable'];
                    $queryCode[] = '$query = ' . $className . '::query()';
                    $queryCode[] = '    ->alias(\'main\')';
                    $queryCode[] = '    ->joinRelation(\'' . $relation['name'] . '\', \'rel\')';
                    $queryCode[] = '    ->where(\'rel.status\', 1)';
                    $queryCode[] = '    ->find();';
                    $queryCode[] = '';
                    break;
            }
        }

        // Complex query example with relations
        $queryCode[] = '// Komplexes Query-Beispiel mit Relations';
        $queryCode[] = '$items = ' . $className . '::query()';
        $queryCode[] = '    ->alias(\'main\')';
        foreach ($relations as $i => $relation) {
            $alias = 'rel' . ($i + 1);
            $queryCode[] = '    ->joinRelation(\'' . $relation['name'] . '\', \'' . $alias . '\')';
        }
        $queryCode[] = '    ->selectRaw(\'main.*, COUNT(rel1.id) as count\')';
        $queryCode[] = '    ->groupBy(\'main.id\')';
        $queryCode[] = '    ->find();';
        $queryCode[] = '';
    }

    // Show Query Examples
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Query Beispiele');
    $fragment->setVar('body', '
        <pre id="query-code" class="pre-scrollable"><code class="php">'.rex_escape(implode("\n", $queryCode)).'</code></pre>
        <button class="btn btn-default" onclick="copyQueryCode()">
            <i class="rex-icon fa-copy"></i> Query-Code kopieren
        </button>
        <script>
        function copyQueryCode() {
            const code = document.querySelector("#query-code code").textContent;
            navigator.clipboard.writeText(code).then(() => {
                alert("Query-Code wurde in die Zwischenablage kopiert!");
            });
        }
        </script>
    ', false);
    $content .= $fragment->parse('core/page/section.php');
echo $content;
