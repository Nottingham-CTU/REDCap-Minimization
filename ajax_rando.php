<?php

header( 'Content-Type: application/json' );

$return = [ 'status' => false, 'message' => '', 'data' => [] ];

$record = $_POST['record'];
$csrfToken = $_POST['token'];

if ( $record == '' || $csrfToken == '' || !isset( $_SERVER['HTTP_X_RC_MIN_REQ'] ) ||
     !in_array( $csrfToken, $_SESSION['redcap_csrf_token'] ) )
{
	echo json_encode( $return );
	exit;
}

$status = $module->performRando( $record );
if ( $status === true )
{
	$return['status'] = true;
	$randoEvent = $module->getProjectSetting( 'rando-event' );
	$randoField = $module->getProjectSetting( 'rando-field' );
	$bogusField = $module->getProjectSetting( 'bogus-field' );
	$diagField = $module->getProjectSetting( 'diag-field' );
	$metadata = REDCap::getDataDictionary( 'array', false,
	                                       [ $randoField, $bogusField, $diagField ] );
	$form = $metadata[$randoField]['form_name'];
	foreach ( [ $randoField, $bogusField, $diagField ] as $fieldName )
	{
		if ( $fieldName != '' && $metadata[$fieldName]['form_name'] == $form )
		{
			$return['data'][$fieldName] =
				REDCap::getData( 'array', $record, $fieldName,
				                 $randoEvent )[$record][$randoEvent][$fieldName];
		}
	}
	$return['message'] = $module->getDescription( $return['data'][$randoField] );
}
else
{
	$return['status'] = false;
	$return['message'] = $status;
}

echo json_encode( $return );
