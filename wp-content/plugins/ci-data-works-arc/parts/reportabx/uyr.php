<?php
global $wpdb;

wp_enqueue_script('jquery-ui-tabs');


$tabs = array(
	'uti' => array('title' => 'UTI', 'groupids' => array()),
	'wound' => array('title' => 'Wound', 'groupids' => array()),
	'r' => array('title' => 'Respiratory', 'groupids' => array()),
	'ei' => array('title' => 'ENT', 'groupids' => array()),
	'v' => array('title' => 'Vaginal', 'groupids' => array()),
	'sti' => array('title' => 'STI', 'groupids' => array()),
	'nail' => array('title' => 'Nail', 'groupids' => array()),
	'gi' => array('title' => 'GI', 'groupids' => array()),
);

$main_groups = $wpdb->get_results("
select tg_ID, tg_name, tg_other_options
from ci_test_groups
where tg_parent = 0
and tg_type = 'pcr'

and tg_other_options like '%new_abx\":\"1%'
and tg_antibiotic = 0 
order by tg_position asc
"); //and tg_show_req = 1

foreach ($main_groups as $g) {
	$tabkey = 'uti';
	$other_options = json_decode($g->tg_other_options, true);

	if (isset($other_options['abx_type']) && !empty($other_options['abx_type'])) {
		$tabkey = $other_options['abx_type'];
	} else if (stripos($g->tg_name, 'wound') !== false) {
		$tabkey = 'wound';
	} else if (stripos($g->tg_name, 'gi') !== false) {
		$tabkey = 'gi';
	} else if (stripos($g->tg_name, 'Respiratory') !== false || stripos($g->tg_name, 'rpp') !== false) {
		$tabkey = 'r';
	}

	if (in_array($tabkey, array('sm', 'sr', 'es'))) {
		$tabkey = 'sti';
	}

	$tabs[$tabkey]['groupids'][] = $g->tg_ID;
}


foreach ($tabs as $tabkey => $arr) {
	if (empty($arr['groupids'])) {
		unset($tabs[$tabkey]);
	} else {
		$types = ['guides', 'videos', 'faqs'];

		$has_data = false;
		foreach ($types as $type) {

			$tabs[$tabkey][$type] = array();

			$query = "
				SELECT p.*
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND pm.meta_key = '_panels'
				AND  pm.meta_value like %s
			";

			$prepared_query = $wpdb->prepare(
				$query,
				$type,
				'%\"' . $tabkey . '\"%',
			);

			$results = $wpdb->get_results($prepared_query);

			if (!empty($results)) {
				foreach ($results as $post) {
					switch ($type) {
						case 'guides':
							$post->guide_file = get_post_meta($post->ID, '_guide_file', true);
							break;
						case 'videos':
							$post->video_embed_url = get_post_meta($post->ID, '_video_embed_url', true);
							break;
						case 'faqs':
							$post->faq_type = get_post_meta($post->ID, '_faq_type', true);
							$post->faq_position = get_post_meta($post->ID, '_faq_position', true);
							$post->post_content = apply_filters('the_content', $post->post_content);
							break;
					}
				}
				if ($type === 'faqs') {
					usort($results, function ($a, $b) {
						$posA = isset($a->faq_position) ? (int)$a->faq_position : PHP_INT_MAX;
						$posB = isset($b->faq_position) ? (int)$b->faq_position : PHP_INT_MAX;
						return $posA <=> $posB;
					});
				}
				$tabs[$tabkey][$type] = $results;
				$has_data = true;
			}
		}

		if (!$has_data) {
			unset($tabs[$tabkey]);
		}
	}
}


$tab 		= !empty($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
$tab_type 	= isset($_REQUEST['tab_type']) ? $_REQUEST['tab_type'] : 'guides';
$faq_filter = isset($_REQUEST['faq_filter']) ? $_REQUEST['faq_filter'] : 'All';

$faq_types = array("All");
if ($tab_type == 'faqs' && isset($tabs[$tab]['faqs'])) {
	foreach ($tabs[$tab]['faqs'] as $faq) {
		if (!empty($faq->faq_type) && !in_array($faq->faq_type, $faq_types)) {
			$faq_types[] = $faq->faq_type;
		}
	}
	sort($faq_types);
}

?>
<script>
	window.uyrTabsData = <?php echo wp_json_encode($tabs); ?>;
	jQuery(function($) {
		const tabsData = window.uyrTabsData || {};

		let currentTab = $(".tab-btn.border-primary.bg-accent").data("tab-id") || Object.keys(tabsData)[0];
		let currentType = $(".tab_sub[data-state='active']").data("tab-type") || "guides";
		let currentFaqFilter = $(".faq-filter-btn.bg-primary").data("filter") || "All";

		// Icon templates
		const iconGuides = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text h-5 w-5 text-primary mt-0.5" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path><path d="M14 2v4a2 2 0 0 0 2 2h4"></path><path d="M10 9H8"></path><path d="M16 13H8"></path><path d="M16 17H8"></path></svg>`;

		const iconVideos = `<div class="flex-shrink-0 p-2 bg-gradient-to-br from-primary/20 to-primary/5 rounded-lg flex items-center justify-center"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-play h-5 w-5 text-primary" aria-hidden="true"><path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"></path></svg></div>`;

		const iconFaqs = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-question-mark h-5 w-5 text-primary mt-0.5 flex-shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><path d="M12 17h.01"></path></svg>`;

		function formatUSDate(dateString) {
			const date = new Date(dateString.replace(" ", "T"));
			if (isNaN(date)) return dateString;

			const month = (date.getMonth() + 1).toString().padStart(2, "0");
			const day = date.getDate().toString().padStart(2, "0");
			const year = date.getFullYear();

			return `${month}/${day}/${year}`;
		}

		function renderTabContent() {
			const tab = tabsData[currentTab];
			if (!tab) return;

			let items = tab[currentType] || [];
			if (currentType === "faqs" && currentFaqFilter !== "All") {
				items = items.filter((f) => f.faq_type && f.faq_type.includes(currentFaqFilter));
			}

			const $contentArea = $("[data-slot='tabs-content'] .grid");
			$contentArea.empty();

			if (items.length === 0) {
				$contentArea.html(`<p class="text-muted text-sm text-center">No ${currentType} found.</p>`);
				return;
			}

			items.forEach((item) => {
				let html = "";

				// PDF Guides
				if (currentType === "guides") {
					html = `
				<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card hover:shadow-card-hover transition-shadow-smooth">
					<div data-slot="card-content" panel_id="${item.ID}" class="[&:last-child]:pb-4 p-4 btn-collestion">
						<div class="flex items-start justify-between gap-4">
							<div class="flex-1">
								<div class="flex items-start gap-3">
									${iconGuides}
									<div panel_id="${item.ID}">
										<h3 class="font-medium mb-1 text-lg">${item.post_title}</h3>
										<div id="panel_content_${item.ID}" class="panel_content text-sm text-muted-foreground mb-3 mt-3">
											${item.post_content || ""}
										</div>
										<div class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
											<span class="flex items-center gap-1">
												<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock h-3 w-3" aria-hidden="true">
													<path d="M12 6v6l4 2"></path>
													<circle cx="12" cy="12" r="10"></circle>
												</svg>Updated ${formatUSDate(item.post_modified)}
											</span>
										</div>
									</div>
								</div>
							</div>
							<button onclick="window.open('${item.guide_file}', '_blank')" data-slot="button" class="inline-flex cursor-pointer items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground hover:bg-primary/90 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex-shrink-0">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download h-4 w-4 mr-2" aria-hidden="true">
									<path d="M12 15V3"></path>
									<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
									<path d="m7 10 5 5 5-5"></path>
								</svg>Download PDF
							</button>
						</div>
					</div>
				</div>`;
				}

				// Videos
				if (currentType === "videos") {
					html = `
				<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card hover:shadow-card-hover transition-shadow-smooth">
					<div data-slot="card-content" panel_id="${item.ID}" class="[&:last-child]:pb-4 p-4 btn-collestion">
						<div class="flex items-start justify-between gap-4">
							<div class="flex-1">
								<div class="flex items-start gap-3">
									${iconVideos}
									<div panel_id="${item.ID}">
										<h3 class="font-medium mb-1 text-lg">${item.post_title}</h3>
										<div id="panel_content_${item.ID}" class="panel_content text-sm text-muted-foreground mb-3 mt-3">
											${item.post_content || ""}
										</div>
									</div>
								</div>
							</div>
							<button data-video-url="${item.video_embed_url}" data-slot="button" class="btn-watch-video cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground hover:bg-primary/90 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex-shrink-0">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-play h-4 w-4 mr-2" aria-hidden="true">
									<path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"></path>
								</svg>Watch Video
							</button>
						</div>
					</div>
				</div>`;
				}

				// FAQs
				if (currentType === "faqs") {
					let badgeHtml = '';
					if (item.faq_type) {
						const types = item.faq_type.split(',').map(t => t.trim()).filter(Boolean);
						badgeHtml = types.map(type => `
							<span data-slot="badge" 
								class="inline-flex items-center justify-center rounded-md border px-2 py-1 font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden text-foreground [a&]:hover:bg-accent [a&]:hover:text-accent-foreground mt-2 text-xs">
								${type}
							</span>
						`).join(' ');
					}
					html = `
				<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card hover:shadow-card-hover transition-shadow-smooth">
					<div data-slot="card-content" panel_id="${item.ID}" class="[&:last-child]:pb-4 p-4 btn-collestion cursor-pointer">
						<div class="flex items-start justify-between gap-4">
							<div class="flex-1">
								<div class="flex items-start gap-3">
									${iconFaqs}
									<div panel_id="${item.ID}">
										<h3 class="font-medium mb-1 text-lg">${item.post_title}</h3>
										${badgeHtml}
										<div id="panel_content_${item.ID}" style="display:none;cursor:auto;" class="panel_content faq text-sm text-muted-foreground mb-3 mt-8 pt-3 border-t border-border text-base">
											${item.post_content || ""}
										</div>
									</div>
								</div>
							</div>
							<div role="button" panel_id="${item.ID}" class="btn-collestion cursor-pointer">
								<svg id="btn-collestion-show-${item.ID}" style="display:none" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down h-5 w-5 text-muted-foreground flex-shrink-0" aria-hidden="true">
									<path d="m6 9 6 6 6-6"></path>
								</svg>
								<svg id="btn-collestion-hide-${item.ID}" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right h-5 w-5 text-muted-foreground flex-shrink-0" aria-hidden="true">
									<path d="m9 18 6-6-6-6"></path>
								</svg>
							</div>
						</div>
					</div>
				</div>`;
				}

				$contentArea.append(html);
			});
		}

		function updateCountBadges() {
			const tab = tabsData[currentTab];
			if (!tab) return;
			$('.tab_sub[data-tab-type="guides"] .count').text(`(${tab?.guides?.length || 0})`);
			$('.tab_sub[data-tab-type="videos"] .count').text(`(${tab?.videos?.length || 0})`);
			$('.tab_sub[data-tab-type="faqs"] .count').text(`(${tab?.faqs?.length || 0})`);
		}

		function renderFaqFilters() {
			const $filterContainer = $('#faq-filter-container');
			if (currentType !== 'faqs') {
				$filterContainer.hide();
				return;
			}
			const tab = tabsData[currentTab];
			if (!tab || !tab.faqs || tab.faqs.length === 0) {
				$filterContainer.hide();
				return;
			}
			const faqTypes = new Set(["All"]);

			tab.faqs.forEach(faq => {
				if (faq.faq_type) {
					faq.faq_type.split(',')
						.map(t => t.trim())
						.filter(Boolean)
						.forEach(type => faqTypes.add(type));
				}
			});

			const faqTypeArray = Array.from(faqTypes);

			if (faqTypes.length <= 1) {
				$faqTypeArray.hide();
				return;
			}

			let filterHtml = '<div class="flex items-center gap-2 mt-4 flex-wrap">';
			filterHtml += '<span class="text-xs text-muted-foreground">Filter:</span>';

			faqTypeArray.forEach(type => {
				const isActive = currentFaqFilter === type;
				const activeClass = isActive ? 'bg-primary text-white' : 'bg-muted text-foreground';

				filterHtml += `
				<button type="button"
					data-tab="${currentTab}"
					data-tab-type="faqs"
					data-filter="${type}"
					class="faq-filter-btn cursor-pointer inline-flex items-center justify-center rounded-md px-2 py-1 text-xs font-medium border ${activeClass}">
					${type}
				</button>
			`;
			});

			filterHtml += '</div>';

			$filterContainer.html(filterHtml).show();
		}

		$(document).on("click", ".tab-btn", function() {
			const newTab = $(this).data("tab-id");
			if (newTab === currentTab) return;
			$(".tab-btn").removeClass("border-primary bg-accent shadow-card");
			$(this).addClass("border-primary bg-accent shadow-card");
			currentTab = $(this).data("tab-id");
			currentFaqFilter = "All";
			renderTabContent();
			renderFaqFilters();
			updateCountBadges();
		});

		$(document).on("click", ".tab_sub", function() {
			const newType = $(this).data("tab-type");
			if (newType === currentType) return;
			$(".tab_sub").attr("data-state", "inactive");
			$(this).attr("data-state", "active");
			currentType = $(this).data("tab-type");
			currentFaqFilter = "All";
			renderFaqFilters();
			renderTabContent();
		});

		$(document).on("click", ".faq-filter-btn", function() {
			$(".faq-filter-btn").removeClass("bg-primary text-white").addClass("bg-muted text-foreground");
			$(this).removeClass("bg-muted text-foreground").addClass("bg-primary text-white");
			currentFaqFilter = $(this).data("filter");
			renderTabContent();
		});

		$(document).on("click", ".btn-collestion", function(e) {
			if ($(e.target).closest('.panel_content').length > 0 || currentType !== 'faqs') return;
			const panelId = $(this).attr("panel_id");
			const $panel = $(`#panel_content_${panelId}`);
			const $showIcon = $(`#btn-collestion-show-${panelId}`);
			const $hideIcon = $(`#btn-collestion-hide-${panelId}`);

			$panel.slideToggle(200);
			$showIcon.toggle();
			$hideIcon.toggle();
		});

		$(document).on("click", ".btn-watch-video", function(e) {
			e.preventDefault();
			const url = $(this).data("video-url");
			$("#videoIframe").attr("src", url);
			$("#videoModal").css("display", "flex");
		});

		$("#closeVideoModal, #videoModal").on("click", function(e) {
			if ($(e.target).is("#videoModal") || $(e.target).is("#closeVideoModal")) {
				$("#videoModal").hide();
				$("#videoIframe").attr("src", "");
			}
		});
		if ($(".tab-btn").length) {
			$(".tab-btn").first().addClass("border-primary bg-accent shadow-card");
		}
		updateCountBadges();
		renderFaqFilters()
		renderTabContent();
	});
</script>
<style>
	h1,
	h4,
	h3 {
		margin: unset;
	}

	h1 {
		margin-bottom: 5px !important;
	}

	h4,
	h3 {
		margin-bottom: 5px;
		--tw-font-weight: 500 !important;
		font-weight: 500 !important;
		font-size: inherit;
		font-size: 1.17em;
	}

	h5 {
		font-weight: var(--font-weight-medium) !important;
		margin: 0px;
		--tw-font-weight: var(--font-weight-medium) !important;
		font-size: 13px;
		margin-bottom: 10.5px;
	}

	h1,
	h2,
	h4,
	h5,
	h6 {
		font-weight: unset !important;
		font-size: inherit;
	}

	h4 {
		font-size: 14.5px;
	}

	p {
		margin-bottom: 10px;
	}

	p strong {
		color: var(--color-gray-700);
	}
</style>
<div class="wrap">
	<div id="videoModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
		<div style="position: relative; width: 90%; max-width: 900px; background: #fff; border-radius: 12px; overflow: hidden;">
			<button id="closeVideoModal" style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.5); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; z-index: 10000; font-size: 24px; display: flex; align-items: center; justify-content: center;">×</button>
			<div style="position: relative; padding-bottom: 56.25%; height: 0;">
				<iframe id="videoIframe" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" frameborder="0" allowfullscreen></iframe>
			</div>
		</div>
	</div>
	<div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8">
		<div class="space-y-6">
			<div>
				<h1 class="text-2xl text-imp font-semibold">Understanding Your Report</h1>
			</div>
			<div>
				<div class="mb-4">
					<h2 style="font-weight:500 !important;" class="font-medium">Select Report Type:</h2>
				</div>
				<div data-slot="card"
					class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
					<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
						<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
							<?php
							foreach ($tabs as $k => $arr) {
								$tabs[$k]['count_guide'] = isset($tabs[$k]['guides']) ? count($tabs[$k]['guides']) : 0;
								$tabs[$k]['count_video'] = isset($tabs[$k]['videos']) ? count($tabs[$k]['videos']) : 0;
								$tabs[$k]['count_faqs']  = isset($tabs[$k]['faqs'])  ? count($tabs[$k]['faqs'])  : 0;
							?>
								<button data-tab-id="<?php echo $k; ?>"
									class="tab-btn p-3 rounded-lg border text-left transition-all hover:shadow-md cursor-pointer">
									<div class="flex items-start justify-between mb-2">
										<h3 class="font-medium"><?php echo $arr['title']; ?></h3>
										<?php if ($tab == $k): ?>
											<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
												fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
												stroke-linejoin="round"
												class="lucide lucide-circle-check-big h-4 w-4 text-primary flex-shrink-0 mt-0.5"
												aria-hidden="true">
												<path d="M21.801 10A10 10 0 1 1 17 3.335"></path>
												<path d="m9 11 3 3L22 4"></path>
											</svg>
										<?php endif; ?>
									</div>
									<div class="flex items-center gap-1.5 text-xs text-muted-foreground flex-wrap">
										<span><?php echo $tabs[$k]['count_guide']; ?> guides</span>
										<span>•</span>
										<span><?php echo $tabs[$k]['count_video']; ?> videos</span>
										<span>•</span>
										<span><?php echo $tabs[$k]['count_faqs']  ?> FAQs</span>
									</div>
								</button>
							<?php
							}
							?>
						</div>
					</div>
				</div>
			</div>

			<!---Tab panel body---->
			<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
				<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
					<div dir="ltr" data-orientation="horizontal" data-slot="tabs" class="flex flex-col gap-2">
						<div role="tablist" aria-orientation="horizontal" data-slot="tabs-list"
							class="bg-muted text-muted-foreground h-9 items-center justify-center rounded-xl p-[3px] grid w-full grid-cols-3"
							tabindex="0" data-orientation="horizontal" style="outline: none;">

							<?php if ($tab == 'new') { ?>
								<button type="button" data-tab="uti" data-tab-type="faqs" role="tab" aria-selected="false" aria-controls="radix-:r3i:-content-faq" data-state="active" id="radix-:r3i:-trigger-faq" data-slot="tabs-trigger" class="tab_sub cursor-pointer data-[state=active]:bg-card dark:data-[state=active]:text-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 text-foreground dark:text-muted-foreground h-[calc(100%-1px)] flex-1 justify-center rounded-xl border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4 flex items-center gap-2" tabindex="-1" data-orientation="horizontal" data-radix-collection-item=""><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-question-mark h-4 w-4" aria-hidden="true">
										<circle cx="12" cy="12" r="10"></circle>
										<path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
										<path d="M12 17h.01"></path>
									</svg>New (<?php echo $tabs[$tab]['count_faqs']; ?>)
								</button>
							<?php } else { ?>
								<button type="button" data-tab="<?php echo $tab; ?>" data-tab-type="guides" role="tab"
									aria-selected="true" aria-controls="radix-:r3i:-content-guides" data-state="<?php if ($tab_type == 'guides') echo 'active';
																												else echo 'inactive' ?>" id="radix-:r3i:-trigger-guides" data-slot="tabs-trigger"
									class="tab_sub cursor-pointer data-[state=active]:bg-primary data-[state=active]:text-white focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 text-foreground dark:text-muted-foreground h-[calc(100%-1px)] flex-1 justify-center rounded-xl border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4 flex items-center gap-2"
									tabindex="-1" data-orientation="horizontal" data-radix-collection-item=""><svg
										xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
										fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
										stroke-linejoin="round" class="lucide lucide-file-text h-4 w-4" aria-hidden="true">
										<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
										<path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
										<path d="M10 9H8"></path>
										<path d="M16 13H8"></path>
										<path d="M16 17H8"></path>
									</svg>PDF Guides <span class="count"></span>
								</button>

								<button type="button" data-tab="<?php echo $tab; ?>" data-tab-type="videos" role="tab"
									aria-selected="false" aria-controls="radix-:r3i:-content-videos" data-state="<?php if ($tab_type == 'videos') echo 'active';
																													else echo 'inactive' ?>" id="radix-:r3i:-trigger-videos" data-slot="tabs-trigger"
									class="tab_sub cursor-pointer data-[state=active]:bg-primary data-[state=active]:text-white focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 text-foreground dark:text-muted-foreground h-[calc(100%-1px)] flex-1 justify-center rounded-xl border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4 flex items-center gap-2"
									tabindex="-1" data-orientation="horizontal" data-radix-collection-item=""><svg
										xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
										fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
										stroke-linejoin="round" class="lucide lucide-video h-4 w-4" aria-hidden="true">
										<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5">
										</path>
										<rect x="2" y="6" width="14" height="12" rx="2"></rect>
									</svg>Videos <span class="count"></span>
								</button>

								<button type="button" data-tab="<?php echo $tab; ?>" data-tab-type="faqs" role="tab"
									aria-selected="false" aria-controls="radix-:r3i:-content-faq" data-state="<?php if ($tab_type == 'faqs') echo 'active';
																												else echo 'inactive' ?>" id="radix-:r3i:-trigger-faq" data-slot="tabs-trigger"
									class="tab_sub cursor-pointer data-[state=active]:bg-primary data-[state=active]:text-white focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 text-foreground dark:text-muted-foreground h-[calc(100%-1px)] flex-1 justify-center rounded-xl border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4 flex items-center gap-2"
									tabindex="-1" data-orientation="horizontal" data-radix-collection-item=""><svg
										xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
										fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
										stroke-linejoin="round" class="lucide lucide-circle-question-mark h-4 w-4"
										aria-hidden="true">
										<circle cx="12" cy="12" r="10"></circle>
										<path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
										<path d="M12 17h.01"></path>
									</svg>FAQ <span class="count"></span>
								</button>
							<?php } ?>
						</div>
						<div id="faq-filter-container" style="display: none;"></div>

						<div data-state="active" data-orientation="horizontal" role="tabpanel"
							aria-labelledby="radix-:r3i:-trigger-guides" id="radix-:r3i:-content-guides" tabindex="0"
							data-slot="tabs-content" class="flex-1 outline-none space-y-4 <?php echo $tab_type == 'faqs' ? 'mt-4' : 'mt-6'; ?>">
							<div class="grid gap-4"></div>
						</div>
						<div data-state="inactive" data-orientation="horizontal" role="tabpanel"
							aria-labelledby="radix-:r3i:-trigger-videos" hidden="" id="radix-:r3i:-content-videos"
							tabindex="0" data-slot="tabs-content" class="flex-1 outline-none space-y-4 mt-6"></div>
						<div data-state="inactive" data-orientation="horizontal" role="tabpanel"
							aria-labelledby="radix-:r3i:-trigger-faq" hidden="" id="radix-:r3i:-content-faq" tabindex="0"
							data-slot="tabs-content" class="flex-1 outline-none space-y-4 mt-6"></div>
					</div>
				</div>
			</div>
			<!--End tab body panel!-->

			<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
				<div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-6">
					<div class="flex items-start gap-4">
						<div class="p-3 bg-primary/10 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
								height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
								stroke-linecap="round" stroke-linejoin="round"
								class="lucide lucide-external-link h-6 w-6 text-primary" aria-hidden="true">
								<path d="M15 3h6v6"></path>
								<path d="M10 14 21 3"></path>
								<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
							</svg></div>
						<div class="flex-1">
							<h3 class="font-medium mb-1">Need More Help?</h3>
							<p class="text-sm text-muted-foreground mb-3">Our support team is here to help you understand
								your results.</p>
							<div class="flex flex-wrap gap-2"><button data-slot="button" onclick="location.href='?page=ci_reportabx_review'"
									class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5">Request
									ABX Review</button><button data-slot="button" onclick="location.href='?page=ci_reportabx_contact'"
									class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5">Contact
									Support</button></div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
