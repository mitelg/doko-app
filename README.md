doko-app
========

this is a small web app to save points for player of the german game "Doppelkopf"

based on Symfony 3

# Installation

- git clone this repo where ever you like
- cd into directory `doko-app`
- maybe adjust database setting in `/app/config/parameters.yml`
```sh
$ php bin/console doctrine:database:create
$ php bin/console doctrine:schema:create
```
- call `<your-domain>/doko-app/web/`