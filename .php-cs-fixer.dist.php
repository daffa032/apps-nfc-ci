<?php

declare(strict_types=1);

use CodeIgniter\CodingStandard\CodeIgniter4;
use Nexus\CsConfig\Factory;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
    ->in([__DIR__ . '/app', __DIR__ . '/tests'])
    ->exclude(['Views'])
    ->append([__FILE__]);

return Factory::create(new CodeIgniter4(), [], [
    'finder'         => $finder,
    'cacheFile'      => 'build/.php-cs-fixer.cache',
    'parallelConfig' => ParallelConfigFactory::detect(),
])->forProjects();
