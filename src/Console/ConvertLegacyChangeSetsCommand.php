<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Console;

use ADT\DoctrineLoggable\Service\LegacyChangeSetConversionResult;
use ADT\DoctrineLoggable\Service\LegacyChangeSetConverter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'doctrine-loggable:convert-legacy-change-sets',
	description: 'Rewrites change_log rows from the serialize() format of version 3 into JSON'
)]
class ConvertLegacyChangeSetsCommand extends Command
{
	private LegacyChangeSetConverter $converter;

	public function __construct(LegacyChangeSetConverter $converter)
	{
		parent::__construct();

		$this->converter = $converter;
	}

	protected function configure(): void
	{
		$this
			->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'How many rows to convert per transaction', '500')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Read and convert everything, but roll back instead of writing');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$dryRun = (bool) $input->getOption('dry-run');
		$batchSize = (int) $input->getOption('batch-size');
		$rows = $this->converter->countRows();

		$output->writeln(($dryRun ? '[dry run] ' : '') . "Converting {$rows} change_log rows in batches of {$batchSize}.");

		$result = $this->converter->convert(
			$batchSize,
			function (LegacyChangeSetConversionResult $batch) use ($output): void {
				$output->writeln(sprintf(
					'  batch: %d converted, %d already json, %d failed',
					$batch->getConverted(),
					$batch->getSkipped(),
					count($batch->getFailures())
				));
			},
			$dryRun
		);

		$output->writeln(sprintf(
			'Done: %d converted, %d already json, %d failed.',
			$result->getConverted(),
			$result->getSkipped(),
			count($result->getFailures())
		));

		foreach ($result->getFailures() as $id => $message) {
			$output->writeln("  row {$id}: {$message}");
		}

		if ($result->hasFailures()) {
			$output->writeln('Rows that failed were left untouched, so the column cannot be changed to JSON yet.');

			return Command::FAILURE;
		}

		if (!$dryRun) {
			$output->writeln('Now change the column type: ALTER TABLE change_log MODIFY change_set JSON NOT NULL;');
		}

		return Command::SUCCESS;
	}
}
