<?php

namespace Localhook\Localhook\Command;

use Localhook\Localhook\ConfigurationStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteConfigurationCommand extends Command
{
    /** @var ConfigurationStorage */
    protected $configurationStorage;

    protected function configure()
    {
        $this
            ->setName('delete-configuration')
            ->setDescription('Delete the whole configuration file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->configurationStorage = new ConfigurationStorage(false);
        $this->configurationStorage->deleteFile();
    }
}
