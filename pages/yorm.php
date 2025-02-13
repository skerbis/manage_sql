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
    
    // Generate YORM code
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
            $label = $field['label'];
            $type = $field['type_name'];
            
            $phpType = 'mixed';
            switch ($type) {
                case 'text':
                case 'textarea':
                case 'email':
                case 'upload':
                    $phpType = 'string';
                    break;
                case 'checkbox':
                    $phpType = 'bool';
                    break;
                case 'int':
                case 'integer':
                    $phpType = 'int';
                    break;
                case 'float':
                case 'decimal':
                    $phpType = 'float';
                    break;
                case 'datetime':
                    $phpType = '?\\DateTime';
                    break;
                case 'be_manager_relation':
                    $phpType = '?self';
                    break;
            }
            
            // Add getter method
            $methodName = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
            $modelCode[] = '    /**';
            $modelCode[] = '     * ' . ($label ?: $name);
            if ($type == 'be_manager_relation') {
                $modelCode[] = '     * @return rex_yform_manager_collection|' . $phpType;
            } else {
                $modelCode[] = '     * @return ' . $phpType;
            }
            $modelCode[] = '     */';
            
            if ($type == 'datetime') {
                $modelCode[] = '    public function ' . $methodName . '()';
                $modelCode[] = '    {';
                $modelCode[] = '        $value = $this->getValue(\'' . $name . '\');';
                $modelCode[] = '        return $value ? new \\DateTime($value) : null;';
                $modelCode[] = '    }';
            } elseif ($type == 'be_manager_relation') {
                $modelCode[] = '    public function ' . $methodName . '()';
                $modelCode[] = '    {';
                $modelCode[] = '        return $this->getRelatedDataset(\'' . $name . '\');';
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
    
    // Generate static factory methods
    $modelCode[] = '    /**';
    $modelCode[] = '     * @return static[]';
    $modelCode[] = '     */';
    $modelCode[] = '    public static function getAll()';
    $modelCode[] = '    {';
    $modelCode[] = '        return static::query()->find();';
    $modelCode[] = '    }';
    $modelCode[] = '';
    $modelCode[] = '    /**';
    $modelCode[] = '     * @return static|null';
    $modelCode[] = '     */';
    $modelCode[] = '    public static function getById($id)';
    $modelCode[] = '    {';
    $modelCode[] = '        return static::get($id);';
    $modelCode[] = '    }';
    $modelCode[] = '';
    
    // Example usage
    $modelCode[] = '    // Example usage:';
    $modelCode[] = '    public static function example()';
    $modelCode[] = '    {';
    $modelCode[] = '        // Get all items';
    $modelCode[] = '        $items = self::getAll();';
    $modelCode[] = '';
    $modelCode[] = '        // Get single item by ID';
    $modelCode[] = '        $item = self::getById(1);';
    $modelCode[] = '';
    $modelCode[] = '        // Create new item';
    $modelCode[] = '        $item = self::create();';
    
    // Example setValue for each field
    foreach ($fields as $field) {
        if ($field['type_id'] == 'value') {
            $name = $field['name'];
            $type = $field['type_name'];
            
            switch ($type) {
                case 'text':
                case 'textarea':
                    $modelCode[] = '        $item->setValue(\'' . $name . '\', \'example\');';
                    break;
                case 'checkbox':
                    $modelCode[] = '        $item->setValue(\'' . $name . '\', true);';
                    break;
                case 'int':
                case 'integer':
                    $modelCode[] = '        $item->setValue(\'' . $name . '\', 42);';
                    break;
                case 'datetime':
                    $modelCode[] = '        $item->setDateTimeValue(\'' . $name . '\', time());';
                    break;
            }
        }
    }
    
    $modelCode[] = '        $item->save();';
    $modelCode[] = '';
    $modelCode[] = '        // Custom query';
    $modelCode[] = '        $items = self::query()';
    $modelCode[] = '            ->where(\'status\', 1)';
    $modelCode[] = '            ->orderBy(\'name\')';
    $modelCode[] = '            ->find();';
    $modelCode[] = '    }';
    $modelCode[] = '}';
    
    // Show generated code
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'YORM Model für ' . $selectedTable);
    $fragment->setVar('body', '
        <p>Speichern Sie diesen Code als <code>' . $className . '.php</code></p>
        <pre class="pre-scrollable"><code class="php">'.rex_escape(implode("\n", $modelCode)).'</code></pre>
        <button class="btn btn-default" onclick="copyToClipboard()">
            <i class="rex-icon fa-copy"></i> In Zwischenablage kopieren
        </button>
        <script>
        function copyToClipboard() {
            const code = document.querySelector("pre code").textContent;
            navigator.clipboard.writeText(code).then(() => {
                alert("Code wurde in die Zwischenablage kopiert!");
            });
        }
        </script>
    ', false);
    $content .= $fragment->parse('core/page/section.php');
    
    // Generate Example Code
    $usageCode = [];
    $usageCode[] = '<?php';
    $usageCode[] = '';
    $usageCode[] = '// Beispiel für die Verwendung des Models';
    $usageCode[] = '';
    $usageCode[] = '// Alle Datensätze abrufen';
    $usageCode[] = '$items = ' . $className . '::getAll();';
    $usageCode[] = '';
    $usageCode[] = '// Einzelnen Datensatz per ID laden';
    $usageCode[] = '$item = ' . $className . '::getById(1);';
    $usageCode[] = 'if ($item) {';
    
    foreach ($fields as $field) {
        if ($field['type_id'] == 'value') {
            $name = $field['name'];
            $methodName = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
            $usageCode[] = '    $' . $name . ' = $item->' . $methodName . '();';
        }
    }
    
    $usageCode[] = '}';
    $usageCode[] = '';
    $usageCode[] = '// Neuen Datensatz erstellen';
    $usageCode[] = '$item = ' . $className . '::create();';
    
    foreach ($fields as $field) {
        if ($field['type_id'] == 'value') {
            $name = $field['name'];
            $type = $field['type_name'];
            
            switch ($type) {
                case 'text':
                case 'textarea':
                    $usageCode[] = '$item->setValue(\'' . $name . '\', \'Wert\');';
                    break;
                case 'checkbox':
                    $usageCode[] = '$item->setValue(\'' . $name . '\', true);';
                    break;
                case 'int':
                case 'integer':
                    $usageCode[] = '$item->setValue(\'' . $name . '\', 42);';
                    break;
                case 'datetime':
                    $usageCode[] = '$item->setDateTimeValue(\'' . $name . '\', time());';
                    break;
            }
        }
    }
    
    $usageCode[] = '$item->save();';
    $usageCode[] = '';
    $usageCode[] = '// Datensätze mit Query API suchen';
    $usageCode[] = '$items = ' . $className . '::query()';
    $usageCode[] = '    ->where(\'status\', 1)';
    $usageCode[] = '    ->orderBy(\'name\')';
    $usageCode[] = '    ->find();';
    
    // Show example code
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Beispiel Code');
    $fragment->setVar('body', '
        <pre class="pre-scrollable"><code class="php">'.rex_escape(implode("\n", $usageCode)).'</code></pre>
        <button class="btn btn-default" onclick="copyExampleToClipboard()">
            <i class="rex-icon fa-copy"></i> In Zwischenablage kopieren
        </button>
        <script>
        function copyExampleToClipboard() {
            const code = document.querySelectorAll("pre code")[1].textContent;
            navigator.clipboard.writeText(code).then(() => {
                alert("Beispiel-Code wurde in die Zwischenablage kopiert!");
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

echo $content;
