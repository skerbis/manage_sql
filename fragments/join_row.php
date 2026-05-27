<?php
$index = $this->getVar('index');
$join = $this->getVar('join');
$tables = $this->getVar('tables');
$columns = $this->getVar('columns');
$csrfParams = $this->getVar('csrfParams', []);
?>
<div class="panel panel-default">
    <div class="panel-heading">
        <div class="row">
            <div class="col-sm-10">
                <div class="panel-title">JOIN Definition <?= $index + 1 ?></div>
            </div>
            <?php if ($index > 0): ?>
            <div class="col-sm-2 text-right">
                <form action="<?= rex_url::currentBackendPage() ?>" method="post" style="display:inline-block; margin:0;">
                    <input type="hidden" name="func" value="remove_join">
                    <input type="hidden" name="index" value="<?= (int) $index ?>">
                    <?php foreach ($csrfParams as $paramKey => $paramValue): ?>
                        <input type="hidden" name="<?= rex_escape((string) $paramKey) ?>" value="<?= rex_escape((string) $paramValue) ?>">
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-delete btn-xs" title="JOIN entfernen">
                        <i class="rex-icon fa-trash"></i>
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-sm-5">
                <div class="form-group">
                    <label>Linke Tabelle</label>
                    <form action="<?= rex_url::currentBackendPage() ?>" method="post" style="margin:0;">
                        <input type="hidden" name="func" value="select_table">
                        <input type="hidden" name="index" value="<?= (int) $index ?>">
                        <input type="hidden" name="side" value="left">
                        <?php foreach ($csrfParams as $paramKey => $paramValue): ?>
                            <input type="hidden" name="<?= rex_escape((string) $paramKey) ?>" value="<?= rex_escape((string) $paramValue) ?>">
                        <?php endforeach; ?>
                        <div class="rex-select-style">
                            <select class="form-control" name="table" onchange="this.form.submit()">
                                <option value="">Tabelle wählen...</option>
                                <?php foreach ($tables as $table): ?>
                                    <option value="<?= rex_escape((string) $table) ?>"<?= $table === $join['left_table'] ? ' selected' : '' ?>>
                                        <?= $table ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                    
                    <?php if ($join['left_table']): ?>
                    <form action="<?= rex_url::currentBackendPage() ?>" method="post" style="margin:8px 0 0;">
                        <input type="hidden" name="func" value="select_column">
                        <input type="hidden" name="index" value="<?= (int) $index ?>">
                        <input type="hidden" name="side" value="left">
                        <?php foreach ($csrfParams as $paramKey => $paramValue): ?>
                            <input type="hidden" name="<?= rex_escape((string) $paramKey) ?>" value="<?= rex_escape((string) $paramValue) ?>">
                        <?php endforeach; ?>
                        <div class="rex-select-style">
                            <select class="form-control" name="column" onchange="this.form.submit()">
                                <option value="">Spalte wählen...</option>
                                <?php foreach ($columns($join['left_table']) as $column): ?>
                                    <option value="<?= rex_escape((string) $column) ?>"<?= $column === $join['left_column'] ? ' selected' : '' ?>>
                                        <?= $column ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-sm-2">
                <div class="form-group">
                    <label>Join Typ</label>
                    <form action="<?= rex_url::currentBackendPage() ?>" method="post" style="margin:0;">
                        <input type="hidden" name="func" value="select_type">
                        <input type="hidden" name="index" value="<?= (int) $index ?>">
                        <?php foreach ($csrfParams as $paramKey => $paramValue): ?>
                            <input type="hidden" name="<?= rex_escape((string) $paramKey) ?>" value="<?= rex_escape((string) $paramValue) ?>">
                        <?php endforeach; ?>
                        <div class="rex-select-style">
                            <select class="form-control" name="type" onchange="this.form.submit()">
                                <?php foreach ([
                                    'INNER JOIN' => 'INNER JOIN',
                                    'LEFT JOIN' => 'LEFT JOIN',
                                    'RIGHT JOIN' => 'RIGHT JOIN',
                                    'FULL JOIN' => 'FULL JOIN'
                                ] as $type => $label): ?>
                                    <option value="<?= rex_escape((string) $type) ?>"<?= $type === $join['type'] ? ' selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-sm-5">
                <div class="form-group">
                    <label>Rechte Tabelle</label>
                    <form action="<?= rex_url::currentBackendPage() ?>" method="post" style="margin:0;">
                        <input type="hidden" name="func" value="select_table">
                        <input type="hidden" name="index" value="<?= (int) $index ?>">
                        <input type="hidden" name="side" value="right">
                        <?php foreach ($csrfParams as $paramKey => $paramValue): ?>
                            <input type="hidden" name="<?= rex_escape((string) $paramKey) ?>" value="<?= rex_escape((string) $paramValue) ?>">
                        <?php endforeach; ?>
                        <div class="rex-select-style">
                            <select class="form-control" name="table" onchange="this.form.submit()">
                                <option value="">Tabelle wählen...</option>
                                <?php foreach ($tables as $table): ?>
                                    <option value="<?= rex_escape((string) $table) ?>"<?= $table === $join['right_table'] ? ' selected' : '' ?>>
                                        <?= $table ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                    
                    <?php if ($join['right_table']): ?>
                    <form action="<?= rex_url::currentBackendPage() ?>" method="post" style="margin:8px 0 0;">
                        <input type="hidden" name="func" value="select_column">
                        <input type="hidden" name="index" value="<?= (int) $index ?>">
                        <input type="hidden" name="side" value="right">
                        <?php foreach ($csrfParams as $paramKey => $paramValue): ?>
                            <input type="hidden" name="<?= rex_escape((string) $paramKey) ?>" value="<?= rex_escape((string) $paramValue) ?>">
                        <?php endforeach; ?>
                        <div class="rex-select-style">
                            <select class="form-control" name="column" onchange="this.form.submit()">
                                <option value="">Spalte wählen...</option>
                                <?php foreach ($columns($join['right_table']) as $column): ?>
                                    <option value="<?= rex_escape((string) $column) ?>"<?= $column === $join['right_column'] ? ' selected' : '' ?>>
                                        <?= $column ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
