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
							$aliquotName = strtolower($lineParts[$columnHeader['Sample Name']]);
							$compoundName = $lineParts[$columnHeader['Component Name']];

							if (strpos($compoundName, '_ISTD') || strpos($compoundName, '-ISTD')) {
								
								// ignore ISTD exported lines, they are part of the compound export

							} else {

								$datetime = $lineParts[$columnHeader['Acquisition Date & Time']];

								if (!isset($samples[$aliquotName])){
									$samples[$aliquotName] = array();
									$samples[$aliquotName]['Sample'] = array();
									$samples[$aliquotName]['Measurements'] = array();
								}

								$samples[$aliquotName]['Sample']['batch'] = $batch;

								$fileParts = explode(DIRECTORY_SEPARATOR,$file);
								$samples[$aliquotName]['Sample']['file'] = end($fileParts);

								$samples[$aliquotName]['Sample']['order'] = '';
								$samples[$aliquotName]['Sample']['datetime'] = $this->parseDate($datetime);

								// sample type
								$sampleType = 'sample';
								$calno = '';
								if (strpos($aliquotName, 'blank_') >= 1){ $sampleType = 'blank'; }
								if (strpos($aliquotName, 'qc_') >= 1){ $sampleType = 'qc'; }
								if (strpos($aliquotName, 'sst_') >= 1){ $sampleType = 'sst'; }
								for ($intCal = 0; $intCal <= 15; $intCal++) {
									if (strpos($aliquotName, 'cal' . $intCal . '_') >= 1){
										$sampleType = 'cal'; 
										$calno = $intCal;	
									}
								}

                                $sampleName = substr($aliquotName, 0, strpos($aliquotName, ($sampleType . "_")));
                                if ($sampleName == "") { // must be a sample
                                    $sampleName = substr($aliquotName, 0 , -3);
                                }
								$samples[$aliquotName]['Sample']['name'] = $sampleName;
								$samples[$aliquotName]['Sample']['aliquot'] = $aliquotName;

                                $samples[$aliquotName]['Sample']['injection'] = (int) $aliquotName[-1];
                                $samples[$aliquotName]['Sample']['replicate'] = $aliquotName[-2];

								$samples[$aliquotName]['Sample']['type'] = $sampleType;
								$samples[$aliquotName]['Sample']['calno'] = $calno;

								$samples[$aliquotName]['Measurements'][] = array(
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

				$this->setError('Final construction of raw data array fails for file '.$file.' . Error: '. $e->getMessage());

			}

			return $fileData;
		}
	}

?>