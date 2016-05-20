<?php

namespace Localhook\Localhook\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Localhook\Localhook\ConfigurationStorage;
use Localhook\Localhook\Websocket\UserClient;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\VarDumper\VarDumper;

class RunCommand extends AbstractCommand

{
    /** @var array */
    private $webHookConfiguration;

    /** @var string */
    protected $webHookLocalUrl;

    /** @var string */
    private $endpoint;

    protected function configure()
    {
        if (!$this->getName()) {
            $this->setName('run');
        }
        $this
            ->addArgument('endpoint', InputArgument::OPTIONAL, 'The name of the endpoint')
            ->addArgument('local-url', InputArgument::OPTIONAL, 'The local URL to call when a request in received from the server')
            ->addOption('secret', null, InputOption::VALUE_REQUIRED, 'The configuration key given on the website', null)
            ->addOption('max', null, InputOption::VALUE_OPTIONAL, 'The maximum number of notification before stop watcher', null)
            ->addOption('no-config-file', null, InputOption::VALUE_NONE, 'If you don\'t want to save the current configuration', null)
            ->addOption('timeout', null, InputOption::VALUE_OPTIONAL, 'The timeout when forwarding requests to the local URL', 15)
            ->setDescription('Watch for a notification and output it in JSON format');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Retrieve configuration (and store it if necessary)
        $max = $input->getOption('max');
        $timeout = $input->getOption('timeout');
        $this->endpoint = $input->getArgument('endpoint');
        $this->webHookLocalUrl = $input->getArgument('local-url');
        $this->secret = $input->getOption('secret');
        $noConfigFile = $input->getOption('no-config-file');

        $this->configurationStorage = new ConfigurationStorage($noConfigFile);
        $this->io = new SymfonyStyle($input, $output);
        $this->input = new $input;
        $this->output = new $output;
        $this->loadConfiguration();

        $this->socketUserClient = new UserClient($this->serverUrl);
        $this->socketUserClient->setIo($this->io);
        $this->io->write('Connecting to ' . $this->socketUserClient->getUrl() . ' ...');

        try {
            $this->socketUserClient->start(function () use ($max, $timeout) {
                $this->io->write('Connected. Syncing configuration...');
                $this->syncConfiguration(function ($configuration) use ($max, $timeout) {
                    $this->io->writeln('Configuration synced.');
                    $this->io->success('To administrate your Webhooks, visit the following URL: ' . $configuration['web_url']);
                    $this->detectWebHookConfiguration($this->endpoint, function () use ($max, $timeout) {
                        $url = $this->webHookLocalUrl;
                        $this->socketUserClient->executeSubscribeWebHook(function ($message) use ($url) {
                            $this->io->success("Successfully subscribed to forward {$this->endpoint}.");
                            $table = new Table($this->output);
                            $table
                                ->setRows([
                                    ['External URL', $message['external_url']],
                                    ['Local URL', $url],
                                ]);
                            $table->render();
                            $this->output->writeln('Watch for notification...');
                            while ($this->socketUserClient->waitForMessage()) {
                                // loop until max configured forward is reached
                            }
                        }, function ($request) use ($url, $timeout) {
                            if (count($request['query'])) {
                                $url .= '?' . http_build_query($request['query']);
                            }
                            $this->displayRequest($request, $url);
                            $this->sendRequest($request, $url, $timeout);
                        }, function () use ($max) {
                            $this->socketUserClient->stop();
                            $this->io->success('Max number of forward(s) (' . $max . ') reached. Client closed successfully.');
                        }, $this->secret, $this->endpoint, $max);
                    });
                }, function ($msg) {
                    $configuration = $this->configurationStorage->get();
                    $configuration['web_hooks'] = array_merge(
                        $configuration['web_hooks'], [['endpoint' => $msg['endpoint']]]
                    );
                    $this->configurationStorage->replaceConfiguration($configuration)->save();
                    $this->io->comment('New WebHook added: ' . $msg['endpoint'] . '. Restart this command to use it.');
                }, function ($msg) {
                    $endpoint = $msg['endpoint'];
                    $configuration = $this->configurationStorage->get();
                    $keyToRemove = null;
                    foreach ($configuration['web_hooks'] as $key => $item) {
                        if ($item['endpoint'] == $endpoint) {
                            $keyToRemove = $key;
                        }
                    }
                    if (!is_null($keyToRemove)) {
                        unset($configuration[$keyToRemove]);
                        $this->configurationStorage->replaceConfiguration($configuration)->save();
                        if ($this->endpoint == $msg['endpoint']) {
                            $this->io->warning('WebHook removed on remote: ' . $msg['endpoint']);
                            $this->socketUserClient->stop();
                        } else {
                            $this->io->note('WebHook removed on remote: ' . $msg['endpoint']);
                        }
                    }
                });
            });
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            $this->socketUserClient->stop();
        }
    }

    private function detectWebHookConfiguration($endpoint, callable $onSuccess)
    {
        $configuration = $this->configurationStorage->get();
        if (!$endpoint) {
            $webHooks = $configuration['web_hooks'];
            $nbConfigs = count($webHooks);
            if ($nbConfigs) {
                if ($nbConfigs > 1) {
                    $webHookEndpoints = [];
                    foreach ($webHooks as $webHook) {
                        $webHookEndpoints[] = $webHook['endpoint'];
                    }

                    $question = new ChoiceQuestion('Select a configured WebHook', $webHookEndpoints);
                    $endpoint = $this->io->askQuestion($question);
                } else {
                    $endpoint = $webHooks[0]['endpoint'];
                }
                $this->endpoint = $endpoint;
            }
        } else {
            $this->endpoint = $endpoint;
        }
        $webHookConfiguration = $this->getLocalWebHookConfigurationBy('endpoint', $this->endpoint);
        if (!$webHookConfiguration) {
            $this->noWebHookAction();
        } else {
            $this->webHookConfiguration = $webHookConfiguration;
            $this->initLocalUrl();
            $onSuccess();
        }
    }

    private function displayRequest($request, $localUrl)
    {
        $vd = new VarDumper();

        // Local Request
        $this->io->success($request['method'] . ' ' . $localUrl);

        // Headers
        $headers = [];
        foreach ($request['headers'] as $key => $value) {
            $headers[] = [$key, implode(';', $value)];
        }
        (new Table($this->output))->setHeaders(['Request Header', 'Value'])->setRows($headers)->render();

        // POST body
        if ($request['body'] && strlen($request['body'])) {
            parse_str($request['body'], $params);
            if ($params && count($params) && array_values($params)[0]) {
                $this->io->writeln('POST parameters');
                $vd->dump($params);
            } else {
                $this->io->writeln('POST body');
                $this->io->writeln($request['body']);
            }
        } else {
            $this->io->writeln('No POST body');
        }
    }

    private function sendRequest($request, $url, $timeout)
    {
        $client = new Client();
        try {
            $this->io->writeln('Waiting for response (timeout=' . $timeout . ')..');
            $body = $request['body'] ? $request['body'] : null;
            $guzzleRequest = new Request($request['method'], $url, $request['headers'], $body);
            $response = $client->send($guzzleRequest, ['timeout' => $timeout]);
            $this->io->writeln('LOCAL RESPONSE: ' . $response->getStatusCode());
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $this->io->warning('LOCAL RESPONSE: ' . $e->getResponse()->getStatusCode());
            }
        }
    }

    private function initLocalUrl()
    {
        if (!isset($this->webHookConfiguration['local_url'])) {
            if (!$this->webHookLocalUrl) {
                $this->webHookLocalUrl = $this->io->ask(
                    'Local URL to call when notification received from endpoint "' . $this->endpoint . '"',
                    'http://localhost/' . $this->endpoint . '/notifications'
                );
                $this->webHookConfiguration['local_url'] = $this->webHookLocalUrl;

                // Store the local URL
                $configuration = $this->configurationStorage->get();
                $configurationKey = null;
                foreach ($configuration['web_hooks'] as $key => $item) {
                    if ($item['endpoint'] == $this->endpoint) {
                        $configurationKey = $key;
                    }
                }
                $configuration['web_hooks'][$configurationKey]['local_url'] = $this->webHookLocalUrl;
                $this->configurationStorage->replaceConfiguration($configuration)->save();
            }
        } else {
            $this->webHookLocalUrl = $this->webHookConfiguration['local_url'];
            $this->io->writeln('Local URL: ' . $this->webHookLocalUrl);
        }
    }

    private function noWebHookAction()
    {
        $this->io->warning(
            'No WebHook configured. Visit ' . $this->configurationStorage->get()['web_url'] .
            '/webhook/new to configure a new WebHook configuration.'
        );
        $this->socketUserClient->stop();
    }
}
