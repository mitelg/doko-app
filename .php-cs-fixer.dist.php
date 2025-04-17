<?php

use PhpCsFixer\Config;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests');

return (new Config())
    ->setRiskyAllowed(true)
    ->setCacheFile('var/cache/php-cs-fixer')
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,

        'concat_space' => ['spacing' => 'one'],
        'header_comment' => [
            'header' => 'Copyright (c) Michael Telgmann

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.',
            'separate' => 'bottom',
            'comment_type' => 'PHPDoc'
        ],
        'phpdoc_summary' => false,
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
    ])
    ->setFinder($finder);
