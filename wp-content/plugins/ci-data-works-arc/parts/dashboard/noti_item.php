<?php
$bg = '';
$level = '';
$text_color = '';
$level_class = '';
$style = '';
if ($msg->background == 'critical') {
	$level = 'CRITICAL';
	$level_class = 'bg-red-50 border-red-200';
	$text_color = 'text-red-800';
} elseif ($msg->background == 'high') {
	$level = 'HIGH';
	$level_class = 'bg-orange-50 border-orange-200';
	$text_color = 'text-orange-800';
} elseif ($msg->background == 'medium') {
	$level = 'MEDIUM';
	$level_class = 'text-blue-800 bg-blue-50 border-blue-200';
	$text_color = 'text-blue-800';
} elseif ($msg->background == 'low') {
	$level = 'LOW';
	$level_class = 'text-gray-800 bg-gray-50 border-gray-200';
	$text_color = 'text-gray-800';
} elseif ($msg->background == 'reminder') {
	$level_class = 'text-black-800 bg-reminder border-yellow-600';
	$text_color = 'text-black-800';
} elseif ($msg->background == 'required') {
	$level_class = 'text-white bg-required border-orange-600';
	$text_color = 'text-white';
} elseif ($msg->background == 'suspended') {
	$level_class = 'text-white bg-suspended border-red-200';
	$text_color = 'text-white';
} else {
	$style = "color: #ffffff;";
	$text_color = 'text-white';
	if ($msg->background === "#f1b100") {
		$style = "color: #1a1a1a;";
		$text_color = "text-black-800";
	}
	$style .= "background-color: " . $msg->background;
}

?>
<div id="noti_<?php echo $msg->ID; ?>" class="flex items-start gap-3 p-3 rounded-lg border relative group <?php echo $level_class; ?>" style="<?php echo $style; ?>">

	<!-- Icon -->
	<div class="mt-0.5">
		<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
			stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
			class="lucide lucide-monitor h-4 w-4 text-blue-600" aria-hidden="true"
			style="color:<?php echo $text_color; ?>">
			<rect width="20" height="14" x="2" y="3" rx="2"></rect>
			<line x1="8" x2="16" y1="21" y2="21"></line>
			<line x1="12" x2="12" y1="17" y2="21"></line>
		</svg>
	</div>

	<!-- Content -->
	<div class="flex-1 min-w-0">
		<div class="flex session_noti items-center justify-between mb-2">
			<h4 class="font-medium <?php echo $text_color; ?> text-sm">
				<?php echo esc_html($msg->subject) ?: 'No Subject'; ?>
			</h4>
			<div class="flex items-center gap-2">
				<span data-slot="badge" class="inline-flex <?php echo $text_color; ?> items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap text-xs">
					<?php
					if ($msg->ID < 0) {
						echo 'System';
					} else {
						if (!isset($msg->role)) {
							$msg->roles = maybe_unserialize($msg->user_roles);
							if (isset($msg->roles)) $msg->role = array_key_first($msg->roles);
						}

						if (in_array($msg->role, array('administrator'))) {
							echo 'System';
						} else {
							echo 'Laboratory';
						}
					}
					?>
				</span>
				<button noti-id="noti_<?php echo $msg->ID; ?>" noti-id-db="<?php echo $msg->ID; ?>" data-slot="button"
					class="btn_del_noti inline-flex items-center justify-center whitespace-nowrap text-sm font-medium disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:hover:bg-accent/50 rounded-md gap-1.5 has-[&gt;svg]:px-2.5 h-6 w-6 p-0 opacity-0 group-hover:opacity-100 transition-opacity hover:bg-white/50"><svg
						xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
						stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
						class="lucide lucide-x h-3 w-3" aria-hidden="true">
						<path d="M18 6 6 18"></path>
						<path d="m6 6 12 12"></path>
					</svg></button>
			</div>
		</div>
		<div class="text-sm <?php echo $text_color; ?> mb-1">
			<?php echo nl2br($msg->msg); ?>
		</div>
		<p class="text-xs <?php echo $text_color; ?> text-muted-foreground flex items-center gap-1">
			<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
				stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
				class="lucide lucide-clock h-3 w-3" aria-hidden="true">
				<path d="M12 6v6l4 2"></path>
				<circle cx="12" cy="12" r="10"></circle>
			</svg>
			<?php echo human_time_diff(strtotime($msg->created_date), current_time('timestamp')); ?> ago
		</p>
	</div>
</div>