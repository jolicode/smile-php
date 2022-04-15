<?php

/*
 * This file is part of the Smile PHP project, a project by JoliCode.
 */

namespace Jolicode\SmilePhp\Command;

use Jolicode\SmilePhp\Decoder\SmileDecoder;
use Jolicode\SmilePhp\Exception\UnexpectedValueException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'smile-decode',
    description: 'Decodes a smile binary file. You may directly decode a Smile binary string as well.'
)]
class DecodeCommand extends Command
{
    protected function configure()
    {
        $this->addArgument('smile-data', InputArgument::REQUIRED, 'The data you want to decode. You may use a file path, a SMILE string or "-" to read from STDIN.');
        $this->addOption('pretty', '', InputOption::VALUE_NONE, 'Should the result be pretty printed.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $style = new SymfonyStyle($input, $output);
        $decoder = new SmileDecoder();

        $data = $input->getArgument('smile-data');

        if (is_file($data)) {
            $data = file_get_contents($data);
        }

        if ('-' === $data) {
            $data = file_get_contents('php://stdin');
        }

        try {
            $pretty = $input->getOption('pretty');
            $flags = $pretty ? \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE : \JSON_UNESCAPED_UNICODE;
            $results = json_encode($decoder->decode($data), $flags);
            $style->writeln($results);
        } catch (UnexpectedValueException $exception) {
            $style->getErrorStyle()->error('An error occured while decoding the Smile data. Message : ' . $exception->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
