<?php

namespace Kasifi\Localhook\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Localhook\Core\SocketIoClientConnector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command

{
    protected function configure()
    {
        $this
            ->setName('run')
            ->addArgument('endpoint', InputArgument::REQUIRED, 'The name of the endpoint.')
            ->addOption('max', null, InputOption::VALUE_OPTIONAL, 'The maximum number of notification before stop watcher', null)
            ->setDescription('Watch for a notification and output it in JSON format.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $port = 1337; // TODO Dynamize
        $endpoint = $input->getArgument('endpoint');
        $max = $input->getOption('max');
        $counter = 0;

        $output->writeln('Watch for a notification to endpoint ' . $endpoint . ' ...');
        $socketIoClientConnector = new SocketIoClientConnector($port);
        $socketIoClientConnector->ensureConnection();

        $socketIoClientConnector->subscribeChannel($endpoint);

        while (true) {
            // apply max limitation
            if (!is_null($max) && $counter >= $max) {
                break;
            }
            $counter++;

            $notification = $socketIoClientConnector->waitForNotification();
            $url = 'http://localhost/notifications';

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

        $socketIoClientConnector->closeConnection();
    }
}
