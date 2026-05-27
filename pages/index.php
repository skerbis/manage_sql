<?php
$package = rex_addon::get('manage_sql');
echo rex_view::title($package->i18n('manage_sql'));
rex_be_controller::includeCurrentPageSubPath();
