Quick start installation
========================

### 1) Get a free account in a second

It's just to give you a private acces to your data.

[localhook.umansoft.com/register](https://localhook.umansoft.com/register/)

No payment required, no data catched, no premium version.


### 2) Create a endpoint

[localhook.umansoft.com/webhook/new](https://localhook.umansoft.com/webhook/new)

### 3) Install the localhook client on you computer

```bash
curl -OSL https https://git.io/vwu9e -o localhook.phar && chmod +x localhook.phar && sudo mv localhook.phar /usr/local/bin/localhook && localhook auto-configure [YOUR_SECRET_KEY]
```

note: replace `[YOUR_SECRET_KEY]` by the secret available here: [localhook.umansoft.com/get-started](https://localhook.umansoft.com/get-started)

### 4) Start using localhook
```
localhook run
```

Now enjoy localhook usage! :-)

