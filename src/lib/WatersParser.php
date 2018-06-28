<?php

	class WatersParser extends Parser {
		
		public function parseFile($file){

			$lines = file($file);
			$compounds = array();
			$compound = array();
			$samples = array();
			$columnHeader = array();

			$batch = @reset(explode('.', end(explode('batch', end(explode('/', strtolower($file)))))));			
			if ($batch != (int) $batch){
				$batch = reset(explode('.', end(explode('/', $file))));
			}

			try {
				foreach ($lines as $lIdx => $line){

					if (trim($line)){
						$lineParts = explode("\t", $line);

						if (substr(strtolower($lineParts[0]), 0, 8) == 'compound'){

							// save previous compound info
							if (!empty($compound)){
								$compounds[] = $compound;
							}
							
							// new compound starts
							$compound = array();

							$compoundNameParts = explode(":",$lineParts[0]);
							$compoundName = trim(end($compoundNameParts));
							$compound['name'] = $compoundName;
						} else if (@trim($lineParts[1]) == '#' && empty($columnHeader)){
							// we found the header
							foreach ($lineParts as $hpIdx => $header){
								$columnHeader[$hpIdx] = trim($header);
							}
						} else if (isset($lineParts[1]) && $lineParts[0] == $lineParts[1] ) { // data

							if (!isset($compound['measurements'])){
								$compound['measurements'] = array();
							}

							$compoundMeasurement = array();
							foreach ($lineParts as $lpIdx => $column){
								if ($columnHeader[$lpIdx]){

									switch($columnHeader[$lpIdx]){
										case '#'	: $header = 'order'; break;
										case 'Name'	: $header = 'file'; break;
										case 'Type'		: $header = 'type'; break;
										case 'RT'		: $header = 'rt'; break;
										case 'Area'				: $header = 'area'; break;
										case 'IS Area'		: $header = 'istd_area'; break;
										case 'Response'		: $header = 'response'; break;
										default : $header = $columnHeader[$lpIdx];
									}

									$value = trim($column);

									$compoundMeasurement[$header] = $value;
								}
							}

							$compound['measurements'][] = $compoundMeasurement;
						} else {
							//ignore empty lines
						}				
					}
				}
			} catch(Exception $e){

				$this->setError('File parsing failed of file '.$file.' . Error: '. $e->getMessage());

			}	

			// save last compound info
			if (!empty($compound)){
				$compounds[] = $compound;
			}

			try {
				$istds = array();
				$istdDetails = array();
				foreach ($compounds as $cIdx => $compound){
					foreach ($compound['measurements'] as $cmIdx => $measurement) {
						if ($measurement['area'] != '' && ($measurement['area'] == $measurement['response'])){ // then it must be a istd
							if (!isset($istds[$compound['name']])){ $istds[$compound['name']] = array(); }
							$istds[$compound['name']][] = array('rt'=>$measurement['rt'], 'area'=>$measurement['area']);

							if (!isset($istdDetails[$cmIdx])){ $istds[$cmIdx] = array(); }
							$istdDetails[$compound['name']][$measurement['area']] = $measurement['rt'];
						}
					}
				}

				// match compound with istd
				$compoundIstd = array();
				foreach ($compounds as $cIdx => $compound){
					foreach ($compound['measurements'] as $cmIdx => $measurement) {
						if ($measurement['istd_area']){

							foreach ($istds as $istdIdx => $rtAreas){
								foreach ($rtAreas as $rtAreaIdx => $rtArea){
									if ($rtArea['area'] == $measurement['istd_area']){
										$compoundIstd[$compound['name']] = $istdIdx;
									}
								}
							}
						}
					}
				}

				foreach ($compounds as $cIdx => $compound){

					foreach ($compound['measurements'] as $cmIdx => $measurement) {

						// save aliquot info
						$aliquotName = strtolower(explode(".", $measurement['file'])[0]);

						// sample type
						$sampleType = 'sample';
						$calNo = '';
						if (strpos($aliquotName, 'blank_') >= 1){ $sampleType = 'blank'; }
						if (strpos($aliquotName, 'qc_') >= 1){ $sampleType = 'qc'; }
                        if (strpos($aliquotName, 'sst_') >= 1){ $sampleType = 'sst'; }
						for ($intCal = 0; $intCal <= 15; $intCal++) {
							if (strpos($aliquotName, 'cal' . $intCal . '_') >= 1){
								$sampleType = 'cal'; 
								$calNo = $intCal;	
							}
						}

                        if ($sampleType == 'cal'){
                            $sampleName = substr($aliquotName, 0, strpos($aliquotName, ($sampleType . $calNo . "_")));
                        } else {
						    $sampleName = substr($aliquotName, 0, strpos($aliquotName, ($sampleType . "_")));
						}
						if ($sampleName == "") { // must be a sample
						    $sampleName = substr($aliquotName, 0 , -3);
						}

						if ($sampleType != 'sample'){
						    $sampleName = $sampleName . "_" . $sampleType;
						    if ($sampleType == 'cal'){
						        $sampleName = $sampleName . $calNo;
						    }
						}


                        $injection = (int) $aliquotName[-1];
                        $replicate = $aliquotName[-2];

						$samples[$sampleName] = array(
										'name'=>$sampleName,
										'aliquot'=>$aliquotName,
										'file'=>$measurement['file'],
										'type'=>$sampleType,
										'batch'=>$batch,
										'order'=>$measurement['order'],
										'calno'=>$calNo,
                                        'injection'=>$injection,
                                        'replicate'=>$replicate
									);

						// save measurement
						if (!isset($measurements[$sampleName])){ $measurements[$sampleName] = array(); }
											
						// keep track of sample measurements
						if ($measurement['area'] != $measurement['response']){
							$measurements[$sampleName][] = array(
																'compound' => array(
																	'name'=>$compound['name'],
																	'rt'=>$measurement['rt'],
																	'area'=>$measurement['area']
																),
																'istd' => array(
																	'name'=>$compound['name'] . "_ISTD",
																	'rt'=> 0,
																	'area'=>$measurement['istd_area']
																)
															);
						}
					}
				}
			} catch(Exception $e){

				$this->setError('Building raw data array failed for file '.$file.' . Error: '. $e->getMessage());

			}	


			//sort and construct raw data array
			try {
				
				$batchSort = array();
				$orderSort = array();
				foreach ($samples as $key => $row) {
				    $batchSort[$key]  = $row['batch'];
				    $orderSort[$key]  = $row['order'];
				}
				array_multisort($batchSort, SORT_ASC, $orderSort, SORT_ASC, $samples);			

				foreach ($samples as $sIdx => $sample){

					// waters doesn't have a clear date field, so we make one based on batch and order
					$seconds = (100000 * $sample['batch']) + $sample['order'];

					$sample['datetime'] = date('Y-m-d H:i:s', $seconds);

					$fileData[] = array(
							'Sample'=>$sample,
							'Measurements'=>$measurements[$sample['name']]
						);
				}

			} catch(Exception $e){

				$this->setError('Final construction of raw data array failes for file '.$file.' . Error: '. $e->getMessage());

			}
			return $fileData;
		}
	}

?>