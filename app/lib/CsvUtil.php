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
     * @throws \Exception
     */
    public function getHeadersString(string $fname, bool $asArray = false) {
        $f = $this->fullName($fname);
        $handler = fopen($f, 'r');
        $headers = fgets($handler);
        return $asArray ? str_getcsv($headers) : $headers;
    }

    /**
     * @throws \Exception
     */
    public function fullName($f): string {
        if (empty($this->d))
            throw new \Exception('ERROR (fullName): No se ha inicializado directorio en CsvUtil');

        return $this->d->path . "\\" . $f;
    }

    public function nextFileName(string $extension = null): bool|string {
        while ($next = $this->d->read()) {
            if (is_null($extension) or !$next)
                return $next;  // puede retornar false

            if (pathinfo($next, PATHINFO_EXTENSION) != $extension)
                continue;
        }
        return $next;
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