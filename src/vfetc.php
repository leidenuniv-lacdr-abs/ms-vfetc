#!/usr/bin/env php

<?php

	/* MS Vendor feature export converter */

	ini_set("memory_limit","2048M");

	mb_internal_encoding('UTF-8');

	require_once dirname(__FILE__) . '/lib/AgilentParser.php';
	require_once dirname(__FILE__) . '/lib/ShimadzuParser.php';
	require_once dirname(__FILE__) . '/lib/WatersParser.php';
	require_once dirname(__FILE__) . '/lib/SciexParser.php';

	ini_set('auto_detect_line_endings',TRUE);
	date_default_timezone_set('Europe/Amsterdam');	

	// read in parameters
	if (isset($argv)){
		parse_str(implode('&', array_slice($argv, 1)), $_GET);
	}

	// check for required input parameters
	if (!isset($_GET['files']) || !isset($_GET['outputfile'])){
		print("Incorrect input parameters");
		exit(1);
	}

	$files = explode(',',$_GET['files']);
	$outputfile = $_GET['outputfile'];

	foreach ($files as $key => $file) {
		print("\nAdding file: " . $file);
	}
	print("\nSettings output path to: " . $outputfile . "\n");


	$parsers = array();
	$parserDetect = new ParserDetect();
	foreach ($files as $fIdx => $file){ 

		// filter out BOM
		file_put_contents($file, remove_utf8_bom(file_get_contents($file)));

		$parser = $parserDetect->getParser($file); 
		if ($parser != null){
			$parserClass = get_class($parser);
			if (!isset($parsers[$parserClass])){
				$parsers[$parserClass] = $parser;
			} 
			
			$parsers[$parserClass]->addFile($file);
		} else {
			print('Parser detection failed');
			exit(1);			
		}
	}

	foreach ($parsers as $pIdx => $parser){
		
		if (!$parser->hasErrors()){ // no errors > write results to file
			file_put_contents($outputfile, $parser->getDataAsTsv()); 			
		} else { // errors > display them
			print_r($parser->getErrors()); 
			exit(1);			
		}
	}

	class ParserDetect {

		/**
		 * Try to detect which parser to use
		 **/
		public function getParser($file){
		
			$parser = null;

			// all files are expected to be text, so start reading the lines
			$lines = file($file);

			foreach ($lines as $lIdx => $line){

				if (strpos($line, 'Compound Summary Report') > 0){
					$parser = new WatersParser();
					print("Detected Waters file\n");
					break;
				}

				if (strlen($line) >= 3 && substr($line, 0, 3) == 'ID#' && substr($lines[($lIdx+1)], 0, 4) == 'Name'){
					$parser = new ShimadzuParser();
					print("Detected Shimadzu file\n");
					break;
				}

				if (strpos($line, 'IS Name') > 0 && strpos($line, 'Component Name') > 0 && strpos($line, 'Retention Time') > 0){
					$parser = new SciexParser();
					print("Detected Sciex file\n");
					break;
				}

				if (strpos($line, 'Acq. Date-Time') > 0){
					$parser = new AgilentParser();
					print("Detected Agilent file\n");
					break;
				}

				if ($lIdx >= 10){ break; } // limit the #lines to read for detection
			}

			return $parser;
		}

	}

	class Parser {

		public $files = array();
		public $data = array();
		public $compounds = array();
		public $errors = array();
		public $tsv = '';
		public $tsv4r = '';

		/**
		 * Retrieve raw data
		 **/		
		public function getRawData(){
			
			if (empty($this->data)){ // parse files, no data found
				$this->parseFiles($this->files);
			}
			
			return $this->data;
		}

		/**
		 * Retrieve raw data as JSON
		 **/		
		public function getDataAsJson(){
			return json_encode($this->getRawData());
		}

		/**
		 * Retrieve data a tsv
		 **/ 
		public function getDataAsTsv($header = true){
			if ($this->tsv == ''){

				$tsvHeader = array();
				$tsvHeader[] = 'sample';
				$tsvHeader[] = 'type';
                $tsvHeader[] = 'injection';
                $tsvHeader[] = 'replicate';
				$tsvHeader[] = 'batch';
				$tsvHeader[] = 'order';
				$tsvHeader[] = 'datetime';
				$tsvHeader[] = 'compound';
				$tsvHeader[] = 'rt';				
				$tsvHeader[] = 'area';	
				$tsvHeader[] = 'compound_is';											
				$tsvHeader[] = 'rt_is';
				$tsvHeader[] = 'area_is';
				$this->tsv .= implode("\t", $tsvHeader) . "\n"; 


				$data = $this->getRawData();
				$compounds = $this->getCompounds();

				try {
					foreach ($data as $file => $lines){
						foreach ($lines as $lIdx => $line){
							foreach ($compounds as $compound => $istd){
								foreach ($line['Measurements'] as $mlIdx => $measurement){
									if (($measurement['compound']['name'] == $compound) && ($measurement['istd']['name'] == $istd)){
										$tsvLine = array();
										$tsvLine[] = $line['Sample']['name'];
										$tsvLine[] = $line['Sample']['type'];
                                        $tsvLine[] = $line['Sample']['injection'];
                                        $tsvLine[] = $line['Sample']['replicate'];
										$tsvLine[] = $line['Sample']['batch'];
										$tsvLine[] = $line['Sample']['order'];
										$tsvLine[] = $line['Sample']['datetime'];
										$tsvLine[] = $compound;
										$tsvLine[] = $measurement['compound']['rt'];
										$tsvLine[] = $measurement['compound']['area'];
										$tsvLine[] = $istd;
										$tsvLine[] = $measurement['istd']['rt'];
										$tsvLine[] = $measurement['istd']['area'];
										
										$this->tsv .= implode("\t", $tsvLine) . "\n";										
									}
								}							
							}
						}
					}
				} catch(Exception $e){

					$this->setError('Unable to create tsv from raw data. Error: '. $e->getMessage());

				}					
			}

			return $this->tsv;
		}

		/**
		 * Retrieve compounds found
		 **/
		public function getCompounds(){
			if (empty($this->compounds)){
				$data = $this->getRawData();

				try {
					foreach ($data as $file => $samples){
						foreach ($samples as $sIdx => $sample){
							foreach ($sample['Measurements'] as $mIdx => $measurement){
								$this->compounds[$measurement['compound']['name']] = $measurement['istd']['name'];
							}
						}
					}
				} catch(Exception $e){

					$this->setError('Unable to retrieve compounds from raw data. Error: '. $e->getMessage());

				}									
			}
			
			ksort($this->compounds);
			return $this->compounds;
		}


		// File handling
		public function addFile($file){
			if (!in_array($file, $this->files)){
				$this->files[] = $file;

				$this->resetData(); // force re-creation of $this->data;
			}
			return true;
		}

		public function getFiles(){
			return $this->files;
		}

								// Error handling
		public function hasErrors(){
			return count($this->errors) ? true : false;
		}

		public function getErrors(){
			return $this->errors;
		}

		public function setError($error = ''){
			if ($error != '' && !in_array($error, $this->getErrors())){
				$this->errors[] = $error;
			}
			return true;
		}

		// after adding/removing files we should re-parse the files
		public function resetData(){
			$this->data = array();
			$this->compounds = array();
			$this->errors = array();
			$this->tsv = '';
		}	

		// Parsing
		public function parseFiles($files){
			foreach ($files as $fIdx => $file){
				$this->data[$file] = $this->parseFile($file);
			}
		}	
		
		public function parseFile($file){
			$fileData = array();
			return $fileData;
		}

		/**
		 * corrects date from dd-mm-yyyy hh:mm or dd/mm/yyyy hh:mm to yyyy-mm-dd hh:mm:ss
		 * so far this works for both the Agilent and Shimadzu parser
		 **/
		public function parseDate($datetime){

			$correctedDateTime = $datetime;

			try {
				// format to yyyy-mm-dd hh:mm:ss
				$datatimeParts = explode(" ", $datetime);
				$dateParts = array();
				
				$date = $datatimeParts[0];
				if (strpos($date, '-') >= 1){
					$dateParts = explode("-", $date);
				} else if (strpos($date, '/') >= 1){
					$dateParts = explode("/", $date);
				}

				if (count($dateParts) == 3){ // fix date looks possible :)
					$date = "";

					// maybe this should be corrected based on the file create date
					$date .= (strlen($dateParts[2]) == 2) ? substr(@date('Y'), 0, 2) . $dateParts[2] : $dateParts[2];
					$date .= "-";

					$date .= (strlen($dateParts[1]) == 1) ? "0" . $dateParts[1] : $dateParts[1];
					$date .= "-";
					
					if (strlen($dateParts[0]) == 1){
						$dateParts[0] = "0" . $dateParts[0];
					}
					$date .= $dateParts[0];
					
					$time = $datatimeParts[1] . ":00";
					if (strlen($time) == 7){ $time = "0" . $time; }

					$correctedDateTime = $date . " " . $time;
				} 

				if ($correctedDateTime == $datetime){
					$this->setError('Unable to format date/time correctly ('.$datetime.')');
				}
			} catch(Exception $e){

				$this->setError('Unable to format data/time correctly ('.$datetime.'). Error: '. $e->getMessage());

			}								

			return $correctedDateTime;			
		}

	}

	function clean($string) {
	   $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.

	   return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
	}	

	function remove_utf8_bom($text){
	    $bom = pack('H*','EFBBBF');
	    $text = preg_replace("/^$bom/", '', $text);
	    return $text;
	}

	// correct use of dots and comma's
	function reformat_value($value){

		//FINDOUT: how to handle ',' used for thousand sep not turning into a decimal sep.

		$value 		= str_replace(',','',$value);		
		/* LEAVE AS IS! 
		$value 		= str_replace(',','.',$value);		
		if (strpos($value, '.')){
			$valueParts = explode('.', $value);
			$decimals	= array_pop($valueParts);
			$value 		= implode($valueParts) . '.' . $decimals;
		}
		*/

		return $value;
	}		
		
?>	