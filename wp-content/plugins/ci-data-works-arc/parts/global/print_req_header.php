  <?php
	$upload_dir = tr_wp_upload_dir();
	$logo = $current_lab['lab_logo'];
	$logo_path = $upload_dir['basedir'] . '/logos/' . $logo;
	$logo_url = $upload_dir['baseurl'] . '/logos/' . $logo;
	?>

  <input type="button" class="button print_button" onclick="window.print()" value="Print" />

  <table class="headertbl" style="width:100%;border:none">
  	<tr>
  		<td style="width:28%">
  			<h3><?php echo $current_lab['lab_name'] ?></h3>
  			<h4>
  				<?php
					echo $current_lab['lab_address'] . ', ';
					echo $current_lab['lab_city'] . '<br>';
					echo $current_lab['lab_state'] . ' ';
					echo $current_lab['lab_zip'];
					?>
  			</h4>
  		</td>
  		<td class="center" style="width:44%">
  			<img src="<?php echo $logo_url ?>" style="max-width:400px;max-height:80px" />
  		</td>
  		<td style="width:28%">
  			<?php include(trpdd_get_file_path('global', 'get_form_barcode')); ?>
  		</td>
  	</tr>
  </table>