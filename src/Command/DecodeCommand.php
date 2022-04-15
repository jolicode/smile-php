<?php

/*
 * This file is part of the Smile PHP project, a project by JoliCode.
 */

namespace Jolicode\SmilePhp\Command;

use Jolicode\SmilePhp\Decoder\SmileDecoder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use UnexpectedValueException;

#[AsCommand(
    name: 'smile-decode',
    description: 'Decodes a smile binary file as json. You may directly decode a smile string as well.'
)]
class DecodeCommand extends Command
{
    protected function configure()
    {
        $this->addArgument('smile-data', InputArgument::OPTIONAL, 'The data you want to decode, you can use a file or a string. Defaults to files/decode/input.smile', __DIR__ . '/../../files/decode/input.smile');
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
            file_put_contents(
                __DIR__ . '/../../files/decode/output.json',
                json_encode($decoder->decode($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        } catch (UnexpectedValueException $exception) {
            $style->error('An error occured while decoding the smile data. Message : ' . $exception->getMessage());

            return Command::FAILURE;
        }

        $style->success('Smile file successfuly decoded as json. Result written at files/decode/output.json');

        return Command::SUCCESS;
    }
}
