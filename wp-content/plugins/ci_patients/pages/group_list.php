<?php
echo '<div class="group_list">';
foreach($form_options['listgroups'] as $group)
{
	if(!is_array($group['tests']))
	{
		$group['tests'] = array();
	}
	if(!is_array($group['testsopt']))
	{
		$group['testsopt'] = array();
	}
	?>
	<div class="row">
		<label>
			<input type="radio" name="group" required value="<?php echo $group['key']?>"
			       data-tests="<?php echo implode(',',$group['tests'])?>"
			       data-testsopt="<?php echo implode(',',$group['testsopt'])?>" />
			<span><?php echo $group['text']?></span>
            <div style="display: none;" class="desc"><?php echo $group['desc']?></div>
		</label>
	</div>
	<?php
}
echo '</div>';