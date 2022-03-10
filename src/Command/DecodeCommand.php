<?php

namespace Jolicode\SmilePhp\Command;

use Jolicode\SmilePhp\Encoder\SmileDecoder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
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
        $this->addArgument('smile-data', InputArgument::OPTIONAL, 'The data you want to decode, you can use a file or a string. Defaults to files/decode/input', __DIR__ . '/../../files/decode/input');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $decoder = new SmileDecoder();

        $data = $input->getArgument('smile-data');

        if (is_file($data)) {
            $file = fopen($data, 'r');
            $data = fread($file, filesize($data));
            fclose($file);
        }

        // TODO : add a progress bar

        // TODO : use a proper logger instead
        echo($decoder->decode($data));

        return Command::SUCCESS;
    }
}
