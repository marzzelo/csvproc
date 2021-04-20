<?php


namespace App\lib;


class CsvUtil
{
    private \Directory $d;

    /**
     * @return \Directory
     */
    public function getD(): \Directory {
        return $this->d;
    }

    /**
     * @param \Directory $d
     * @return CsvUtil
     */
    public function setD(\Directory $d): CsvUtil {
        $this->d = $d;
        return $this;
    }

    /**
     * Inserta una línea de texto al comienzo de un archivo.
     * Utiliza streams para optimizar el uso de memoria con archivos grandes.
     */
    public function prepend($string, $filename): bool {
        $context = stream_context_create();
        $orig_file = fopen($filename, 'r', false, $context);
        if (!$orig_file) return false;

        $temp_filename = tempnam(sys_get_temp_dir(), 'php_prepend_');
        file_put_contents($temp_filename, $string);
        file_put_contents($temp_filename, $orig_file, FILE_APPEND);

        fclose($orig_file);
        unlink($filename);
        rename($temp_filename, $filename);
        return true;
    }

    /**
     * Devuelve la primera línea de un archivo como string o como array
     * @throws \Exception
     */
    public function getHeadersString(string $fname, bool $asArray = false): bool|array|string {
        $f = $this->fullName($fname);
        $headers = fgets(fopen($f, 'r'));
        return $asArray ? str_getcsv($headers) : $headers;
    }

    /**
     * Devuelve el nombre completo del archivo, incluyendo el path
     * @throws \Exception
     */
    public function fullName($f): string {
        if (empty($this->d))
            throw new \Exception('ERROR (fullName): No se ha inicializado directorio en CsvUtil');

        return $this->d->path . "\\" . $f;
    }


    /**
     * Si $extension == null: devuelve el siguiente archivo del directorio, o false si no hay más.
     * Si $extension == 'ext': devuelve el siguiente archivo con extensión 'ext' o false si no hay más.
     */
    public function nextFileName(string $extension = null): bool|string {
        while ($next = $this->d->read()) {
            if (is_dir($next)) continue;  // no devolver directorios
            if (is_null($extension)) return $next;  // si no hay restricción de extensión, devolver el archivo.
            if (pathinfo($next, PATHINFO_EXTENSION) != $extension) continue;  // no devolver archivo si no coincide extensión
            break; // devolver el siguiente archivo con la extensión solicitada
        }
        return $next;  // no hay más archivos, devolver false (o archivo con extensión en caso de break)
    }

    public function dirRewind() {
        $this->d->rewind();
    }

    /**
     * @throws \Exception
     */
    public function is_dir($f): bool {
        return is_dir($this->fullName($f));
    }
}