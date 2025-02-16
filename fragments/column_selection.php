<?php
$table = $this->getVar('table');
$columns = $this->getVar('columns');
$selectedColumns = $this->getVar('selectedColumns');
?>
<div class="panel panel-default">
    <div class="panel-heading">
        <div class="panel-title"><?= $table ?></div>
    </div>
    <div class="panel-body">
        <select class="form-control selectpicker" 
                name="columns[<?= $table ?>][]" 
                multiple
                data-live-search="true"
                data-actions-box="true"
                data-selected-text-format="count > 3"
                onchange="window.location='<?= rex_url::currentBackendPage([
                    'func' => 'update_columns',
                    'table' => $table
                ]) ?>&columns=' + JSON.stringify($(this).val())">
            <?php foreach ($columns as $column): ?>
                <option value="<?= $column ?>" 
                    <?= in_array($column, $selectedColumns) ? ' selected' : '' ?>>
                    <?= $column ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
