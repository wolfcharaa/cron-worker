<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Dev\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wolfcharaa\Cron\Configuration\Conversion\CrontabYamlConverter;

#[AsCommand(
    name: 'cron:crontab-to-yaml',
    description: 'Convert a classic crontab list to cron worker YAML.',
)]
final class CrontabToYamlCommand extends Command
{
    public function __construct(private readonly CrontabYamlConverter $converter = new CrontabYamlConverter())
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::OPTIONAL, 'Crontab file or "-" for STDIN.', '-')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'YAML file or "-" for STDOUT.', '-')
            ->addOption('profile', 'p', InputOption::VALUE_REQUIRED, 'Target YAML profile.', 'default')
            ->addOption('timezone', null, InputOption::VALUE_REQUIRED, 'Fallback schedule timezone.', 'UTC')
            ->addOption('system', null, InputOption::VALUE_NONE, 'Parse /etc/crontab user columns.')
            ->addOption('schema', null, InputOption::VALUE_REQUIRED, 'Optional JSON Schema path written to $schema.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = $this->stringInput($input->getArgument('source'), 'source');
        $destination = $this->stringInput($input->getOption('output'), 'output');
        $contents = $source === '-'
            ? stream_get_contents(STDIN)
            : file_get_contents($source);

        if ($contents === false) {
            throw new \RuntimeException(\sprintf('Unable to read crontab input: %s', $source));
        }

        $yaml = $this->converter->convert(
            contents: $contents,
            profile: $this->stringInput($input->getOption('profile'), 'profile'),
            systemFormat: (bool) $input->getOption('system'),
            timezone: $this->stringInput($input->getOption('timezone'), 'timezone'),
            schema: $this->nullableStringInput($input->getOption('schema'), 'schema'),
        );

        if ($destination === '-') {
            $output->write($yaml, false, OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

        if (file_put_contents($destination, $yaml) === false) {
            throw new \RuntimeException(\sprintf('Unable to write YAML output: %s', $destination));
        }

        return self::SUCCESS;
    }

    private function stringInput(mixed $value, string $name): string
    {
        if (!\is_string($value) || $value === '') {
            throw new \InvalidArgumentException(\sprintf('Console input "%s" must be a non-empty string.', $name));
        }

        return $value;
    }

    private function nullableStringInput(mixed $value, string $name): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->stringInput($value, $name);
    }
}
