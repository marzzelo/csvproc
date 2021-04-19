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
	private CsvUtil $util;

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
		$this->util = new CsvUtil();

		if (!$this->hasParam('dir')) {
			$this->getPrinter()
			     ->info("---------- HEADERS PROCESSOR ----------", true);

			$table = new TableHelper();
			$table->addHeader(['Param/Flag', 'Definition', 'Default Value']);
			$table->addRow(['<dir="folder">', "Directorio a procesar", 'no default, required parameter',]);
			$table->addRow(['[sep="separator"]', "comma|tab|semicolon", "comma",]);

			$this->getPrinter()
			     ->newline();
			$this->getPrinter()
			     ->rawOutput($table->getFormattedTable(new ColorOutputFilter()));
			$this->getPrinter()
			     ->newline();

			exit();
		}

		$dir = $this->getParam('dir');
		if (!is_dir($dir)) {
			$this->getPrinter()
			     ->error("$dir No es un directorio.");
			exit();
		}

		// ABRIR EL DIRECTORIO
		$this->util->setD(dir($dir));

		$nfiles = 0;
		$nAlready = 0;

		// POSIBLES VALORES PARA sep
		$seps = ['comma' => ',', 'tab' => "\t", 'semicolon' => ';'];

		// ASIGNAR SEPARADOR
		if (isset($seps[$this->getParam('sep')])) {
			$separator = $seps[$this->getParam('sep') ?? 'comma'];
			$newHeader = "sep=$separator\n";
		} else {
			$this->getPrinter()
			     ->error("Separador no soportado.");
			exit();
		}

		while ($f = $this->util->nextFileName()) {

			if ($this->util->is_dir($f)) continue;

			echo "\n--> $f";

			if ($this->util->getHeadersString($f) == $newHeader) {
				$this->getPrinter()
				     ->error("$f already has the header - Skipped");
				++$nAlready;
				continue;
			}

			if (!$this->util->prepend($newHeader, $this->util->fullName($f))) {
				echo "\nCan't process {$this->util->fullName($f)} file";
			}

			$this->getPrinter()
			     ->success("done.");
			++$nfiles;

		}

		$this->getPrinter()
		     ->success("$nfiles files headered, $nAlready files skipped", 1);
	}


}