<?php
if(tr_get_option('ci_pdf_data','pdf_approved_by') && $report['approved_by_ID'] > 0){
	$useremail = $wpdb->get_var("select user_email from {$wpdb->users} where ID = {$report['approved_by_ID']}");
	echo '<div style="padding:3px 0">';
	echo 'Approved by: ';
	echo $useremail;
	echo '</div>';
}