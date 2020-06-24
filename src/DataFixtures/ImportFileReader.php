<?php

namespace App\DataFixtures;

use Psr\Log\LoggerInterface;

class ImportFileReader {
	private $logger;
	public function __construct(LoggerInterface $loggerInterface) {
		$this->logger = $loggerInterface;
	}

	/**
	 *
	 * @param string $path
	 *        	Path to file
	 * @param int $offset
	 *        	Number of rows from the top to read as common header in two columns: Key<tab>Value (0 by default)
	 * @return array
	 */
	function GetRows(string $path, int $offset = 0): array {
		$result = array ();
		$this->logger->info ( "Trying to open the file ". $path );
		// check if file exists
		if (! file_exists ( $path )) {
			$this->logger->warning ( "The requested file doesn't exist." );
			return $result;
		}

		// Open the file to read from.
		$readText = file ( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if (empty ( $readText )) {
			$this->logger->error ( "Error opening the file" );
			return $result;
		}
		$this->logger->info ( "Opening '" . $path . "'...: OK" );

		// $readText = array_map("utf8_encode", $readText);
		// $parameters = array();
		$headers = array ();
		$firstLine = true;
		$lineCount = 0;
		foreach ( $readText as $line ) {
			$this->logger->info ( $line );
			$lineCount ++;
			if ($lineCount <= $offset) {
				$param = explode ( "\t", $line );
				if (! empty ( $param [0] ) || (count ( $param ) < 1)) {
					continue;
				}
				$key = $param [0];
				// $value = empty($param[1]) ? "unknown " . $lineCount : $param[1];
				// $parameters[$key] = $value;
				continue;
			}
			// if first line, parse headers
			if ($firstLine) {
				$headers = explode ( "\t", $line );
				for($i = 0; $i < count ( $headers ); $i ++) {
					$headers [$i] = empty ( $headers [$i] ) ? "unknown " . $i : $headers [$i];
				}
				$firstLine = false;
				$this->logger->info ( "Headers in first line: [" . implode ( ",", $headers ) . "]" );

				continue;
			}
			// else try to parse line
			$fields = explode ( "\t", $line );
			if (count ( $fields ) < 1) {
				$this->logger->notice ( "Line " . $lineCount . " is empty, will be ignored" );
				continue;
			}
			if ($this->isAllEmpty ( $fields )) {
				$this->logger->notice ( " Line " . $lineCount . " has only empty fields, will be ignored" );
				continue;
			}
			$lineParams = array ();
			for($i = 0; $i < count ( $fields ); $i ++) {
				$key = count ( $headers ) > $i && ! empty ( $headers [$i] ) ? $headers [$i] : "unknown " . $i;
				$lineParams [$key] = $fields [$i];
			}
			array_push ( $result, $lineParams );
		}
		if (count ( $result ) > 0)
			$this->logger->info ( " " . count ( $result ) . " lines will be processed." );
		else
			$this->logger->warning ( " There's no valid lines in file, no lines will be processed." );

		// var_dump($result);
		return $result;
	}

	/**
	 *
	 * @param array $array
	 * @return bool
	 */
	private function isAllEmpty(array $array): bool {
		$isEmpty = true;
		foreach ( $array as $item ) {
			$isEmpty &= empty ( $item );
		}
		return $isEmpty;
	}
}
?>