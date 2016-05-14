Client installation guide
=========================

```bash
$ curl -OSL https https://git.io/vwu9e -o localhook.phar && chmod +x localhook.phar
```

Please note that as Github is using a DDOS protection system, if using CURL fails, just manually download the phar file.

If you want to run localhook instead of php localhook.phar, move it to /usr/local/bin:

```bash
$ sudo mv localhook.phar /usr/local/bin/localhook
```

Please note that you need to have the phar extension installed to use this method. It should be installed by default on most OSes.
