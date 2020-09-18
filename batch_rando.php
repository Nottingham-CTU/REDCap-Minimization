<?php

if ( ! $module->framework->getUser()->isSuperUser() )
{
	exit;
}

if ( !empty( $_POST ) && in_array( $_POST['csrf_token'], $_SESSION['redcap_csrf_token'] ) &&
     isset( $_POST['rando_record'] ) )
{
	foreach ( $_POST['rando_record'] as $record )
	{
		$module->performRando( $record );
	}
}

$randoEvent = $module->getProjectSetting( 'rando-event' );
$randoField = $module->getProjectSetting( 'rando-field' );
$bogusField = $module->getProjectSetting( 'bogus-field' );
$diagField = $module->getProjectSetting( 'diag-field' );
$listRecords = REDCap::getData( 'array' );

// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

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

<div class="projhdr"><i class="far fa-list-alt"></i> Batch Randomization (minimization)</div>

<form method="post" id="batch-rando-frm">
 <table class="dataTable cell-border no-footer">
  <thead>
   <tr>
    <th>Record</th>
    <th>Randomize</th>
<?php

$recsPerRow = 5;
for ( $i = 1; $i < $recsPerRow; $i++ )
{
	if ( count( $listRecords ) > $i )
	{

?>
    <th></th>
    <th>Record</th>
    <th>Randomize</th>
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
    <td style="text-align:right"><?php echo htmlspecialchars( $recordID ); ?></td>
<?php

	if ( isset( $infoRecord[$randoEvent][$randoField] ) &&
	     $infoRecord[$randoEvent][$randoField] != '' )
	{
		$details = 'Allocation: ' . $infoRecord[$randoEvent][$randoField] .
		           ' (' . $module->getDescription( $infoRecord[$randoEvent][$randoField] ) . ')';
		if ( $bogusField != '' )
		{
			$details .= "\nFake allocation: " . $infoRecord[$randoEvent][$bogusField] . ' (' .
			            $module->getDescription( $infoRecord[$randoEvent][$bogusField] ) . ')';
		}
		if ( $diagField != '' )
		{
			$diag = json_decode( $infoRecord[$randoEvent][$diagField], true );
			$details .= "\n\nRandomization number: " . $diag['num'];
			if ( $diag['stratify'] )
			{
				$details .= "\nStratification variables:";
				foreach ( $diag['strata_values'] as $fieldName => $value )
				{
					$details .= "\n    $fieldName: $value";
				}
			}
			else
			{
				$details .= "\nStratification variables: none";
			}
			$details .= "\nMinimization variables:";
			foreach ( $diag['minim_values'] as $fieldName => $value )
			{
				$details .= "\n    $fieldName: $value";
			}
			$details .= "\nMinimization totals:";
			foreach ( $diag['minim_totals']['final'] as $minimCode => $total )
			{
				$details .= "\n    $minimCode: $total";
			}
			$details .= "\nCodes for random selection:\n   '" .
			            implode( "', '", $diag['codes_full'] ) . "'";
			$details .= "\nRandom factor details:\n   " . $diag['minim_random']['details'];
		}
		$details = str_replace( "\n", '&#10;', htmlspecialchars( $details ) );

?>
    <td>&nbsp;<i class="fas fa-random" title="<?php echo $details; ?>"></i></td>
<?php

	}
	else
	{

?>
    <td>&nbsp;<input type="checkbox" checked="checked"
                     name="rando_record[]" value="<?php echo $recordID; ?>"></td>
<?php

	}
	$count++;
}
if ( count( $listRecords ) % $recsPerRow != 0 )
{
	echo str_repeat( "   <td></td>\n", ( $recsPerRow - count( $listRecords ) % $recsPerRow ) * 3 );
}

?>
   </tr>
  </tbody>
 </table>
 <p>
  <a href="#" onclick="$('#batch-rando-frm input[type=checkbox]').prop('checked',true);return false">Select All</a>
  |
  <a href="#" onclick="$('#batch-rando-frm input[type=checkbox]').prop('checked',false);return false">Select None</a>
 </p>
 <p>&nbsp;</p>
 <p>
  <input type="hidden" name="csrf_token" value="<?php echo System::getCsrfToken(); ?>">
  <button class="jqbuttonmed ui-button ui-corner-all ui-widget"
          onclick="return confirm('Randomize the selected records?')">
   <span style="vertical-align:middle;color:green"><i class="fas fa-random"></i> Randomize selected</span>
  </button>
 </p>
</form>

<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
