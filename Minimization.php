<?php

namespace Nottingham\Minimization;

class Minimization extends \ExternalModules\AbstractExternalModule
{
	function performRando( $newRecordID )
	{
		// Check that the randomization event/field is defined.
		$randoEvent = $this->getProjectSetting( 'rando-event' );
		$randoField = $this->getProjectSetting( 'rando-field' );
		if ( $randoEvent == '' || $randoField == '' )
		{
			return false;
		}

		// Get all the records for the project.
		$listRecords = \REDCap::getData( [ 'return_format' => 'array',
		                                   'records' => $newRecordID,
		                                   'combine_checkbox_values' => true,
		                                   'exportDataAccessGroups' => true ] );

		// Get the record to randomize.
		if ( ! isset( $listRecords[$newRecordID] ) ||
		     $listRecords[$newRecordID][$randoEvent][$randoField] != '' )
		{
			return false;
		}
		$infoNewRecord = $listRecords[$newRecordID];

		// Remove unrandomized records from the list and perform stratification.
		foreach ( $listRecords as $recordID => $infoRecord )
		{
			if ( $recordID == $newRecordID || $infoRecord[$randoEvent][$randoField] == '' )
			{
				unset( $listRecords[$recordID] );
				continue;
			}
			if ( $this->getProjectSetting( 'stratify' ) )
			{
				$listStratEvents = $this->getProjectSetting( 'strat-event' );
				$listStratFields = $this->getProjectSetting( 'strat-field' );
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
			return false;
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
		return [ $listCodeRatios, $listCodeDescriptions ];

		// Get minimization field values for the record to randomize.
		// TODO

		// Calculate the minimization totals using the minimization field values for the existing
		// records.
		// TODO

		// Divide the minimization totals by the allocation ratio.
		// TODO

		// Perform a fake randomization if required.
		// TODO
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

		if ( $errMsg != '' )
		{
			return "Your minimization configuration contains errors:$errMsg";
		}
		return null;
	}
}