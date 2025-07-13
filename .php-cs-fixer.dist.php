<?php

$fixers = [
    '@PSR1'                   => true,
    '@PSR2'                   => true,
    '@Symfony'                => true,
    'align_multiline_comment' => true,
    'array_indentation'       => true,
    'array_syntax'            => [
        'syntax' => 'short',
    ],
    'binary_operator_spaces' => [
        'operators' => [
            '=>' => 'align',
            '='  => 'align',
        ],
    ],
    'blank_line_before_statement' => [
        'statements' => ['return'],
    ],
    'combine_consecutive_issets'  => true,
    'combine_consecutive_unsets'  => true,
    'comment_to_phpdoc'           => true,
    'compact_nullable_typehint'   => true,
    'concat_space'                => [
        'spacing' => 'one',
    ],
    'escape_implicit_backslashes'  => true,
    'explicit_indirect_variable'   => true,
    'fully_qualified_strict_types' => true,
    'heredoc_to_nowdoc'            => true,
    'single_line_comment_style'    => [
        'comment_types' => ['hash'],
    ],
    'list_syntax'                  => [
        'syntax' => 'long',
    ],
    'logical_operators'                => true,
    'method_argument_space'            => [
        'on_multiline' => 'ensure_fully_multiline',
    ],
    'method_chaining_indentation'        => true,
    'multiline_comment_opening_closing'  => true,
    'new_with_braces'                    => false,
    'no_alternative_syntax'              => true,
    'no_binary_string'                   => true,
    'no_blank_lines_after_class_opening' => true,
    'no_empty_statement'                 => true,
    'no_extra_blank_lines'               => [
        'tokens' => [
            'break',
            'continue',
            'extra',
            'return',
            'throw',
            'use',
            'parenthesis_brace_block',
            'square_brace_block',
            'curly_brace_block',
        ],
    ],
    'no_leading_import_slash'                     => true,
    'no_multiline_whitespace_around_double_arrow' => true,
    'no_null_property_initialization'             => true,
    'echo_tag_syntax'                             => [
        'format' => 'long',
    ],
    'no_superfluous_elseif'                       => true,
    'no_trailing_comma_in_singleline_array'       => true,
    'no_unneeded_curly_braces'                    => true,
    'no_unneeded_final_method'                    => true,
    'no_unreachable_default_argument_value'       => true,
    'no_unset_on_property'                        => true,
    'no_unused_imports'                           => true,
    'no_useless_else'                             => true,
    'no_useless_return'                           => true,
    'no_whitespace_in_blank_line'                 => true,
    'not_operator_with_successor_space'           => true,
    'ordered_imports'                             => [
        'imports_order' => ['class', 'function', 'const'],
    ],
    'php_unit_internal_class'                     => true,
    'phpdoc_order_by_value'                       => [
        'annotations' => ['covers'],
    ],
    'php_unit_set_up_tear_down_visibility'        => true,
    'php_unit_strict'                             => true,
    'php_unit_test_annotation'                    => true,
    'php_unit_test_case_static_method_calls'      => [
        'call_type' => 'this',
    ],
    'phpdoc_add_missing_param_annotation'           => true,
    'phpdoc_align'                                  => true,
    'phpdoc_no_empty_return'                        => false,
    'phpdoc_order'                                  => true,
    'phpdoc_separation'                             => true,
    'phpdoc_summary'                                => false,
    'phpdoc_trim_consecutive_blank_line_separation' => true,
    'phpdoc_types_order'                            => true,
    'return_assignment'                             => true,
    'semicolon_after_instruction'                   => true,
    'single_line_comment_style'                     => true,
    'strict_comparison'                             => true,
    'string_line_ending'                            => true,
    'trailing_comma_in_multiline'                   => [
        'elements' => ['arrays'],
    ],
    'yoda_style'                                    => false,
    'no_empty_comment'                              => false,
];

$finder = PhpCsFixer\Finder::create()
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    ->exclude([
        '.git',
        '.idea',
        'bower_components',
        'node_modules',
        'vendor',
        'bin',
    ])
    ->in(__DIR__);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true) // The --allow-risky option (pass yes or no) allows you to set whether risky rules may run. Default value is taken from config file. Risky rule is a rule, which could change code behaviour. By default no risky rules are run.
    ->setRules($fixers)
    ->setUsingCache(true)
    ->setFinder($finder)
    ->setLineEnding("\n");
