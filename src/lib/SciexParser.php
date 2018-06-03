<?php

	class SciexParser extends Parser {
		
		public function parseFile($file){

			$lines = file($file);
			$columnHeader = array();
			$fileData = array();
			
			$batch = @reset(explode('.', end(explode('batch', end(explode('/', strtolower($file)))))));

			if ($batch != (int) $batch){
				$batch = reset(explode('.', end(explode('/', $file))));
			}
			
			$samples = array();

			try {
				foreach ($lines as $lIdx => $line){

					if (trim($line)){
						$lineParts = explode("\t", trim($line));

						if (strpos($line, 'IS Name') > 0 && strpos($line, 'Component Name') > 0 && strpos($line, 'Retention Time') > 0){
							// header
							$columnHeader = array_flip($lineParts);
						} else {
							// data
							$sampleName = $lineParts[$columnHeader['Sample Name']];
							$compoundName = $lineParts[$columnHeader['Component Name']];

							if (strpos($compoundName, '_ISTD') || strpos($compoundName, '-ISTD')) {
								
								// ignore ISTD exported lines, they are part of the compound export

							} else {

								$datetime = $lineParts[$columnHeader['Acquisition Date & Time']];

								if (!isset($samples[$sampleName])){
									$samples[$sampleName] = array();
									$samples[$sampleName]['Sample'] = array();
									$samples[$sampleName]['Measurements'] = array();
								}

								$samples[$sampleName]['Sample']['name'] = $sampleName;
								$samples[$sampleName]['Sample']['batch'] = $batch;

								$fileParts = explode(DIRECTORY_SEPARATOR,$file);
								$samples[$sampleName]['Sample']['file'] = end($fileParts);

								// TODO
								$samples[$sampleName]['Sample']['order'] = '';
								$samples[$sampleName]['Sample']['datetime'] = $this->parseDate($datetime);

								// sample type
								$sampleType = 'sample';
								$calno = '';
								if (strpos(strtolower($sampleName), 'blank_') >= 1){ $sampleType = 'blank'; }
								if (strpos(strtolower($sampleName), 'qc_') >= 1){ $sampleType = 'qc'; }
								if (strpos(strtolower($sampleName), 'sst_') >= 1){ $sampleType = 'sst'; }
								for ($intCal = 0; $intCal <= 15; $intCal++) {
									if (strpos(strtolower($sampleName), 'cal' . $intCal . '_') >= 1){ 
										$sampleType = 'cal'; 
										$calno = $intCal;	
									}
								}

                                $samples[$sampleName]['Sample']['injection'] = (int) $sampleName[-1];
                                $samples[$sampleName]['Sample']['replicate'] = $sampleName[-2];

								$samples[$sampleName]['Sample']['type'] = $sampleType;	
								$samples[$sampleName]['Sample']['calno'] = $calno;					

								$samples[$sampleName]['Measurements'][] = array(
									'compound'=>array(
													'name'=>$lineParts[$columnHeader['Component Name']],
													'area'=>($lineParts[$columnHeader['Area']] != 'N/A') ? $lineParts[$columnHeader['Area']] : '',
													'rt'=>($lineParts[$columnHeader['Retention Time']] != 'N/A') ? $lineParts[$columnHeader['Retention Time']] : '',
												),
									'istd'=>	array(
													'name'=>$lineParts[$columnHeader['IS Name']],
													'area'=>($lineParts[$columnHeader['IS Area']]) != 'N/A' ? $lineParts[$columnHeader['IS Area']] : '',
													'rt'=>($lineParts[$columnHeader['IS Retention Time']] != 'N/A') ? $lineParts[$columnHeader['IS Retention Time']] : '',
												),								
								);
							}
						}
					}
				}
			} catch(Exception $e){

				$this->setError('File parsing failed of file '.$file.' . Error: '. $e->getMessage());

			}	

			//sort and construct raw data array
			try {
				
				$batchSort = array();
				$dateSort = array();
				foreach ($samples as $key => $row) {
				    $batchSort[$key]  = $row['Sample']['batch'];
				    $dateSort[$key]  = $row['Sample']['datetime'];
				}
				array_multisort($batchSort, SORT_ASC, $dateSort, SORT_ASC, $samples);			

				$orderNumber = 0;
				foreach ($samples as $sIdx => $sample){

					$sample['Sample']['order'] = $orderNumber;
					$orderNumber++;

					$fileData[] = $sample;	
				}

			} catch(Exception $e){

				$this->setError('Final construction of raw data array failes for file '.$file.' . Error: '. $e->getMessage());

			}

			return $fileData;
		}
	}

?>