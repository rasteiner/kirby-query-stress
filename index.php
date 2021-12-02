<?php
use Kirby\Cms\App as Kirby;

require 'kirby/bootstrap.php';

// load the trouble maker only here, so it doesn't confuse the generator script
Kirby::plugin('test/blueprints', [
    'blueprints' => [
        'pages/person' => __DIR__ . '/blueprints/pages/person.yml'
    ]
]);

echo (new Kirby)->render();
