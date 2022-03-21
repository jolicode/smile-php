<?php

/*
 * This file is part of the Smile PHP project, a project by JoliCode.
 */

namespace Jolicode\SmilePhp\Command;

use Jolicode\SmilePhp\Encoder\SmileDecoder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'smile-decode',
    description: 'Decodes a smile binary file. You may directly decode a string as well.'
)]
class DecodeCommand extends Command
{
    protected function configure()
    {
        $this->addArgument('smile-data', InputArgument::OPTIONAL, 'The data you want to decode, you can use a file or a string. Defaults to files/decode/input.smile', __DIR__ . '/../../files/decode/input.smile');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $decoder = new SmileDecoder();

        $data = $input->getArgument('smile-data');

        if (is_file($data)) {
            $data = file_get_contents($data);
        }

        // TODO : add a progress bar

        // TODO : use a proper logger instead
        $output->writeln($decoder->decode($data));

        return Command::SUCCESS;
    }
}
