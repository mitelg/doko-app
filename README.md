doko-app
========

this is a small web app to save points for players of the german card game "Doppelkopf"

based on Symfony 3

# Installation

- git clone this repo where ever you like
- cd into directory `doko-app`
- install composer requirements
```sh
$ composer install
```
- create `.env file with `cp .env.dist .env``
- make adjustments according to your setup (e.g. db connection)
```sh
$ php bin/console doctrine:database:create
$ php bin/console doctrine:schema:create
```
- call `<your-domain>/doko-app/web/`

# TODO

- extract logic from controller into a service