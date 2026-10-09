<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Discovery;

final readonly class ComposerProjectClassProvider implements ClassProviderInterface
{
    public function __construct(
        private string $projectRoot,
        private bool $includeDev = false,
    ) {}

    public function classes(): iterable
    {
        $composerFile = rtrim($this->projectRoot, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . 'composer.json';

        if (!is_file($composerFile)) {
            throw new \RuntimeException(\sprintf('Composer configuration does not exist: %s', $composerFile));
        }

        $contents = file_get_contents($composerFile);

        if ($contents === false) {
            throw new \RuntimeException(\sprintf('Unable to read Composer configuration: %s', $composerFile));
        }

        $composer = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (!\is_array($composer)) {
            throw new \UnexpectedValueException('Composer configuration must decode to an object.');
        }

        $providers = $this->providersFrom($composer['autoload'] ?? null);

        if ($this->includeDev) {
            array_push($providers, ...$this->providersFrom($composer['autoload-dev'] ?? null));
        }

        yield from (new ChainClassProvider(...$providers))->classes();
    }

    /** @return list<ClassProviderInterface> */
    private function providersFrom(mixed $autoload): array
    {
        if (!\is_array($autoload)) {
            return [];
        }

        $psr4 = $autoload['psr-4'] ?? null;

        if (!\is_array($psr4)) {
            return [];
        }

        $providers = [];

        foreach ($psr4 as $prefix => $paths) {
            if (!\is_string($prefix) || (!\is_string($paths) && !\is_array($paths))) {
                continue;
            }

            foreach ((array) $paths as $path) {
                if (!\is_string($path) || $path === '') {
                    continue;
                }

                $providers[] = new Psr4DirectoryClassProvider([
                    $prefix => rtrim($this->projectRoot, \DIRECTORY_SEPARATOR)
                        . \DIRECTORY_SEPARATOR
                        . ltrim($path, \DIRECTORY_SEPARATOR),
                ]);
            }
        }

        return $providers;
    }
}
