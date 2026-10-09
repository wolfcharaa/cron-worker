<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Discovery;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\Cron\Discovery\ChainClassProvider;
use Wolfcharaa\Cron\Discovery\ClassListProvider;
use Wolfcharaa\Cron\Discovery\ComposerClassMapProvider;
use Wolfcharaa\Cron\Discovery\RegexFilterClassProvider;
use Wolfcharaa\Cron\Tests\Fixture\Discovery\FisUploadCommand;
use Wolfcharaa\Cron\Tests\Fixture\Discovery\RecoverStaleCommand;

#[Test]
final class ClassProviderTest
{
    public function chainsFiltersAndDeduplicatesClasses(): void
    {
        $provider = new RegexFilterClassProvider(
            new ChainClassProvider(
                new ClassListProvider([FisUploadCommand::class, RecoverStaleCommand::class]),
                new ClassListProvider([FisUploadCommand::class]),
            ),
            '/UploadCommand$/D',
        );

        Assert::same(iterator_to_array($provider->classes()), [FisUploadCommand::class]);
    }

    public function readsComposerClassMapByNamespacePrefix(): void
    {
        $classMap = tempnam(sys_get_temp_dir(), 'cron-class-map-');

        if ($classMap === false) {
            throw new \RuntimeException('Unable to create a temporary Composer class map.');
        }

        try {
            file_put_contents($classMap, \sprintf(
                '<?php return %s;',
                var_export([
                    FisUploadCommand::class => __FILE__,
                    RecoverStaleCommand::class => __FILE__,
                ], true),
            ));

            $provider = new ComposerClassMapProvider(
                $classMap,
                ['Wolfcharaa\Cron\Tests\Fixture\Discovery\Fis'],
            );

            Assert::same(iterator_to_array($provider->classes()), [FisUploadCommand::class]);
        } finally {
            if (is_file($classMap)) {
                unlink($classMap);
            }
        }
    }
}
