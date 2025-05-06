<?php

namespace Nottingham\Minimization;

// This script responds to AJAX requests triggered when the randomize button is clicked.
// Output is in JSON format.
header( 'Content-Type: application/json' );

// Prepare default value for output.
$return = [ 'status' => false, 'message' => '', 'data' => [] ];

// Note the record ID and anti-CSRF token.
$record = $_POST['record'];
$csrfToken = $_POST['token'];

// Check that the request is valid, exit immediately (with default output) if not.
if ( $record == '' || $csrfToken == '' || !isset( $_SERVER['HTTP_X_RC_MIN_REQ'] ) ||
     !in_array( $csrfToken, $_SESSION['redcap_csrf_token'] ) )
{
	echo json_encode( $return );
	exit;
}

// Use the manual randomization code, if supplied and permitted.
$manualCode = false;
if ( isset( $_POST['manualcode'] ) && $module->canManualRando() )
{
	$manualCode = $_POST['manualcode'];
}

// Attempt the randomization. This will return boolean true if successful, or a string containing
// an error message if unsuccessful.
$status = $module->performRando( $record, $manualCode );
if ( $status === true )
{
	// Retrieve the saved randomization values for the record, and output the values and allocation
	// description so that these can be updated on the data entry form.
	$return['status'] = true;
	$randoEvent = $module->getProjectSetting( 'rando-event' );
	$randoField = $module->getProjectSetting( 'rando-field' );
	$dateField = $module->getProjectSetting( 'rando-date-field' );
	$bogusField = $module->getProjectSetting( 'bogus-field' );
	$diagField = $module->getProjectSetting( 'diag-field' );
	$packField = '';
	list( $packMgmt, $packMgmtCat ) =
			$this->getPackMgmtModule( $randoField, [ 'getMinimPackField' ] );
	if ( $packMgmt !== false )
	{
		// Get the field name for the allocation pack ID.
		$packField = $packMgmt->getMinimPackField( $packMgmtCat );
	}
	$metadata = \REDCap::getDataDictionary( 'array', false, [ $randoField, $dateField, $bogusField,
	                                                          $diagField, $packField ] );
	$form = $metadata[$randoField]['form_name'];
	foreach ( [ $randoField, $dateField, $bogusField, $diagField ] as $fieldName )
	{
		if ( $fieldName != '' && $metadata[$fieldName]['form_name'] == $form )
		{
			$return['data'][$fieldName] =
				$module->escapeHTML( \REDCap::getData( 'array', $record, $fieldName, $randoEvent
				                                           )[$record][$randoEvent][$fieldName] );
		}
	}
	$return['message'] =
		$module->escapeHTML( $module->getDescription( $return['data'][$randoField] ) );
}
else
{
	// Randomization was unsuccessful. The unsuccessful status and the error message (message
	// contained in $status variable) are output.
	$return['status'] = false;
	$return['message'] = $module->escapeHTML( $status );
}

// Perform the data output.
echo json_encode( $return );

