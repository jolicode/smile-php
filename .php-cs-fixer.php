<?php

$fileHeaderComment = <<<'EOF'
    This file is part of the Smile PHP project, a project by JoliCode.
    EOF;

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->notPath('src/JoliTypo/Bridge/Symfony/DependencyInjection/Configuration.php')
    ->append([
        __FILE__,
    ])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP74Migration' => true,
        '@PhpCsFixer' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'php_unit_internal_class' => false,
        'php_unit_test_class_requires_covers' => false,
        'phpdoc_add_missing_param_annotation' => false,
        'header_comment' => ['header' => $fileHeaderComment],
        'concat_space' => ['spacing' => 'one'],
        'ordered_class_elements' => true,
        'blank_line_before_statement' => true,
    ])
    ->setFinder($finder)
;
