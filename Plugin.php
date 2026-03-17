<?php

if (! defined('ESCALATED_LOADED')) {
    exit('Direct access not allowed.');
}

require_once __DIR__ . '/src/IntercomImportAdapter.php';
require_once __DIR__ . '/src/IntercomClient.php';
require_once __DIR__ . '/src/IntercomFieldMapper.php';

use Escalated\Plugins\ImportIntercom\IntercomImportAdapter;

escalated_add_filter('import.adapters', function (array $adapters) {
    $adapters[] = new IntercomImportAdapter();
    return $adapters;
}, 10);
