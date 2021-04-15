<?php

namespace App\Command\Csv;

use JetBrains\PhpStorm\NoReturn;
use Minicli\Command\CommandController;

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


	#[NoReturn] public function handle()
	{
		if (!$this->hasParam('dir')) {
			$this->getPrinter()
			     ->success('csv dir="dir" [out][offrow][buflen][step]');
			exit();
		}

		$name = $this->getParam('dir');

		$this->outFName = $this->getParam('out') ?? 'T0';

		$this->offsetRow = $this->getParam('offrow') ?? 2000;

		$this->buffLen = $this->getParam('buflen') ?? 10;

		$this->step =
			$this->getParam('step') ?? 0.002;  // sampling period in seconds

		$this->getPrinter()->success(sprintf("\nDir: %s", $name));

		// print_r($this->getParams());

		$dir = $this->getParam('dir');
		if (!is_dir($dir)) {
			$this->getPrinter()->error("$dir No es un directorio.");
			exit();
		}

		// ABRIR EL DIRECTORIO
		$this->d = dir($dir);

		// ENCONTRAR MIDDLE
		if (!$this->middle = $this->findMiddle()) {
			$this->getPrinter()->error("No se encuentran archivos dn_xxxx.csv");
			exit();
		}

		$this->getPrinter()->success("Serie a procesar: [" .
		                             substr($this->middle, 1) . "]");

		// ENCONTRAR PRIMER D0
		if (!$this->findFirst()) {
			$this->getPrinter()
			     ->error("No se encuentra el archivo d0$this->middle.csv");
			exit();
		}

		//$f = fopen($this->first, 'r');

		// ABRIR ARCHIVOS DE SALIDA
		$o = [];
		for ($layer = 0; $layer <= 3; $layer++) {
			$o[$layer] = fopen($this->getFullName("d{$layer}_out.csv"), 'a');
			ftruncate($o[$layer], 0);
		}

		// COMPILAR TODOS LOS SEGMENTOS DE TODOS LOS LAYERS
		for ($layer = 0; $layer <= 3; $layer++) {
			$this->processSegments($layer, $o[$layer]);
		}

		$this->getPrinter()->display('<< Offsets >>');
		$this->getPrinter()->display(implode(',', $this->offsets));

		/////////////////////////////////////////
		// COMPILACION FINAL
		/////////////////////////////////////////

		// IMPRIMIR Separador CSV y ENCABEZADOS
		$t = fopen($this->getFullName("{$this->outFName}.csv"), 'a');
		ftruncate($t, 0);
		fputs($t, "sep=,\n");
		fputcsv($t, $this->headers);

		// ABRIR 4 ARCHIVOS DE ENTRADA
		for ($n = 0; $n <= 3; $n++) {
			$o[$n] = fopen($this->getFullName("d{$n}_out.csv"), 'r');
		}

		// MERGE LAYERS HORIZONTALLY
		while ($nline = $this->mergeHorizontal($o, $t)) {
			// Mensaje de monitoreo
			if ($nline % 10000 == 0) $this->getPrinter()
			                              ->display("Processing line #$nline...");
		};

		fclose($t);

		$this->getPrinter()->success('---------------------------------');
		$this->getPrinter()->display("Output file: $this->outFName.csv");
		$this->getPrinter()->success('---------------------------------');
	}

	public function findMiddle(): bool|string
	{
		while (false !== ($entry = $this->d->read())) {
			if (is_dir($entry)) continue;
			return substr(basename($entry, 'csv'), 2, 16);
		}
		return false;
	}

	public function findFirst(): bool
	{
		$first = 'd0' . $this->middle . '.csv';

		while (false !== ($entry = $this->d->read())) {
			if (is_dir($entry)) continue;

			if ($entry == $first) {
				$this->first = $this->getFullName($entry);
				return true;
			}
		}
		return false;
	}

	public function getFullName($name): string
	{
		return $this->d->path . '/' . $name;
	}



	public function processSegments(int $layer, $outputFile)
	{
		// POR CADA SEGMENTO...
		for ($segment = 1; ; $segment++) {
			// El primer archivo no lleva indicador de orden (n)
			$trail = ($segment > 1) ? "($segment)" : "";
			$fname = $this->getFullName("d{$layer}{$this->middle}{$trail}.csv");

			$this->getPrinter()->display("Procesando segmento $fname...");

			// PROCESAR UN SEGMENTO
			if (file_exists($fname)) {
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
				$offset = $this->process($f, $outputFile);
				if ($segment == 1) {
					if ($layer == 0) {
						$offset[0] = 0;
					} else {
						array_shift($offset);
					}
					$this->offsets = array_merge($this->offsets, $offset);
				}

			} else {

				// NO HAY MAS SEGMENTOS en el LAYER
				return;  // PASAR AL SIGUIENTE LAYER
			}
		}
	}



	public function mergeLines(array $inputFiles): array|bool
	{
		$l = [];
		for ($layer = 0; $layer <= 3; $layer++) {
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

		// UNIR LAS 4 LINEAS LEIDAS
		return array_merge($l[0],
			$l[1],
			$l[2],
			$l[3]);
	}



	public function offsetRecord(bool|array $line): array
	{
		return array_map(function ($a, $b) {
			return $a - $b;
		},
			$line,
			$this->offsets);
	}

	public function noiseReduction(array $line1): array
	{
		// Inicializar un buffer para el cálculo de medias móviles.
		$buffer = [];
		// AGREGAR LA LINEA AL BUFFER DE FILTRADO
		array_push($buffer, $line1);

		// QUITAR LA LINEA MÁS ANTIGUA
		if (($bufflen = count($buffer)) > $this->buffLen) {
			array_shift($buffer);
		}

		// PROMEDIAR LAS COLUMNAS
		$line2 = [];
		foreach ($line1 as $row => $value) {
			$line2[$row] =
				number_format(array_sum(array_column($buffer, $row)) / $bufflen,
					6);
		}

		// La columna del tiempo no debe promediarse
		$line2[0] = $line1[0];
		return $line2;
	}

	public function process($f, $o): array
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

	public function mergeHorizontal(array $inputFiles, $outputFile): int|bool
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
}