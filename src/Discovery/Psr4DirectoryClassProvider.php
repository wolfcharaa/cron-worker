<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Discovery;

/** Maps explicit PSR-4 namespace roots to readable source directories. */
final readonly class Psr4DirectoryClassProvider implements ClassProviderInterface
{
    /** @param array<string, string> $paths namespace prefix => directory */
    public function __construct(private array $paths)
    {
        foreach ($this->paths as $prefix => $directory) {
            if ($prefix === '' || trim($directory) === '') {
                throw new \InvalidArgumentException('PSR-4 namespace prefixes and directories must not be empty.');
            }

            if (!is_dir($directory) || !is_readable($directory)) {
                throw new \InvalidArgumentException(\sprintf(
                    'PSR-4 cron discovery directory "%s" must be a readable directory.',
                    $directory,
                ));
            }
        }
    }

    /** Yields existing classes inferred from PHP paths under each explicit root. */
    public function classes(): iterable
    {
        foreach ($this->paths as $prefix => $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = substr($file->getPathname(), \strlen(rtrim($directory, \DIRECTORY_SEPARATOR)) + 1);
                $class = rtrim($prefix, '\\') . '\\' . str_replace(
                    [\DIRECTORY_SEPARATOR, '.php'],
                    ['\\', ''],
                    $relative,
                );

                if (class_exists($class)) {
                    yield $class;
                }
            }
        }
    }
}
