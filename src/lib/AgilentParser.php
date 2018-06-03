<?php

	class AgilentParser extends Parser {
		
		public function parseFile($file){
			
			$fileData = array();
			$fileHeader = array();
			$fileHeader[0] = array();
			$fileHeader[1] = array();

			// batch info
			$batch = @reset(explode('.', end(explode('batch', end(explode('/', strtolower($file)))))));			
			if ($batch != (int) $batch){
				$batch = reset(explode('.', end(explode('/', $file))));
			}
			
			$lines = file($file);
			foreach ($lines as $lIdx => $line){	
				
				$line = str_replace(' Results', '', $line);
				$lineParts = explode("\t", $line);
				$previousCompound = null;
							
				if ($lineParts[0] == 'Sample'){ // first header line
					foreach ($lineParts as $hlIdx => $hlVar){
						$hlVar = trim($hlVar);
						if ($hlVar == ''){
							if ($hlIdx <= 6){
								$hlVar = 'Sample';
							} else {
								$hlVar = $lineParts[$hlIdx-1];
							}
						}
						$fileHeader[0][$hlIdx] = $hlVar;
					}
				} else if ($lineParts[2] == 'Name'){ // second header line
					foreach ($lineParts as $hlIdx => $hlVar){
						$hlVar = trim($hlVar);
						$fileHeader[1][$hlIdx] = $hlVar;
					}
				} else { // consider this as data lines

					$fileDataRow = array();
					foreach ($lineParts as $hlIdx => $hlVar){
						$hlVar = trim($hlVar);

							// check if this is the filename, then parse it for meta info
							if ($fileHeader[0][$hlIdx] == 'Sample' && $fileHeader[1][$hlIdx] == 'Name'){

								// sample type
								$sampleType = 'sample';
								if (strpos(strtolower($hlVar), 'blank_') >= 1){ $sampleType = 'blank'; }
								if (strpos(strtolower($hlVar), 'qc_') >= 1){ $sampleType = 'qc'; }
								if (strpos(strtolower($hlVar), 'sst_') >= 1){ $sampleType = 'sst'; }

								$fileDataRow[$fileHeader[0][$hlIdx]]['calno'] = '';	
								for ($intCal = 0; $intCal <= 15; $intCal++) {
									if (strpos(strtolower($hlVar), 'cal' . $intCal . '_') >= 1){ 
										$sampleType = 'cal'; 
										$fileDataRow[$fileHeader[0][$hlIdx]]['calno'] = $intCal;	
									}
								}

								$fileDataRow[$fileHeader[0][$hlIdx]]['aliquot'] = strtolower($hlVar); // add aliquot name

                                $sampleName = substr($hlVar, 0, strpos(strtolower($hlVar), ($sampleType . "_")));
                                if ($sampleName == "") { // must be a sample
                                    $sampleName = substr(strtolower($hlVar), 0 , -3);
                                }
                                $fileDataRow[$fileHeader[0][$hlIdx]]['name'] = $sampleName;

                                $fileDataRow[$fileHeader[0][$hlIdx]]['injection'] = (int) $fileDataRow[$fileHeader[0][$hlIdx]]['aliquot'][-1];
                                $fileDataRow[$fileHeader[0][$hlIdx]]['replicate'] = $fileDataRow[$fileHeader[0][$hlIdx]]['aliquot'][-2];
							
								$fileDataRow[$fileHeader[0][$hlIdx]]['type'] = $sampleType;	
								$fileDataRow[$fileHeader[0][$hlIdx]]['batch'] = $batch;	
								$fileDataRow[$fileHeader[0][$hlIdx]]['order'] = $lIdx - 1;	

							
							} else if ($hlIdx >= 7) {

								if (!isset($fileDataRow['Measurements'])){
									$fileDataRow['Measurements'] = array();
								}

								if ($fileHeader[1][$hlIdx] == 'RT'){
									$previousRT = str_replace(',','.',$hlVar);
								} else if ($fileHeader[1][$hlIdx] == 'Area'){

									$thisCompound = array(
										'name'=>$fileHeader[0][$hlIdx],
										'area'=>str_replace(',','.',$hlVar),
										'rt'=>$previousRT
									);
									
									if (isset($previousCompound) && $previousCompound != null && strpos(strtolower($thisCompound['name']), '(istd)') >= 1){

										$measurement = array_merge(array('compound'=>$previousCompound),array('istd'=>$thisCompound));
//										print($measurement);
//										exit();
										
										$fileDataRow['Measurements'][] = $measurement; // add as data row
										
										$previousCompound = null;

									} else if ($previousCompound == null) {
										$previousCompound = array(
											'name'=>$fileHeader[0][$hlIdx],
											'area'=>str_replace(',','.',$hlVar),
											'rt'=>$previousRT
										);
									} else {
										// this means we skipped a column !
										$previousCompound = $thisCompound;
									}
								}
							} else if ($hlVar != '') {
								if ($fileHeader[1][$hlIdx] == 'Acq. Date-Time'){

									$correctedDateTime = $this->parseDate($hlVar);
									if ($correctedDateTime != $hlVar){
										$fileDataRow[$fileHeader[0][$hlIdx]]['datetime'] = $correctedDateTime;
									} else { // it seems the date is unsupported
										$fileDataRow[$fileHeader[0][$hlIdx]]['datetime'] = $hlVar;
										$this->setError('Agilent parser found a date which could not be corrected ('.$hlVar.')');
									}
									
								} else if ($fileHeader[1][$hlIdx] == 'Data File'){
									$fileDataRow[$fileHeader[0][$hlIdx]]['file'] = $hlVar;

								} else {
									if (!isset($fileDataRow[$fileHeader[0][$hlIdx]][strtolower($fileHeader[1][$hlIdx])])){
										$fileDataRow[$fileHeader[0][$hlIdx]][strtolower($fileHeader[1][$hlIdx])] = $hlVar;
									}
								}
								
							}
					}
					$fileData[] = $fileDataRow;
				}
			}
			print '<pre>'; print_r($fileData); print '</pre>';
			return $fileData;
		}
	}
?>