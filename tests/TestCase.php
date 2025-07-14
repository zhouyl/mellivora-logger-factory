<?php

declare(strict_types=1);

namespace Mellivora\Logger\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Initialize unit test settings here.
 *
 * For example, timezone settings, etc.
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        date_default_timezone_set('Asia/Shanghai');
        error_reporting(E_ALL);
        mb_internal_encoding('UTF-8');
        ini_set('display_errors', '1');
        ini_set('html_errors', '0');
    }

    public static function assertStringContains(string $haystack, string $needle): void
    {
        self::assertStringContainsString($needle, $haystack);
    }

    public static function assertStringNotContains(string $haystack, string $needle): void
    {
        self::assertStringNotContainsString($needle, $haystack);
    }
}
