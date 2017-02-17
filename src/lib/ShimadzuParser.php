<?php
	class ShimadzuParser extends Parser {

		public function parseFile($file){
		
			// batch info
			$batch = @reset(explode('.', end(explode('batch', end(explode('/', strtolower($file)))))));						
			if ($batch != (int) $batch){
				$batch = reset(explode('.', end(explode('/', $file))));
			}

			$fileData = array();
			$columnHeader = array();
			$samples = array();
			$compounds = array();			
			$compound = array();
			$measurements = array();

			$lines = file($file);

			try {
				foreach ($lines as $lIdx => $line){

					$lineLowercase = strtolower($line);
					$lineParts = explode("\t", $line);
					
					if ($lineParts[0] == 'ID#'){

						// save previous compound info
						if (!empty($compound)){
							$compounds[] = $compound;
						}

						$compound = array();
						$compound['id'] = $lineParts[1];

					} else if ($lineParts[0] == 'Name'){
						$compound['name'] = clean($lineParts[1]);

					} else if (empty($columnHeader) && strpos($lineLowercase, 'area') >= 1 && strpos($lineLowercase, 'istd') >= 1){ // this must be the header of the compound
						foreach ($lineParts as $hpIdx => $header){
							$columnHeader[$hpIdx] = trim($header);
						}
					} else if ($lineParts[0] != '') { // data

						if (!isset($compound['measurements'])){
							$compound['measurements'] = array();
						}

						$compoundMeasurement = array();
						foreach ($lineParts as $lpIdx => $column){
							if ($columnHeader[$lpIdx]){

								switch($columnHeader[$lpIdx]){

									case 'Data Filename'	: $header = 'file'; break;
									case 'Sample Type'		: $header = 'type'; break;
									case 'Ret. Time'		: $header = 'rt'; break;
									case 'Area'				: $header = 'area'; break;
									case 'ISTD Area'		: $header = 'istd_area'; break;
									case 'ISTD Ret.Time'	: $header = 'istd_rt'; break;
									case 'Date Acquired'	:	// try to reformat datetime
																$column = $this->parseDate(trim($column));
																$header = 'datetime'; 
															  break;
									case 'S/N'				: $header = 'sn'; break; // not used for now
									default : $header = $columnHeader[$lpIdx];
								}

								$value = trim($column);
								if ($value == '-----'){ $value = ''; }

								$compoundMeasurement[$header] = $value;
							}
						}

						if (!empty($compoundMeasurement)){
							// set correct decimal sep
							$compoundMeasurement['sn'] = $compoundMeasurement['sn']; //reformat_value($compoundMeasurement['sn']);
							$compoundMeasurement['rt'] = str_replace(',','.',$compoundMeasurement['rt']); //reformat_value($compoundMeasurement['rt']);
							$compoundMeasurement['area'] = reformat_value($compoundMeasurement['area']);
							$compoundMeasurement['istd_rt'] = str_replace(',','.',$compoundMeasurement['istd_rt']); //reformat_value($compoundMeasurement['istd_rt']);
							$compoundMeasurement['istd_area'] = reformat_value($compoundMeasurement['istd_area']);					
						
							$compound['measurements'][] = $compoundMeasurement;
						}
					} else {
						//ignore empty lines
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
				foreach ($compounds as $cIdx => $compound){
					foreach ($compound['measurements'] as $cmIdx => $measurement) {
						if ($measurement['area'] != '' && ($measurement['area'] == $measurement['istd_area'])){ // then it must be a istd
							$istds[$measurement['rt'] . '|' . $measurement['area']] = $compound['name'];
						}

					}
				}

				// match compound with istd
				$compoundIstd = array();
				foreach ($compounds as $cIdx => $compound){
					$compoundIstd[$compound['name']] = array(); 
					foreach ($compound['measurements'] as $cmIdx => $measurement) {
						if ($measurement['istd_rt'] && $measurement['istd_area']){
							$compoundIstd[$compound['name']] = $istds[$measurement['istd_rt'] . '|' . $measurement['istd_area']];
						}
					}
				}

				foreach ($compounds as $cIdx => $compound){

					foreach ($compound['measurements'] as $cmIdx => $measurement) {

						// save sample info
						$sampleName = explode(".", $measurement['file'])[0];

						// sample type
						$sampleType = 'sample';
						$calNo = '';
						if (strpos(strtolower($sampleName), 'blank_') >= 1){ $sampleType = 'blank'; }
						if (strpos(strtolower($sampleName), 'qc_') >= 1){ $sampleType = 'qc'; }
						for ($intCal = 0; $intCal <= 15; $intCal++) {
							if (strpos(strtolower($sampleName), 'cal' . $intCal . '_') >= 1){ 
								$sampleType = 'cal'; 
								$calNo = $intCal;	
							}
						}

						$samples[$sampleName] = array(
										'name'=>$sampleName,
										'file'=>$measurement['file'],
										'type'=>$sampleType,
										'batch'=>$batch,
										'datetime'=>$measurement['datetime'],
										'calno'=>$calNo
									);

						// save measurement
						if (!isset($measurements[$sampleName])){ $measurements[$sampleName] = array(); }
											
						// keep track of sample measurements
						$measurements[$sampleName][] = array(
															'compound' => array(
																'name'=>$compound['name'],
																'rt'=>$measurement['rt'],
																'area'=>$measurement['area']
															),
															'istd' => array(
																'name'=>$compoundIstd[$compound['name']],
																'rt'=>$measurement['istd_rt'],
																'area'=>$measurement['istd_area']
															)
														);
					}
				}
			} catch(Exception $e){

				$this->setError('Building raw data array failed for file '.$file.' . Error: '. $e->getMessage());

			}	

			//sort and construct raw data array
			try {
				
				$acquisitionDate = array();
				foreach ($samples as $key => $row) {
				    $acquisitionDate[$key]  = $row['datetime'];
				}
				array_multisort($acquisitionDate, SORT_ASC, $samples);			

				$sampleOrderCount = 1;
				foreach ($samples as $sIdx => $sample){
					$samples[$sIdx]['order'] = $sampleOrderCount;
					$sampleOrderCount++;
				}

				$batchSort = array();
				$orderSort = array();
				foreach ($samples as $key => $row) {
				    $batchSort[$key]  = $row['batch'];
				    $orderSort[$key]  = $row['order'];
				}
				array_multisort($batchSort, SORT_ASC, $orderSort, SORT_ASC, $samples);			


				foreach ($samples as $sIdx => $sample){
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