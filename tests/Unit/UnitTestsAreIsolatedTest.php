<?php

namespace Datalogix\Guardian\Tests\Unit;

use Datalogix\Guardian\Tests\TestCase as ApplicationTestCase;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A test that needs the Laravel application belongs in tests/Feature, so this
 * suite stays fast and free of framework state.
 */
class UnitTestsAreIsolatedTest extends TestCase
{
    public function test_no_unit_test_boots_the_application(): void
    {
        $offenders = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__)) as $file) {
            if (! str_ends_with($file->getFilename(), 'Test.php')) {
                continue;
            }

            $class = 'Datalogix\\Guardian\\Tests\\Unit\\'.str_replace(
                ['/', '.php'],
                ['\\', ''],
                substr($file->getPathname(), strlen(__DIR__) + 1),
            );

            if (is_subclass_of($class, ApplicationTestCase::class)) {
                $offenders[] = $class;
            }
        }

        $this->assertSame([], $offenders, 'Move these tests to tests/Feature.');
    }
}
