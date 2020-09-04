<?php

namespace Nottingham\Minimization;

class Minimization extends \ExternalModules\AbstractExternalModule
{
	function redcap_data_entry_form( $project_id, $record, $instrument, $event_id )
	{
		// Check that the randomization event/field is defined.
		$randoEvent = $this->getProjectSetting( 'rando-event' );
		$randoField = $this->getProjectSetting( 'rando-field' );
		if ( $randoEvent == '' || $randoField == '' )
		{
			return;
		}

		// Determine whether the randomization field is on the current form.
		$listFields = \REDCap::getDataDictionary( 'array' );
		$infoRandoField = $listFields[$randoField];
		if ( $infoRandoField['form_name'] == $instrument && $randoEvent == $event_id )
		{
			// Get the randomization code for the record (if randomized).
			$randoCode = $this->getRandomization( $record );

			// Determine if randomize button should be displayed.
			$showButton = ( $this->getProjectSetting( 'rando-submit-form' ) == '' );


?>
<script type="text/javascript">
  (function ()
  {
    $( 'tr[sq_id=<?php echo $randoField; ?>] [name=<?php
			echo $randoField; ?>]' ).css( 'display', 'none' )
    $( 'tr[sq_id=<?php echo $randoField; ?>] .choicevert' ).css( 'display', 'none' )
    $( 'tr[sq_id=<?php echo $randoField; ?>] .resetLinkParent' ).css( 'display', 'none' )
    var vRandoDetails = document.createElement( 'div' )
<?php

			if ( $randoCode === false )
			{
				if ( $showButton )
				{

?>
    var vRandoButton = document.createElement( 'button' )
    vRandoButton.className = 'jqbuttonmed ui-button ui-corner-all ui-widget'
    vRandoButton.onclick = function () { return false } // TODO
    vRandoButton.innerHTML = '<span style="vertical-align:middle;color:green;">' +
                             '<i class="fas fa-random"></i> Randomize</span>'
    vRandoDetails.appendChild( vRandoButton )
<?php

				}
				else
				{

?>
    vRandoDetails.innerHTML =
        'The randomization allocation will show here once randomization has been performed.'
<?php

				}
			}
			else
			{

?>
    vRandoDetails.innerText = '<?php echo addslashes( $this->getDescription( $randoCode ) ); ?>'
<?php

			}

?>
    $('tr[sq_id=<?php echo $randoField; ?>] [name=<?php
			echo $randoField; ?>]')[0].before( vRandoDetails )
  })()
</script>
<?php


		}
	}

	function redcap_save_record( $project_id, $record, $instrument )
	{
	}


	function getDescription( $code )
	{
		// Search for the specified randomization code.
		// Once found, return the corresponding description.
		$listAllRandoCodes = $this->getProjectSetting( 'rando-code' );
		$listAllRandoDescs = $this->getProjectSetting( 'rando-desc' );
		foreach ( $listAllRandoCodes as $minMode => $listRandoCodes )
		{
			$listRandoDescs = $listAllRandoDescs[$minMode];
			foreach ( $listRandoCodes as $i => $c )
			{
				if ( $code == $c )
				{
					return $listRandoDescs[$i];
				}
			}
		}
		return null;
	}

	function getRandomization( $recordID )
	{
		// Check that the randomization event/field is defined.
		$randoEvent = $this->getProjectSetting( 'rando-event' );
		$randoField = $this->getProjectSetting( 'rando-field' );
		if ( $randoEvent == '' || $randoField == '' )
		{
			return false;
		}

		// Get the record to check.
		$listRecords = \REDCap::getData( [ 'return_format' => 'array',
		                                   'records' => $recordID,
		                                   'combine_checkbox_values' => true,
		                                   'exportDataAccessGroups' => true ] );

		// Get the randomization code.
		if ( isset( $listRecords[$recordID] ) &&
		     $listRecords[$recordID][$randoEvent][$randoField] != '' )
		{
			return $listRecords[$recordID][$randoEvent][$randoField];
		}
		return false;
	}

	function performRando( $newRecordID )
	{
		// Check that the randomization event/field is defined.
		$randoEvent = $this->getProjectSetting( 'rando-event' );
		$randoField = $this->getProjectSetting( 'rando-field' );
		if ( $randoEvent == '' || $randoField == '' )
		{
			return 'Randomization not enabled. The randomization event and field must be defined.';
		}

		// Get all the records for the project.
		$listRecords = \REDCap::getData( [ 'return_format' => 'array',
		                                   'combine_checkbox_values' => true,
		                                   'exportDataAccessGroups' => true ] );

		// Get the record to randomize.
		if ( ! isset( $listRecords[$newRecordID] ) ||
		     $listRecords[$newRecordID][$randoEvent][$randoField] != '' )
		{
			return 'Randomization already performed for this record.';
		}
		$infoNewRecord = $listRecords[$newRecordID];

		// Remove unrandomized records from the list and perform stratification.
		$randoNum = 1;
		if ( $this->getProjectSetting( 'stratify' ) )
		{
			$listStratEvents = $this->getProjectSetting( 'strat-event' );
			$listStratFields = $this->getProjectSetting( 'strat-field' );
			$listStratValues = [];
			for ( $i = 0; $i < count($listStratEvents); $i++ )
			{
				$stratEvent = $listStratEvents[$i];
				$stratField = $listStratFields[$i];
				if ( $infoNewRecord[$stratEvent][$stratField] == '' )
				{
					return "Stratification variable $stratField missing.";
				}
				$listStratValues[$stratEvent][$stratField] =
						$infoNewRecord[$stratEvent][$stratField];
			}
		}
		foreach ( $listRecords as $recordID => $infoRecord )
		{
			if ( $recordID == $newRecordID || $infoRecord[$randoEvent][$randoField] == '' )
			{
				unset( $listRecords[$recordID] );
				continue;
			}
			$randoNum++;
			if ( $this->getProjectSetting( 'stratify' ) )
			{
				for ( $i = 0; $i < count($listStratEvents); $i++ )
				{
					$stratEvent = $listStratEvents[$i];
					$stratField = $listStratFields[$i];
					if ( $infoRecord[$stratEvent][$stratField] !=
					     $infoNewRecord[$stratEvent][$stratField] )
					{
						unset( $listRecords[$recordID] );
						continue 2;
					}
				}
			}
		}

		// Select the minimization mode to use. If multiple minimization modes are not in use, then
		// the appropriate mode is the first (and only) one.
		$minMode = 0;
		if ( $this->getProjectSetting( 'mode-variable' ) )
		{
			$modeEvent = $this->getProjectSetting( 'mode-event' );
			$modeField = $this->getProjectSetting( 'mode-field' );
			$modeValue = $infoNewRecord[$modeEvent][$modeField];
			$minMode = -1;
			$listModeValues = $this->getProjectSetting( 'minim-mode' );
			for ( $i = 0; $i < count( $listModeValues ); $i++ )
			{
				if ( $listModeValues[$i] == $modeValue )
				{
					$minMode = $i;
					break;
				}
			}
		}
		if ( $minMode < 0 )
		{
			return "Minimization mode variable $modeField missing.";
		}

		// Get the randomization codes and ratios.
		$listAllRandoCodes = $this->getProjectSetting( 'rando-code' );
		$listRandoCodes = $listAllRandoCodes[$minMode];
		$listAllRandoDescs = $this->getProjectSetting( 'rando-desc' );
		$listRandoDescs = $listAllRandoDescs[$minMode];
		$listAllRandoRatios = $this->getProjectSetting( 'rando-ratio' );
		$listRandoRatios = $listAllRandoRatios[$minMode];
		$listCodeRatios = [];
		$listCodeDescriptions = [];
		for ( $i = 0; $i < count( $listRandoCodes ); $i++ )
		{
			$listCodeRatios[ $listRandoCodes[$i] ] = $listRandoRatios[$i];
			$listCodeDescriptions[ $listRandoCodes[$i] ] = $listRandoDescs[$i];
		}

		// Get minimization field values for the record to randomize.
		$listAllMinEvents = $this->getProjectSetting( 'minim-event' );
		$listMinEvents = $listAllMinEvents[$minMode];
		$listAllMinFields = $this->getProjectSetting( 'minim-field' );
		$listMinFields = $listAllMinFields[$minMode];
		$listNewMinValues = [];
		for ( $i = 0; $i < count( $listMinFields ); $i++ )
		{
			$minEvent = $listMinEvents[$i];
			$minField = $listMinFields[$i];
			$minValue = $infoNewRecord[$minEvent][$minField];
			if ( $minValue == '' )
			{
				return "Minimization variable $minField missing.";
			}
			$listNewMinValues[$minEvent][$minField] = $minValue;
		}

		// Calculate the minimization totals using the minimization field values for the existing
		// records.
		$listMinTotals = [];
		$listMinFieldTotals = [];
		foreach ( $listRandoCodes as $code )
		{
			// Prepare the minimization totals, both the overall totals for each allocation code
			// used for minimization, and the per-field totals for diagnostic output.
			$listMinTotals[$code] = 0;
			$listMinFieldTotals[$code] = [];
			for ( $i = 0; $i < count( $listMinFields ); $i++ )
			{
				$minEvent = $listMinEvents[$i];
				$minField = $listMinFields[$i];
				$listMinFieldTotals[$code][$minEvent][$minField] = 0;
			}
		}
		foreach ( $listRecords as $infoRecord )
		{
			// Get the existing record's randomization allocation.
			$existingCode = $infoRecord[$randoEvent][$randoField];
			for ( $i = 0; $i < count( $listMinFields ); $i++ )
			{
				// Increment the minimization totals where the minimization field value on the new
				// and existing records match.
				$minEvent = $listMinEvents[$i];
				$minField = $listMinFields[$i];
				$minValue = $infoRecord[$minEvent][$minField];
				$newMinValue = $listNewMinValues[$minEvent][$minField];
				if ( $minValue == $newMinValue )
				{
					$listMinTotals[$existingCode]++;
					$listMinFieldTotals[$existingCode][$minEvent][$minField]++;
				}
			}
		}

		// Divide the minimization totals by the allocation ratio, and sort lowest to highest.
		// Minimization totals are multiplied by the lowest common multiple of the allocation ratios
		// first, so the values following division are still integers.
		// If two totals are equal, sort randomly.
		$ratioCommonMultiple =
			array_reduce( $listCodeRatios, function ( $carry, $item )
			{
				if ( $carry == 0 )
				{
					return $item;
				}
				else
				{
					if ( $carry > $item )
					{
						$a = $carry;
						$b = $item;
					}
					else
					{
						$a = $item;
						$b = $carry;
					}
					while ( $b != 0 )
					{
						$c = $b;
						$b = $a % $b;
						$a = $c;
					}
					return ( $carry * $item ) / $a;
				}
			}, 0 );
		$listAdjustedTotals = [];
		foreach ( $listMinTotals as $code => $total )
		{
			$listAdjustedTotals[$code] = ( $total * $ratioCommonMultiple ) / $listCodeRatios[$code];
		}
		uasort( $listAdjustedTotals, function( $a, $b )
		{
			if ( $a == $b )
			{
				return random_int( 0, 1 ) ? -1 : 1;
			}
			return ( $a < $b ) ? -1 : 1;
		});

		// Perform the randomization.
		$listAdjustedCodes = array_keys( $listAdjustedTotals );
		$randoCode = array_shift( $listAdjustedCodes );
		$initialRandom = $this->getProjectSetting( 'initial-random' );
		$randomFactor = $this->getProjectSetting( 'random-factor' );
		$randomPercent = $this->getProjectSetting( 'random-percent' );
		$randomApplied = [ 'initial' => false, 'factor' => null,
		                   'values' => [], 'details' => 'none' ];
		$listRandoProportional = [];
		foreach ( $listCodeRatios as $code => $ratio )
		{
			$listRandoProportional =
					array_merge( $listRandoProportional, array_fill( 0, $ratio, $code ) );
		}
		if ( $initialRandom != '' && $randoNum <= $initialRandom )
		{
			// Always allocate randomly for the specified number of initial records.
			$randomApplied['initial'] = true;
			$randomApplied['details'] = 'randomization number (' . $randoNum . ') <= ' .
			                            $initialRandom . ', allocation chosen randomly';
			$randoValue = random_int( 0, count( $listRandoProportional ) - 1 );
			$randoCode = $listRandoProportional[$randoValue];
			$randomApplied['details'] .= ' (' . $randoValue . ')';
		}
		elseif ( $randomFactor == 'S' || $randomFactor == 'C' )
		{
			// Based on the random percentage, skip an allocation either once or 'compounding'
			// (i.e. random-percent of random-percent times, skip two allocations, and so on...)
			$randomApplied['details'] = '';
			$testPercent = random_int( 0, 1000000 ) / 10000;
			while ( $testPercent < $randomPercent && count( $listAdjustedCodes ) > 0 )
			{
				$randomApplied['factor'] = $randomFactor;
				$randomApplied['values'][] = $testPercent;
				$randomApplied['details'] .= ( $randomApplied['details'] == '' ) ? '' : '; ';
				$randomApplied['details'] .= 'random value (' . $testPercent . ') < ' .
				                             $randomPercent . ', allocation ' .
				                             $randoCode . ' skipped';
				$randoCode = array_shift( $listAdjustedCodes );
				if ( $randomFactor == 'S' )
				{
					break;
				}
				$testPercent = random_int( 0, 1000000 ) / 10000;
			}
			if ( $randomApplied['details'] == '' || $randomFactor == 'C' )
			{
				$randomApplied['values'][] = $testPercent;
				$randomApplied['details'] .= ( $randomApplied['details'] == '' ) ? '' : '; ';
				$randomApplied['details'] .= 'random value (' . $testPercent . ') >= ' .
				                             $randomPercent . ', minimized allocation used';
			}
		}
		elseif ( $randomFactor == 'R' )
		{
			$testPercent = random_int( 0, 1000000 ) / 10000;
			if ( $testPercent < $randomPercent )
			{
				$randomApplied['factor'] = $randomFactor;
				$randomApplied['values'][] = $testPercent;
				$randomApplied['details'] = 'random value (' . $testPercent . ') < ' .
				                            $randomPercent . ', allocation chosen randomly';
				$randoValue = random_int( 0, count( $listRandoProportional ) - 1 );
				$randoCode = $listRandoProportional[$randoValue];
				$randomApplied['details'] .= ' (' . $randoValue . ')';
			}
			else
			{
				$randomApplied['values'][] = $testPercent;
				$randomApplied['details'] = 'random value (' . $testPercent . ') >= ' .
				                            $randomPercent . ', minimized allocation used';
			}
		}

		// Perform a fake randomization if required.
		$bogusField = $this->getProjectSetting( 'bogus-field' );
		if ( $bogusField != '' )
		{
			$bogusValue = random_int( 0, count( $listRandoProportional ) - 1 );
			$bogusCode = $listRandoProportional[$bogusValue];
		}

		// Generate the diagnostic information if required.
		$diagField = $this->getProjectSetting( 'diag-field' );
		if ( $diagField != '' )
		{
			$diagData = [ 'num' => $randoNum,
			              'stratify' => ( $this->getProjectSetting( 'stratify' ) ? true : false ) ];
			if ( $diagData['stratify'] )
			{
				$diagData['strata_values'] = [];
				foreach ( $listStratValues as $eventNum => $infoStratEvent )
				{
					$eventName = \REDCap::getEventNames( true, true, $eventNum );
					foreach ( $infoStratEvent as $fieldName => $value )
					{
						$diagData['strata_values']["$eventName.$fieldName"] = $value;
					}
				}
				$diagData['strata_records'] = array_keys( $listRecords );
			}
			$diagData['minim_multi'] = $this->getProjectSetting( 'mode-variable' ) ? true : false;
			if ( $diagData['minim_multi'] )
			{
				$diagData['minim_mode'] = $minMode + 1;
				$diagData['minim_mode_value'] = $modeValue;
			}
			$diagData['codes_full'] = $listRandoProportional;
			$diagData['minim_values'] = [];
			foreach ( $listNewMinValues as $eventNum => $infoMinEvent )
			{
				$eventName = \REDCap::getEventNames( true, true, $eventNum );
				foreach ( $infoMinEvent as $fieldName => $value )
				{
					$diagData['minim_values']["$eventName.$fieldName"] = $value;
				}
			}
			$diagData['minim_totals'] = [ 'final' => $listAdjustedTotals,
			                              'base' => $listMinTotals,
			                              'fields' => [] ];
			foreach ( $listMinFieldTotals as $code => $infoMinField )
			{
				foreach ( $infoMinField as $eventNum => $infoMinEvent )
				{
					$eventName = \REDCap::getEventNames( true, true, $eventNum );
					foreach ( $infoMinEvent as $fieldName => $value )
					{
						$diagData['minim_totals']['fields'][$code]["$eventName.$fieldName"] = $value;
					}
				}
			}
			$diagData['minim_random'] = $randomApplied;
			if ( $bogusField != '' )
			{
				$diagData['bogus_value'] = $bogusValue;
			}
			$diagData = json_encode( $diagData );
		}

		// Save the randomization code to the record.
		$inputData[$newRecordID][$randoEvent][$randoField] = $randoCode;
		if ( $bogusField != '' )
		{
			$inputData[$newRecordID][$randoEvent][$bogusField] = $bogusCode;
		}
		if ( $diagField != '' )
		{
			$inputData[$newRecordID][$randoEvent][$diagField] = $diagData;
		}
		$result = \REDCap::saveData( 'array', $inputData, 'normal', 'YMD', 'flat', null, false );
		if ( count( $result['errors'] ) > 0 )
		{
			return "Errors occurred while saving randomization:\n" .
			       //print_r( $result['errors'], true );
			       implode( "\n", $result['errors'] );
		}
		\REDCap::logEvent( 'Randomization (minimization)',
		                   ( $randoField .
		                     ( $bogusField == '' ? '' : "\n$bogusField" ) .
		                     ( $diagField == '' ? '' : "\n$diagField" ) ),
		                   null, $newRecordID, $randoEvent );
		return true;
	}


	function validateSettings( $settings )
	{
		$errMsg = '';

		if ( $this->getProjectID() === null )
		{
			return null;
		}

		$rando = true;
		if ( $settings['rando-event'] == '' || $settings['rando-field'] == '' )
		{
			$rando = false;
		}

		// Check that stratification variables are correctly specified.
		if ( $settings['stratify'] )
		{
			for ( $i = 0; $i < count( $settings['stratification'] ); $i++ )
			{
				if ( $settings['strat-event'][$i] == '' || $settings['strat-field'][$i] == '' )
				{
					$errMsg .= "\n- Stratification variable " . ($i+1) . " is missing or invalid";
				}
			}
		}

		// Check that there is only one minimization mode, unless multiple modes selected.
		if ( !$settings['mode-variable'] && count( $settings['mode'] ) > 1 )
		{
			$errMsg .= "\n- Multiple modes is not selected," .
			           " but more than 1 minimization mode has been entered.";
		}

		// If multiple modes selected, ensure that the mode variable is specified.
		if ( $settings['mode-variable'] &&
		     ( $settings['mode-event'] == '' || $settings['mode-field'] == '' ) )
		{
			$errMsg .= "\n- Mode variable is missing or invalid";
		}

		// Check that the minimization modes have been specified correctly.
		$listCodeDescs = [];
		for ( $i = 0; $i < count( $settings['mode'] ); $i++ )
		{
			// If using multiple modes, check the mode value has been entered.
			if ( $settings['mode-variable'] && $settings['minim-mode'][$i] == '' )
			{
				$errMsg .= "\n- Minimization mode " . ($i+1) . ": mode value is missing";
			}
			// Check that the allocation code, description and ratio have been entered.
			// Ensure that the allocation ratio is an integer greater than 0.
			for ( $j = 0; $j < count( $settings['minim-codes'][$i] ); $j++ )
			{
				if ( $settings['rando-code'][$i][$j] == '' && ( $rando || $i + $j > 0 ) )
				{
					$errMsg .= "\n- Minimization mode " . ($i+1) . ": code for allocation " .
					           ($j+1) . " is missing";
				}
				if ( $settings['rando-desc'][$i][$j] == '' && ( $rando || $i + $j > 0 ) )
				{
					$errMsg .= "\n- Minimization mode " . ($i+1) . ": description for allocation " .
					           ($j+1) . " is missing";
				}
				if ( !preg_match( '/^[1-9][0-9]*$/', $settings['rando-ratio'][$i][$j] ) &&
				     ( $rando || $i + $j > 0 ) )
				{
					$errMsg .= "\n- Minimization mode " . ($i+1) . ": ratio for allocation " .
					           ($j+1) . " is missing or invalid (integer required)";
				}
				if ( $settings['rando-code'][$i][$j] != '' &&
				     $settings['rando-desc'][$i][$j] != '' )
				{
					if ( ! isset( $listCodeDescs[ $settings['rando-code'][$i][$j] ] ) )
					{
						$listCodeDescs[ $settings['rando-code'][$i][$j] ] =
							$settings['rando-desc'][$i][$j];
					}
					elseif ( $listCodeDescs[ $settings['rando-code'][$i][$j] ] !=
					         $settings['rando-desc'][$i][$j] )
					{
						$errMsg .= "\n- Minimization mode " . ($i+1) . ": code for allocation " .
						           ($j+1) . " has already been defined with a different " .
						           "description\n  Please use a different code or ensure the " .
						           "descriptions match";
					}
				}
			}
			// Check that the minimization variables have been specified.
			for ( $j = 0; $j < count( $settings['minim-vars'][$i] ); $j++ )
			{
				if ( ( $settings['minim-event'][$i][$j] == '' ||
				       $settings['minim-field'][$i][$j] == '' ) &&
				     ( $rando || $i + $j > 0 ) )
				{
					$errMsg .= "\n- Minimization mode " . ($i+1) . ": minimization variable " .
					           ($j+1) . " is missing or invalid";
				}
			}
		}

		// Check that a percentage is specified if a random factor has been chosen.
		if ( $settings['random-factor'] != '' &&
		     ( ! is_numeric( $settings['random-percent'] ) || $settings['random-percent'] < 0 ||
		       $settings['random-percent'] > 100 ) )
		{
			$errMsg .= "\n- % randomizations using random factor must be between 0 and 100";
		}

		if ( $settings['initial-random'] != '' &&
		     ! preg_match( '/^(0|[1-9][0-9]*)$/', $settings['initial-random'] ) )
		{
			$errMsg .= "\n- Number of initial random allocations must be an integer";
		}

		if ( $errMsg != '' )
		{
			return "Your minimization configuration contains errors:$errMsg";
		}
		return null;
	}
}