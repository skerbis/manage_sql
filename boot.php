<?php

if (!rex::isBackend()) {
    return;
}

if ('manage_sql' !== rex_be_controller::getCurrentPagePart(1)) {
    return;
}

$addon = rex_addon::get('manage_sql');
rex_view::addJsFile($addon->getAssetsUrl('manage_sql.js?v=' . $addon->getVersion()));
