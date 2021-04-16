# csvproc

[Csvproc](https://github.com/marzzelo/csvproc) es un utilitario creado para el 
post-procesamiento de los datos en formato CSV generados por la aplicación UEILogger de United
Electronic Industries.


## Instalación

Es necesario contar con `php-cli`, `git` y [Composer](https://getcomposer.org/) para comenzar.

Descargar el programa desde GitHub:

```
c:/> git clone https://github.com/marzzelo/csvproc.git
c:/> cd csvproc
```

Una vez descargado el programa, deberán instalarse las dependencias mediante Composer: 

```
c:/csvproc> composer install
No lock file found. Updating dependencies instead of installing from lock file...
Loading composer repositories with package information...
```

Probar la correcta instalación del programa mediante el siguiente comando:

```
c:/csvproc> php proc csv

uso: > php proc csv dir="dir"                                       
[out='nombre archivo salida sin extension'] => OUT_YYYYMMDD_HHMMSS  
[offrow='fila para el cálculo del offset inicial'] => 2000          
[buflen='cantidad de filas a promediar'] => 10                      
[step='periodo de muestreo'] => 0.002                               
```

Para procesar un directorio de datos (ej: c:/data/Test01/) ingresar el siguiente comando:

```
c:/csvproc/> php proc csv dir="c:/data/Test01" out="test01" offrow="5" step="0.1"
```
