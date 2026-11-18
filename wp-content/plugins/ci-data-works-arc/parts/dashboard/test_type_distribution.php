<div id="dis_content" style="min-height: 500px;" data-slot=card class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
	<p style="width:100%;padding:20px;text-align:center"></p>
</div>
<script>
	var ctxPie;
	var dis_day_from = 0;
	var dis_day_to = 0;
	const leaderLinePlugin = {
		id: 'leaderLine',
		afterDraw(chart, args, options) {
			const {
				ctx,
				chartArea: {
					top,
					bottom,
					left,
					right,
					width,
					height
				}
			} = chart;
			chart.getDatasetMeta(0).data.forEach((el, i) => {
				const {
					x,
					y
				} = el.tooltipPosition();
				const label = chart.data.labels[i];
				if (!label) return;

				const angle = el.startAngle + (el.endAngle - el.startAngle) / 2;
				const xLine = el.x + Math.cos(angle) * (el.outerRadius + 10);
				const yLine = el.y + Math.sin(angle) * (el.outerRadius + 10);
				const extraLine = 20 * (xLine >= el.x ? 1 : -1);

				ctx.beginPath();
				ctx.moveTo(x, y);
				ctx.lineTo(xLine, yLine);
				ctx.strokeStyle = chart.data.datasets[0].backgroundColor[i];
				ctx.lineWidth = 1;
				ctx.stroke();
			});
		}
	};

	showLoading("dis_content");
	jQuery(function($) {
		function load_dis_by_day_type() {
			$("#dis_loading").show();
			const selDayType = $("#sel_dis_day").val();
			const btype = $('#test_type_btype').val();
			const cid = $('#test_type_clinic').val();
			$.ajax({
				data: {
					trajax: 'type_distribution_ajax',
					selDay: selDayType,
					btype: btype,
					cid: cid
				},
				success: function(rs) {
					$('#dis_content').html(rs);
					$("#dis_loading").hide();
				},
				error: function(e) {
					console.log(e)
				}
			});
		}

		$('body').on('click', '.run_test_type_report', function() {
			var valHtml = $(this).find("textshow").html();
			if (valHtml.trim() == "Live Report") {
				$(this).find("textshow").html("Hide Live Report");
				$("#test_type_analysis").show();
			} else {
				$(this).find("textshow").html("Live Report");
				$("#test_type_analysis").hide();
			}
		}).on('change', 'select#test_type_btype', function() {
			load_dis_by_day_type();
		}).on('change', '#sel_dis_day', function() {
			load_dis_by_day_type();
		}).on('change', 'select#test_type_clinic', function() {
			load_dis_by_day_type();
		}).on('click', ".dis_export_item", function() {
			var export_type = $(this).attr("export_type");
			showToast('info', 'Preparing your download...', 'Your ' + export_type.toUpperCase() + ' export is being generated. This may take a few moments.', 4000);
			var $url = location.href + '&trajax=type_distribution_ajax&export_type=' + export_type;
			$url += '&selDay=' + $("#sel_dis_day").val();
			$url += '&from=' + dis_day_from;
			$url += '&to=' + dis_day_to;
			$url += '&btype=' + $('#test_type_btype').val();
			$url += '&cid=' + $('#test_type_clinic').val();
			location.href = $url;
			setTimeout(() => {
				showToast('success', 'Download ready!', 'Your sample report has been downloaded successfully.', 4000);
			}, 5000);
			return false;
		}).on('click', "#dis_export_btn", function(e) {
			e.stopPropagation();
			$("#menu_dis_export").toggle();
		})
		load_dis_by_day_type();
	});
</script>