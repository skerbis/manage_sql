<?php
$joins = [
    'INNER JOIN' => [
        'title' => 'Nur übereinstimmende Datensätze',
        'description' => 'Zeigt nur Datensätze, die in beiden Tabellen vorhanden sind. Wie bei einer Schnittmenge.',
        'example' => 'Beispiel: Artikel und deren Kategorien verknüpfen. Nur Artikel mit Kategorie werden angezeigt.',
        'icon' => 'fa-link',
        'class' => 'text-info'
    ],
    'LEFT JOIN' => [
        'title' => 'Alle Datensätze der linken Tabelle',
        'description' => 'Zeigt alle Datensätze der linken Tabelle, auch wenn keine Verknüpfung zur rechten Tabelle existiert.',
        'example' => 'Beispiel: Alle Artikel anzeigen, auch solche ohne Kategorie.',
        'icon' => 'fa-long-arrow-right',
        'class' => 'text-success'
    ],
    'RIGHT JOIN' => [
        'title' => 'Alle Datensätze der rechten Tabelle',
        'description' => 'Zeigt alle Datensätze der rechten Tabelle, auch wenn keine Verknüpfung zur linken Tabelle existiert.',
        'example' => 'Beispiel: Alle Kategorien anzeigen, auch solche ohne Artikel.',
        'icon' => 'fa-long-arrow-left',
        'class' => 'text-warning'
    ],
    'FULL JOIN' => [
        'title' => 'Alle Datensätze beider Tabellen',
        'description' => 'Zeigt alle Datensätze beider Tabellen, unabhängig davon ob eine Verknüpfung existiert.',
        'example' => 'Beispiel: Alle Artikel und alle Kategorien anzeigen, unabhängig von Verknüpfungen.',
        'icon' => 'fa-arrows-h',
        'class' => 'text-muted'
    ]
];
?>

<!-- Button für Modal -->
<button class="btn btn-default pull-right" data-toggle="modal" data-target="#joinHelpModal">
    <i class="rex-icon fa-question-circle"></i> JOIN-Typen erklärt
</button>

<!-- Modal -->
<div class="modal fade" id="joinHelpModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title"><i class="rex-icon fa-question-circle"></i> JOIN-Typen erklärt</h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong>Was ist ein JOIN?</strong><br>
                    Ein JOIN verbindet zwei Tabellen miteinander anhand von gemeinsamen Werten. 
                    Zum Beispiel können Artikel und Kategorien über eine Kategorie-ID verbunden werden.
                </div>

                <div class="row">
                    <?php foreach ($joins as $type => $info): ?>
                        <div class="col-sm-6">
                            <h4 class="<?= $info['class'] ?>">
                                <i class="rex-icon <?= $info['icon'] ?>"></i> 
                                <?= $type ?>
                            </h4>
                            <p><strong><?= $info['title'] ?></strong></p>
                            <p><?= $info['description'] ?></p>
                            <p><em><?= $info['example'] ?></em></p>
                            <hr>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="panel panel-default">
                    <div class="panel-heading">Praktische Tipps</div>
                    <div class="panel-body">
                        <ul>
                            <li>Beginnen Sie mit der Haupttabelle als linke Tabelle.</li>
                            <li>Benutzen Sie INNER JOIN, wenn Sie nur Datensätze mit Verknüpfungen brauchen.</li>
                            <li>Benutzen Sie LEFT JOIN, wenn Sie alle Datensätze der Haupttabelle brauchen.</li>
                            <li>Die Verknüpfung erfolgt meist über IDs (z.B. category_id = id).</li>
                        </ul>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>
