<?php

namespace Localhook\Localhook\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Localhook\Localhook\ConfigurationStorage;
use Localhook\Localhook\Exceptions\NoConfigurationException;
use Localhook\Localhook\Ratchet\UserClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\VarDumper\VarDumper;

class RunCommand extends Command

{
    /** @var integer */
    private $timeout;

    /** @var int */
    private $counter;

    /** @var int */
    private $max;

    /** @var string */
    private $configKey;

    /** @var SymfonyStyle */
    protected $io;

    /** @var InputInterface */
    protected $input;

    /** @var OutputInterface */
    protected $output;

    /** @var ConfigurationStorage */
    protected $configurationStorage;

    /** @var UserClient */
    protected $socketUserClient;

    /** @var array */
    private $webHookConfiguration;

    /** @var string */
    protected $webHookLocalUrl;

    /** @var string */
    protected $serverUrl;

    /** @var boolean */
    protected $noConfigFile;

    /** @var string */
    protected $endpoint;

    /** @var string */
    private $secret;

    protected function configure()
    {
        if (!$this->getName()) {
            $this->setName('run');
        }
        $this
            ->addArgument('endpoint', InputArgument::OPTIONAL, 'The name of the endpoint')
            ->addArgument('local-url', InputArgument::OPTIONAL, 'The local URL to call when a request in received from the server')
            ->addOption('config-key', null, InputOption::VALUE_REQUIRED, 'The configuration key given on the website', null)
            ->addOption('max', null, InputOption::VALUE_OPTIONAL, 'The maximum number of notification before stop watcher', null)
            ->addOption('no-config-file', null, InputOption::VALUE_NONE, 'If you don\'t want to save the current configuration.', null)
            ->setDescription('Watch for a notification and output it in JSON format');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Retrieve configuration (and store it if necessary)
        $this->max = $input->getOption('max');
        $this->counter = 0;
        $this->timeout = 15;
        $this->endpoint = $input->getArgument('endpoint');
        $this->webHookLocalUrl = $input->getArgument('local-url');
        $this->configKey = $input->getOption('config-key');
        $this->noConfigFile = $input->getOption('no-config-file');

        $this->configurationStorage = new ConfigurationStorage($this->noConfigFile);
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
                    $this->detectWebHookConfiguration($this->endpoint, function () {
                        $url = $this->webHookLocalUrl;
                        $this->socketUserClient->executeSubscribeWebHook(function () use ($url) {
                            $this->io->success('Successfully subscribed to ' . $this->endpoint . '. Local URL: ' . $url);
                            $this->output->writeln('Watch for notification to endpoint ' . $this->endpoint . ' ...');
                        }, function ($request) use ($url) {
                            if (count($request['query'])) {
                                $url .= '?' . http_build_query($request['query']);
                            }
                            $this->displayRequest($request, $url);
                            $this->sendRequest($request, $url);
                        }, function () {
                            $this->socketUserClient->stop();
                            $this->io->warning('Max forward reached (' . $this->max . ')');
                        }, $this->secret, $this->endpoint, $this->max);
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

    protected function loadConfiguration()
    {
        try {
            $configuration = $this->configurationStorage->loadFromFile()->get();
        } catch (NoConfigurationException $e) {

            $this->io->comment($e->getMessage());

            if (!$this->configKey) {
                $this->configKey = $this->io->ask(
                    'Secret',
                    'WyJ3czpcL1wvMTI3LjAuMC4xOjEzMzciLCIyOTNkOWMxNTAwMDkxZjI3MGYzYzVlZGY1Yjc0OTE2OTU5MjAzNzk4Il0='
                );
            }
            $configuration = $this->parseConfigurationKey();
        }
        $this->serverUrl = $configuration['socket_url'];
        $this->secret = $configuration['secret'];
        $this->configurationStorage->merge($configuration)->save();
    }

    protected function detectWebHookConfiguration($endpoint, callable $onSuccess)
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
            } else {
                $this->noWebHookAction();
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

    private function getLocalWebHookConfigurationBy($key, $value)
    {
        $configuration = $this->configurationStorage->get();

        foreach ($configuration['web_hooks'] as $webHook) {
            if ($webHook[$key] == $value) {
                return $webHook;
            }
        }

        return null;
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

    private function sendRequest($request, $url)
    {
        $client = new Client();
        try {
            $this->io->writeln('Waiting for response (timeout=' . $this->timeout . ')..');
            $body = $request['body'] ? $request['body'] : null;
            $guzzleRequest = new Request($request['method'], $url, $request['headers'], $body);
            $response = $client->send($guzzleRequest, ['timeout' => $this->timeout]);
            $this->io->writeln('LOCAL RESPONSE: ' . $response->getStatusCode());
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $this->io->warning('LOCAL RESPONSE: ' . $e->getResponse()->getStatusCode());
            }
        }
    }

    private function parseConfigurationKey()
    {
        $data = json_decode(base64_decode($this->configKey, true), true);

        return ['socket_url' => $data[0], 'secret' => $data[1], 'web_hooks' => []];
    }

    private function syncConfiguration(callable $onSuccess, callable $onAddWebHook, callable $onRemoveWebHook)
    {
        $this->socketUserClient->executeRetrieveConfigurationFromSecret($this->secret,
            function ($configuration) use ($onSuccess) {
                unset($configuration['comKey']);
                unset($configuration['status']);
                $this->updateLocalConfiguration($configuration);
                $this->io->note('To administrate your Webhooks, visit the following URL: ' . $configuration['web_url']);
                $onSuccess();
            }, $onAddWebHook, $onRemoveWebHook);
    }

    private function initLocalUrl()
    {
        if (!isset($this->webHookConfiguration['local_url'])) {
            if (!$this->webHookLocalUrl) {
                $this->webHookLocalUrl = $this->io->ask(
                    'Local URL to call when notification received from endpoint "' . $this->endpoint . '"',
                    'http://localhost/my-project/notifications'
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

    private function updateLocalConfiguration($remoteConfiguration)
    {
        $localWebHooksConfiguration = $this->configurationStorage->get()['web_hooks'];
        $remoteWebHooksConfiguration = $remoteConfiguration['web_hooks'];

        // remove old
        $localKeysToRemove = [];
        foreach ($localWebHooksConfiguration as $localKey => $localWebHook) {
            $found = false;
            foreach ($remoteWebHooksConfiguration as $remoteWebHook) {
                if ($localWebHook['endpoint'] == $remoteWebHook['endpoint']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $localKeysToRemove[] = $localKey;
            }
        }
        $removedEndpoints = [];
        foreach ($localKeysToRemove as $localKeyToRemove) {
            $removedEndpoints[] = $localWebHooksConfiguration[$localKeyToRemove]['endpoint'];
            unset($localWebHooksConfiguration[$localKeyToRemove]);
        }
        if (count($removedEndpoints)) {
            $this->io->writeln('Endpoint(s) "' . implode(', ', $removedEndpoints) . '" removed from local configuration as it/they does not exists anymore on the server.');
        }

        // add new
        $addedEndpoints = [];
        foreach ($remoteWebHooksConfiguration as $key => $remoteWebHook) {
            if (!$this->getLocalWebHookConfigurationBy('endpoint', $remoteWebHook['endpoint'])) {
                $localWebHooksConfiguration[] = $remoteWebHook;
                $addedEndpoints[] = $remoteWebHook['endpoint'];
            }
        }
        if (count($addedEndpoints)) {
            $this->io->writeln('Endpoint(s) ' . implode(', ', $addedEndpoints) . ' added to the local configuration as it/they has been configured on the server.');
        }

        $remoteConfiguration['web_hooks'] = array_values($localWebHooksConfiguration);

        $this->configurationStorage->replaceConfiguration($remoteConfiguration)->save();
    }
}
