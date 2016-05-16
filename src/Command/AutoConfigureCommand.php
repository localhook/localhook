<?php

namespace Localhook\Localhook\Command;

use Localhook\Localhook\ConfigurationStorage;
use Localhook\Localhook\Ratchet\UserClient;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AutoConfigureCommand extends AbstractCommand
{
    protected function configure()
    {
        if (!$this->getName()) {
            $this->setName('auto-configure');
        }
        $this
            ->addArgument('secret', InputArgument::REQUIRED, 'The secret key given on the website')
            ->setDescription('Auto-configure this Localhook client application to connect with a Localhook server');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->secret = $input->getArgument('secret');

        $this->configurationStorage = new ConfigurationStorage();
        $this->io = new SymfonyStyle($input, $output);
        $this->input = new $input;
        $this->output = new $output;
        $this->loadConfiguration();

        $this->socketUserClient = new UserClient($this->serverUrl);
        $this->socketUserClient->setIo($this->io);
        $this->io->writeln('Connecting to ' . $this->socketUserClient->getUrl() . ' ...');

        try {
            $this->socketUserClient->start(function () {
                $this->syncConfiguration(function () {
                    $this->io->success('Localhook client successfully configured. Enjoy!');
                    $this->socketUserClient->stop();
                });
            });
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            $this->socketUserClient->stop();
        }
    }
}
