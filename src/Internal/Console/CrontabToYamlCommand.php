<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Internal\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wolfcharaa\Cron\Internal\Migration\CrontabYamlConverter;

/** @internal */
#[AsCommand(name: 'cron:crontab-to-yaml', description: 'Convert supported crontab entries to cron worker YAML.')]
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
            ->addOption('system', null, InputOption::VALUE_NONE, 'Parse /etc/crontab user columns.')
            ->addOption('schema', null, InputOption::VALUE_REQUIRED, 'Optional JSON Schema path written to $schema.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = $this->string($input->getArgument('source'), 'source');
        $destination = $this->string($input->getOption('output'), 'output');
        $contents = $source === '-' ? stream_get_contents(STDIN) : file_get_contents($source);

        if ($contents === false) {
            throw new \RuntimeException(\sprintf('Unable to read crontab input: %s', $source));
        }

        $yaml = $this->converter->convert(
            $contents,
            $this->string($input->getOption('profile'), 'profile'),
            (bool) $input->getOption('system'),
            $this->nullableString($input->getOption('schema'), 'schema'),
        );

        if ($destination === '-') {
            $output->write($yaml, false, OutputInterface::OUTPUT_RAW);
        } elseif (file_put_contents($destination, $yaml) === false) {
            throw new \RuntimeException(\sprintf('Unable to write YAML output: %s', $destination));
        }

        if ($output instanceof ConsoleOutputInterface) {
            $output->getErrorOutput()->writeln(
                '<comment>Review the generated YAML, user, environment, paths and cutover plan before disabling system cron.</comment>',
            );
        }

        return self::SUCCESS;
    }

    private function string(mixed $value, string $name): string
    {
        if (!\is_string($value) || $value === '') {
            throw new \InvalidArgumentException(\sprintf('Console input "%s" must be a non-empty string.', $name));
        }

        return $value;
    }

    private function nullableString(mixed $value, string $name): ?string
    {
        return $value === null ? null : $this->string($value, $name);
    }
}
