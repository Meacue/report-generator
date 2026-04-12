<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class DependencyTest extends TestCase
{
    public function test_domain_does_not_import_infrastructure(): void
    {
        $domainPath = realpath(__DIR__ . '/../../../app/Domain');

        if ($domainPath === false) {
            $this->markTestSkipped('Domain directory not found');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($domainPath),
        );

        $violations = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            if ($content === false) {
                continue;
            }

            if (str_contains($content, 'App\\Infrastructure\\')) {
                $violations[] = str_replace(
                    realpath(__DIR__ . '/../../..') . DIRECTORY_SEPARATOR,
                    '',
                    $file->getPathname(),
                );
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Domain files must not import from Infrastructure: ' . implode(', ', $violations),
        );
    }
}
