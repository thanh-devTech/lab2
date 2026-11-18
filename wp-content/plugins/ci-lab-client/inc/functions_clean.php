<?php
function trclient_clear_by_dir($path, $timeout, $current_time, $remove_dir = false)
{
	if (
		stripos($path, '/cache/') === false
		&& stripos($path, '/log/') === false
	) {
		return false;
	}

	$handle = @opendir($path);
	if ($handle) {
		while ($file = readdir($handle)) {
			if ($file == '.' || $file == '..' || $file[0] == '_') continue;

			if (is_dir($path . '/' . $file)) {
				trpdd_clear_by_dir($path . '/' . $file, $timeout, $current_time, $remove_dir);
				if ($remove_dir) {
					rmdir($path . '/' . $file);
				}
			} else {
				$t = @filemtime($path . '/' . $file);
				if ($current_time - $t > $timeout) {
					if (stripos($path, 'uploads') !== false && !$remove_dir) continue;
					@unlink($path . '/' . $file);
				}
			}
		}
		closedir($handle);
	}
}
