// Debug output
    echo '<div class="alert alert-info">';
    echo '<h4>Gefundene Felder:</h4>';
    echo '<pre>';
    print_r($fields);
    echo '</pre>';
    echo '</div>';

    // Find relations in the fields
    $relations = [];
    foreach ($fields as $field) {
        if ($field['type_name'] === 'be_manager_relation') {
            $options = [];
            
            // Decode options string to array
            if (!empty($field['options'])) {
                // Remove potential escaped quotes
                $optionsStr = str_replace('\"', '"', $field['options']);
                $options = json_decode($optionsStr, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // Debug output if JSON parsing fails
                    echo '<div class="alert alert-warning">';
                    echo 'JSON Parse Error für Feld ' . $field['name'] . ': ' . json_last_error_msg();
                    echo '<br>Options String: ' . $optionsStr;
                    echo '</div>';
                }
            }
            
            // Debug output for relation field
            echo '<div class="alert alert-info">';
            echo '<h4>Relations-Feld gefunden:</h4>';
            echo '<pre>';
            echo "Name: " . $field['name'] . "\n";
            echo "Label: " . $field['label'] . "\n";
            echo "Options:\n";
            print_r($options);
            echo '</pre>';
            echo '</div>';

            $relations[] = [
                'name' => $field['name'],
                'label' => $field['label'],
                'type' => $options['type'] ?? '1',
                'table' => $options['table'] ?? '',
                'relationTable' => $options['relation_table'] ?? ''
            ];
        }
    }

    // Debug output for found relations
    echo '<div class="alert alert-info">';
    echo '<h4>Gefundene Relationen:</h4>';
    echo '<pre>';
    print_r($relations);
    echo '</pre>';
    echo '</div>';
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
    $formCode[] = '$dataset = ' . $className . '::create();';
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
    $editCode[] = '$dataset = ' . $className . '::getById(rex_get(\'id\', \'int\'));';
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
    $listCode[] = '$items = ' . $className . '::getAll();';
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
    echo $content;
