<?php

namespace Localhook\Localhook\Ratchet;

use Exception;

class UserClient extends AbstractClient implements ClientInterface
{
    /** @var integer */
    private $max;

    /** @var integer */
    private $counter;

    /** @var callable */
    private $forwardRequestCallback;

    /** @var callable */
    private $maxReachedCallback;

    /** @var callable */
    private $onAddWebHook;

    /** @var callable */
    private $onRemoveWebHook;

    public function routeInputEvents($type, $msg, $comKey)
    {
        switch ($type) {
            case '_retrieveConfigurationFromSecret':
                $this->defaultReceive($msg, $comKey);
                break;
            case '_subscribeWebHook':
                $this->defaultReceive($msg, $comKey);
                break;
            case '_unsubscribeWebHook':
                $this->defaultReceive($msg, $comKey);
                break;
            case '_forwardRequest':
                $this->receiveForwardRequest($msg, $comKey);
                break;
            case '_forwardAddWebHook':
                $onAddWebHook = $this->onAddWebHook;
                $onAddWebHook($msg);
                break;
            case '_forwardRemoveWebHook':
                $onRemoveWebHook = $this->onRemoveWebHook;
                $onRemoveWebHook($msg);
                break;
            default:
                throw new Exception('Type "' . $type . '" not managed');
        }
    }

    private function receiveForwardRequest($msg, $comKey)
    {
        $requestData = $msg['request'];
        $forwardRequestCallback = $this->forwardRequestCallback;
        $forwardRequestCallback($requestData, $comKey);
        $this->counter++;

        if (!is_null($this->max) && $this->counter >= $this->max) {
            $maxReachedCallback = $this->maxReachedCallback;
            $maxReachedCallback($comKey);
        }
    }

    public function executeRetrieveConfigurationFromSecret(
        $secret,
        callable $onSuccess,
        callable $onAddWebHook,
        callable $onRemoveWebHook
    ) {
        $this->onAddWebHook = $onAddWebHook;
        $this->onRemoveWebHook = $onRemoveWebHook;
        $this->defaultExecute('retrieveConfigurationFromSecret', [
            'secret' => $secret,
        ], $onSuccess);
    }

    public function executeSubscribeWebHook(
        callable $onSuccess,
        callable $onForward,
        callable $onMaxReached,
        $secret,
        $endpoint,
        $max
    ) {
        $this->forwardRequestCallback = $onForward;
        $this->maxReachedCallback = $onMaxReached;
        $this->max = $max;
        $this->counter = 0;
        $this->defaultExecute('subscribeWebHook', [
            'secret'   => $secret,
            'endpoint' => $endpoint,
        ], $onSuccess);
    }
}
