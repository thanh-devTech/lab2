<?php
global $wpdb;

$statuses = array(
	'CAN' => 'Cancel',
	'REJ' => 'Rejected',
	'INC' => 'Incomplete',
	'INV' => 'Invalid'
);


if(isset($_POST['save']))
{
	foreach($statuses as $statuskey => $statustxt)
	{
		$comments = json_encode($_POST['comments'][$statuskey]);
		$meta_key = 'comments_'.$statuskey;
		$meta_id = $wpdb->get_var("select id from ci_test_meta where test_id = -1 
	                                                and meta_key='{$meta_key}' ");
		if($meta_id>0)
		{
			$wpdb->update('ci_test_meta',array('meta_value'=>$comments),array('id'=>$meta_id));
		}else{
			$wpdb->insert('ci_test_meta',array('meta_value'=>$comments,'test_id'=>-1,'meta_key'=>$meta_key));
		}
	}
}
?>
<div class="wrap">
	<h2>Comments</h2>

	<br>

	<form method="post">
		<?php
		foreach($statuses as $statuskey => $statustxt){
			$comments = $wpdb->get_var("select meta_value from ci_test_meta 
	                                    where test_id = -1
	                                    and meta_key='comments_{$statuskey}' ");
			$comments = json_decode($comments,false);
			?>
			<div class="group_type" data-key="<?php echo $statuskey?>" data-items="<?php echo esc_attr(json_encode($comments))?>">
				<div class="frow">
					<div class="col32"><h4><b class="red"><?php echo $statustxt?></b> - Comment</h4></div>
					<div class="col7"><h4>Action</h4></div>
				</div>
				<div class="items" ></div>
				<a class="button add_item">+</a>
			</div>
			<?php
		}
		?>
		<div class="frow actions" style="padding-top:20px">
			<input type="submit" name="save" value="Save" class="button-primary"/>
		</div>
	</form>

	<div style="display: none" id="default_item">
		<div class="item frow">
			<div class="col32">
				<input type="text" class="comment"/>
			</div>

			<div class="col7">
				<a class="button remove_item">-</a>
			</div>
		</div>
	</div>
</div>
<style>
	.ui-sortable-handle{
		cursor: move;
	}
</style>
<script>
    jQuery(function ($) {

        function add_item($div,$comment) {
            var $key = $div.data('key');
            var $default_html = $($('#default_item').html());

            $('.comment',$default_html).attr('name','comments['+$key+'][]').val($comment);
            $('.items',$div).append($default_html);
        }
        function init_items() {
            $('.group_type').each(function () {
                var $itemsdiv = $('.items',$(this));
                var $items = $(this).data('items');
                for(var i in $items)
                {
                    add_item($(this), $items[i]);
                }
            })
        }
        $('body').on('click','a.add_item',function () {
            var $pr = $(this).parents('.group_type');
            add_item($pr, '');
        }).on('click','a.remove_item',function () {
            $(this).parents('div.item').remove();
            return false;
        }).on('paste','input.comment',function (e) {
            e.preventDefault();
            var text = (e.originalEvent || e).clipboardData.getData('text/plain');
            var $pr = $(this).parents('.group_type');
            var $items = text.split("\n");
            $(this).parents('div.item').remove();
            for(var i in $items)
            {
                add_item($pr, $items[i]);
            }
        })

        init_items();
    })
</script>
