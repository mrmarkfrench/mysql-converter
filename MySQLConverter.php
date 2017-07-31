<?php
namespace MySQLConverter;

/**
 * A fairly quick-and-dirty tool for converting raw output from MySQL into
 * CSV.
 */
class MySQLConverter {
	/**
	 * Convert the specified input file to CSV
	 * 
	 * @param string $input Path to the input file
	 * @param string $output An optional output file (will return an array of
	 * 	CSV data if none is specified, or print string CSV data directly
	 * 	to the output if set to TRUE.
	 */
	public function convert($input, $output=false) {
		// Get the data from the file
		$mysqlData = $this->extractData($input);

		// How long is the first line?
		$lineLen = strlen($mysqlData[0]);

		// Find the column delimiter locations
		$delimiters = $this->findDelimiters($mysqlData[0], true);

		// Fix any rows with newlines in the data
		$mysqlData = $this->fixLineLengths($mysqlData, $delimiters, $lineLen);

		// Force array indexes back to consecutive numbers
		$mysqlData = array_values($mysqlData);

		// Strip off the junk line at the top and bottom of the input
		$mysqlData = array_slice($mysqlData, 1, -1);
		// Strip off the junk line below the column names
		unset($mysqlData[1]);

		// Extract array data from the MySQL output strings
		$csvData = $this->getCsvData($mysqlData, $delimiters, $lineLen);

		// Were we asked to output the result?
		if ($output) {
			if ($output === true) {
				$out = 'php://output';
			}
			$out = fopen($output, 'w');
			foreach ($csvData as $row) {
				fputcsv($out, $row);
			}
		}

		return $csvData;
	}

	///////////////////////////////////////////////////////////////////////
	// PROTECTED /////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////

	/**
	 * Count the number of verts ("|") in a row.
	 * @return int
	 */
	protected function countVerts($row) {
		$count = 0;
		$pos = strpos($row, '|');
		while ($pos !== false) {
			$count++;
			$pos = strpos($row, '|', $pos+1);
		}
		return $count;
	}

	/**
	 * Load the data from the file
	 * 
	 * @input string $input
	 * @return array An array of string MySQL data
	 *
	 * @throws \Exception if an invalid input file path is specified
	 */
	protected function extractData($input) {
		// Valid file?
		if (!is_file($input)) {
			throw new \Exception("Invalid file path {$input} provided.");
		}
		// Get the SQL data from the file
		return file($input);
	}

	/**
	 * Get an individual field from the row of data. Remove the delimiters,
	 * the space padding each delmiter from the data, and any spaces used to
	 * pad the data out to fixed length.
	 * 
	 * @param string $row
	 * @param int    $start The delimiter position before the data
	 * @param int    $end   The delimiter position after the data
	 * @return string
	 */
	protected function extractField($row, $start, $end) {
		$len = ($end - $start) - 3;
		$field = substr($row, $start+2, $len);
		$field = rtrim($field, ' ');
		return $field;
	}
	
	/**
	 * Find the location of column delimiters within a row
	 * 
	 * @param string $row
	 * @param bool   $trashLine
	 * @return array An array of integer delimiter positions
	 */
	protected function findDelimiters($row, $trashLine=false) {
		// Is this one of the decorator lines?
		if ($trashLine) {
			$row = str_replace('+', '|', $row);
		}
		// Find the delimiters
		$delimiters = array();
		// Find the first delimiter
		$pos = strpos($row, '|');
		while ($pos !== false) {
			$delimiters[] = $pos;
			$pos = strpos($row, '|', $pos+1);
		}
		return $delimiters;
	}

	/**
	 * Fix an individual row with an incorrect line length due to newline or
	 * other problematic characters in the data.
	 * 
	 * @param string $row
	 * @param int    $index
	 * @param array  $mysqlData
	 * @param array  $removed   The rows removed to fix other rows
	 * @param int    $lineLen
	 * @param int    $numDelimiters
	 * 
	 * @throws \Exception if the line cannot be fixed
	 */
	protected function fixLineLength($row, $index, &$mysqlData, &$removed, $lineLen, $numDelimiters) {
		$thisLen      = strlen($row);
		$workingIndex = $index;
		$linesAdded   = 0;
		$lineFixed    = false;
		while (($thisLen < $lineLen) && isset($mysqlData[$workingIndex+1])) {
			// The line is too short...
			$workingIndex++;
			// ...so get a copy of the next line...
			$nextLine = $mysqlData[$workingIndex];
			// ...and determine what the total length would be...
			$totalLength = $thisLen + strlen($nextLine);
			// ...and what the total number of delimiters would be...
			$totalDelimiters = $this->countVerts($row.$nextLine);
			// ...check if the next line wouldn't overshoot the mark...
			if (($totalLength <= $lineLen) || ($totalDelimiters <= $numDelimiters)) {
				// ...so tack the next line onto the end of it...
				$row = $row.$mysqlData[$workingIndex];
				// ...and we won't be needing the next line any longer...
				unset($mysqlData[$workingIndex]);
				$removed[$workingIndex] = true;
				// ...but we should note that we added an additional line...
				$linesAdded++;
				// ...and then measure the new length
				$thisLen = strlen($row);

				if (($thisLen + $linesAdded) == $lineLen) {
					// If the length is short by exactly the number of lines added,
					// convert newlines not preceded by a carriage return to include
					// the carriage return, as the issue is almost certainly a
					// difference in line termination between systems.
					$row = preg_replace('/\n(?<!\r)/', "\r\n", $row, $linesAdded);
					$thisLen = strlen($row);
				}

				// We might have added a line that gets us to the wrong length,
				// but which has fixed the number of delimiters in the line. In
				// that case, we should note that the line is effectively fixed.
				// We should only bother to check if the line is not the correct
				// length.
				if ($thisLen != $lineLen) {
					$thisCount     = $this->countVerts($row);
					// Did we find the right number of delimiters?
					if ($thisCount == $numDelimiters) {
						$lineFixed = true;
						// Stop work on this line, we're done.
						break;
					}
				}
			}
		}
		if (($thisLen != $lineLen) && !$lineFixed) {
			throw new \Exception("Unable to fix length of line {$index}. Expected {$lineLen}, got {$thisLen}");
		}
		return $row;
	}

	/**
	 * Fix any rows with an incorrect line length due to a newline character
	 * in the data.
	 * 
	 * @param array $mysqlData
	 * @param array $delimiters The expected field delimiter character locations
	 * @param int   $lineLen
	 * @return array Returns the modified data array
	 */
	protected function fixLineLengths($mysqlData, $delimiters, $lineLen) {
		// How many delimiters do we expect to find?
		$numDelimiters = count($delimiters);
		// Because of difficulties removing items from an array while it is
		// iterated, we should keep track of which items were removed so that
		// we don't attempt to process them.
		$removed = array();

		// Fix any rows that have the wrong length due to newlines in the data
		foreach ($mysqlData as $index => $row) {
			if ($removed[$index]) {
				continue;
			}
			// Make sure all lines end in a newline (so the last line of the file
			// is not a different length, even if it's missing a trailing newline).
			if (substr($row, -1) != "\n") {
				$row .= "\n";
			}
			// Fix this individual row
			$mysqlData[$index] = $this->fixLineLength(
				$row,
				$index,
				$mysqlData,
				$removed,
				$lineLen,
				$numDelimiters);
		}

		return $mysqlData;
	}

	/**
	 * Convert the string data returned from MySQL into an array of CSV
	 * data, broken up by field. Because the data returned by MySQL was string-
	 * padded with spaces, any trailing spaces in the data will be lost.
	 * 
	 * @param array $mysqlData
	 * @param array $delimiters
	 * @param int   $lineLen
	 */
	protected function getCsvData($mysqlData, $delimiters, $lineLen) {
		$output = array();
		foreach ($mysqlData as $index => $row) {
			// Do we need to look up unique delimiter positions for this line?
			if (strlen($row) == $lineLen) {
				$thisDelimiters = $delimiters;
			} else {
				$thisDelimiters = $this->findDelimiters($row);
			}

			$fields  = array();
			for ($i=0; isset($thisDelimiters[$i+1]); $i++) { 
				$fields[] = $this->extractField(
					$row,
					$thisDelimiters[$i],
					$thisDelimiters[$i+1]);
			}
			$output[] = $fields;
		}
		return $output;
	}
}
