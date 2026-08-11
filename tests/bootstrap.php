<?php

// Guarded: pest includes the phpunit bootstrap through more than one path,
// and October's own bootstrap uses plain require for TestCase
if (!class_exists('TestCase', false)) {
    require __DIR__.'/../../../../modules/system/tests/bootstrap.php';
}

require_once __DIR__.'/BaseDashboardShopaholicTestCase.php';
