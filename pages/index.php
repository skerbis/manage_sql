<?php
$package = rex_addon::get('table_manager');
echo rex_view::title($package->i18n('manage_sql'));
rex_be_controller::includeCurrentPageSubPath();
