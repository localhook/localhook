<?php
namespace Kasifi\Localhook;

use Kasifi\Localhook\Exceptions\DeletedChannelException;

class SocketIoClientConnector extends AbstractSocketIoConnector
{
    /**
     * @param string $privateKey
     *
     * @return $this
     */
    public function retrieveConfigurationFromPrivateKey($privateKey)
    {
        return $this->ask('retrieve_configuration_from_private_key', ['privateKey' => $privateKey]);
    }

    /**
     * @param string $channel
     *
     * @param        $privateKey
     *
     * @return $this
     */
    public function subscribeChannel($channel, $privateKey)
    {
        $this->emitAndCheck('subscribe_channel', ['channel' => $channel, 'privateKey' => $privateKey]);

        return $this;
    }

    /**
     * @return array
     * @throws DeletedChannelException
     */
    public function waitForNotification()
    {
        $message = $this->waitForMessage(['forwarded_notification', 'deleted_channel']);

        switch ($message['key']) {
            case 'forwarded_notification':
                return $message['content'];
                break;
            case 'deleted_channel':
                throw new DeletedChannelException('Channel has been deleted by remote.');
        }
    }
}
