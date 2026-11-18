<?php
class TR_Error_Log
{


	function scan_dir($dir)
	{
		$ignored = array('.', '..', '.svn', '.htaccess');

		$files = array();
		$fdir = file_exists($dir) ? scandir($dir) : false;
		if ($fdir) {
			foreach ($fdir as $file) {
				if (in_array($file, $ignored)) continue;
				$files[$file] = filemtime($dir . '/' . $file);
			}
			arsort($files);
			$files = array_keys($files);
		}
		return $files;
	}

	function display_dir($dir)
	{
		$files  = $this->scan_dir($dir);
		if (count($files) > 0) {
			echo '<table>';
			echo '<tr>';
			echo '<th>File</th>';
			echo '<th style="width:140px">Date</th>';
			echo '<th>Size</th>';
			echo '</tr>';
			foreach ($files as $f) {
				if (!in_array($f, array('.', '..')) && stripos($f, '.gz') === false) {
					$filepath         = $dir . '/' . $f;
					echo '<tr>';
					echo '<td>';
					echo '<a href="' . Tr_Base_Class_V6::link(array('f' => $f, 'dirt' => stripos($dir, 'nginx') !== false ? 'nginx' : 'errors', 'clear' => ''), false) . '">' . $f . '</a>';
					echo '</td>';
					echo '<td>';
					echo date('Y-m-d H:i', filemtime($filepath));
					echo '</td>';
					echo '<td>';
					echo trlr_format_byte(filesize($filepath));
					echo '</td>';
					echo '</tr>';
				}
			}
			echo '</table>';
		} else {
			echo '<br><br>';
			echo $dir;
			echo '<p >Not found</p>';
		}
	}

	function display_file($filepath)
	{

		if (isset($_REQUEST['clear']) && $_REQUEST['clear'] > time() - 7) {
			file_put_contents($filepath, '');
		}

		$f = fopen($filepath, 'r');
		if (!$f) {
			echo 'Cant not open file: ' . $filepath;
			return;
		}


		echo basename($filepath);
		echo ' <a href="' . Tr_Base_Class_V6::link(array('f' => basename($filepath), 'dirt' => stripos($filepath, 'nginx') !== false ? 'nginx' : 'errors', 'clear' => time()), false) . '">Clear</a>';

?>
		<style>
			li {
				font-size: 11px;
				margin-top: 5px;
			}

			#mainlist div {
				font-size: 11px;
			}
		</style>

		<div id="mainlist" style="max-height:1006px;overflow:auto">
			<?php

			$hasdata = false;
			$count_row = 0;
			while (!feof($f)) {
				$row = fgets($f);
				if (
					empty($row)
					|| strpos($row, 'is forbidden') > 0
					|| strpos($row, 'access forbidden by rule') > 0
					|| strpos($row, 'bridge_clinic') > 0
					|| strpos($row, 'continue 2') > 0
				) {
					continue;
				}
				$row = str_replace('FastCGI sent in stderr:', '', $row);
				$count_row++;
				echo '<li>' . $row . '</li>';

				if ($count_row > 1000) {
					break;
				}

				$hasdata = true;
			}
			?>
		</div>
<?php
	}

	function show()
	{
		$dir1 = '/var/log/nginx';
		$dir2 = WP_CONTENT_DIR . '/log/errors';

		echo '<div class="clear"></div>';
		$this->display_dir($dir1);
		$this->display_dir($dir2);
		if (isset($_REQUEST['f'])) {

			$dir = isset($_REQUEST['dirt']) && $_REQUEST['dirt'] == 'nginx' ? $dir1 : $dir2;
			$this->display_file($dir . '/' . sanitize_text_field($_REQUEST['f']));
		}
	}
}


(new TR_Error_Log())->show();
