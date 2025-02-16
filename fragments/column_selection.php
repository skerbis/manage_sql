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
        <div class="rex-select-style">
            <?php foreach ($columns as $column): ?>
                <div class="checkbox">
                    <label>
                        <a href="<?= rex_url::currentBackendPage([
                            'func' => 'toggle_column',
                            'table' => $table,
                            'column' => $column
                        ]) ?>" class="<?= in_array($column, $selectedColumns) ? 'text-primary' : 'text-muted' ?>">
                            <i class="rex-icon <?= in_array($column, $selectedColumns) ? 'fa-check-square-o' : 'fa-square-o' ?>"></i>
                            <?= $column ?>
                        </a>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
