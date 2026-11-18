<?php
global $ci_pdf_added_pagination;
$showpagination = !isset($report['add_style']) || empty($report['add_style']);
if (!$showpagination && tr_get_option('ci_pdf_data', 'new_abx_pagination')) {
  $showpagination = true;
}
if ($showpagination) {
  $ci_pdf_added_pagination = true;
?>
  <script type='text/php'>
    if ( isset($pdf) && isset($fontMetrics)) {
  $font = $fontMetrics->get_font('helvetica', 'normal');
  $size = 8;
  $y = $pdf->get_height() - 15;
  $x = $pdf->get_width() - 20 - $fontMetrics->get_text_width('Page 8 of 8', $font, $size);
  $pdf->page_text($x, $y, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, $size,array(0.55, 0.55, 0.55));
}
else if ( isset($pdf) ) {
  $font = Font_Metrics::get_font('helvetica', 'normal');
  $size = 8;
  $y = $pdf->get_height() - 15;
  $x = $pdf->get_width() - 20 - Font_Metrics::get_text_width('Page 8 of 8', $font, $size);
  $pdf->page_text($x, $y, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, $size,array(0.55, 0.55, 0.55));
}
</script>
<?php }
