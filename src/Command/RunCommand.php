<?php

namespace Kasifi\Localhook\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends AbstractCommand

{
    protected function configure()
    {
        $this
            ->setName('run')
            ->addArgument('endpoint', InputArgument::OPTIONAL, 'The name of the endpoint.')
            ->addOption('max', null, InputOption::VALUE_OPTIONAL, 'The maximum number of notification before stop watcher', null)
            ->setDescription('Watch for a notification and output it in JSON format.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $this->ensureServerConnection();
        $endpoint = $input->getArgument('endpoint');
        // Retrieve configuration (and store it if necessary)
        $webHookConfiguration = $this->retrieveWebHookConfiguration($endpoint);
        $url = $webHookConfiguration['localUrl'];
        $endpoint = $webHookConfiguration['endpoint'];
        //$privateKey = $webHookConfiguration['privateKey'];
        $max = $input->getOption('max');
        $counter = 0;

        $output->writeln('Watch for notification to endpoint ' . $endpoint . ' ...');
        $this->socketIoClientConnector->subscribeChannel($endpoint);

        while (true) {
            // apply max limitation
            if (!is_null($max) && $counter >= $max) {
                break;
            }
            $counter++;

            $notification = $this->socketIoClientConnector->waitForNotification();

            $client = new Client();
            $request = new Request($notification['method'], $url);

            $output->writeln('REQUEST: ' . $notification['method'] . ' ' . $url);

            try {
                $response = $client->send($request, ['timeout' => 15]);
                $output->writeln('RESPONSE: ' . $response->getStatusCode());
            } catch (RequestException $e) {
                if ($e->hasResponse()) {
                    $output->writeln('RESPONSE: ' . $e->getResponse()->getStatusCode());
                }
            }
        }
        $this->socketIoClientConnector->closeConnection();
    }
}
