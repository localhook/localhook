[WIP]

# Localhook

A simple tool to receive http/https webhook notifications for development purpose

This tool simplify web developments while using API using webhooks.

1) Install the [server application](https://github.com/lucascherifi/localhook-server) at https://yourdomain.com

2) Configure a new webhook on the server

- go to https://yourdomain.com/admin (enter login / password)
- Type a webhook path, ex : webhook_1
- store the generated hash (GENERATED_HASH).

3) Install, configure and run the client:

- Install:
```bash
composer global require lucascherifi/localhook-client
```
- Configure:
```bash
localhook configure # when prompted, enter the <GENERATED_HASH> and the local endpoint "http://localhost:8080/notifications"
```
- Run :
```bash
localhook run webhook_1
```

The client will now redirect all notifications received at https://yourdomain.com/webhook_1 to http://localhost:8080/notifications
