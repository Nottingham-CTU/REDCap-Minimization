<?php

namespace Nottingham\Minimization;

// Exit here if not project designer.
if ( $module->getProjectSetting( 'diag-field' ) == null ||
     ( $module->getSystemSetting( 'config-require-user-permission' ) == 'true' &&
       ! in_array( 'minimization',
                   $module->getUser()->getRights()['external_module_config'] ) ) ||
     ! $module->getUser()->hasDesignRights() )
{
	exit;
}


// Get the randomization fields.
$listFieldNames = \REDCap::getFieldNames();
$randoEvent = $module->getProjectSetting( 'rando-event' );
$randoField = $module->getProjectSetting( 'rando-field' );
$bogusField = $module->getProjectSetting( 'bogus-field' );
if ( ! in_array( $bogusField, $listFieldNames ) )
{
	$bogusField = null;
}
$diagField = $module->getProjectSetting( 'diag-field' );
if ( ! in_array( $diagField, $listFieldNames ) )
{
	$diagField = null;
}


// Get the project status.
$isDev = $module->getProjectStatus() == 'DEV';
$canTestRun = ( $diagField != '' );


// Get the test run status.
$testRunStatus = $module->getProjectSetting( 'testrun-status' );
if ( $testRunStatus == '' )
{
	$testRunStatus = false;
}
else
{
	$testRunStatus = json_decode( $testRunStatus, true );
	if ( $testRunStatus['timestamp'] + 1800 < time() )
	{
		$testRunStatus = false;
	}
}


// If returning the test run status only.
if ( isset( $_GET['testrunstatus'] ) )
{
	header( 'Content-Type: application/json' );
	if ( $testRunStatus === false )
	{
		echo 'false';
	}
	else
	{
		echo json_encode( $module->tt( 'testrun_progress', $testRunStatus['current_run'],
		                               $testRunStatus['total_runs'],
		                               $testRunStatus['current_record'],
		                               $testRunStatus['total_records'] ) );
	}
	exit;
}


// If submission...
if ( !empty( $_POST ) && in_array( $_POST['csrf_token'], $_SESSION['redcap_csrf_token'] ) )
{
	// Perform the batch randomization.
	// Note that errors will not be displayed. If any records don't randomize, the errors can be
	// viewed in the log.
	if ( isset( $_POST['rando_record'] ) )
	{
		foreach ( $_POST['rando_record'] as $record )
		{
			$module->performRando( $record );
		}
		if ( isset( $_SERVER['HTTP_X_RC_MIN_BATCH'] ) )
		{
			header( 'Content-Type: application/json' );
			echo 'null';
			exit;
		}
	}

	// Perform the test runs.
	if ( $isDev && isset( $_POST['testrun_records'] ) && isset( $_POST['testrun_runs'] ) &&
	     preg_match( '/^1?[0-9]{1,5}$/', $_POST['testrun_records'] ) &&
	     preg_match( '/^1?[0-9]$/', $_POST['testrun_runs'] ) )
	{
		ignore_user_abort( true );
		\System::increaseMaxExecTime( 300 );
		// Prepare the test run status.
		$dataTable = method_exists( '\REDCap', 'getDataTable' )
		             ? \REDCap::getDataTable( $module->getProjectId() ) : ( 'redcap' . '_data' );
		$fileName =
				trim( preg_replace( '/[^A-Za-z0-9-]+/', '_', \REDCap::getProjectTitle() ), '_-' ) .
				'_' . $module->tt('dldiag_title') . '_' . date( 'Ymd-Hi' ) . '_';
		$testRunStatus = [ 'timestamp' => time(), 'current_record' => 0,
		                   'total_records' => intval( $_POST['testrun_records'] ),
		                   'current_run' => 1, 'total_runs' => intval( $_POST['testrun_runs'] ),
		                   'longitudinal' => \REDCap::isLongitudinal(), 'datatable' => $dataTable,
		                   'filename' => $fileName, 'testdata' => [], 'events' => false ];
		// Prepare the input parameters.
		$listDataFields = [];
		for ( $i = 0; $i < count( $_POST['testrun_field'] ); $i++ )
		{
			if ( $_POST['testrun_field'][$i] == '' || $_POST['testrun_values'] == '' )
			{
				continue;
			}
			$infoDataField = [ 'field' => $_POST['testrun_field'][$i] ];
			if ( \REDCap::isLongitudinal() )
			{
				if ( $_POST['testrun_event'][$i] == '' )
				{
					continue;
				}
				$infoDataField['event'] = $_POST['testrun_event'][$i];
			}
			$infoDataField['values'] = [];
			foreach ( explode( "\n", $_POST['testrun_values'][$i] ) as $v )
			{
				$v = trim( $v );
				if ( $v != '' )
				{
					$infoDataField['values'][] = $v;
				}
			}
			if ( ! empty( $infoDataField['values'] ) )
			{
				$listDataFields[] = $infoDataField;
			}
		}
		// Save the test run parameters to the file repository.
		$testRunStatus['testdata'] = $listDataFields;
		$testRunStatus['events'] = \REDCap::getEventNames( true, true );
		$module->setProjectSetting( 'testrun-status', json_encode( $testRunStatus ) );
		$paramsData = json_encode( [ 'timestamp' => gmdate( 'Y-m-d H:i' ),
		                             'testdata' => $listDataFields,
		                             'records' => $testRunStatus['total_records'],
		                             'runs' => $testRunStatus['total_runs'] ], JSON_PRETTY_PRINT );
		$paramsFileName = $fileName . $module->tt('testrun_params') . '.json';
		file_put_contents( APP_PATH_TEMP . $paramsFileName, $paramsData );
		$paramsID = \REDCap::storeFile( APP_PATH_TEMP . $paramsFileName, $module->getProjectId() );
		\REDCap::addFileToRepository( $paramsID, $module->getProjectId() );
		unlink( APP_PATH_TEMP . $paramsFileName );
		exit;
	}
}

// Get the records.
$listRecords = \REDCap::getData( 'array' );

// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// Define page style.
$style = '
	table.dataTable thead tr th {
		background-color: #FFFFE0;
		border-top: 1px solid #aaaaaa;
		border-bottom: 1px solid #aaaaaa;
	}
	table.dataTable.cell-border thead tr th {
		border-right: 1px solid #ddd;
	}
	table.dataTable.cell-border thead tr th:first-child {
		border-left: 1px solid #ddd;
	}
	table.dataTable tr td a.rl { font-size:8pt;font-family:Verdana;text-decoration:underline; }
	table.dataTable tr th { line-height: 11px; }
	table.dataTable tr th.rpthdrc { border-top:0; }
	table.dataTable tr th.rptchclbl { border-bottom:1px dashed #ccc; }
	table.dataTable tbody td, table.dataTable thead th { padding:5px; }
	table.dataTable tbody tr:nth-child(2n) { background-color: #eee !important; }
	table.dataTable tbody tr:nth-chlid(2n+1) { background-color: #fcfef5 !important; }
	';
echo '<script type="text/javascript">',
	 '(function (){var el = document.createElement(\'style\');',
	 'el.setAttribute(\'type\',\'text/css\');',
	 'el.innerText = \'', addslashes( preg_replace( "/[\t\r\n ]+/", ' ', $style ) ), '\';',
	 'document.getElementsByTagName(\'head\')[0].appendChild(el)})()</script>';

?>

<div class="projhdr"><i class="far fa-list-alt"></i> <?php echo $module->tt('testrun_title'); ?></div>

<?php
if ( $isDev && $canTestRun && \REDCap::versionCompare( REDCAP_VERSION, '12.5.0', '>=' ) )
{
?>

<form method="post" id="rando-runs-frm">
 <table class="dataTable cell-border no-footer">
  <thead>
   <tr>
    <th><?php echo $module->tt('testrun_title'); ?></th>
   </tr>
  </thead>
  <tbody>
<?php
	if ( $testRunStatus === false )
	{
?>
   <tr>
    <td>
     <b><?php echo $module->tt('testrun_fields'); ?></b>
     <table><tr style="background-color:unset !important">
       <td style="vertical-align:top">
<?php
		if ( \REDCap::isLongitudinal() )
		{
?>
        <select name="testrun_event[]">
         <option value="">- <?php echo $module->tt('setting_event'); ?> -</option>
<?php
			foreach ( \REDCap::getEventNames( true ) as $eventID => $eventName )
			{
				$eventLabel = \REDCap::getEventNames( false, true, $eventID );
?>
         <option value="<?php echo $module->escapeHTML( $eventName ); ?>">
          <?php echo $module->escapeHTML( $eventLabel ), "\n"; ?>
         </option>
<?php
			}
?>
        </select>
<?php
		}
?>
       </td>
       <td style="vertical-align:top">
        <select name="testrun_field[]">
         <option value="">- <?php echo $module->tt('setting_field'); ?> -</option>
<?php
		foreach ( $listFieldNames as $fieldName )
		{
?>
         <option value="<?php echo $module->escapeHTML( $fieldName ); ?>">
          <?php echo $module->escapeHTML( $fieldName ), "\n"; ?>
         </option>
<?php
		}
?>
        </select>
       </td>
       <td style="vertical-align:top">
        <textarea name="testrun_values[]"
                  placeholder="<?php echo $module->tt('testrun_fieldvals'); ?>"
                  style="height:4em;font-size:0.8em;width:350px;overflow-y:scroll"></textarea>
       </td>
      </tr>
      <tr style="background-color:unset !important">
       <td colspan="3" style="text-align:right">
        <a onclick="$(this).closest('tr').before($(this).closest('tr').prev().clone());
                    $(this).closest('tr').prev().find('select, textarea').val('');return false"
           href="#"><i class="fas fa-square-plus"></i> <?php echo $module->tt('testrun_addfield'); ?></a>
       </td>
      </tr>
     </table>
    </td>
   </tr>
   <tr>
    <td>
     <b><?php echo $module->tt('testrun_runrecords'); ?></b>
     <input type="number" name="testrun_records" min="1" max="199999" style="width:8em">&nbsp;&nbsp;
     <b><?php echo $module->tt('testrun_numruns'); ?></b>
     <input type="number" name="testrun_runs" min="1" max="19" style="width:5em">
    </td>
   </tr>
   <tr>
    <td class="error" style="font-weight:bold">
     <i class="fas fa-triangle-exclamation"></i>
     <?php echo $module->tt('testrun_warnmsg'), "\n"; ?>
    </td>
   </tr>
   <tr>
    <td>
     <input type="hidden" name="csrf_token" value="<?php echo \System::getCsrfToken(); ?>">
     <button id="test-runs-button" class="jqbuttonmed ui-button ui-corner-all ui-widget"
             onclick="doTestRuns();return false">
      <span style="vertical-align:middle;color:green">
       <i class="fas fa-square" style="opacity:0.2"></i>
       <i class="fas fa-random" style="margin-left:-15px;opacity:0.4"></i>
       <i class="fas fa-random" style="margin-left:-12px"></i>
       <?php echo $module->tt('testrun_submit'), "\n"; ?>
      </span>
     </button>
     &nbsp;
     <?php echo $module->tt('testrun_filerepo'), "\n"; ?>
    </td>
   </tr>
<?php
	}
	else
	{
?>
   <tr>
    <td>
     <i class="fas fa-list-check"></i>
     <span id="test-runs-progress">
      <?php echo $module->tt( 'testrun_progress', $testRunStatus['current_run'],
                              $testRunStatus['total_runs'], $testRunStatus['current_record'],
                              $testRunStatus['total_records'] ), "\n"; ?>
     </span>
     &nbsp;
    </td>
   </tr>
   <tr>
    <td><?php echo $module->tt('testrun_progress2'); ?></td>
   </tr>
<?php
	}
?>
  </tbody>
 </table>
</form>
<?php
	if ( $testRunStatus === false )
	{
?>
<script type="text/javascript">
  $('#rando-runs-frm').on('drop',function(ev)
  {
    ev.preventDefault()
    var vFiles = ev.originalEvent.target.files || ev.originalEvent.dataTransfer.files
    if ( typeof( vFiles[0].type ) == 'string' && vFiles[0].type == 'application/json' )
    {
      var vReader = new FileReader()
      vReader.onload = function()
      {
        var vData = JSON.parse(vReader.result)
        if ( typeof( vData.records ) == 'number' )
        {
          $('[name="testrun_records"]').val( vData.records )
        }
        if ( typeof( vData.runs ) == 'number' )
        {
          $('[name="testrun_runs"]').val( vData.runs )
        }
        if ( typeof( vData.testdata ) == 'object' )
        {
          for ( var i = 0; i < vData.testdata.length; i++ )
          {
            if ( i > 0 && $('[name="testrun_field[]"]').length <= i )
            {
              $('#rando-runs-frm a').trigger('click')
            }
            var vFieldData = vData.testdata[i]
            if ( typeof( vFieldData.event ) == 'string' &&
                 $('[name="testrun_event[]"]').length > 0 )
            {
              $('[name="testrun_event[]"]').slice(i,i+1).val( vFieldData.event )
            }
            if ( typeof( vFieldData.field ) == 'string' )
            {
              $('[name="testrun_field[]"]').slice(i,i+1).val( vFieldData.field )
            }
            if ( typeof( vFieldData.values ) == 'object' )
            {
              $('[name="testrun_values[]"]').slice(i,i+1).val( vFieldData.values.join('\n') )
            }
          }
        }
      }
      vReader.readAsText(vFiles[0])
    }
  })
  $('#rando-runs-frm').on('dragover',function(ev)
  {
    ev.preventDefault()
  })
</script>
<?php
	}
?>
<p>&nbsp;</p>
<hr style="width:95%">
<p>&nbsp;</p>

<?php
}

if ( $testRunStatus === false )
{
?>

<div class="projhdr"><i class="far fa-list-alt"></i> <?php echo $module->tt('batch_title'); ?></div>

<form method="post" id="batch-rando-frm">
 <table class="dataTable cell-border no-footer">
  <thead style="position:sticky;top:0px">
   <tr>
    <th><?php echo $module->tt('record'); ?></th>
    <th><?php echo $module->tt('rando'); ?></th>
<?php

	// Lay out the records in a grid.
	$recsPerRow = 5;
	for ( $i = 1; $i < $recsPerRow; $i++ )
	{
		if ( count( $listRecords ) > $i )
		{

?>
    <th style="width:15px"></th>
    <th><?php echo $module->tt('record'); ?></th>
    <th><?php echo $module->tt('rando'); ?></th>
<?php

		}
	}

?>
   </tr>
  </thead>
  <tbody>
   <tr>
<?php

	$count = 0;
	foreach ( $listRecords as $recordID => $infoRecord )
	{
		if ( $count > 0 )
		{
			if ( $count % $recsPerRow == 0 )
			{

?>
   </tr>
   <tr>
<?php

			}
			else
			{

?>
    <td></td>
<?php

			}
		}

?>
    <td style="text-align:right"><?php echo $module->escapeHTML( $recordID ); ?></td>
<?php

		// For randomized records, display the randomization icon and show the allocation and
		// diagnostic data in a tooltip.
		if ( isset( $infoRecord[$randoEvent][$randoField] ) &&
		     $infoRecord[$randoEvent][$randoField] != '' )
		{
			$details = $module->tt('batch_alloc') . ': ' . $infoRecord[$randoEvent][$randoField] .
			           ' (' . $module->getDescription( $infoRecord[$randoEvent][$randoField] ) . ')';
			if ( $bogusField != '' )
			{
				$details .= "\n" . $module->tt('batch_alloc_bogus') . ': ' .
				            $infoRecord[$randoEvent][$bogusField] . ' (' .
				            $module->getDescription( $infoRecord[$randoEvent][$bogusField] ) . ')';
			}
			if ( $diagField != '' )
			{
				$diag = json_decode( $module->dataDecrypt( $infoRecord[$randoEvent][$diagField] ),
				                     true );
				$details .= "\n\n" . $module->tt('batch_rando_num') . ': ' . $diag['num'];
				if ( $diag['stratify'] )
				{
					$details .= "\n" . $module->tt('batch_strat') . ':';
					foreach ( $diag['strata_values'] as $fieldName => $value )
					{
						$details .= "\n    $fieldName: $value";
					}
				}
				else
				{
					$details .= "\n" . $module->tt('batch_strat') . ': none';
				}
				$details .= "\n" . $module->tt('batch_minim') . ':';
				foreach ( $diag['minim_values'] as $fieldName => $value )
				{
					$details .= "\n    $fieldName: $value";
				}
				$details .= "\n" . $module->tt('batch_totals') . ':';
				foreach ( $diag['minim_totals']['final'] as $minimCode => $total )
				{
					$details .= "\n    $minimCode: $total";
				}
				$details .= "\n" . $module->tt('batch_rand_codes') . ":\n   '" .
				            implode( "', '", $diag['codes_full'] ) . "'";
				$details .= "\n" . $module->tt('batch_rand_factor') . ":\n   " .
				            $diag['minim_random']['details'];
			}
			$details = str_replace( "\n", '&#10;', $module->escapeHTML( $details ) );

?>
    <td>&nbsp;<i class="fas fa-random" title="<?php echo $details; ?>"></i></td>
<?php

		}
		// For unrandomized records, display a checkbox (checked by default) so the record can be
		// selected on deselected for randomization.
		else
		{

?>
    <td>&nbsp;<input type="checkbox" checked="checked"
                     name="rando_record[]" value="<?php echo $module->escapeHTML( $recordID ); ?>"></td>
<?php

		}
		$count++;
	}
	if ( count( $listRecords ) > $recsPerRow && count( $listRecords ) % $recsPerRow != 0 )
	{
		echo str_repeat( "   <td></td>\n", ( $recsPerRow - count( $listRecords ) % $recsPerRow ) * 3 );
	}


	// Show options to select or deselect all the checkboxes, and provide a button to perform
	// randomization on the selected records.
?>
   </tr>
  </tbody>
 </table>
 <p>
  <a href="#" onclick="$('#batch-rando-frm input[type=checkbox]').prop('checked',true);return false"><?php
	echo $module->tt('batch_sel_all');
?></a>
  |
  <a href="#" onclick="$('#batch-rando-frm input[type=checkbox]').prop('checked',false);return false"><?php
	echo $module->tt('batch_sel_none');
?></a>
 </p>
 <p>&nbsp;</p>
 <p>
  <input type="hidden" name="csrf_token" value="<?php echo \System::getCsrfToken(); ?>">
  <button id="batch-rando-button" class="jqbuttonmed ui-button ui-corner-all ui-widget"
          onclick="doBatchRando();return false">
   <span style="vertical-align:middle;color:green"><i class="fas fa-random"></i> <?php
	echo $module->tt('batch_rando');
?></span>
  </button>
 </p>
</form>
<script type="text/javascript">
  function doBatchRando()
  {
    if ( confirm('<?php echo $module->tt('batch_rando_confirm'); ?>') )
    {
      var vSelected = $('#batch-rando-frm input[type=checkbox]:checked')
      var vButton = $('#batch-rando-button')
      vButton.css('display', 'none')
      vButton.after('<progress id="batch-rando-progress" value="0"></progress>')
      var vProgress = $('#batch-rando-progress')[0]
      vProgress.max = vSelected.length
      for ( var i = 0; i < vSelected.length; i++ )
      {
        (function ()
        {
          var vPostData = vSelected[i].value
          var vIndex = i
          var vRandoReq = setInterval( function ()
          {
            if ( vProgress.value == vIndex )
            {
              clearInterval( vRandoReq )
              $.ajax( { url : '<?php echo $module->getUrl( 'batch_rando.php' ); ?>',
                        method : 'POST',
                        data : { 'rando_record[]' : vPostData,
                                 csrf_token : $('[name=csrf_token')[0].value },
                        headers : { 'X-RC-Min-Batch' : '1' },
                        dataType : 'json',
                        success : function ( result )
                        {
                          vProgress.value++
                        }
                      } )
            }
          }, 250 )
        })()
      }
      var vCheckComplete = setInterval( function()
      {
        if ( vProgress.value == vProgress.max )
        {
          window.location = '<?php echo $module->getUrl( 'batch_rando.php' ); ?>'
          clearInterval( vCheckComplete )
        }
      }, 500 )
    }
  }
  function doTestRuns()
  {
    var vForm = $('#rando-runs-frm')
    if ( $('[name="testrun_records"]').val() != '' && $('[name="testrun_runs"]').val() != '' &&
         confirm( '<?php echo $module->tt('testrun_warnmsg'); ?>\n\n' +
                  '<?php echo $module->tt('testrun_confirm'); ?>' ) )
    {
      $('#test-runs-button').css('display', 'none')
      $.ajax( { url : '<?php echo $module->getUrl( 'batch_rando.php' ); ?>',
                method : 'POST',
                data : vForm.serialize()
              } )
      setTimeout( function()
                  {
                    window.location.href = '<?php echo $module->getUrl( 'batch_rando.php' ); ?>'
                  }, 1000 )
    }
  }
</script>
<?php
}
else
{
?>
<script type="text/javascript">
  setInterval( function ()
  {
    $.get( '<?php echo $module->getUrl( 'batch_rando.php' ); ?>&testrunstatus=1',
           function( vData )
           {
             if ( vData === false )
             {
               window.location.href = '<?php echo $module->getUrl( 'batch_rando.php' ); ?>'
             }
             else
             {
               $('#test-runs-progress').text( vData )
             }
           }, 'json' )
  }, 10000)
</script>
<?php
}
?>

<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

