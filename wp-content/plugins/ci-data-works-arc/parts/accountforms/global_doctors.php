<?php
global $wpdb;

wp_enqueue_script('signature-pad', TRCPDD_URL. 'js/signature_pad.min.js' ,array(),TRCPDD_VS,false);
include_once(TRCPDD_PATH.'pages/formdata.php');

if($act=='addgroup' || $act=='editgroup'){

	if(!empty($_POST['group'])){
		$group = tr_pdd_stripslashes_deep($_POST['group']);
		if($group['group_ID']>0)
		{
			$wpdb->update('ci_doc_groups',$group,array('group_ID'=>$group['group_ID']));
			Tr_Session_Class::add("Saved Group Successfully!");
		}else{
			$wpdb->insert('ci_doc_groups',$group);
			$group['group_ID'] = $wpdb->insert_id;
			Tr_Session_Class::add("Saved Group Successfully!");
		}

		if($group['group_ID']>0)
		{
			$docs = $_POST['doc'];
			foreach($docs as $j => $doc)
			{
				$doc['group_ID'] = $group['group_ID'];
				if(empty($doc['doc_first']))continue;

				$file = array(
					'name' => $_FILES['doc_signature']['name'][$j],
					'tmp_name' => $_FILES['doc_signature']['tmp_name'][$j],
					'type' => $_FILES['doc_signature']['type'][$j],
					'error' => $_FILES['doc_signature']['error'][$j],
				);
				tr_data_update_doc($doc,$file);
			}
		}
	}

	$group = array();
	$docs = array();
	if($act=='editgroup')
	{
		$id = $_REQUEST['id'];
		$group = $wpdb->get_row("select * from ci_doc_groups where group_ID = {$id} ", ARRAY_A);

		$docs = $wpdb->get_results("select doc.* from ci_doc doc
                                Where group_ID = {$id}
                                group by doc.doc_ID
                                ", ARRAY_A);
	}
	?>
	<div class="wrap">
	<h2>Global Physicians
		<a class="add-new-h2" href="<?php Tr_Base_Class_V6::link( array( 'act' => '','id'=>'' ) ) ?>">All Groups</a>
	</h2>
	<?php Tr_Session_Class::show() ?>

		<form method="post" enctype="multipart/form-data">
			<div class="frow">
				<div class="col2">
					<label>Group Name</label>
					<input type="text" name="group[group_name]" value="<?php echo $group['group_name']?>" required/>
				</div>
			</div>

			<div class="frow">
				<h4>PRIMARY FACILITY PHYSICIAN INFORMATION </h4>
			</div>

			<?php
			$doctor_col7 = 'col7';
			if(tr_get_option('ci_pdf_data','api_athena_api'))
			{
				$doctor_col7 = 'col8';
			}
			?>
			<div class="frow blabel">
				<div class="<?php echo $doctor_col7?>"><label>FIRST NAME</label></div>
				<div class="<?php echo $doctor_col7?>"><label>LAST NAME</label></div>
				<div class="col9"><label class="small2">SPECIALTY/CREDENTIAL</label></div>
				<div class="<?php echo $doctor_col7?>"><label>NPI</label></div>
				<?php
				if(tr_get_option('ci_pdf_data','api_athena_api'))
				{
					echo '<div class="col9"><label>Athena</label></div>';
				}
				?>
				<div class="<?php echo $doctor_col7?>"><label>PHONE NO.</label></div>
				<div class="<?php echo $doctor_col7?>"><label>EMAIL ADDRESS</label></div>
				<div class="<?php echo $doctor_col7?>"></div>
			</div>
			<ol id="doctors_dv" style="overflow:auto;max-height:300px">
				<?php
				$max_j = is_array($docs) && count($docs)>0? count($docs) : 1;
				for($j = 0;$j<$max_j;$j++):?>
					<li class="frow blabel pdoctorrow <?php if($j==0)echo 'default'?>">
						<div class="<?php echo $doctor_col7?>">
							<input type="text" name="doc[<?php echo $j?>][doc_first]" value="<?php echo $docs[$j]['doc_first']?>" required="" maxlength="100"/>

							<input type="hidden" name="doc[<?php echo $j?>][doc_ID]" value="<?php echo $docs[$j]['doc_ID']?>" />
						</div>
						<div class="<?php echo $doctor_col7?>">
							<input type="text" name="doc[<?php echo $j?>][doc_last]" value="<?php echo $docs[$j]['doc_last']?>" required="" maxlength="100"/>
						</div>
						<div class="col9">
							<input type="text" name="doc[<?php echo $j?>][doc_specialty]" value="<?php echo $docs[$j]['doc_specialty']?>"  maxlength="30"/>
						</div>
						<div class="<?php echo $doctor_col7?>">
							<input type="text" name="doc[<?php echo $j?>][doc_npi]" value="<?php echo $docs[$j]['doc_npi']?>" required="" maxlength="100"/>
						</div>
						<?php
						if(tr_get_option('ci_pdf_data','api_athena_api'))
						{
							?>
							<div class="col9">
								<input type="text" name="doc[<?php echo $j?>][doc_athena_username]" value="<?php echo $docs[$j]['doc_athena_username']?>" />
							</div>
							<?php
						}
						?>
						<div class="<?php echo $doctor_col7?>">
							<input type="text" class="has_phone"  name="doc[<?php echo $j?>][doc_phone]" value="<?php echo $docs[$j]['doc_phone']?>"/>
						</div>
						<div class="<?php echo $doctor_col7?>">
							<input type="email" name="doc[<?php echo $j?>][doc_email]" value="<?php echo $docs[$j]['doc_email']?>"/>
						</div>
						<div class="<?php echo $doctor_col7?>">
							<?php if($act!='print'):?>
								<span class="card_file ">
                                <a class="button selectfile addsign"><?php echo(empty($docs[$j]['doc_signature']))? 'Signature':'View'?></a>
                                <input type="file" name="doc_signature[<?php echo $j?>]" accept="image/*"/>
									<?php if(!empty($docs[$j]['doc_signature'])){?>
										<span class="hover">
                                        <img src="<?php echo $sign_dir['url'].'/'.$docs[$j]['doc_signature']?>"/>
                                        <a class="button removesign">Remove</a>
                                    </span>
									<?php }?>
                            </span>
								<input type="hidden" class="doc_signature" name="doc[<?php echo $j?>][signature]"/>
								<a class="button dashicons dashicons-edit add_doc_signature" href="#"></a>
								<a class="button rm_doctor_row right">-</a>
							<?php endif;?>
						</div>
					</li>
				<?php endfor;?>
			</ol>
			<?php if($act!='print'):?>
				<a class="button add_doctor_row" data-id="<?php echo $j?>">+</a>
				<a class="button import_csv" data-type="doctors">Import from csv</a>
				<div class="note">Note: You can search PHYSICIAN by NPI</div>
			<?php endif;?>
			<br><br>

			<div class="frow">
				<input type="submit" class="button-primary" value="Save"/>
			</div>
			<input type="hidden" name="group[group_ID]" value="<?php echo $group['group_ID']?>"/>
		</form>
	</div>
	<script>
		jQuery(function ($) {

            function setup_physician_npi_auto()
            {
                $('[name*="[doc_npi]"]').autocomplete({
                    source: ajaxurl+'?action=ajax&autocomplete=requesting_physician_npi_auto',
                    minLength: 1,
                    select: function( event, ui ) {
                        if(ui.item)
                        {
                            var $pr = $(this).parents('.pdoctorrow');
                            $('[name*="[doc_ID]"]',$pr).val(ui.item.id);
                            $('[name*="[doc_first]"]',$pr).val(ui.item.doc_first);
                            $('[name*="[doc_last]"]',$pr).val(ui.item.doc_last);
                            $('[name*="[doc_specialty]"]',$pr).val(ui.item.doc_specialty);
                            $('[name*="[doc_phone]"]',$pr).val(ui.item.doc_phone);
                            $('[name*="[doc_email]"]',$pr).val(ui.item.doc_email);
                        }
                    }
                });
            };


			$('body').on('click','a.removesign',function () {
                if(confirm('Do you want remove Signature?')==false)return false;
                var $this = $(this);
                var $pr = $(this).parents('.card_file');
                var $li = $(this).parents('.pdoctorrow');
                var $docid = $('[name*="doc_ID"]',$li).val();

                $.ajax({
                    data:{trajax:1,act:'remove_doc_signature',id:$docid},
                    type:'post',
                    dataType:'json',
                    success:function (json) {
                        if(json.status=='ok'){
                            $('.hover',$pr).remove();
                            $('a.addsign',$pr).html('Signature');
                            $('input[type="file"]',$pr).val('');
                        }else{
                            alert('Error.Please try again');
                        }
                    },
                    error:function () {
                        setTimeout(function () {
                            $this.click();
                        },1500);
                    }
                });
            }).on('change','.card_file input[type="file"]',function (event) {
                var $pr = $(this).parents('.card_file');

                if($(this).val()==''){
                    $('a.addsign',$pr).html('Signature');
                    $('.hover',$pr).remove();
                }else{
                    $('a.addsign',$pr).html('View');
                    $pr.append('<span class="hover"><img src=""/><a class="button removesign">Remove</a></span>');
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('img',$pr).attr('src', e.target.result);
                    }
                    reader.readAsDataURL(event.target.files[0]);
                }
            }).on('click','.add_doc_signature',function () {
                var $input = $(this).parent().find('.doc_signature');
                tb_show('Signature','#TB_inline?height=240&width=400');
                $('#TB_ajaxContent').html('<span id="signature_wrap"><canvas style="background:#ffffc3" id="signature_canvas" width="400" height="173"></canvas>' +
                    '<a class="button clear_btn">Clear</a> <a class="button done_btn">Apply</a></span>');
                var $swrap = $('#TB_ajaxContent #signature_wrap');
                var $cavas = document.querySelector("#signature_canvas");
                if (window.devicePixelRatio) {
                    var hidefContext = $cavas.getContext('2d');
                    var hidefCanvasWidth = $($cavas).attr('width');
                    var hidefCanvasHeight = $($cavas).attr('height');

                    $($cavas).attr('width', hidefCanvasWidth * window.devicePixelRatio);
                    $($cavas).attr('height', hidefCanvasHeight * window.devicePixelRatio);
                    $($cavas).css('width', hidefCanvasWidth);
                    $($cavas).css('height', hidefCanvasHeight);
                    hidefContext.scale(window.devicePixelRatio, window.devicePixelRatio);
                }

                var signaturePad = new SignaturePad($cavas);

                $(".clear_btn",$swrap).click(function(){
                    if(confirm("Are you sure you want to clear signature?")==false)return false;
                    signaturePad.clear();
                    $input.val("");
                });
                $(".done_btn",$swrap).click(function(){
                    if (!signaturePad.isEmpty()){
                        $input.val(signaturePad.toDataURL());
                    }
                    else{
                        $input.val("");
                    }
                    tb_remove();
                });
                return false;
            }).on('click','a.import_csv',function () {
                var $type = $(this).data('type');
                if($(this).html()!='Uploading...'){
                    var $fname = 'input.upload_csv_file[data-type="'+$type+'"]';
                    $($fname).remove();
                    $('body').append('<input type="file" class="upload_csv_file" data-type="'+$type+'" accept="text/csv"/>');
                    $($fname).click();
                }

            }).on('change','input.upload_csv_file',function () {
                if($(this).val()!=''){
                    var $type = 'doctors';
                    var $but = $('a.import_csv[data-type="'+$type+'"]');
                    var fd = new FormData();
                    var files = $(this)[0].files[0];
                    $(this).remove();
                    fd.append('file', files);
                    fd.append('trajax','upload_'+$type);
                    if($but.html()!='Uploading...'){
                        $but.data('oldtext', $but.html());
                        $but.html('Uploading...');
                    }

                    $.ajax({
                        type: 'post',
                        dataType:'json',
                        data: fd,
                        contentType: false,
                        processData: false,
                        success: function(rs){
                            $but.html($but.data('oldtext'));
                            if(rs.status = 'ok'){
                                for(i in rs.list)
                                {
                                    var $li = $('#doctors_dv li:last-child');
                                    if($('[name*="doc_first"]',$li).val()!='' || $('[name*="doc_last"]',$li).val()!=''){
                                        $('a.add_doctor_row').click();
                                    }

                                    var $li = $('#doctors_dv li:last-child');
                                    for(j in rs.list[i]){
                                        $('input[name*="'+j+'"]',$li).val(rs.list[i][j]);
                                    }
                                }
                            }
                            else{
                                alert('file not uploaded');
                            }
                        },
                        error:function(){
                            alert('Error. Please try again');
                            $but.html($but.data('oldtext'));
                        }
                    });
                }
            })

            $( "#doctors_dv" ).on( "new_row",function(){
                setup_physician_npi_auto();
            } );
            setup_physician_npi_auto();
        })
	</script>
	<?php
}else {
	if($act=='pdelete' && $_REQUEST['idd']>0)
	{
		$id = $_REQUEST['idd'];
		$wpdb->delete('ci_doc_groups',array('group_ID'=>$id));
		$wpdb->update('ci_doc',array('group_ID'=>0),array('group_ID'=>$id));
		Tr_Session_Class::add('Deleted Successfully!');
	}
	$groups = $wpdb->get_results( "select *,
				(select count(*) from ci_doc doc where doc.group_ID = gr.group_ID) as count
	            from ci_doc_groups gr
	            order by group_ID asc" );
	?>
	<div class="wrap">
		<h2>Global Physicians
			<a class="add-new-h2" href="<?php Tr_Base_Class_V6::link( array( 'act' => 'addgroup' ) ) ?>">Add Group</a>
		</h2>

		<?php Tr_Session_Class::show() ?>

		<div class="wraptbl">
			<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
				<thead>
				<tr>
					<th style="width:50px">#</th>
					<th>Group</th>
					<th>Physicians</th>
					<th>Actions</th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $groups as $i => $group ) {
					?>
					<tr>
						<td><?php echo $group->group_ID ?></td>
						<td><?php echo $group->group_name ?></td>
						<td><?php echo $group->count ?></td>
						<td>
							<a href="<?php Tr_Base_Class_V6::link( array(
								'act' => 'editgroup',
								'id'  => $group->group_ID
							) ) ?>">Edit</a>
								-
								<a class="pdelete" href="#" data-id="<?php echo $group->group_ID?>">Delete</a>

						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>

	</div>
	<?php
}

