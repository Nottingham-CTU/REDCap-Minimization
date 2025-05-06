<?php

namespace Nottingham\Minimization;

class Minimization extends \ExternalModules\AbstractExternalModule
{

	static $listTREvents = null;


	// Check if an encryption key has been set and set one if not.
	function redcap_module_system_enable( $version )
	{
		if ( $this->getSystemSetting( 'encryption-key' ) == '' )
		{
			// Create a 256 bit encryption key.
			$this->setSystemSetting( 'encryption-key', base64_encode( random_bytes( 32 ) ) );
		}
	}



	// Determine whether the module links should be displayed, based on user type/role.
	// Only show the links to users with permission to modify the module configuration.
	function redcap_module_link_check_display( $project_id, $link )
	{
		// Get the project's field names.
		$listFieldNames = \REDCap::getFieldNames();
		$randoField = $this->getProjectSetting( 'rando-field' );
		$diagField = $this->getProjectSetting( 'diag-field' );

		// Always hide the links if randomization event or field not specified.
		if ( $this->getProjectSetting( 'rando-event' ) == null || $randoField == null ||
		     ! in_array( $randoField, $listFieldNames ) )
		{
			return null;
		}

		// Always hide the diagnostic download link if diagnostics are not being saved.
		if ( $link['tt_name'] == 'module_link_diag' &&
		     ( $diagField == null || ! in_array( $diagField, $listFieldNames ) ) )
		{
			return null;
		}

		// If module specific rights enabled, show link based on this.
		if ( $this->getSystemSetting( 'config-require-user-permission' ) == 'true' )
		{
			return in_array( 'minimization',
			                 $this->getUser()->getRights()['external_module_config'] )
			       ? $link : null;
		}

		// Otherwise show link based on project setup/design rights.
		return $this->getUser()->hasDesignRights() ? $link : null;
	}



	function redcap_every_page_before_render( $project_id )
	{
		// Stop here if not in a project or not on a data entry page.
		if ( $project_id === null ||
		     substr( PAGE_FULL, strlen( APP_PATH_WEBROOT ), 10 ) != 'DataEntry/' )
		{
			return;
		}
		// If the randomization event/field is defined, ensure that REDCap treats the field as
		// *not* required, even if it is marked as required. This will stop REDCap from complaining
		// about a lack of value while waiting on this module to populate the field. Do the same for
		// the fake randomization field and the diagnostic field if these are defined.
		$randoEvent = $this->getProjectSetting( 'rando-event' );
		$randoField = $this->getProjectSetting( 'rando-field' );
		if ( $randoEvent == '' || $randoField == '' )
		{
			return;
		}
		if ( array_key_exists( $randoField, $GLOBALS['Proj']->metadata ) )
		{
			$GLOBALS['Proj']->metadata[$randoField]['field_req'] = 0;
			$GLOBALS['Proj']->metadata[$randoField]['misc'] =
				'@READONLY-SURVEY @READONLY-APP ' . $GLOBALS['Proj']->metadata[$randoField]['misc'];
		}
		$dateField = $this->getProjectSetting( 'rando-date-field' );
		$bogusField = $this->getProjectSetting( 'bogus-field' );
		$diagField = $this->getProjectSetting( 'diag-field' );
		if ( $dateField != '' && array_key_exists( $dateField, $GLOBALS['Proj']->metadata ) )
		{
			$GLOBALS['Proj']->metadata[$dateField]['field_req'] = 0;
			$GLOBALS['Proj']->metadata[$dateField]['misc'] =
					'@READONLY ' . $GLOBALS['Proj']->metadata[$dateField]['misc'];
		}
		if ( $bogusField != '' && array_key_exists( $bogusField, $GLOBALS['Proj']->metadata ) )
		{
			$GLOBALS['Proj']->metadata[$bogusField]['field_req'] = 0;
			$GLOBALS['Proj']->metadata[$bogusField]['misc'] =
					'@READONLY ' . $GLOBALS['Proj']->metadata[$bogusField]['misc'];
		}
		if ( $diagField != '' && array_key_exists( $diagField, $GLOBALS['Proj']->metadata ) )
		{
			$GLOBALS['Proj']->metadata[$diagField]['field_req'] = 0;
			$GLOBALS['Proj']->metadata[$diagField]['misc'] =
					'@READONLY ' . $GLOBALS['Proj']->metadata[$diagField]['misc'];
		}
		// If randomizaton allocation value protection is enabled, ensure that the randomization
		// and diagnostics fields are not displayed on the form at all once the randomization
		// field has a value.
		if ( substr( PAGE_FULL, strlen( APP_PATH_WEBROOT ), 10 ) == 'DataEntry/' &&
		     $this->getProjectSetting( 'rando-protect-value' ) && isset( $_GET['id'] ) &&
		     isset( $_GET['page'] ) && $_GET['page'] != '' )
		{
			$protectedData = \REDCap::getData( 'array', $_GET['id'], $randoField, $randoEvent );
			if ( $protectedData[ $_GET['id'] ][ $randoEvent ][ $randoField ] != '' )
			{
				if ( isset( $GLOBALS['Proj']->forms[ $_GET['page'] ]['fields'][ $randoField ] ) )
				{
					unset( $GLOBALS['Proj']->forms[ $_GET['page'] ]['fields'][ $randoField ] );
				}
				if ( isset( $GLOBALS['Proj']->forms[ $_GET['page'] ]['fields'][ $diagField ] ) )
				{
					unset( $GLOBALS['Proj']->forms[ $_GET['page'] ]['fields'][ $diagField ] );
				}
			}
		}
	}



	// If a randomization failed on form submission, load the error message from the session
	// variable and display it on page load. Clear the session variable once triggered.
	function redcap_every_page_top( $project_id )
	{
		if ( isset( $_SESSION['module_minimization_message'] ) )
		{
			$errMsg = $this->escapeHTML( $_SESSION['module_minimization_message'] );
			$errMsg = str_replace( "\n", '\n', addslashes( $errMsg ) );
			unset( $_SESSION['module_minimization_message'] );


?>
<script type="text/javascript">
  $(function () {simpleDialog('<?php echo $errMsg; ?>','<?php echo $this->tt('cannot_rando'); ?>')})
</script>
<?php


		}
	}



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
			// Get the randomization date field.
			$randoDateField = $this->getProjectSetting( 'rando-date-field' );

			// Get the randomization code for the record (if randomized).
			$randoCode = $this->getRandomization( $record );

			// Determine if randomize button should be displayed.
			$showButton = ( $this->getProjectSetting( 'rando-submit-form' ) == '' );



			// Hide the randomization field and prepare to display text or button.
?>
<script type="text/javascript">
  (function ()
  {
    var vWasManual = false
    var vFuncResult = function( result )
    {
      if ( result.status )
      {
        var vH = $('<div>')
        Object.keys(result.data).forEach( function( fieldName )
        {
          var vField = $( '[name=' + fieldName + ']' )
          var vData = vH.html( result.data[fieldName] ).text()
          if ( vField.hasClass( 'date_dmy' ) || vField.hasClass( 'datetime_dmy' ) ||
               vField.hasClass( 'datetime_seconds_dmy' ) )
          {
            vData = vData.substring( 8, 10 ) + '-' + vData.substring( 5, 7 ) + '-' +
                    vData.substring( 0, 4 ) + vData.substring( 10 )
          }
          else if ( vField.hasClass( 'date_mdy' ) || vField.hasClass( 'datetime_mdy' ) ||
                    vField.hasClass( 'datetime_seconds_mdy' ) )
          {
            vData = vData.substring( 5, 7 ) + '-' + vData.substring( 8, 10 ) + '-' +
                    vData.substring( 0, 4 ) + vData.substring( 10 )
          }
          vField[0].value = vData
        } )
        vRandoDetails.innerHTML = result.message
        dataEntryFormValuesChanged = vOldFormChangedVal
<?php
			if ( $randoDateField != '' )
			{
?>
        if ( vWasManual )
        {
          var vDateRow = $( '#<?php echo $this->escapeHTML( $randoDateField ); ?>-tr' )
          var vDateField = $( 'input[name="<?php echo $this->escapeHTML( $randoDateField ); ?>"]' )
          vDateRow.removeClass( '@READONLY' ).removeClass( '@READONLY-FORM' )
          vDateRow.removeClass( '@HIDDEN' ).removeClass( '@HIDDEN-FORM' )
          vDateField.prop( 'disabled', false )
        }
<?php
			}
?>
        calculate()
        doBranching()
      }
      else
      {
        simpleDialog( result.message, '<?php echo $this->tt('cannot_rando'); ?>' )
      }
      vWasManual = false
    }
    $( 'tr[sq_id=<?php echo $randoField; ?>] [name=<?php
			echo $randoField; ?>]' ).css( 'display', 'none' )
    $( 'tr[sq_id=<?php echo $randoField; ?>] .choicevert' ).css( 'display', 'none' )
    $( 'tr[sq_id=<?php echo $randoField; ?>] .resetLinkParent' ).css( 'display', 'none' )
    var vOldFormChangedVal
    var vRandoDetails = document.createElement( 'div' )
<?php


			if ( $randoCode === false ) // randomization has *not* been performed for record yet
			{
				if ( $showButton ) // randomization is performed by clicking a button
				{


					// Display the button and handle button click (perform the randomization and
					// display the result or show an error message).
					// If the randomization is successful, the values on the form will be updated
					// to reflect the values now stored in the database. If a field is a d-m-y or
					// m-d-y date field, the data will be appropriately transformed for display.
?>
    var vRandoButton = document.createElement( 'button' )
    vRandoButton.className = 'jqbuttonmed ui-button ui-corner-all ui-widget'
    vRandoButton.onclick = function ()
    {
      if ( ! confirm( '<?php echo $this->tt('rando_record'), ' ', addslashes( $record ); ?>?' ) )
      {
        return false
      }
      vOldFormChangedVal = dataEntryFormValuesChanged
      $.ajax( { url : '<?php echo $this->getUrl( 'ajax_rando.php' ); ?>',
                method : 'POST',
                data : { record : '<?php echo addslashes( $record ); ?>',
                         token : $('[name=redcap_csrf_token]')[0].value },
                headers : { 'X-RC-Min-Req' : '1' },
                dataType : 'json',
                success : vFuncResult
              } )
      return false
    }
    vRandoButton.innerHTML = '<span style="vertical-align:middle;color:green">' +
                             '<i class="fas fa-random"></i> <?php echo $this->tt('rando'); ?></span>'
    vRandoDetails.appendChild( vRandoButton )
<?php


				}
				else // randomization is performed by submitting the form
				{


					// Inform the user that randomization is yet to be performed.
?>
    vRandoDetails.innerHTML = '<?php echo $this->tt('rando_form_sub'); ?>'
<?php


				}
				// If the user can perform manual randomizations, show the option for this.
				if ( $this->canManualRando() )
				{
					$listPacks = [];
					list( $packMgmt, $packMgmtCat ) =
						$this->getPackMgmtModule( $randoField, [ 'getMinimManualList' ] );
					if ( $packMgmt !== false )
					{
						$listPacks = $packMgmt->getMinimManualList( $record, $packMgmtCat );
					}
?>
    var vManualLink = $('<a href="#" onclick="event.preventDefault()"><?php
					echo $this->tt('rando_form_manual');
?></a>')
    vManualLink.css('font-size','x-small').css('margin-top','5px').css('display','inline-block')
    var vManualDialog = $('<div></div>')
    vManualDialog.append('<p><?php echo $this->tt('rando_form_manual_choose'); ?></p>')
    vManualDialog.append('<p><select><option></option><?php
					if ( ! empty( $listPacks ) )
					{
						echo '<optgroup label="', $this->tt('rando_form_manual_choose_p'), '">';
					}
					foreach ( $listPacks as $c => $d )
					{
						echo '<option value="p', $this->escapeHTML( $c ), '">',
						     $this->escapeHTML( $d ), '</option>';
					}
					if ( ! empty( $listPacks ) )
					{
						echo '</optgroup>';
					}
					echo '<optgroup label="', $this->tt('rando_form_manual_choose_a'), '">';
					foreach ( $this->getCodeList( $record ) as $c => $d )
					{
						echo '<option value="a', $this->escapeHTML( $c ), '">',
						     $this->escapeHTML( $d ), '</option>';
					}
					echo '</optgroup>';
?></select>')
    vManualLink.click( function()
    {
      vManualDialog.dialog( {
        buttons : [
          {
            text : '<?php echo $this->tt('rando'); ?>',
            icon : 'ui-icon-shuffle',
            click : function ()
            {
              var vChoice = $(this).find('select').val()
              if ( vChoice != '' )
              {
                vOldFormChangedVal = dataEntryFormValuesChanged
                vWasManual = true
                $.ajax( { url : '<?php echo $this->getUrl( 'ajax_rando.php' ); ?>',
                          method : 'POST',
                          data : { record : '<?php echo addslashes( $record ); ?>',
                                   manualcode : vChoice,
                                   token : $('[name=redcap_csrf_token]')[0].value },
                          headers : { 'X-RC-Min-Req' : '1' },
                          dataType : 'json',
                          success : vFuncResult
                        } )
              }
              $(this).dialog('close')
            }
          }
        ],
        modal : true,
        title : '<?php echo $this->tt('rando_form_manual'); ?>'
      } )
    } )
    $('<br>').appendTo( vRandoDetails )
    vManualLink.appendTo( vRandoDetails )
<?php
				}
			}
			else // randomization *has* been performed for record
			{


				// Inform the user of the randomization result.
?>
    vRandoDetails.innerText = '<?php echo addslashes( $this->getDescription( $randoCode ) ); ?>'
<?php


			}


			// Show the text or button in place of the hidden field.
?>
    $('tr[sq_id=<?php echo $randoField; ?>] [name=<?php
			echo $randoField; ?>]').before( vRandoDetails )
  })()
</script>
<?php


		} // end if randomization field on current form
	} // end function redcap_data_entry_form



	// Perform randomization on form submission, if configured.
	function redcap_save_record( $project_id, $record, $instrument, $event_id )
	{
		$randoEvent = $this->getProjectSetting( 'rando-event' );
		$randoField = $this->getProjectSetting( 'rando-field' );
		if ( // Check rando event/field are defined and the submission is for the rando event.
		     $randoEvent != '' && $randoField != '' && $event_id == $randoEvent &&
		     // Check that the submission is for the form which triggers randomization.
		     $instrument == $this->getProjectSetting( 'rando-submit-form' ) &&
		     // Check that the record is not already randomized (randomization field is blank).
		     \REDCap::getData( 'array', $record, $randoField,
		                       $randoEvent )[$record][$randoEvent][$randoField] == '' &&
		     // Check that the submitted form is complete (<instrument_name>_complete == 2), or
		     // that randomizatons are to be performed regardless of form status.
		     ( $this->getProjectSetting( 'rando-submit-any-status' ) ||
		       \REDCap::getData( 'array', $record, $instrument . '_complete',
		                       $event_id )[$record][$event_id][$instrument . '_complete'] == '2' ) )
		{
			// Attempt randomization and get status (true if successful, otherwise error message).
			$status = $this->performRando( $record );
			// If randomization failed...
			if ( $status !== true )
			{
				// Save the error message to the session.
				// This will be used on the next page load to display an alert.
				$_SESSION['module_minimization_message'] = $status;
				// Reset the form status to incomplete (if reset option enabled).
				if ( $this->getProjectSetting( 'rando-submit-status-reset' ) )
				{
					\REDCap::saveData( 'array', [ $record =>
					                              [ $event_id =>
					                                [ $instrument . '_complete' => '0' ] ] ] );
				}
			}
		}
	}



	// Check if the current user can do manual randomizations.
	function canManualRando()
	{
		// If module specific rights enabled and the user has them.
		if ( $this->getSystemSetting( 'config-require-user-permission' ) == 'true' )
		{
			if ( in_array( 'minimization',
			               $this->getUser()->getRights()['external_module_config'] ) )
			{
				return true;
			}
		}
		// If module specific rights disabled and the user has project setup/design rights
		elseif ( $this->getUser()->hasDesignRights() )
		{
			return true;
		}

		// Otherwise check if the user has a permitted role.
		$userRights = $this->getUser()->getRights();
		$roleName = ( isset( $userRights ) && isset( $userRights['role_name'] ) &&
		              $userRights['role_name'] != '' ? $userRights['role_name'] : null );
		if ( $roleName !== null )
		{
			$listRoles = explode( "\n", str_replace( "\r\n", "\n",
			                                   $this->getProjectSetting( 'rando-manual-roles' ) ) );
			if ( in_array( $roleName, $listRoles ) )
			{
				return true;
			}
		}
		return false;
	}



	// Encryption/decryption functions for e.g. diagnostic data, to help maintain blinding.
	// These functions are not designed to guarantee encryption. If encryption is not possible or
	// fails for some reason the plaintext will be returned.
	function dataDecrypt( $data )
	{
		if ( ! preg_match( '/^[0-9]+\//', $data ) )
		{
			return $data;
		}
		$encryptionKey = $this->getSystemSetting( 'encryption-key' );
		if ( substr( $data, 0, 2 ) == '1/' )
		{
			$ivLength = openssl_cipher_iv_length( 'aes-256-gcm' );
			$encrypted = base64_decode( substr( $data, 2 ) );
			$iv = substr( $encrypted, 0, $ivLength );
			$tag = substr( $encrypted, -16 );
			$encrypted = substr( $encrypted, $ivLength, -16 );
			$decrypted = openssl_decrypt( $encrypted, 'aes-256-gcm', $encryptionKey,
			                              OPENSSL_RAW_DATA, $iv, $tag, '' );
			if ( is_string( $decrypted ) )
			{
				return $decrypted;
			}
		}
		return $data;
	}

	function dataEncrypt( $data )
	{
		if ( ! function_exists( 'openssl_cipher_iv_length' ) )
		{
			return $data;
		}
		$encryptionKey = $this->getSystemSetting( 'encryption-key' );
		$iv = random_bytes( openssl_cipher_iv_length( 'aes-256-gcm' ) );
		$encrypted = openssl_encrypt( $data, 'aes-256-gcm', $encryptionKey,
		                              OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
		if ( ! is_string( $encrypted ) )
		{
			return $data;
		}
		return '1/' . base64_encode( $iv . $encrypted . $tag );
	}



	// Cron job to perform test runs.
	function doTestRuns( $infoCron )
	{
		$startTime = time();
		$queryTestRuns =
			$this->query( "SELECT project_id, ems.value FROM redcap_external_module_settings ems " .
			              "JOIN redcap_external_modules em ON ems.external_module_id = " .
			              "em.external_module_id WHERE " .
			              "em.directory_prefix = 'minimization' AND ems.key = 'testrun-status' " .
			              "AND json_unquote( json_extract( ems.value, '$.timestamp' ) ) > ? " .
			              "ORDER BY json_unquote( json_extract( ems.value, '$.timestamp' ) ) " .
			              "LIMIT 1", [ $startTime - 1800 ] );
		$infoTestRuns = $queryTestRuns->fetch_assoc();
		if ( $infoTestRuns == null )
		{
			return;
		}
		$oldContext = $_GET['pid'];
		$_GET['pid'] = $infoTestRuns['project_id'];
		$testRunStatus = json_decode( $infoTestRuns['value'], true );
		$listDataFields = $testRunStatus['testdata'];
		self::$listTREvents = $testRunStatus['events'];
		$funcGetDiagOutput = function( $module, $listEventNames )
		{
			$forTestRuns = true;
			ob_start();
			require 'diag_download.php';
			return ob_get_clean();
		};
		$dataHeadings = 'record,field_name,value';
		if ( $testRunStatus['longitudinal'] )
		{
			$dataHeadings .= ',redcap_event_name';
		}
		$datatable = '';
		if ( substr( $testRunStatus['datatable'], 0, 11 ) == ( 'redcap' . '_data' ) )
		{
			$datatable = 'redcap' . '_data';
			if ( strlen( $testRunStatus['datatable'] ) > 11 )
			{
				$datatable .= intval( substr( $testRunStatus['datatable'], 11 ) );
			}
		}
		// Do the test runs.
		for ( $testRun = $testRunStatus['current_run'];
		      $testRun <= $testRunStatus['total_runs']; $testRun++ )
		{
			// Update test run status.
			$testRunStatus['timestamp'] = time();
			$testRunStatus['current_run'] = $testRun;
			$this->setProjectSetting( 'testrun-status', json_encode( $testRunStatus ) );
			if ( $testRunStatus['current_record'] == 0 )
			{
				// Delete any existing records.
				$this->query( 'DELETE FROM ' . $datatable .
				              ' WHERE project_id = ?', [ $this->getProjectId() ] );
				\Records::resetRecordCountAndListCache( $this->getProjectId() );
				// Create the new records.
				for ( $testRecord = 1;
				      $testRecord <= $testRunStatus['total_records']; $testRecord++ )
				{
					$newData = $dataHeadings;
					foreach ( $listDataFields as $infoDataField )
					{
						$newData .= "\n$testRecord," . $infoDataField['field'] . ',';
						$v = random_int( 0, count( $infoDataField['values'] ) - 1 );
						$v = $infoDataField['values'][ $v ];
						$newData .= str_replace( '"', '""', $v );
						if ( isset( $infoDataField['event'] ) )
						{
							$newData .= ',' . $infoDataField['event'];
						}
					}
					$saveResponse = \REDCap::saveData( [ 'project_id' => $this->getProjectId(),
					                                     'dataFormat' => 'csv',
					                                     'type' => 'eav', 'data' => $newData ] );
					if ( ! empty( $saveResponse['errors'] ) )
					{
						// Exit here if the data could not be saved due to errors.
						break 2;
					}
				}
				\Records::resetRecordCountAndListCache( $this->getProjectId() );
				$testRunStatus['current_record'] = 1;
			}
			// Perform the randomizations.
			for ( $testRecord = $testRunStatus['current_record'];
			      $testRecord <= $testRunStatus['total_records']; $testRecord++ )
			{
				// Update test run status.
				$testRunStatus['timestamp'] = time();
				$testRunStatus['current_record'] = $testRecord;
				$this->setProjectSetting( 'testrun-status', json_encode( $testRunStatus ) );
				// Perform randomization.
				$randoResult = $this->performRando( $testRecord );
				if ( $randoResult !== true )
				{
					// Exit here if a randomization could not be performed.
					break 2;
				}
				if ( $testRecord == $testRunStatus['total_records'] )
				{
					// Get the diagnostic output and save to the file repository.
					$diagOutput = $funcGetDiagOutput( $this, self::$listTREvents );
					$diagFileName = $testRunStatus['filename'] . $this->tt('testrun_run') .
					                substr( '0' . $testRun, -2 ) . '.csv';
					file_put_contents( $this->getSafePath( APP_PATH_TEMP . $diagFileName,
					                                       APP_PATH_TEMP ), $diagOutput );
					$diagID = \REDCap::storeFile( APP_PATH_TEMP . $diagFileName,
					                              $this->getProjectId() );
					\REDCap::addFileToRepository( $diagID, $this->getProjectId() );
					unlink( $this->getSafePath( APP_PATH_TEMP . $diagFileName, APP_PATH_TEMP ) );
					$testRunStatus['timestamp'] = time();
					$testRunStatus['current_record'] = 0;
					$testRunStatus['current_run']++;
					$this->setProjectSetting( 'testrun-status', json_encode( $testRunStatus ) );
					$testRunStatus['current_run']--;
				}
				else
				{
					// Update test run status.
					$testRunStatus['timestamp'] = time();
					$testRunStatus['current_record'] = $testRecord + 1;
					$this->setProjectSetting( 'testrun-status', json_encode( $testRunStatus ) );
				}
				// Keep individual cron runs within approx 5 minutes.
				if ( $startTime < time() - 300 )
				{
					$_GET['pid'] = $oldContext;
					return;
				}
			}
		}
		// Clear the test run status and exit.
		$this->removeProjectSetting( 'testrun-status' );
		$_GET['pid'] = $oldContext;
	}



	// Echo plain text to output (without Psalm taints).
	// Use only for e.g. JSON or CSV output.
	function echoText( $text )
	{
		$text = htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XHTML );
		$chars = [ '&amp;' => 38, '&quot;' => 34, '&apos;' => 39, '&lt;' => 60, '&gt;' => 62 ];
		$text = preg_split( '/(&(?>amp|quot|apos|lt|gt);)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $text as $part )
		{
			echo isset( $chars[ $part ] ) ? chr( $chars[ $part ] ) : $part;
		}
	}



	// Escapes text for inclusion in HTML.
	function escapeHTML( $text )
	{
		return htmlspecialchars( $text, ENT_QUOTES );
	}



	// Get the list of allowed randomization codes/descriptions for a record.
	function getCodeList( $record )
	{
		// Select the minimization mode to use. If multiple minimization modes are not in use, then
		// the appropriate mode is the first (and only) one.
		$minMode = 0;
		if ( $this->getProjectSetting( 'mode-variable' ) )
		{
			$modeEvent = $this->getProjectSetting( 'mode-event' );
			$modeField = $this->getProjectSetting( 'mode-field' );
			$infoRecord = \REDCap::getData( [ 'project_id' => $this->getProjectId(),
		                                      'return_format' => 'array',
		                                      'combine_checkbox_values' => true,
		                                      'records' => $record,
		                                      'events' => $modeEvent,
		                                      'fields' => $modeField ] )[ $record ];
			$modeValue = $infoRecord[$modeEvent][$modeField];
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
			return [];
		}


		// Get the randomization codes and descriptions.
		$listAllRandoCodes = $this->getProjectSetting( 'rando-code' );
		$listRandoCodes = $listAllRandoCodes[$minMode];
		$listAllRandoDescs = $this->getProjectSetting( 'rando-desc' );
		$listRandoDescs = $listAllRandoDescs[$minMode];
		$listCodeDescriptions = [];
		for ( $i = 0; $i < count( $listRandoCodes ); $i++ )
		{
			$listCodeDescriptions[ $listRandoCodes[$i] ] = $listRandoDescs[$i];
		}
		return $listCodeDescriptions;
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



	function getPackMgmtModule( $randoField, $checkMethods = [] )
	{
		if ( ! $this->isModuleEnabled( 'pack_management', $this->getProjectId() ) )
		{
			return [ false, false ];
		}
		$packMgmt = \ExternalModules\ExternalModules::getModuleInstance( 'pack_management' );
		foreach ( $checkMethods as $method )
		{
			if ( ! method_exists( $packMgmt, $method ) )
			{
				return [ false, false ];
			}
		}
		$packMgmtCat = false;
		if ( method_exists( $packMgmt, 'hasMinimPackCategory' ) )
		{
			if ( $packMgmt->hasMinimPackCategory( $randoField ) )
			{
				$packMgmtCat = $randoField;
			}
			elseif ( $packMgmt->hasMinimPackCategory( '' ) )
			{
				$packMgmtCat = '';
			}
		}
		if ( $packMgmtCat === false )
		{
			return [ false, false ];
		}
		return [ $packMgmt, $packMgmtCat ];
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



	function performRando( $newRecordID, $manualCode = false )
	{
		// Check that the randomization event/field is defined.
		$randoEvent = $this->getProjectSetting( 'rando-event' );
		$randoField = $this->getProjectSetting( 'rando-field' );
		if ( $randoEvent == '' || $randoField == '' )
		{
			// If the randomization event and/or field are not defined, treat randomization as
			// disabled. Therefore this message is not written to the log.
			return $this->tt('rando_msg_not_enable');
		}


		// Check that the logic (if specified) is satisfied for this record.
		$randoLogic = $this->getProjectSetting( 'rando-logic' );
		if ( $randoLogic != '' && ! \REDCap::evaluateLogic( $randoLogic, $this->getProjectId(),
		                                                    $newRecordID, $randoEvent ) )
		{
			return $this->logRandoFailure( $this->tt('rando_msg_logic'), $newRecordID );
		}


		// Get all the records for the project.
		$listRecords = \REDCap::getData( [ 'project_id' => $this->getProjectId(),
		                                   'return_format' => 'array',
		                                   'combine_checkbox_values' => true,
		                                   'exportDataAccessGroups' => true ] );


		// Get the record to randomize.
		if ( ! isset( $listRecords[$newRecordID] ) ||
		     $listRecords[$newRecordID][$randoEvent][$randoField] != '' )
		{
			return $this->logRandoFailure( $this->tt('rando_msg_performed'),
			                               $newRecordID );
		}
		$infoNewRecord = $listRecords[$newRecordID];


		// If a custom strata is used for counting randomizations for initial random allocations,
		// get the strata values for this here.
		$useIRStrata = false;
		if ( $this->getProjectSetting( 'initial-random' ) != '' &&
		     $this->getProjectSetting( 'initial-random-strata' ) == 'C' )
		{
			$useIRStrata = true;
			$listIRStratEvents = $this->getProjectSetting( 'ir-strat-event' );
			$listIRStratFields = $this->getProjectSetting( 'ir-strat-field' );
			$listIRStratValues = [];
			for ( $i = 0; $i < count($listIRStratEvents); $i++ )
			{
				$irStratEvent = $listIRStratEvents[$i];
				$irStratField = $listIRStratFields[$i];
				if ( $infoNewRecord[$irStratEvent][$irStratField] == '' )
				{
					return $this->logRandoFailure( $this->tt( 'rando_msg_field_missing',
					                                          $irStratField ), $newRecordID );
				}
				$listIRStratValues[$irStratEvent][$irStratField] =
						$infoNewRecord[$irStratEvent][$irStratField];
			}
		}


		// Remove unrandomized records from the list and perform stratification.
		// Set and increment the randomization number and strata randomization number.
		$randoNum = 1;
		$strataRandoNum = 1;
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
					return $this->logRandoFailure( $this->tt( 'rando_msg_strat_missing',
					                                          $stratField ), $newRecordID );
				}
				$listStratValues[$stratEvent][$stratField] =
						$infoNewRecord[$stratEvent][$stratField];
			}
		}

		foreach ( $listRecords as $recordID => $infoRecord )
		{
			// Do not consider the record being randomized.
			if ( $recordID == $newRecordID || $infoRecord[$randoEvent][$randoField] == '' )
			{
				unset( $listRecords[$recordID] );
				continue;
			}
			// Increment the randomization number.
			$randoNum++;
			// If using custom strata for initial random allocations, check here if the record is in
			// that strata, and increment the strata randomization number if so.
			if ( $useIRStrata )
			{
				$inIRStrata = true;
				for ( $i = 0; $i < count($listIRStratEvents); $i++ )
				{
					$irStratEvent = $listIRStratEvents[$i];
					$irStratField = $listIRStratFields[$i];
					if ( $infoRecord[$irStratEvent][$irStratField] !=
					     $infoNewRecord[$irStratEvent][$irStratField] )
					{
						$inIRStrata = false;
						break;
					}
				}
				if ( $inIRStrata )
				{
					$strataRandoNum++;
				}
			}
			// If stratification is enabled, check that the record is in the strata and disregard it
			// if not. If using the randomization strata for initial random allocations, increment
			// the strata randomization number for records in the strata.
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
				if ( $this->getProjectSetting( 'initial-random-strata' ) == 'S' )
				{
					$strataRandoNum++;
				}
			}
		}

		// If initial random allocations are not being used, or if they are based on the project-
		// wide randomization count, set the strata randomization number equal to the randomization
		// number.
		if ( $this->getProjectSetting( 'initial-random' ) == '' ||
		     $this->getProjectSetting( 'initial-random-strata' ) == '' )
		{
			$strataRandoNum = $randoNum;
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
			return $this->logRandoFailure( $this->tt( 'rando_msg_mmode_missing', $modeField ),
			                               $newRecordID );
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
				return $this->logRandoFailure( $this->tt( 'rando_msg_minim_missing', $minField ),
				                               $newRecordID );
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
			if ( ! in_array( $existingCode, $listRandoCodes ) )
			{
				// If the existing record's allocation is not one of the defined allocation codes,
				// then skip it.
				continue;
			}
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


		// Generate a random value for each randomization code, which will be used during
		// minimization when the minimization totals are equal. If a generated random value is
		// equal to a previously generated random value, it is regenerated so that the random values
		// are guaranteed to be unique.
		$listRandomValues = [];
		$randomRange = count( $listRandoCodes ) * 100;
		foreach ( $listRandoCodes as $code )
		{
			do
			{
				$randomValue = random_int( 1, $randomRange );
			}
			while ( in_array( $randomValue, $listRandomValues ) );
			$listRandomValues[$code] = $randomValue;
		}


		// Determine the lowest common multiple of the allocation ratios. When dividing the
		// minimization totals by the allocation ratio, the totals are first multiplied by the
		// lowest common multiple. This ensures the values following division are still integers.
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

		// Multiply each minimization total by the lowest common multiple, then divide by the
		// allocation ratio.
		$listAdjustedTotals = [];
		foreach ( $listMinTotals as $code => $total )
		{
			$listAdjustedTotals[$code] = ( $total * $ratioCommonMultiple ) / $listCodeRatios[$code];
		}


		// Include the random values generated earlier alongside the adjusted minimization totals,
		// ready for sorting.
		foreach ( $listAdjustedTotals as $code => $total )
		{
			$listAdjustedTotals[$code] = [ 'total' => $total,
			                               'random' => $listRandomValues[$code] ];
		}

		// Sort the allocations by adjusted minimization total.
		// If two totals are equal, sort instead using the random values.
		uasort( $listAdjustedTotals, function( $a, $b )
		{
			if ( $a['total'] == $b['total'] )
			{
				return ( $a['random'] < $b['random'] ) ? -1 : 1;
			}
			return ( $a['total'] < $b['total'] ) ? -1 : 1;
		});

		// Remove the random values from the list of adjusted minimization totals.
		foreach ( $listAdjustedTotals as $code => $total )
		{
			$listAdjustedTotals[$code] = $total['total'];
		}

		// Perform the randomization.
		$listAdjustedCodes = array_keys( $listAdjustedTotals );
		$randoCode = array_shift( $listAdjustedCodes );
		$initialRandom = $this->getProjectSetting( 'initial-random' );
		$initialRandomStrata = ( $this->getProjectSetting( 'initial-random-strata' ) != '' );
		$randomFactor = $this->getProjectSetting( 'random-factor' );
		$randomPercent = $this->getProjectSetting( 'random-percent' );
		$randomApplied = [ 'initial' => false, 'factor' => null, 'threshold' => null,
		                   'values' => [], 'details' => 'none' ];
		$listRandoProportional = [];
		foreach ( $listCodeRatios as $code => $ratio )
		{
			$listRandoProportional =
					array_merge( $listRandoProportional, array_fill( 0, $ratio, $code ) );
		}
		if ( $manualCode !== false && substr( $manualCode, 0, 1 ) == 'a' )
		{
			// Do the manual randomization if requested.
			$randoCode = substr( $manualCode, 1 );
			$randomApplied['details'] = $this->tt( 'diag_manual' );
		}
		elseif ( $initialRandom != '' && $strataRandoNum <= $initialRandom )
		{
			// Always allocate randomly for the specified number of initial records.
			$randoValue = random_int( 0, count( $listRandoProportional ) - 1 );
			$randoCode = $listRandoProportional[$randoValue];
			$randomApplied['initial'] = true;
			$randomApplied['threshold'] = $initialRandom;
			$randomApplied['values'][] = $strataRandoNum;
			$randomApplied['details'] = $this->tt( 'diag_initial_rand',
			                                       ( $initialRandomStrata
			                                         ? $this->tt('diag_initial_rand_strata') : '' ),
			                                       $strataRandoNum, $initialRandom, $randoValue );
		}
		elseif ( $randomFactor == 'S' || $randomFactor == 'C' ) // skip allocation (once/compound)
		{
			// Based on the random percentage, skip an allocation either once or 'compounding'
			// (i.e. random-percent of random-percent times, skip two allocations, and so on...)
			$randomApplied['details'] = '';
			$testPercent = random_int( 0, 1000000 ) / 10000;
			$randomApplied['factor'] = $randomFactor;
			$randomApplied['threshold'] = $randomPercent;
			while ( $testPercent < $randomPercent && count( $listAdjustedCodes ) > 0 )
			{
				$randomApplied['values'][] = $testPercent;
				$randomApplied['details'] .= ( $randomApplied['details'] == '' ) ? '' : '; ';
				$randomApplied['details'] .= $this->tt( 'diag_alloc_skipped', $testPercent,
				                                        $randomPercent, $randoCode );
				$randoCode = array_shift( $listAdjustedCodes );
				if ( $randomFactor == 'S' )
				{
					break;
				}
				$testPercent = random_int( 0, 1000000 ) / 10000;
			}
			// For skip once, note that minimized allocation was used if not skipped.
			// For compounding ($randomFactor == 'C'), always note when the minimized allocation
			// used (after all the skipped allocations).
			if ( $randomApplied['details'] == '' || $randomFactor == 'C' )
			{
				$randomApplied['values'][] = $testPercent;
				$randomApplied['details'] .= ( $randomApplied['details'] == '' ) ? '' : '; ';
				$randomApplied['details'] .= $this->tt( 'diag_alloc_minim',
				                                        $testPercent, $randomPercent );
			}
		}
		elseif ( $randomFactor == 'R' ) // allocate randomly
		{
			$testPercent = random_int( 0, 1000000 ) / 10000;
			$randomApplied['factor'] = $randomFactor;
			$randomApplied['threshold'] = $randomPercent;
			$randomApplied['values'][] = $testPercent;
			if ( $testPercent < $randomPercent )
			{
				$randomApplied['details'] = $this->tt( 'diag_rand_rand',
				                                       $testPercent, $randomPercent );
				$randoValue = random_int( 0, count( $listRandoProportional ) - 1 );
				$randoCode = $listRandoProportional[$randoValue];
				$randomApplied['details'] .= ' (' . $randoValue . ')';
			}
			else
			{
				$randomApplied['details'] = $this->tt( 'diag_rand_minim',
				                                       $testPercent, $randomPercent );
			}
		}

		// If the pack management module is enabled, and a minimization pack category is enabled,
		// request a pack for the chosen allocation.
		$packField = '';
		$packExtraData = [];
		list( $packMgmt, $packMgmtCat ) =
			$this->getPackMgmtModule( $randoField, [ 'getMinimPackField', 'assignMinimPack' ] );
		if ( $packMgmt !== false )
		{
			// Get the field name for the allocation pack ID.
			$packField = $packMgmt->getMinimPackField( $packMgmtCat );
			if ( $packField !== null )
			{
				// Re-obtain the codes from the list of adjusted minimization totals,
				// and prepend the selected randomization code.
				$listAdjustedCodes = array_keys( $listAdjustedTotals );
				array_unshift( $listAdjustedCodes, $randoCode );
				// For manual randomization by pack ID, set the pack ID.
				$packID = null;
				if ( $manualCode !== false && substr( $manualCode, 0, 1 ) == 'p' )
				{
					$packID = substr( $manualCode, 1 );
					$randomApplied = [ 'initial' => false, 'factor' => null, 'threshold' => null,
					                   'values' => [], 'details' => $this->tt( 'diag_manual' ) ];
				}
				// Get the allocation pack details.
				$infoPack = $packMgmt->assignMinimPack( $newRecordID, $listAdjustedCodes,
				                                        $packMgmtCat, $packID );
				// If false returned, a pack could not be assigned.
				if ( $infoPack === false )
				{
					return $this->logRandoFailure( $this->tt( 'rando_msg_no_pack' ), $newRecordID );
				}
				$packID = $infoPack['packID'];
				$randoCode = $infoPack['randoCode'];
				$packExtraData = $infoPack['extraData'];
			}
		}

		// Determine the date/time if required.
		$dateField = $this->getProjectSetting( 'rando-date-field' );
		if ( $dateField != '' )
		{
			$dateTZ = $this->getProjectSetting( 'rando-date-tz' );
			if ( $dateTZ == 'U' ) // UTC
			{
				$dateValue = gmdate( 'Y-m-d H:i:s' );
			}
			elseif ( $dateTZ == 'S' ) // server timezone
			{
				$dateValue = date( 'Y-m-d H:i:s' );
			}
			// Adjust the date/time value if a date or datetime (without seconds) field is used.
			$dateFieldType =
				\REDCap::getDataDictionary( $this->getProjectId(), 'array', false, $dateField
					)[$dateField]['text_validation_type_or_show_slider_number'];
			if ( substr( $dateFieldType, 0, 4 ) == 'date' )
			{
				if ( substr( $dateFieldType, 0, 8 ) != 'datetime' )
				{
					$dateValue = substr( $dateValue, 0, 10 );
				}
				elseif ( substr( $dateFieldType, 0, 16 ) != 'datetime_seconds' )
				{
					$dateValue = substr( $dateValue, 0, -3 );
				}
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
					if ( $eventName != '' )
					{
						$eventName .= '.';
					}
					foreach ( $infoStratEvent as $fieldName => $value )
					{
						$diagData['strata_values']["$eventName$fieldName"] = $value;
					}
				}
				$diagData['strata_records'] = count( $listRecords );
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
				if ( self::$listTREvents === null )
				{
					$eventName = \REDCap::getEventNames( true, true, $eventNum );
				}
				else
				{
					$eventName = is_array( self::$listTREvents ) ? self::$listTREvents[ $eventNum ]
					                                             : false;
				}
				if ( $eventName != '' )
				{
					$eventName .= '.';
				}
				foreach ( $infoMinEvent as $fieldName => $value )
				{
					$diagData['minim_values']["$eventName$fieldName"] = $value;
				}
			}
			$diagAdjustedTotals = (object)$listAdjustedTotals;
			$diagMinTotals = (object)$listMinTotals;
			$diagMinFieldCodes = [];
			foreach ( $listMinFieldTotals as $code => $infoMinField )
			{
				foreach ( $infoMinField as $eventNum => $infoMinEvent )
				{
					if ( self::$listTREvents === null )
					{
						$eventName = \REDCap::getEventNames( true, true, $eventNum );
					}
					else
					{
						$eventName = is_array( self::$listTREvents )
						                ? self::$listTREvents[ $eventNum ] : false;
					}
					if ( $eventName != '' )
					{
						$eventName .= '.';
					}
					foreach ( $infoMinEvent as $fieldName => $value )
					{
						$diagMinFieldCodes[$code]["$eventName$fieldName"] = $value;
					}
				}
			}
			$diagMinFields = (object)$diagMinFieldCodes;
			$diagMinRandom = (object)$listRandomValues;
			$diagData['minim_totals'] = [ 'final' => $diagAdjustedTotals,
			                              'base' => $diagMinTotals,
			                              'fields' => $diagMinFields,
			                              'random' => $diagMinRandom ];
			$diagData['minim_alloc'] = array_keys( $listAdjustedTotals );
			$diagData['minim_random'] = $randomApplied;
			if ( $bogusField != '' )
			{
				$diagData['bogus_value'] = $bogusValue;
			}
			if ( $packField != '' )
			{
				$diagData['pack_id'] = $packID;
				$diagData['pack_new_code'] = ( $randoCode != $listAdjustedCodes[0] );
			}
			$diagData = $this->dataEncrypt( json_encode( $diagData ) );
		}

		// Save the randomization code to the record.
		$inputData[$newRecordID][$randoEvent][$randoField] = $randoCode;
		if ( $dateField != '' )
		{
			$inputData[$newRecordID][$randoEvent][$dateField] = $dateValue;
		}
		if ( $bogusField != '' )
		{
			$inputData[$newRecordID][$randoEvent][$bogusField] = $bogusCode;
		}
		if ( $diagField != '' )
		{
			$inputData[$newRecordID][$randoEvent][$diagField] = $diagData;
		}
		if ( $packField != '' )
		{
			$inputData[$newRecordID][$randoEvent][$packField] = $packID;
		}
		$result = \REDCap::saveData( $this->getProjectId(), 'array', $inputData, 'normal',
		                             'YMD', 'flat', null, false );
		if ( is_string( $result['errors'] ) )
		{
			$result['errors'] = [ $result['errors'] ];
		}
		if ( count( $result['errors'] ) > 0 )
		{
			return $this->logRandoFailure( $this->tt('rando_save_error') . ":\n" .
			                               implode( "\n", $result['errors'] ), $newRecordID );
		}
		\REDCap::logEvent( $this->tt('log_success'),
		                   ( $randoField .
		                     ( $dateField == '' ? '' : "\n$dateField" ) .
		                     ( $bogusField == '' ? '' : "\n$bogusField" ) .
		                     ( $diagField == '' ? '' : "\n$diagField" ) .
		                     ( $packField == '' ? '' : "\n$packField" ) ),
		                   null, $newRecordID, $randoEvent, $this->getProjectId() );
		// Store extra pack data.
		if ( ! empty( $packExtraData ) )
		{
			$inputExtraData = [];
			$inputExtraData[$newRecordID][$randoEvent] = $packExtraData;
			\REDCap::saveData( $this->getProjectId(), 'array', $inputExtraData,
			                   'normal', 'YMD', 'flat');
		}
		return true;
	}


	// Writes the supplied failure description to the project log.
	// The description is returned by this function to simplify using or returning the description
	// in the randomization function following logging.
	function logRandoFailure( $description, $recordID )
	{
		\REDCap::logEvent( $this->tt('log_failure'), $description, null, $recordID,
		                   null, $this->getProjectId() );
		return $description;
	}


	function validateSettings( $settings )
	{
		$errMsg = '';

		if ( $this->getProjectID() === null )
		{
			$encryptionKey = $this->getSystemSetting( 'encryption-key' );
			if ( $encryptionKey == '' || strlen( base64_decode( $encryptionKey ) ) != 32 )
			{
				return $this->tt( 'validate_setting_encryption_key' );
			}
			return null;
		}

		$rando = true;
		if ( $settings['rando-event'] == '' || $settings['rando-field'] == '' )
		{
			$rando = false;
		}

		// Check that the rando date/time timezone is specified if a date/time field is specified.
		if ( $settings['rando-date-field'] != '' && $settings['rando-date-tz'] == '' )
		{
			$errMsg .= "\n- " . $this->tt('validate_setting_tz');
		}

		// Check that stratification variables are correctly specified.
		if ( $settings['stratify'] )
		{
			for ( $i = 0; $i < count( $settings['stratification'] ); $i++ )
			{
				if ( $settings['strat-event'][$i] == '' || $settings['strat-field'][$i] == '' )
				{
					$errMsg .= "\n- " . $this->tt( 'validate_setting_strat', ($i+1) );
				}
			}
		}

		// Check that there is only one minimization mode, unless multiple modes selected.
		if ( !$settings['mode-variable'] && count( $settings['mode'] ) > 1 )
		{
			$errMsg .= "\n- " . $this->tt('validate_setting_1mode');
		}

		// If multiple modes selected, ensure that the mode variable is specified.
		if ( $settings['mode-variable'] &&
		     ( $settings['mode-event'] == '' || $settings['mode-field'] == '' ) )
		{
			$errMsg .= "\n- " . $this->tt('validate_setting_mode_missing');
		}

		// Check that the minimization modes have been specified correctly.
		$listCodeDescs = [];
		for ( $i = 0; $i < count( $settings['mode'] ); $i++ )
		{
			// If using multiple modes, check the mode value has been entered.
			if ( $settings['mode-variable'] && $settings['minim-mode'][$i] == '' )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_value_missing', ($i+1) );
			}
			// Check that the allocation code, description and ratio have been entered.
			// Ensure that the allocation ratio is an integer greater than 0.
			for ( $j = 0; $j < count( $settings['minim-codes'][$i] ); $j++ )
			{
				if ( $settings['rando-code'][$i][$j] == '' && ( $rando || $i + $j > 0 ) )
				{
					$errMsg .= "\n- " .
					           $this->tt( 'validate_setting_code_missing', ($i+1), ($j+1) );
				}
				if ( $settings['rando-desc'][$i][$j] == '' && ( $rando || $i + $j > 0 ) )
				{
					$errMsg .= "\n- " .
					           $this->tt( 'validate_setting_desc_missing', ($i+1), ($j+1) );
				}
				if ( !preg_match( '/^[1-9][0-9]*$/', $settings['rando-ratio'][$i][$j] ) &&
				     ( $rando || $i + $j > 0 ) )
				{
					$errMsg .= "\n- " .
					           $this->tt( 'validate_setting_ratio_missing', ($i+1), ($j+1) );
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
						$errMsg .= "\n- " .
						           $this->tt( 'validate_setting_code_mismatch', ($i+1), ($j+1) ) .
						           "\n  " . $this->tt('validate_setting_code_mismatch2');
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
					$errMsg .= "\n- " .
					           $this->tt( 'validate_setting_minim_missing', ($i+1), ($j+1) );
				}
			}
		}

		// Check that a percentage is specified if a random factor has been chosen.
		if ( $settings['random-factor'] != '' &&
		     ( ! is_numeric( $settings['random-percent'] ) || $settings['random-percent'] < 0 ||
		       $settings['random-percent'] > 100 ) )
		{
			$errMsg .= "\n- " . $this->tt('validate_setting_random_pc');
		}

		// If initial random allocations is specified...
		if ( $settings['initial-random'] != '' )
		{
			// Check that the number of allocations is an integer value.
			if ( ! preg_match( '/^(0|[1-9][0-9]*)$/', $settings['initial-random'] ) )
			{
				$errMsg .= "\n- " . $this->tt('validate_setting_random_num');
			}

			// Check that 'use randomization strata' is only chosen if stratification enabled.
			if ( $settings['initial-random-strata'] == 'S' && ! $settings['stratify'] )
			{
				$errMsg .= "\n- " . $this->tt('validate_setting_random_strata');
			}

			// If using custom strata, check that the variables are correctly specified.
			if ( $settings['initial-random-strata'] == 'C' )
			{
				for ( $i = 0; $i < count( $settings['ir-stratification'] ); $i++ )
				{
					if ( $settings['ir-strat-event'][$i] == '' ||
					     $settings['ir-strat-field'][$i] == '' )
					{
						$errMsg .= "\n- " . $this->tt( 'validate_setting_random_var', ($i+1) );
					}
				}
			}
		}

		if ( $errMsg != '' )
		{
			return $this->tt('validate_setting') . ":$errMsg";
		}
		return null;
	}

}

