<?php

namespace App\Command\Csv;

use Minicli\App;
use JetBrains\PhpStorm\NoReturn;
use Minicli\Command\CommandController;
use Minicli\Output\Helper\TableHelper;
use Minicli\Output\Filter\ColorOutputFilter;

class DefaultController extends CommandController
{
	protected \Directory $d;

	protected string $middle;

	protected string $first;

	protected string $outFName;

	protected array $headers = [];

	protected array $offsets = [];

	protected int $buffLen;

	protected int $offsetRow;

	protected float $step;

	protected int $nLayers;

	protected array $buffer = [];

	protected bool $doTare;

	protected bool  $verbose = false;

	public function boot(App $app)
	{
		parent::boot($app);
		$this->command_map = $app->command_registry->getCommandMap();
	}


	#[NoReturn] public function handle()
	{
		if (!$this->hasParam('dir')) {
			$this->getPrinter()->info("---------- UEI CSV Post Processor ----------", true);

			$table = new TableHelper();
			$table->addHeader(['Param/Flag', 'Definition', 'Default Value']);
			$table->addRow(['<dir="folder">', "Directorio a procesar", 'no default, required parameter']);
			$table->addRow(['[out="name"]', "Nombre archivo salida sin extension", 'OUT_YYYYMMDD_ HMMSS']);
			$table->addRow(['[offrow="row#"]', "Fila para el calculo del offset", '2000']);
			$table->addRow(['[buflen="buff-length"]', "cantidad de filas a promediar", '10']);
			$table->addRow(['[step="period"]', "periodo de muestreo [s]", '0.002']);
			$table->addRow(['[--nofilter]', "No aplicar filtro de reducción de ruido", '']);
			$table->addRow(['[--nooffset]', "No aplicar corrección de offsets", '']);
			$table->addRow(['[--raw]', "Sólo compilar los archivos", '']);
			$table->addRow(['[--verbose]', "Imprimir mensajes de estado del proceso", '']);

			$this->getPrinter()->newline();
			$this->getPrinter()->rawOutput($table->getFormattedTable(new ColorOutputFilter()));
			$this->getPrinter()->newline();

			exit();
		}

		if($this->verbose) print_r($this->getParams());

		$dir = $this->getParam('dir');
		if (!is_dir($dir)) {
			$this->getPrinter()->error("$dir No es un directorio.");
			exit();
		}

		$this->outFName = $this->getParam('out') ?? '';

		$this->offsetRow = $this->getParam('offrow') ?? 2000;

		$this->buffLen = $this->getParam('buflen') ?? 10;

		if ($this->hasFlag('--nofilter') || $this->hasFlag('--raw') ) $this->buffLen = 0;

		$this->doTare = !$this->hasFlag('--nooffset') && !$this->hasFlag('--raw') ;

		$this->step =
			$this->getParam('step') ?? 0.002;  // sampling period in seconds

		$this->getPrinter()->success(sprintf("\nDir: %s", $name));




		// ABRIR EL DIRECTORIO
		$this->d = dir($dir);

		// ENCONTRAR MIDDLE
		if (!$this->middle = $this->findMiddle()) {
			$this->getPrinter()->error("No se encuentran archivos dn_xxxx.csv");
			exit();
		}

		// ASIGNAR OUT FILENAME POR DEFAULT
		if ($this->outFName == '') $this->outFName =
			'OUT_' . substr($this->middle, 1);

		$this->getPrinter()->success("Serie a procesar: [" .
		                             substr($this->middle, 1) . "]");

		// ENCONTRAR PRIMER D0
		if (!$this->findFirst()) {
			$this->getPrinter()
			     ->error("No se encuentra el archivo d0$this->middle.csv");
			exit();
		}

		// NUMERO DE LAYERS
		for ($layer = 0; ; $layer++) {
			if (!file_exists($this->getFullName("d{$layer}{$this->middle}.csv"))) {
				$this->nLayers = $layer;
				$this->getPrinter()->info("nLayers = {$this->nLayers}", true);
				break;
			}
		}

		// ABRIR ARCHIVOS DE SALIDA
		$o = [];
		for ($layer = 0; $layer < $this->nLayers; $layer++) {
			$o[$layer] = fopen($this->getFullName("d{$layer}_out.csv"), 'a');
			ftruncate($o[$layer], 0);
		}

		// COMPILAR TODOS LOS SEGMENTOS DE TODOS LOS LAYERS
		for ($layer = 0; $layer < $this->nLayers; $layer++) {
			$this->processSegments($layer, $o[$layer]);
		}

		$this->getPrinter()->info('<< Offsets >>', true);
		$this->getPrinter()->display(implode(',', $this->offsets));

		/////////////////////////////////////////
		// COMPILACION FINAL
		/////////////////////////////////////////

		// IMPRIMIR Separador CSV y ENCABEZADOS
		$t = fopen($this->getFullName("{$this->outFName}.csv"), 'a');
		ftruncate($t, 0);
		fputs($t, "sep=,\n");
		fputcsv($t, $this->headers);

		// ABRIR nLayers ARCHIVOS DE ENTRADA
		for ($n = 0; $n < $this->nLayers; $n++) {
			$o[$n] = fopen($this->getFullName("d{$n}_out.csv"), 'r');
		}

		// MERGE LAYERS HORIZONTALLY
		while ($nline = $this->mergeHorizontal($o, $t)) {
			// Mensaje de monitoreo
			if ($nline % 10000 == 0) $this->getPrinter()
			                              ->display("Processing line #$nline...");
		};

		fclose($t);

		$this->getPrinter()
		     ->success(" Output file: $this->outFName.csv ", true);
	}

	protected function findMiddle()
	{
		$this->d->rewind();

		while (false !== ($entry = $this->d->read())) {
			if (is_dir($entry)) continue;
			return substr(basename($entry, 'csv'), 2, 16);
		}
		return false;
	}

	protected function findFirst(): bool
	{
		$first = 'd0' . $this->middle . '.csv';

		$this->d->rewind();

		while (false !== ($entry = $this->d->read())) {
			if (is_dir($entry)) continue;

			if ($entry == $first) {
				$this->first = $this->getFullName($entry);
				return true;
			}
		}
		return false;
	}

	protected function getFullName($name): string
	{
		return $this->d->path . '/' . $name;
	}

	protected function processSegments(int $layer, $outputFile)
	{
		// POR CADA SEGMENTO...
		for ($segment = 1; ; $segment++) {

			// El primer archivo no lleva indicador de orden (n)
			$trail = ($segment > 1) ? "($segment)" : "";
			$fname = $this->getFullName("d{$layer}{$this->middle}{$trail}.csv");

			// PROCESAR UN SEGMENTO
			if (!file_exists($fname)) return;  // PASAR AL SIGUIENTE LAYER
			$this->getPrinter()->display("Procesando segmento $fname...");

			$f = fopen($fname, 'r');

			// HEADERS
			$h = fgetcsv($f);
			if ($segment == 1) {
				if ($layer > 0) {
					array_shift($h);
				}
				$this->headers = array_merge($this->headers, $h);
			}
			$this->headers = array_map(function ($a) {
				return substr($a,
					0,
					strpos($a, '(') > 0 ? strpos($a, '(') - 1 : null);
			},
				$this->headers);
			$this->headers[0] = 't[s]';

			// PROCESAR LINEAS
			$offset = $this->processLines($f, $outputFile);
			if ($segment == 1) {
				if ($layer == 0) {
					$offset[0] = 0;
				} else {
					array_shift($offset);
				}
				$this->offsets = array_merge($this->offsets, $offset);
			}
		}
	}

	protected function mergeHorizontal(array $inputFiles, $outputFile)
	{
		static $nline = 0;

		// LEER LINEAS CORRESPONDIENTES DE LOS 4 LAYERS Y UNIRLAS
		if (!($line = $this->mergeLines($inputFiles))) {
			return false;  // no hay más líneas en alguno de los layers
		}

		// RESTAR OFFSETS
		$line1 = $this->offsetRecord($line);

		// APLICAR MEDIA MOVIL
		$line2 = $this->noiseReduction($line1);

		// Imprimir la línea final
		fputcsv($outputFile, $line2);

		return ++$nline;
	}

	protected function processLines($f, $o): array
	{
		$offsets = [];

		$i = 0;

		while ($line = fgetcsv($f)) {
			if ($i == $this->offsetRow) {
				$offsets = $line;
			}
			++$i;
			fputcsv($o, $line);
		}

		return $offsets;
	}

	protected function mergeLines(array $inputFiles)
	{
		$l = [];
		for ($layer = 0; $layer < $this->nLayers; $layer++) {
			if (!($l[$layer] = fgetcsv($inputFiles[$layer]))) {
				return false;
			}

			if ($layer == 0) {
				// LAYER 0: CONVERTIR SEQUENCE A TIME
				$l[0][0] *= $this->step;
			} else {
				// LAYERS 1... ELIMINAR SEQUENCE
				array_shift($l[$layer]); // quitar columna #sequence
			}
		}

		$merged = $l[0];
		for ($layer = 1; $layer < $this->nLayers; $layer++) {
			// UNIR LAS LINEAS LEIDAS
			$merged =  array_merge($merged , $l[$layer]);
		}
		return $merged;
	}

	protected function offsetRecord(array $line): array
	{
		return $this->doTare ?
			array_map(function ($a, $b) { return $a - $b;}, $line, $this->offsets)
			: $line;
	}

	protected function noiseReduction(array $line1): array
	{
		if ($this->buffLen == 0)
			return $line1;

		// AGREGAR LA LINEA AL BUFFER DE FILTRADO
		array_push($this->buffer, $line1);

		// QUITAR LA LINEA MÁS ANTIGUA
		if (($bufflen = count($this->buffer)) > $this->buffLen) {
			array_shift($this->buffer);
		}

		// PROMEDIAR LAS COLUMNAS
		$line2 = [];
		foreach ($line1 as $row => $value) {
			$line2[$row] =
				number_format(array_sum(array_column($this->buffer, $row)) / $bufflen,
					7);
		}

		// La columna del tiempo no debe promediarse
		$line2[0] = $line1[0];

		return $line2;
	}
}