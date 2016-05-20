<?php

namespace Localhook\Localhook\Websocket;

use Exception;

class UserClient extends AbstractClient
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
            case '_subscribeWebHook':
            case '_unsubscribeWebHook':
                return $this->defaultReceive($msg, $comKey);
            case '_forwardRequest':
                return $this->receiveForwardRequest($msg, $comKey);
                break;
            case '_forwardAddWebHook':
                return $this->{'onAddWebHook'}($msg);
            case '_forwardRemoveWebHook':
                return $this->{'onRemoveWebHook'}($msg);
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
            return false;
        }
        return true;
    }

    public function executeRetrieveConfigurationFromSecret(
        $secret,
        callable $onSuccess,
        callable $onAddWebHook = null,
        callable $onRemoveWebHook = null
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
