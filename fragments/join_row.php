<?php
/** @var int $index */
/** @var array{left_table: string, left_column: string, right_table: string, right_column: string, type: string} $join */
/** @var list<string> $tables */
/** @var callable(string): list<string> $columns */
?>
<div class="panel panel-default">
    <div class="panel-heading">
        <div class="row">
            <div class="col-sm-10">
                <div class="panel-title">JOIN Definition <?= $index + 1 ?></div>
            </div>
            <?php if ($index > 0): ?>
            <div class="col-sm-2 text-right">
                <a href="<?= rex_url::currentBackendPage(['func' => 'remove_join', 'index' => $index]) ?>" 
                   class="btn btn-delete btn-xs" title="JOIN entfernen">
                    <i class="rex-icon fa-trash"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-sm-5">
                <div class="form-group">
                    <label>Linke Tabelle</label>
                    <div class="rex-select-style">
                        <select class="form-control" 
                                onchange="window.location=this.value">
                            <option value="">Tabelle wählen...</option>
                            <?php foreach ($tables as $table): ?>
                                <option value="<?= rex_url::currentBackendPage([
                                    'func' => 'select_table',
                                    'index' => $index,
                                    'side' => 'left',
                                    'table' => $table
                                ]) ?>"
                                    <?= $table === $join['left_table'] ? ' selected' : '' ?>>
                                    <?= $table ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($join['left_table']): ?>
                    <div class="rex-select-style">
                        <select class="form-control" 
                                onchange="window.location=this.value">
                            <option value="">Spalte wählen...</option>
                            <?php foreach ($columns($join['left_table']) as $column): ?>
                                <option value="<?= rex_url::currentBackendPage([
                                    'func' => 'select_column',
                                    'index' => $index,
                                    'side' => 'left',
                                    'column' => $column
                                ]) ?>"
                                    <?= $column === $join['left_column'] ? ' selected' : '' ?>>
                                    <?= $column ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-sm-2">
                <div class="form-group">
                    <label>Join Typ</label>
                    <div class="rex-select-style">
                        <select class="form-control"
                                onchange="window.location=this.value">
                            <?php foreach ([
                                'INNER JOIN' => 'INNER JOIN',
                                'LEFT JOIN' => 'LEFT JOIN',
                                'RIGHT JOIN' => 'RIGHT JOIN',
                                'FULL JOIN' => 'FULL JOIN'
                            ] as $type => $label): ?>
                                <option value="<?= rex_url::currentBackendPage([
                                    'func' => 'select_type',
                                    'index' => $index,
                                    'type' => $type
                                ]) ?>"
                                    <?= $type === $join['type'] ? ' selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="col-sm-5">
                <div class="form-group">
                    <label>Rechte Tabelle</label>
                    <div class="rex-select-style">
                        <select class="form-control"
                                onchange="window.location=this.value">
                            <option value="">Tabelle wählen...</option>
                            <?php foreach ($tables as $table): ?>
                                <option value="<?= rex_url::currentBackendPage([
                                    'func' => 'select_table',
                                    'index' => $index,
                                    'side' => 'right',
                                    'table' => $table
                                ]) ?>"
                                    <?= $table === $join['right_table'] ? ' selected' : '' ?>>
                                    <?= $table ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($join['right_table']): ?>
                    <div class="rex-select-style">
                        <select class="form-control"
                                onchange="window.location=this.value">
                            <option value="">Spalte wählen...</option>
                            <?php foreach ($columns($join['right_table']) as $column): ?>
                                <option value="<?= rex_url::currentBackendPage([
                                    'func' => 'select_column',
                                    'index' => $index,
                                    'side' => 'right',
                                    'column' => $column
                                ]) ?>"
                                    <?= $column === $join['right_column'] ? ' selected' : '' ?>>
                                    <?= $column ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
