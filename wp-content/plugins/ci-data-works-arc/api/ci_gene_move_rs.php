<?php
class CI_Gene_Move_RS
{
	public $options = array();
	public $log = '';
	public function __construct()
	{
		
		$settings	= get_option('ci_result_settings_gene', array());
		if (!empty($settings['gene_rs_folder'])) {
			$this->options['enable'] = true;
		}
		
	}

	public function cron()
	{
		global $wpdb;

		$settings	= get_option('ci_result_settings_gene', array());
		if (empty($settings['gene_rs_folder'])) {
			return;
		}

		$dir 		= ABSPATH . $settings['gene_rs_folder'] . '/results';
		$log_dir  	= WP_CONTENT_DIR . '/log/results_gene/';
		$lastid 	= get_option('ci_last_id_sync_gene_file', 0);
		$files 		= $wpdb->get_results("select * from ci_reports_files 
		where rf_btype = 'gene'
		and rf_ID > {$lastid} order by rf_ID asc");

		foreach ($files as $row) {
			$filename = $row->rf_file;
			$localfile = $log_dir . $filename;
			$remotefile = $dir . '/' . $filename;
			copy($localfile, $remotefile);

			update_option('ci_last_id_sync_gene_file', $row->rf_ID);
		}

	}
}
