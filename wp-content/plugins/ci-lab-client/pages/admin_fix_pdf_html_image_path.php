<?php


function fix_html_image_path($dir)
{
	if (!is_dir($dir)) {
		return;
	}
	$files = scandir($dir);
	foreach ($files as $file) {
		if ($file === '.' || $file === '..') {
			continue;
		}
		$filePath = $dir . '/' . $file;
		if (is_dir($filePath)) {
			fix_html_image_path($filePath);
		} else if (in_array(pathinfo($filePath, PATHINFO_EXTENSION), array('html', 'htm')) !== false) {
			echo '<li>' . $filePath;
			$content = file_get_contents($filePath);

			$updatedContent = preg_replace('/="([^\"]+)\/wp-content/', '="[ABSPATH]/wp-content', $content);
			if (!empty($updatedContent) && strlen($content) !== strlen($updatedContent)) {

				file_put_contents($filePath, $updatedContent);
				echo ' - Fixed';
			}
			echo ' </li>';
		}
	}
}


$dir = WP_CONTENT_DIR . '/results/html';
echo '<h3>Fixing PDF HTML Image Paths in ' . $dir . '</h3><ul>';
fix_html_image_path($dir);
echo '</ul>';
