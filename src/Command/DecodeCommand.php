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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'smile-decode',
    description: 'Decodes a smile binary file as JSON. You may directly decode a Smile string as well.'
)]
class DecodeCommand extends Command
{
    protected function configure()
    {
        $this->addArgument('smile-data', InputArgument::OPTIONAL, 'The data you want to decode, you can use a file or a string. Defaults to files/decoder/input.smile', __DIR__ . '/../../files/decoder/input.smile');
        $this->addArgument('output-file', InputArgument::OPTIONAL, 'The file that will be used to write the results. Defaults to files/decoder/output.json', __DIR__ . '/../../files/decoder/output.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $style = new SymfonyStyle($input, $output);
        $decoder = new SmileDecoder();

        $data = $input->getArgument('smile-data');

        if (is_file($data)) {
            $data = file_get_contents($data);
        }

        try {
            $results = $decoder->decode($data);
            $outputFile = $input->getArgument('output-file');

            file_put_contents(
                $outputFile,
                json_encode($results, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE)
            );

            $style->success(sprintf('Smile file successfuly decoded as JSON. Result written at %s', $outputFile));
            $style->section('Result :');
            $style->block(json_encode($results));
        } catch (UnexpectedValueException $exception) {
            $style->getErrorStyle()->error('An error occured while decoding the Smile data. Message : ' . $exception->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
