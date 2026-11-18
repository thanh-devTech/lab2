<?php
global $wpdb;

return include(trpdd_get_file_path('abx', 'new_uyr'));

wp_enqueue_script('jquery-ui-tabs');

$row = $wpdb->get_row("select * from ci_abx_texts where ctype = 'uyr' ");

echo '<div class="wrap">';
echo '<div class="maincontent">';
echo apply_filters('the_content', $row->desc_1);
echo '</div><br>';

if (!isset($tabs)) {

	$tabs = array();
	$main_groups = $wpdb->get_results("
	select tg_ID, tg_name, tg_other_options
	from ci_test_groups
	where tg_parent = 0
	and tg_type = 'pcr'

	and tg_other_options like '%new_abx\":\"1%'
	and tg_antibiotic = 0 
	order by tg_position asc
	"); //and tg_show_req = 1

	foreach ($main_groups as $g) {
		$tabtext = 'UTI';
		$tabkey = 'uti';
		$other_options = json_decode($g->tg_other_options, true);

		if (isset($other_options['abx_type']) && !empty($other_options['abx_type'])) {
			$tabkey = $other_options['abx_type'];
			$tabtext = strlen($g->tg_name) < 7 || strlen($tabkey) <= 2 ? $g->tg_name : strtoupper($tabkey);
			if ($tabkey == 'v') {
				$tabtext = 'Vaginitis';
			} else if ($tabkey == 'gi') {
				$tabtext = 'GI';
			}
		} else if (stripos($g->tg_name, 'wound') !== false) {
			$tabkey = 'wound';
			$tabtext = 'Wound';
		} else if (stripos($g->tg_name, 'gi') !== false) {
			$tabkey = 'gi';
			$tabtext = 'GI';
		} else if (stripos($g->tg_name, 'Respiratory') !== false || stripos($g->tg_name, 'rpp') !== false) {
			$tabkey = 'r';
			$tabtext = 'RPP';
		}

		$tabtext = trim(str_ireplace(array('(new)', ' panel'), '', $tabtext));

		if (!isset($tabs[$tabkey])) {
			$tabs[$tabkey] = array(
				'title' => $tabtext,
				'groupids' => array()
			);
		}

		$tabs[$tabkey]['groupids'][] = $g->tg_ID;
	}
}

$faqtabs = array(
	'faqs_new' => array('text' => 'NEW', 'rs' => array()),
	'faqs_uti' => array('text' => 'UTI FAQs', 'rs' => array()),
	'faqs_wound' => array('text' => 'Wound FAQs', 'rs' => array()),
	'faqs' => array('text' => 'BV FAQs', 'rs' => array()),
);

if (!isset($tabs['uti'])) {
	unset($faqtabs['faqs_uti']);
}
if (!isset($tabs['v'])) {
	unset($faqtabs['faqs']);
}
if (!isset($tabs['wound'])) {
	unset($faqtabs['faqs_wound']);
}


$cols 		= $wpdb->get_col("explain ci_abx_texts");
$ordercol 	= in_array('position', $cols) ? 'position' : 'ID';

foreach ($faqtabs as $tk => $arr) {
	$faqs = $wpdb->get_results("select * from ci_abx_texts where ctype = '{$tk}' order by {$ordercol} asc ");

	if (empty($faqs)) {
		unset($faqtabs[$tk]);
	} else {
		$faqtabs[$tk]['rs'] = $faqs;
	}
}

if (count($faqtabs) > 0) {
	echo '<div id="faqs_tabs">';
	echo '<ul>';
	foreach ($faqtabs as $tk => $arr) {
		echo '<li><a class="nav-tab " href="#tab_' . $tk . '">' . $arr['text'] . '</a></li>';
	}
	echo '</ul>';

	foreach ($faqtabs as $tk => $arr) {
		echo '<div id="tab_' . $tk . '" >';
		echo '<div class="accordions">';
		foreach ($arr['rs'] as $faq) {
			echo '<div class="accordion-section">';
			echo '<div class="accordion-section-title">' . $faq->desc_1 . '</div>';
			echo '<div class="accordion-section-content">' . apply_filters('the_content', $faq->desc_2) . '</div>';
			echo '</div>';
		}
		echo '</div>';
		echo '</div>';
	}

	echo '</div>';
}


echo '</div>';
?>
<style>
	.ui-tabs .ui-tabs-panel {
		padding: 0;
		clear: both;
	}

	img.alignright {
		float: right;
		margin: 0 0 1em 1em
	}

	img.alignleft {
		float: left;
		margin: 0 1em 1em 0
	}

	img.aligncenter {
		display: block;
		margin-left: auto;
		margin-right: auto
	}

	a img.alignright {
		float: right;
		margin: 0 0 1em 1em
	}

	a img.alignleft {
		float: left;
		margin: 0 1em 1em 0
	}

	a img.aligncenter {
		display: block;
		margin-left: auto;
		margin-right: auto
	}




	.accordions .accordion-section {
		margin-bottom: 5px;
	}

	.accordions .accordion-section.disabled {
		display: none !important;
	}

	.accordions .accordion-section-title {
		background-color: #c6cfd0;
		color: #333;
		cursor: pointer;
		padding: 10px 18px;
		border: none;
		text-align: left;
		outline: none;
		font-size: 12px;
		transition: 0.4s;
		font-weight: bold;
	}

	.accordions .accordion-section-title:before {
		content: '';
		display: block;
		float: left;
		width: 0;
		height: 0;
		border-top: 5px solid transparent;
		border-bottom: 5px solid transparent;
		border-left: 8px solid #fff;
		margin-right: 20px;
		margin-top: 5px;
	}

	.accordions .active .accordion-section-title:before {
		content: '';
		display: block;
		width: 0;
		height: 0;
		border-bottom: none;
		border-left: 5px solid transparent;
		border-right: 5px solid transparent;
		border-top: 8px solid #fff;
	}

	.accordions .accordion-section.notclick .accordion-section-title {
		cursor: default;
	}

	.accordions .active .accordion-section-title {
		background-color: #939d9e;
		color: #fff;
	}

	.js .accordions .accordion-section-title:after {
		display: none;
	}

	.accordions .active .accordion-section-content {
		display: block;
	}

	li.ui-tabs-active a.nav-tab {
		background: #fff;
	}

	.accordion-section-content ul {
		list-style-type: disc;
		margin-left: 20px;
	}
</style>
<script>
	jQuery(function($) {
		$('body').on('click', '.accordion-section-title', function() {
			var $pr = $(this).parents('.accordion-section');
			//$('.accordion-section-title.active').removeClass('active');
			if ($pr.hasClass('active')) {
				$pr.removeClass('active');
			} else {
				$pr.addClass('active');
			}
		})
		$("#faqs_tabs").tabs();
	})
</script>