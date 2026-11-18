<?php
class CI_Sample_Manifest
{
	public $options;

	public function __construct()
	{
		$this->options = get_option('tr_sample_manifest_st', array());
		if (!empty($this->options['email']) && !empty($this->options['time'])) {
			$this->options['enable'] = true;
		}
	}

	public function cron()
	{
		$emails = explode(',', $this->options['email']);
		foreach ($emails as $email) {
			$email = trim($email);
			if (!empty($email)) {
				$this->send($email);
			}
		}
	}

	public function send($email)
	{
		global $wpdb;
		$today = date_i18n('Y-m-d');
		$file = $this->buildfile($today);

		if (!empty($file)) {
			$labname = $wpdb->get_var("select lab_name from ci_labs order by lab_ID asc limit 1");
			$title = "Sample Manifest {$today} " . $labname;
			$atts  = array();
			$atts['sample_manifest.csv'] = $file;
			$headers = '';
			$message = $title;
			if (isset($_GET['debug'])) {
				var_dump($email);
				var_dump($title);
				var_dump($message);
				var_dump(file_get_contents($file));
			}

			return wp_mail($email, $title, $message, $headers, $atts);
		}
		return false;
	}

	public function buildfile($today)
	{
		global $wpdb;


		$ref_assen_title = tr_get_option('ci_pdf_data', 'title_pid_field', 'PID');
		$show_ref_assen = tr_get_option('ci_pdf_data', 'show_pid_field');
		$blood_settings = get_option('ci_req_settings_blood', array());
		$Phlebotomist 	= isset($blood_settings['Phlebotomist']) && $blood_settings['Phlebotomist'];
		$Phlebotomist_clinics = array();
		if ($Phlebotomist) {
			$Phlebotomist_clinics = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 and cli_phlebotomist = 1 ");
		}

		$where      = ' is_saved = 0 ';
		$joins 		= '';


		$where .= " and ba.doc >= '{$today}' ";
		$where .= " and ba.doc <= '{$today} 23:59:59' ";


		$child_qrs = array();
		foreach (tr_pdd_get_batch_types() as $btype) {
			$batch_tbl = tr_pdd_get_table_by_btype($btype);

			$wherebtype = $where;
			$joinsbtype = $joins;

			if ($Phlebotomist && count($Phlebotomist_clinics) > 0) {

				$wherebtype .= " and (
					ba.cli_ID  not in (" . implode(',', $Phlebotomist_clinics) . ") 
					or (ba.cli_ID in (" . implode(',', $Phlebotomist_clinics) . ") and ba.batch_status = 'so_collected')
				)";
			}

			$child_qrs[] =  "select
			ba.assen,
			ba.oldreq,
			ba.doc,
			ba.cli_ID,
			ba.requesting_practice,
			ba.requesting_physician,
			ba.patient_name,
			ba.insurance
			from {$batch_tbl} ba
			{$joinsbtype}
			where {$wherebtype} ";
		}
		$sql = "select * from (
			" . implode(' UNION ALL ', $child_qrs) . "
		) as ba ";
		$sql .= " order by requesting_practice asc, doc desc";
		$results = $wpdb->get_results($sql);


		$file = tempnam("/tmp", "manifest") . '.csv';


		$output = fopen($file, 'w');

		if ($output) {
			$headers = array();
			$headers = array();
			if ($show_ref_assen) {
				$headers[] = $ref_assen_title;
			}
			$headers[] = 'Accession';
			$headers[] = 'Practice';
			$headers[] = 'Date';
			$headers[] = 'Sample Present?';

			fputcsv($output, $headers);
			$clinicarr = array();
			foreach ($results as $i => $r) {
				if (!in_array($r->requesting_practice, $clinicarr)) {
					if (count($clinicarr) > 0) {
						fputcsv($output, array(''));
						fputcsv($output, array(''));
					}
					$row = array(
						'',
						'',
						'',
						$r->requesting_practice,
					);

					fputcsv($output, $row);

					$clinicarr[] = $r->requesting_practice;
				}
				$row = array(
					$r->assen . "\0",
					$r->requesting_practice,
					$r->doc,
					''
				);

				$row = array();
				if ($show_ref_assen) {
					$row[] = $r->oldreq . "\0";
				}
				$row[] = $r->assen . "\0";
				$row[] = $r->requesting_practice;
				$row[] = $r->doc;
				$row[] = '';

				fputcsv($output, $row);
			}
			@fclose($output);
		}


		return $file;
	}
}
