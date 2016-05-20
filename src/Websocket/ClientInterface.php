<?php

namespace Localhook\Localhook\Websocket;

interface ClientInterface
{
    public function routeInputEvents($type, $msg, $comKey);
}
