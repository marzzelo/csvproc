<?php

namespace App\Command\Csv;

use Minicli\Command\CommandController;

class ProcessController extends CommandController
{
    public function handle()
    {
        $name = $this->hasParam('dir') ? $this->getParam('dir') : 'indicar directorio!';
        $this->getPrinter()->display(sprintf("Dir: %s", $name));
        // print_r($this->getParams());
    }
}