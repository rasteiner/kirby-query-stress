<?php

use Kirby\Cms\Page;
use Kirby\Query\Query;
use Kirby\Toolkit\Query\Runners\Interpreted;
use Kirby\Toolkit\Query\Runners\Transpiled;

/**
 * @var Page $page
 */
$runner = new Transpiled(Query::$entries);
$query = 'page("home").title';

dump($runner->run($query));