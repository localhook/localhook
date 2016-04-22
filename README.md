[WIP]

# Localhook

A simple tool to receive http/https webhook notifications for development purpose.

This tool simplify web developments while using API using webhooks.

1) Install the [server application](https://github.com/lucascherifi/localhook-server) at https://yourdomain.com

2) Configure a new webhook on the server

- go to https://yourdomain.com/admin (default auth: admin / admin)
- Type a webhook path, ex : `webhook_1`
- Store somewhere the generated private key.

3) Install, configure and run the client:

- Install:
```bash
composer global require lucascherifi/localhook-client
```
- Start the client:
```bash
localhook run
```
At first run, you'll be prompt for the server URL and a private key watching a webhook you created.

# when prompted, enter the private and a local endpoint to call, i.e. "http://localhost:8080/notifications"

The client will now redirect all notifications received at https://yourdomain.com/webhook_1 to http://localhost:8080/notifications
