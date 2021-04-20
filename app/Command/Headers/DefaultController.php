<?php


namespace App\Command\Headers;


use Minicli\App;
use App\lib\CsvUtil;
use JetBrains\PhpStorm\NoReturn;
use Minicli\Output\Helper\TableHelper;
use Minicli\Command\CommandController;
use Minicli\Output\Filter\ColorOutputFilter;

class DefaultController extends CommandController
{

    public function boot(App $app)
	{
		parent::boot($app);
		$this->command_map = $app->command_registry->getCommandMap();
	}


	/**
	 * @throws \Exception
	 */
	#[NoReturn] public function handle()
	{
		$util = new CsvUtil();

		// HELP
		if (!$this->hasParam('dir')) {
			$this->getPrinter()->info("---------- HEADERS PROCESSOR ----------", true);

			$table = new TableHelper();
			$table->addHeader(['Param/Flag', 'Definition', 'Default Value']);
			$table->addRow(['<dir="folder">', "Directorio a procesar", 'no default, required parameter',]);
			$table->addRow(['[sep="separator"]', "comma|tab|semicolon", "comma",]);
			$table->addRow(['[ext="extension"]', "txt|dat|csv|...", "csv",]);

			$this->getPrinter()->newline();
			$this->getPrinter()->rawOutput($table->getFormattedTable(new ColorOutputFilter()));
            $this->getPrinter()->newline();
            exit();
		}

		// CHECK IF [dir] IS A DIRECTORY
		$dir = $this->getParam('dir');
		if (!is_dir($dir)) {
			$this->getPrinter()->error("$dir No es un directorio.");
			exit();
		}

		// READ SEPARATOR & EXTENSION
        $separator = $this->getParam('sep') ?? 'comma';
		$extension = $this->getParam('ext') ?? 'csv';

		// ABRIR EL DIRECTORIO
		$util->setD(dir($dir));

		$nfiles = 0;    // PROCESED FILES
		$nAlready = 0;  // SKIPPED FILES

		// POSIBLES VALORES PARA sep
		$seps = ['comma' => ',', 'tab' => "\t", 'semicolon' => ';'];

		// ASIGNAR SEPARADOR
		if (isset($seps[$separator])) {
			$newHeader = "sep={$seps[$separator]}\n";
		} else {
			$this->getPrinter()
			     ->error("Separador no soportado.");
			exit();
		}

		// INSERTAR ENCABEZADOS
		while ($f = $util->nextFileName($extension)) {

			echo "\n--> $f";

			// SKIP FILE IF ALREADY HAS THE HEADER
			if ($util->getHeadersString($f, false) == $newHeader) {
				$this->getPrinter()->error("$f already has the header - Skipped");
				++$nAlready;
				continue;
			}

			// INSERT HEADER
			if (!$util->prepend($newHeader, $util->fullName($f))) {
				echo "\nCan't process {$util->fullName($f)} file";
			}

			$this->getPrinter()->success("done.");
			++$nfiles;

		}

		$this->getPrinter()->success("$nfiles files headered, $nAlready files skipped", 1);
	}


}