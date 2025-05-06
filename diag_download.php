<?php

namespace Nottingham\Minimization;

$forTestRuns = ( isset( $forTestRuns ) && $forTestRuns === true ) ? true : false;


if ( ! $forTestRuns )
{
	$isDev = $module->query( "SELECT value FROM redcap_config WHERE" .
	                         " field_name = 'is_development_server'", [] )->fetch_row()[0] == '1';
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' .
	        trim( preg_replace( '/[^A-Za-z0-9-]+/', '_', \REDCap::getProjectTitle() ), '_-' ) .
	        '_' . $module->tt('dldiag_title') . '_' . gmdate( 'Ymd-His' ) .
	        ( $isDev ? ( '_' . $module->tt('dldiag_dev') ) : '' ) . '.csv"' );

	// Exit here if the requirements are not satisfied.
	if ( $module->getProjectSetting( 'rando-field' ) == null ||
	     $module->getProjectSetting( 'diag-field' ) == null ||
	     ( $module->getSystemSetting( 'config-require-user-permission' ) == 'true' &&
	       ! in_array( 'minimization',
	                   $module->getUser()->getRights()['external_module_config'] ) ) ||
	     ! $module->getUser()->hasDesignRights() )
	{
		exit;
	}
}

// Set variables.
$projectID = intval( $module->getProjectId() );
$dataTable = method_exists( '\REDCap', 'getDataTable' )
             ? \REDCap::getDataTable( $projectID ) : 'redcap_data';
$showEventNames = ( $module->getProjectSetting( 'diag-download' ) != 'O' );
if ( ! isset( $listEventNames ) )
{
	$listEventNames = \REDCap::getEventNames( true );
}
$eventID = intval( $module->getProjectSetting( 'rando-event' ) );
$fieldRando = $module->getProjectSetting( 'rando-field' );
$fieldDate = $module->getProjectSetting( 'rando-date-field' );
$fieldBogus = $module->getProjectSetting( 'bogus-field' );
$fieldDiag = $module->getProjectSetting( 'diag-field' );
$stratEvents = $module->getProjectSetting( 'strat-event' );
$stratFields = $module->getProjectSetting( 'strat-field' );
$minimEvents = array_merge( ... $module->getProjectSetting( 'minim-event' ) );
$minimFields = array_merge( ... $module->getProjectSetting( 'minim-field' ) );
$minimCodes = array_unique( array_merge( ... $module->getProjectSetting( 'rando-code' ) ) );
$numMinimCodes = count( max( $module->getProjectSetting( 'rando-code' ) ) );

$minimHdrs = [];
foreach ( $minimCodes as $code )
{
	$minimHdrs[ $code ] = preg_replace( '[^A-Za-z0-9_]', '_', $code );
}

// Construct SQL query to get randomization values and diagnostics.
$sqlRando = "SELECT record, field_rando" .
            ( $fieldDate == null ? '' : ', field_date' ) .
            ( $fieldBogus == null ? '' : ', field_bogus' ) .
            ', field_diag ' .
            "FROM ( SELECT record, value AS field_rando FROM $dataTable " .
            "WHERE project_id = $projectID AND event_id = $eventID AND field_name = ? ) AS tbl1 ";

$fieldNames = [ $fieldRando ];
if ( $fieldDate != null )
{
	$sqlRando .= "NATURAL LEFT JOIN ( SELECT record, value AS field_date FROM $dataTable WHERE " .
	             "project_id = $projectID AND event_id = $eventID AND field_name = ? ) AS tbl2 ";
	$fieldNames[] = $fieldDate;
}
if ( $fieldBogus != null )
{
	$sqlRando .= "NATURAL LEFT JOIN ( SELECT record, value AS field_bogus FROM $dataTable WHERE " .
	             "project_id = $projectID AND event_id = $eventID AND field_name = ? ) AS tbl3 ";
	$fieldNames[] = $fieldBogus;
}
$sqlRando .= "NATURAL LEFT JOIN ( SELECT record, value AS field_diag FROM $dataTable " .
             "WHERE project_id = $projectID AND event_id = $eventID AND field_name = ? ) AS tbl4";
$fieldNames[] = $fieldDiag;

$queryRando = $module->query( $sqlRando, $fieldNames );
array_unshift( $fieldNames, $module->getRecordIdField() );
array_pop( $fieldNames );

// Output CSV headers.
// - Output the randomization, randomization date, and fake randomization field names.
echo implode( ',', $fieldNames );
// - Output headings for the randomization number and stratification.
echo ',rando_num,stratify';
// - If using stratification, output the stratification field names, and header for the number
//   of records in the strata.
if ( $module->getProjectSetting( 'stratify' ) )
{
	for ( $i = 0; $i < count( $stratEvents ); $i++ )
	{
		echo ',';
		if ( $showEventNames && $listEventNames !== false )
		{
			echo $listEventNames[ $stratEvents[$i] ], '.';
		}
		echo $stratFields[$i];
	}
	echo ',strata_records';
}
// - Output the minimization field names.
for ( $i = 0; $i < count( $minimEvents ); $i++ )
{
	echo ',';
	if ( $showEventNames && $listEventNames !== false )
	{
		echo $listEventNames[ $minimEvents[$i] ], '.';
	}
	echo $minimFields[$i];
}
// - Output headers for 1st, 2nd, ... most minimized allocation.
for ( $i = 1; $i < $numMinimCodes; $i++ )
{
	echo ',minim_alloc_', $i;
}
// - Output headers for the minimization totals.
foreach ( $minimCodes as $code )
{
	echo ',minim_total_', $minimHdrs[ $code ];
}
// - Output headers for the random (tie-break) minimization totals.
foreach ( $minimCodes as $code )
{
	echo ',minim_rtotal_', $minimHdrs[ $code ];
}
// - Output headers for initial random allocation and the minimization threshold.
echo ',minim_initial,minim_threshold';
// - Output headers for the random values used for the random factor.
for ( $i = 1; $i < $numMinimCodes; $i++ )
{
	echo ',minim_random_', $i;
}
// - Output header for the random factor details.
echo ',minim_random_details';
// - Output headers for the base (pre ratio adjustment) minimization totals.
foreach ( $minimCodes as $code )
{
	echo ',minim_btotal_', $minimHdrs[ $code ];
}
// - Output headers for the field minimization totals and the maximum difference.
for ( $i = 0; $i < count( $minimEvents ); $i++ )
{
	foreach ( $minimCodes as $code )
	{
		echo ',minim_ftotal_', $minimHdrs[ $code ], '_';
		if ( $showEventNames && $listEventNames !== false )
		{
			echo $listEventNames[ $minimEvents[$i] ], '.';
		}
		echo $minimFields[$i];
	}
}
echo ',minim_max_diff';


// Output data.
while ( $itemRando = $queryRando->fetch_assoc() )
{
	// Obtain the diagnostic data for the record. If the diagnostic data does not exist or is
	// not valid JSON, skip the record.
	if ( $itemRando['field_diag'] == '' )
	{
		continue;
	}
	$randoDiag = json_decode( $module->dataDecrypt( $itemRando['field_diag'] ), true );
	if ( $randoDiag === null )
	{
		continue;
	}
	echo "\n";
	// Output the record ID, randomization allocation, randomization date and fake allocation.
	echo '"';
	$module->echoText( str_replace( '"', '""', $itemRando['record'] ) );
	echo '","';
	$module->echoText( str_replace( '"', '""', $itemRando['field_rando'] ) );
	echo '"';
	if ( $fieldDate != null )
	{
		echo ',"';
		$module->echoText( str_replace( '"', '""', $itemRando['field_date'] ) );
		echo '"';
	}
	if ( $fieldBogus != null )
	{
		echo ',"';
		$module->echoText( str_replace( '"', '""', $itemRando['field_bogus'] ) );
		echo '"';
	}
	// Output the randomization number, and whether stratification has been used.
	echo ',', intval( $randoDiag['num'] );
	echo ',', ( $randoDiag['stratify'] ? '1' : '0' );
	// If stratification has been used, output the stratification field values and the total number
	// of records previously randomized to the strata.
	if ( $module->getProjectSetting( 'stratify' ) )
	{
		for ( $i = 0; $i < count( $stratEvents ); $i++ )
		{
			echo ',';
			$stratName = $stratFields[$i];
			if ( $listEventNames !== false )
			{
				$stratName = $listEventNames[ $stratEvents[$i] ] . '.' . $stratName;
			}
			if ( isset( $randoDiag['strata_values'][$stratName] ) )
			{
				echo '"';
				$module->echoText( str_replace( '"', '""',
				                                $randoDiag['strata_values'][$stratName] ) );
				echo '"';
			}
		}
		echo ',', intval( $randoDiag['strata_records'] );
	}
	// Output the minimization field values.
	for ( $i = 0; $i < count( $minimEvents ); $i++ )
	{
		echo ',';
		$minimName = $minimFields[$i];
		if ( $listEventNames !== false )
		{
			$minimName = $listEventNames[ $minimEvents[$i] ] . '.' . $minimName;
		}
		if ( isset( $randoDiag['minim_values'][$minimName] ) )
		{
			echo '"';
			$module->echoText( str_replace( '"', '""', $randoDiag['minim_values'][$minimName] ) );
			echo '"';
		}
	}
	// Output the 1st, 2nd, ... most minimized allocation.
	for ( $i = 0; $i < $numMinimCodes - 1; $i++ )
	{
		echo ',';
		if ( isset( $randoDiag['minim_alloc'][$i] ) )
		{
			echo '"';
			$module->echoText( str_replace( '"', '""', $randoDiag['minim_alloc'][$i] ) );
			echo '"';
		}
	}
	// Output the (final) minimization totals.
	foreach ( $minimCodes as $code )
	{
		echo ',';
		if ( isset( $randoDiag['minim_totals']['final'][$code] ) )
		{
			echo intval( $randoDiag['minim_totals']['final'][$code] );
		}
	}
	// Output the random minimization totals (used for tie break).
	foreach ( $minimCodes as $code )
	{
		echo ',';
		if ( isset( $randoDiag['minim_totals']['random'][$code] ) )
		{
			echo intval( $randoDiag['minim_totals']['random'][$code] );
		}
	}
	// Output whether initial random allocations are being used.
	echo ',', ( $randoDiag['minim_random']['initial'] ? '1' : '0' );
	echo ',';
	$module->echoText( isset( $randoDiag['minim_random']['threshold'] )
	                   ? $randoDiag['minim_random']['threshold'] : '' );
	for ( $i = 0; $i < $numMinimCodes - 1; $i++ )
	{
		echo ',';
		if ( isset( $randoDiag['minim_random']['values'][$i] ) )
		{
			$module->echoText( $randoDiag['minim_random']['values'][$i] );
		}
	}
	echo ',"';
	$module->echoText( str_replace( '"', '""', $randoDiag['minim_random']['details'] ) );
	echo '"';
	// Output the base (pre ratio adjustment) minimization totals.
	foreach ( $minimCodes as $code )
	{
		echo ',';
		if ( isset( $randoDiag['minim_totals']['base'][$code] ) )
		{
			echo intval( $randoDiag['minim_totals']['base'][$code] );
		}
	}
	// Output the field minimization totals and the maximum difference between totals for a field.
	$listMinimDiff = [];
	for ( $i = 0; $i < count( $minimEvents ); $i++ )
	{
		foreach ( $minimCodes as $code )
		{
			echo ',';
			$minimName = $minimFields[$i];
			if ( $listEventNames !== false )
			{
				$minimName = $listEventNames[ $minimEvents[$i] ] . '.' . $minimName;
			}
			if ( isset( $randoDiag['minim_totals']['fields'][$code][$minimName] ) )
			{
				if ( ! isset( $listMinimDiff[$minimName]['low'] ) ||
				     $listMinimDiff[$minimName]['low'] >
				        $randoDiag['minim_totals']['fields'][$code][$minimName] )
				{
					$listMinimDiff[$minimName]['low'] =
					        $randoDiag['minim_totals']['fields'][$code][$minimName];
				}
				if ( ! isset( $listMinimDiff[$minimName]['high'] ) ||
				     $listMinimDiff[$minimName]['high'] <
				        $randoDiag['minim_totals']['fields'][$code][$minimName] )
				{
					$listMinimDiff[$minimName]['high'] =
					        $randoDiag['minim_totals']['fields'][$code][$minimName];
				}
				echo intval( $randoDiag['minim_totals']['fields'][$code][$minimName] );
			}
		}
	}
	$minimDiff = 0;
	foreach ( $listMinimDiff as $infoMinimDiff )
	{
		if ( $infoMinimDiff['high'] - $infoMinimDiff['low'] > $minimDiff )
		{
			$minimDiff = $infoMinimDiff['high'] - $infoMinimDiff['low'];
		}
	}
	echo ',', $minimDiff;
}
