<?php

namespace Nottingham\Minimization;

// This script responds to AJAX requests triggered when the minimization config is updated.
// Output is in JSON format.
header( 'Content-Type: application/json' );

// Prepare default value for output.
$return = [ 'status' => false, 'message' => '', 'data' => [] ];

// Note the anti-CSRF token.
$csrfToken = $_POST['token'];

// Check that the request is valid, exit immediately (with default output) if not.
$user = $this->getUser();
if ( $csrfToken == '' || !isset( $_SERVER['HTTP_X_RC_MIN_CONF'] ) || $user === null ||
     ( ! defined('SUPER_USER') && $user->getRights()['random_setup'] != 1 ) ||
     !in_array( $csrfToken, $_SESSION['redcap_csrf_token'] ) )
{
	echo json_encode( $return );
	exit;
}

// Perform the data output.
echo json_encode( $return );

