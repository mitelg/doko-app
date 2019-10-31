doko-app
========

This is a small web app to save points for players of the german card game "Doppelkopf"

Based on Symfony 4.3

# Installation

- git clone this repo where ever you like
- cd into directory `doko-app`
- install composer requirements
```sh
$ composer install
```
- create `.env` file with `cp .env.dist .env`
- make adjustments according to your setup (e.g. DB connection)
```sh
$ php bin/console doctrine:database:create
$ php bin/console doctrine:schema:create
```

# Development
Use the symfony server component to run the application
```sh
$ php bin/console server:run
$ php bin/console server:stop
```

or call `<your-domain>/doko-app/public/`

# TODO

- extract logic from controller into a service
