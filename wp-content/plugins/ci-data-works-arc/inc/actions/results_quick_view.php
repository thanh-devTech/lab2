<?php
$btype = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : '';
$part_file = TRCPDD_PATH . '/parts/' . $btype . '/results_edit.php';

if (!file_exists($part_file)) {
	exit;
}

ob_start();
$GLOBALS['tr_results_quick_view'] = true;
include $part_file;
$GLOBALS['tr_results_quick_view'] = false;
$content = ob_get_clean();
$content = str_replace('<input ', '<input readonly ', $content);
$content = preg_replace('/<input\b([^>]*\btype\s*=\s*["\']?(radio|checkbox)["\']?[^>]*)>/i', '<input$1 disabled="disabled">', $content);
$content = str_replace('<select ', '<select disabled="disabled" ', $content);
$content = str_replace('<textarea ', '<textarea readonly ', $content);

if (!function_exists('tr_results_quick_view_replace_editors')) {
	function tr_results_quick_view_replace_editors($html)
	{
		if (stripos($html, 'wp-editor-wrap') === false) {
			return preg_replace('~<script\b[^>]*>.*?</script>~is', '', $html);
		}

		$prev = libxml_use_internal_errors(true);
		$dom = new DOMDocument('1.0', 'UTF-8');
		$loaded = $dom->loadHTML(
			'<?xml encoding="utf-8" ?><div id="tr-results-quick-view-root">' . $html . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		if (!$loaded) {
			libxml_clear_errors();
			libxml_use_internal_errors($prev);
			return preg_replace('~<script\b[^>]*>.*?</script>~is', '', $html);
		}

		$xpath = new DOMXPath($dom);

		foreach ($xpath->query('//script') as $script) {
			$script->parentNode->removeChild($script);
		}

		foreach ($xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " wp-editor-wrap ")]') as $wrap) {
			$replacement = $dom->createElement('div');
			$replacement->setAttribute('class', 'quick_view_html quick_view_wysiwyg');

			$textarea = $wrap->getElementsByTagName('textarea')->item(0);
			$editor_html = $textarea ? html_entity_decode($textarea->nodeValue, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';

			if ($editor_html !== '') {
				$tmp = new DOMDocument('1.0', 'UTF-8');
				$tmp->loadHTML(
					'<?xml encoding="utf-8" ?><div id="tmp-root">' . $editor_html . '</div>',
					LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
				);

				$tmp_root = $tmp->getElementById('tmp-root');
				if ($tmp_root && $tmp_root->hasChildNodes()) {
					foreach (iterator_to_array($tmp_root->childNodes) as $child) {
						$replacement->appendChild($dom->importNode($child, true));
					}
				} else {
					$replacement->appendChild($dom->createTextNode($textarea->nodeValue));
				}
			}

			$wrap->parentNode->replaceChild($replacement, $wrap);
		}

		$root = $dom->getElementById('tr-results-quick-view-root');
		$result = '';
		if ($root) {
			foreach ($root->childNodes as $child) {
				$result .= $dom->saveHTML($child);
			}
		}

		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		return $result;
	}
}

$content = tr_results_quick_view_replace_editors($content);
?>
<style>
	#tr_results_quick_view {
		padding: 10px 16px 16px;
		box-sizing: border-box;
	}

	#tr_results_quick_view .wrap {
		margin: 0;
	}

	#tr_results_quick_view .rs_note {
		color: red;
		font-weight: 600;
		margin-bottom: 12px;
	}

	#tr_results_quick_view .nav-tab-wrapper,
	#tr_results_quick_view .header_fix,
	#tr_results_quick_view .button,
	#tr_results_quick_view .button-primary,
	#tr_results_quick_view input[type="submit"],
	#tr_results_quick_view input[type="button"],
	#tr_results_quick_view .add_tbl_pha,
	#tr_results_quick_view .add_signedby_pha {
		display: none !important;
	}

	#tr_results_quick_view .frow h4,
	#tr_results_quick_view .frow.hlabel>div>label:first-child,
	#tr_results_quick_view body div .eStore_cart_fancy1_header {
		line-height: 31px;
	}

	#tr_results_quick_view .frow input[type="text"],
	#tr_results_quick_view .frow input[type="password"],
	#tr_results_quick_view .frow input[type="number"],
	#tr_results_quick_view .frow input[type="date"],
	#tr_results_quick_view .frow input[type="email"],
	#tr_results_quick_view .frow select,
	#tr_results_quick_view .frow textarea {
		height: 28px;
		font-size: 12px;
		border: none;
		font-weight: 700;
		pointer-events: none;
		white-space: normal;
	}

	#tr_results_quick_view .frow input[type="radio"],
	#tr_results_quick_view .frow input[type="checkbox"] {
		pointer-events: none;
	}

	#tr_results_quick_view .tbl_results td input[type="text"],
	#tr_results_quick_view .tbl_results td textarea,
	#tr_results_quick_view .tbl_results td select {
		height: 28px;
		font-size: 12px;
		border: none;
		pointer-events: none;
		background-color: #f6f6f6;
		white-space: normal;
	}

	#tr_results_quick_view .quick_view_html {
		background: #f6f6f6;
		border: 1px solid #ddd;
		padding: 10px 12px;
		box-sizing: border-box;
	}

	#tr_results_quick_view .group_header th strong {
		padding-left: 3px;
	}

	#tr_results_quick_view .wp-core-ui select.disabled,
	#tr_results_quick_view .wp-core-ui select:disabled {
		color: black;
		border: none;
		background-image: none;
		font-size: 12px;
		opacity: 1;
	}
</style>
<div id="tr_results_quick_view" class="tr_results_quick_view tr_results_quick_view_<?php echo esc_attr($btype) ?>">
	<div class="rs_note">Note: Displays current values in the system. These may differ from values at the time of release if any data was updated after reporting.</div>
	<?php echo $content; ?>
</div>
<?php
exit;
