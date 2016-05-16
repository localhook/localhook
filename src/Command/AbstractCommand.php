<?php

namespace Localhook\Localhook\Command;

use Localhook\Localhook\ConfigurationStorage;
use Localhook\Localhook\Exceptions\NoConfigurationException;
use Localhook\Localhook\Ratchet\UserClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractCommand extends Command
{
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

    /** @var string */
    protected $serverUrl;

    /** @var string */
    protected $secret;

    protected function loadConfiguration()
    {
        try {
            $configuration = $this->configurationStorage->loadFromFile()->get();
        } catch (NoConfigurationException $e) {

            $this->io->comment($e->getMessage());

            if (!$this->secret) {
                $this->secret = $this->io->ask('Secret');
            }
            $configuration = $this->parseConfigurationKey();
        }
        $this->serverUrl = $configuration['socket_url'];
        $this->secret = $configuration['secret'];
        $this->configurationStorage->merge($configuration)->save();
    }

    protected function syncConfiguration(
        callable $onSuccess,
        callable $onAddWebHook = null,
        callable $onRemoveWebHook = null
    ) {
        $this->socketUserClient->executeRetrieveConfigurationFromSecret($this->secret,
            function ($configuration) use ($onSuccess) {
                unset($configuration['comKey']);
                unset($configuration['status']);
                $this->updateLocalConfiguration($configuration);
                $onSuccess($configuration);
            }, $onAddWebHook, $onRemoveWebHook);
    }

    protected function getLocalWebHookConfigurationBy($key, $value)
    {
        $configuration = $this->configurationStorage->get();

        foreach ($configuration['web_hooks'] as $webHook) {
            if ($webHook[$key] == $value) {
                return $webHook;
            }
        }

        return null;
    }

    private function parseConfigurationKey()
    {
        $data = json_decode(base64_decode($this->secret, true), true);

        return ['socket_url' => $data[0], 'secret' => $data[1], 'web_hooks' => []];
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
