<?php

// Only allow administrators to access this page.
if ( ! $module->framework->getUser()->isSuperUser() )
{
	exit;
}

// Upon submission, perform the batch randomization.
// Note that errors will not be displayed. If any records don't randomize, the errors can be viewed
// in the log.
if ( !empty( $_POST ) && in_array( $_POST['csrf_token'], $_SESSION['redcap_csrf_token'] ) &&
     isset( $_POST['rando_record'] ) )
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

// Get the randomization fields.
$randoEvent = $module->getProjectSetting( 'rando-event' );
$randoField = $module->getProjectSetting( 'rando-field' );
$bogusField = $module->getProjectSetting( 'bogus-field' );
$diagField = $module->getProjectSetting( 'diag-field' );
$listRecords = REDCap::getData( 'array' );

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
			$diag = json_decode( $infoRecord[$randoEvent][$diagField], true );
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
  <input type="hidden" name="csrf_token" value="<?php echo System::getCsrfToken(); ?>">
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
</script>

<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

