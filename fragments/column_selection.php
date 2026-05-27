<?php
$table = $this->getVar('table');
$columns = $this->getVar('columns');
$selectedColumns = $this->getVar('selectedColumns');
$csrfParams = $this->getVar('csrfParams', []);
?>
<div class="panel panel-default">
    <div class="panel-heading">
        <div class="panel-title"><?= $table ?></div>
    </div>
    <div class="panel-body">
        <form action="<?= rex_url::currentBackendPage() ?>" method="post" style="margin:0;">
            <input type="hidden" name="func" value="update_columns">
            <input type="hidden" name="table" value="<?= rex_escape((string) $table) ?>">
            <input type="hidden" name="columns" value="<?= rex_escape(json_encode(array_values($selectedColumns))) ?>">
            <?php foreach ($csrfParams as $paramKey => $paramValue): ?>
                <input type="hidden" name="<?= rex_escape((string) $paramKey) ?>" value="<?= rex_escape((string) $paramValue) ?>">
            <?php endforeach; ?>

            <select class="form-control selectpicker"
                    multiple
                    data-live-search="true"
                    data-actions-box="true"
                    data-selected-text-format="count > 3"
                    onchange="this.form.elements['columns'].value = JSON.stringify($(this).val() || []); this.form.submit();">
                <?php foreach ($columns as $column): ?>
                    <option value="<?= rex_escape((string) $column) ?>"<?= in_array($column, $selectedColumns, true) ? ' selected' : '' ?>>
                        <?= $column ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>
