# csvproc

[Csvproc](https://github.com/marzzelo/csvproc) es un utilitario creado para el 
post-procesamiento de los datos en formato CSV generados por la aplicación UEILogger de United
Electronic Industries.


## Instalación

Es necesario contar con `php-cli` y [Composer](https://getcomposer.org/) para comenzar.

Crear un nuevo proyecto:

```
composer create-project --prefer-dist minicli/application myapp
```

Once the installation is finished, you can run `minicli` it with:

```
cd myapp
./minicli
```

This will show you the default app signature:

```
usage: ./minicli help
```

The default `help` command that comes with minicli (`app/Command/Help/DefaultController.php`) auto-generates a tree of available commands:

```
./minicli help
```

```
Available Commands

help
└──test

```

The `help test` command, defined in `app/Command/Help/TestController.php`, shows an echo test of parameters:

```
./minicli help test user=erika name=value
```

```
Hello, erika!

Array
(
    [user] => erika
    [name] => value
)
```
