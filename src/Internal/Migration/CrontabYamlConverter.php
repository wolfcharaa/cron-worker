<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Internal\Migration;

use Symfony\Component\Yaml\Yaml;
use Wolfcharaa\Cron\Configuration\Loader\YamlConfigLoader;
use Wolfcharaa\Cron\Configuration\Profile;

/** @internal */
final readonly class CrontabYamlConverter
{
    public function __construct(private CrontabParser $parser = new CrontabParser()) {}

    public function convert(
        string $contents,
        Profile|string|\BackedEnum $profile = Profile::DEFAULT,
        bool $systemFormat = false,
        ?string $schema = null,
    ): string {
        $profile = Profile::normalize($profile);
        $jobs = [];

        foreach ($this->parser->parse($contents, $systemFormat) as $index => $source) {
            $job = [
                'name' => 'job-' . ($index + 1),
                'schedule' => $source['expression'],
                'command' => $source['command'],
                'shell' => $source['shell'],
                'overlap_policy' => 'allow',
            ];

            if ($source['workingDirectory'] !== null) {
                $job['working_directory'] = $source['workingDirectory'];
            }

            if ($source['environment'] !== []) {
                $job['environment'] = $source['environment'];
            }

            if ($source['user'] !== null) {
                $job['user'] = $source['user'];
            }

            $jobs[] = $job;
        }

        $document = [
            'version' => 1,
            'profiles' => [$profile->name => ['jobs' => $jobs]],
        ];

        if ($schema !== null && $schema !== '') {
            $document = ['$schema' => $schema, ...$document];
        }

        $yaml = Yaml::dump($document, 8, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        (new YamlConfigLoader($yaml, '<converted-crontab>'))->load();

        return $yaml;
    }
}
