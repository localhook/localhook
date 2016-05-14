Server installation guide
=========================

This project use Symfony Standard 2.8 LTS Version.
For DB purpose, it uses SQLite. But thanks to Doctrine, you can use the database system you need (mySQL, postgreSQL, etc).

- On a server environment, init the project :

```bash
git clone git@github.com:localhook/localhook-server.git
composer install # Fill the asked values with your needs.
```

- Init the database (SQLite):

```bash
php app/console doctrine:database:create
php app/console doctrine:schema:update --force
```

Web server configuration
------------------------

Nginx configuration example :

```nginx
upstream localhook_socket {
  # Directs to the process with least number of connections.
  least_conn;
  server 127.0.0.1:1337;
}

server {
  listen 443 ssl;
  server_name socket.notitications.yourserver.com;
  access_log /var/log/nginx/localhook.access.log;
  error_log /var/log/nginx/localhook.error.log;

  ssl_certificate /etc/ssl/nginx/localhook_socket.crt;
  ssl_certificate_key /etc/ssl/nginx/server.key;

  location / {
    proxy_pass http://localhook_socket;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection upgrade;
    proxy_set_header Host $host;
  }

}

server {
        listen 81;
        listen 443 ssl;
        server_name notitications.yourserver.com;
        access_log /var/log/nginx/localhook.access.log;
        error_log /var/log/nginx/localhook.error.log;
        root /var/www/localhook/web;
        #include /etc/nginx/restrict.conf;

        ssl_certificate      /etc/ssl/nginx/localhook.crt;
        ssl_certificate_key  /etc/ssl/nginx/server.key;
        ssl_session_cache shared:SSL:10m;
        ssl_session_timeout  5m;
        ssl_ciphers  "EECDH+ECDSA+AESGCM EECDH+aRSA+AESGCM EECDH+ECDSA+SHA384 EECDH+ECDSA+SHA256 EECDH+aRSA+SHA384 EECDH+aRSA+SHA256 EECDH+aRSA+RC4 EECDH EDH+aRSA RC4 !EXPORT !aNULL !eNULL !LOW !3DES !MD5 !EXP !PSK !SRP !DSS";
        ssl_prefer_server_ciphers   on;
        ssl_protocols TLSv1 TLSv1.1 TLSv1.2;

        if ($ssl_protocol = "") {
           rewrite ^ https://$host$request_uri? permanent;
        }

        location / {
                try_files $uri /app.php$is_args$args;
        }

        location ~ ^/(app|app_dev|config)\.php(/|$) {
                fastcgi_split_path_info ^(.+\.php)(/.*)$;
                include nginx-fpm.conf;
        }

        location ~* \.(js|css|png|jpg|jpeg|gif|ico|woff|eot|svg|ttf)$ {
                log_not_found off;
        }
}
```

Websockets server configuration
-------------------------------

```bash
php app/console app:server:run-socket-server
```

Supervisor configuration
------------------------

To make the websocket server restart if failed, you can install/use supervisor.

```sh
sudo vim /etc/supervisor/conf.d/localhook-socket.conf
```

```
[program:localhook-socket]
command=/var/www/localhook/app/console app:run-socket
autostart=true
autorestart=true
stderr_logfile=/var/log/localhook-socket.err.log
stdout_logfile=/var/log/localhook-socket.out.log
```

```sh
supervisorctl start localhook-socket
```

```sh
supervisorctl tail -f localhook-socket
```

Verify
------

Visit `https://notitications.yourserver.com` url.

The page is protected, use the login/password values you just set up in the above `composer install` step.

More
----

If you want to quick test this project, you can load "fake data" (fixtures):

```bash
php app/console hautelook:doctrine:fixtures:load -n
```

You can also send a fake notification as following:
```bash
php app/console app:server:simulate-notification webhook_1
```
Note : `webhook_1` is the name of a created webhook.
