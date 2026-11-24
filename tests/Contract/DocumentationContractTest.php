<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Contract;

use Testo\Assert;
use Testo\Test;

#[Test]
final class DocumentationContractTest
{
    private const PAGES = [
        'getting-started.md',
        'configuration-sources.md',
        'symfony-console-tasks.md',
        'yaml-exec-catalog.md',
        'success-chains.md',
        'scheduling-and-profiles.md',
        'runtime-and-operations.md',
        'migrating-system-cron.md',
        'public-api.md',
        'migration-0.1-to-0.2.md',
    ];

    public function keepsTheRussianAndEnglishTreesMirrored(): void
    {
        $root = \dirname(__DIR__, 2) . '/docs';

        foreach (['ru', 'en'] as $locale) {
            $actual = array_map(basename(...), $this->files($root . '/' . $locale . '/*.md'));
            sort($actual);
            $expected = self::PAGES;
            sort($expected);
            Assert::same($actual, $expected);
        }
    }

    public function resolvesEveryLocalMarkdownLink(): void
    {
        $root = \dirname(__DIR__, 2);
        $files = [
            $root . '/README.md',
            $root . '/README.en.md',
            ...$this->files($root . '/docs/ru/*.md', $root . '/docs/en/*.md'),
        ];

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            if ($contents === false) {
                throw new \RuntimeException('Unable to read documentation: ' . $file);
            }

            preg_match_all('/\[[^]]+]\(([^)]+)\)/', $contents, $matches);

            foreach ($matches[1] as $target) {
                if (str_contains($target, '://') || str_starts_with($target, '#')) {
                    continue;
                }

                $path = \dirname($file) . '/' . explode('#', $target, 2)[0];
                Assert::true(file_exists($path), $file . ' -> ' . $target);
            }
        }
    }

    public function keepsRemovedApisOutOfCurrentGuidesAndPackagedSkill(): void
    {
        $root = \dirname(__DIR__, 2);
        $files = [
            $root . '/README.md',
            $root . '/README.en.md',
            ...array_filter(
                $this->files($root . '/docs/ru/*.md', $root . '/docs/en/*.md'),
                static fn(string $path): bool => !str_ends_with($path, '/migration-0.1-to-0.2.md'),
            ),
            ...$this->files(
                $root . '/resources/skills/cron-worker-integration/SKILL.md',
                $root . '/resources/skills/cron-worker-integration/references/*.md',
            ),
        ];
        $removed = [
            'CrontabConfigLoader',
            'Configuration\Conversion',
            'staticTask(',
            'containerTask(',
            'thenStatic(',
            'thenContainer(',
            'EveryTime::',
            'Runtime\SchedulerOptions',
        ];

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            if ($contents === false) {
                throw new \RuntimeException('Unable to read documentation: ' . $file);
            }

            foreach ($removed as $symbol) {
                Assert::true(!str_contains($contents, $symbol), $file . ' contains ' . $symbol);
            }
        }
    }

    /** @return list<string> */
    private function files(string ...$patterns): array
    {
        $files = [];

        foreach ($patterns as $pattern) {
            $matched = glob($pattern);

            if ($matched !== false) {
                array_push($files, ...$matched);
            }
        }

        return $files;
    }
}
