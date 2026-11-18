<?php
class ci_pdf_image
{
	public function image_trim_white($filepath,$white_num=16316664)
	{
		$img = imagecreatefromjpeg($filepath);

		//find the size of the borders
		$b_top = 0;
		$b_btm = 0;
		$b_lft = 0;
		$b_rt = 0;
		$img_width = imagesx($img);
		$img_height = imagesy($img);
		$adding_y = round($img_height/400);
		$adding_x = round($img_width/400);
		//top
		for(; $b_top < $img_height; ++$b_top) {
			for($x = 0; $x < $img_width; ++$x) {

				if(imagecolorat($img, $x, $b_top) < $white_num) {
					if($b_top> $adding_y){
						$b_top = $b_top - $adding_y;
					}
					break 2; //out of the 'top' loop
				}
			}
		}

		//bottom
		for(; $b_btm < $img_height; ++$b_btm) {
			for($x = 0; $x < $img_width; ++$x) {
				if(imagecolorat($img, $x, $img_height - $b_btm-1) < $white_num) {

					if($b_btm < $img_height - $adding_y){
						$b_btm = $b_btm - $adding_y;
					}
					break 2; //out of the 'bottom' loop
				}
			}
		}

		//left
		for(; $b_lft < $img_width; ++$b_lft) {
			for($y = 0; $y < $img_height; ++$y) {
				if(imagecolorat($img, $b_lft, $y) < $white_num) {
					if($b_lft > $adding_x){
						$b_lft = $b_lft - $adding_x;
					}
					break 2; //out of the 'left' loop
				}
			}
		}

		//right
		for(; $b_rt < $img_width; ++$b_rt) {
			for($y = 0; $y < $img_height; ++$y) {
				if(imagecolorat($img, $img_width - $b_rt-1, $y) < $white_num) {
					if($b_rt < $img_width - $adding_x ){
						$b_rt = $b_rt - $adding_x;
					}
					break 2; //out of the 'right' loop
				}
			}
		}


		if($b_top>0 || $b_rt>0 || $b_btm>0 || $b_lft>0)
		{
			//copy the contents, excluding the border
			$newimg = imagecreatetruecolor(
				imagesx($img)-($b_lft+$b_rt), $img_height-($b_top+$b_btm));

			imagecopy($newimg, $img, 0, 0, $b_lft, $b_top, imagesx($newimg), imagesy($newimg));

			return imagejpeg($newimg,$filepath);
		}
		return false;
	}

	public function admin_view_card_image()
	{
		global $wpdb;
		$mid            = $_REQUEST['mid'];
		$saved_id       = $_REQUEST['saved_id'];
		$btype          = $_REQUEST['btype'];
		$field          = $_REQUEST['field'];
		$dir            = tr_get_ins_card_dir();

		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		if($saved_id>0)
		{
			$mid = $saved_id;
		}
		$bat = $wpdb->get_row("select {$field} from {$batch_tbl} where mid = {$mid}");
		if(!$bat){
			exit;
		}
		$oldname = $bat->{$field};
		$filepath = $dir['path'].'/'.$oldname;
		if(stripos($oldname,'.pdf')>0)
		{
			$info       = pathinfo($oldname);
			$newname    = str_replace('.'.$info['extension'],'.jpg',$oldname);
			$imagepath  = $dir['path'].'/'.$newname;

			if ( trpdd_convert_pdf_to_image( $filepath, $imagepath, true, '900x1200' ,100) )
			{
				$this->image_trim_white($imagepath,13521906);

				$fsize = getimagesize($imagepath);
				if($fsize===false)
				{
					unlink($imagepath);
				}
				else{
					unlink($filepath);
					$wpdb->update($batch_tbl,array($field=>$newname),array('mid'=>$mid));
					$filepath = $imagepath;
				}
			}
		}

		header('Content-Type: image/jpeg');
		readfile($filepath);

	}
}