<?php
class CI_Api_Build_Report_Cache
{
	public $options = array();
	public $log = '';
	public $all_tabs = array();
	public function __construct()
	{
		if (in_array('pcr', tr_pdd_get_batch_types())) {
			$this->options['enable'] = true;
		}
	}

	public function cron($parms = array())
	{
		global $current_role;
		global $cache_query_seconds;

		$logfile = TRCPDD_PATH . 'logs/ci_api_build_report_cache.txt';
		if (file_exists($logfile) && (time() - filemtime($logfile) < 300)) {
			// Đã chạy trong 5 phút qua, bỏ qua lần này
			return;
		}

		$current_role = 'administrator';
		$cache_query_seconds = 0;
		define('RECACHEQUERIES', 1);
		$_REQUEST['recachequery'] = 1;

		if (!isset($_GET['tab']) || $_GET['tab'] == 'all') {
			$this->build_abx_all_tabs();
		} else {
			$this->all_tabs[$_GET['tab']] = array();
		}

		foreach ($this->all_tabs as $tabkey => $arr) {
			if (in_array($tabkey, ['all'])) {
				continue;
			}
			if (isset($_GET['tab']) && $_GET['tab'] != $tabkey) {
				continue;
			}
			$this->build_abx_tab($tabkey);
		}

		$this->build_delay_trends_cache();
	}

	function build_abx_all_tabs()
	{
		$start_time = microtime(true);
		$this->log .= "\n" . 'doing build cache for all tabs=> by type.';

		$_REQUEST['trajax'] = 'report_all_panels';
		$_REQUEST['sub_ajax'] = 'all';
		$_REQUEST['tab'] = 'all';
		include(TRCPDD_PATH . 'parts/reportabx/main.php');
		$this->all_tabs = $tabs;
		$this->log .= 'done. time: ' . (microtime(true) - $start_time) . "\n";

		//view by panel
		$_REQUEST['view_by'] = 'panel';
		$start_time = microtime(true);
		$this->log .= 'doing build cache for all tabs=> by panel.';
		include(TRCPDD_PATH . 'parts/reportabx/main.php');
		$this->log .= 'done. time: ' . (microtime(true) - $start_time) . "\n";
	}

	function build_abx_tab($tab)
	{
		$start_time = microtime(true);
		$this->log .= 'doing build cache for tab ' . $tab . '...';
		$_REQUEST['tab'] = $tab;
		$_REQUEST['trajax'] = 'report_tabs';
		$_REQUEST['sub_ajax'] = 'all';
		include(TRCPDD_PATH . 'parts/reportabx/main.php');
		$this->log .= 'done. time: ' . (microtime(true) - $start_time) . "\n";
	}

	function build_delay_trends_cache()
	{
		$start_time = microtime(true);
		$this->log .= 'doing build cache for delay trends...';

		$_REQUEST['trajax'] = 'loadData';
		foreach (tr_pdd_get_batch_types() as $btype) {
			$_REQUEST['btype'] = $btype;
			include(TRCPDD_PATH . 'parts/reports/sample_delay_trends.php');
		}


		$this->log .= 'done. time: ' . (microtime(true) - $start_time) . "\n";
	}
}
