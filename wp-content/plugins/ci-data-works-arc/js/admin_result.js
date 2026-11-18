jQuery(function ($) {

    $('body').on('click', 'a.hold_btn', function () {
        var $bid = $(this).data('batch_id');
        var $labid = $(this).data('lab_id');
        var $ref_lab_id = $(this).data('ref_lab_id') || '';
        var $ids = '';
        if ($labid) {
            //find checked
            $('.p_lab_' + $labid + $ref_lab_id + $bid + ' input:checked').each(function () {
                $ids += $(this).val() + ',';
            })

        } else {
            $labid = 0;
            var $trpr = $(this).parents('tr.parent');
            var $trchild = $('tr.p_' + $trpr.data('p'));
            $('input:checked', $trchild).each(function () {
                $ids += $(this).val() + ',';
            })
        }
        $('body .loadingbody').remove();
        $('body #hold_reason_box').remove();
        $('body').append(`<div id="hold_reason_box" style="display:none">
                <div class="frow">
                <select id="reason_title"><option>Clinic Issue</option><option>Lab Issue</option></select>
                </div>
                <textarea id="reason_hold" style="width:100%;height:200px"></textarea>
                </div>`);
        $("#hold_reason_box").dialog({
            modal: true,
            title: 'Hold Reason',
            dialogClass: 'hold_reason_box',
            width: '420px',
            buttons: {
                "Submit": function () {
                    $('body').append('<div class="loading loadingbody"></div>');
                    var $reason = $('#reason_hold').val();
                    var $reason_title = $('#reason_title').val();
                    $.ajax({
                        data: {
                            "batch_id": $bid,
                            "lab_id": $labid,
                            "ref_lab_id": $ref_lab_id,
                            "ids": $ids,
                            "trajax": 1,
                            "act": "change_hold",
                            'reason': $reason,
                            'reason_title': $reason_title
                        },
                        type: 'post',
                        dataType: 'json',
                        success: function (json) {
                            $('body .loadingbody').remove();
                            for (i in json.ids) {
                                var $id = json.ids[i];
                                var $tr = $('tr.childp[data-id="' + $id + '"]');
                                $tr.addClass('bs_hold');
                                $('input[type="checkbox"]', $tr).removeAttr('checked');
                            }

                        }
                    });
                    $(this).dialog("close");
                },
                "Cancel": function () {
                    $(this).dialog("close");
                }
            }
        });

        return false;
    })
        .on('click', 'a.removehold_btn', function () {
            var $bid = $(this).data('batch_id');
            var $labid = $(this).data('lab_id');
            var $ref_lab_id = $(this).data('ref_lab_id') || '';
            var $ids = '';
            if ($labid) {
                //find checked
                $('.p_lab_' + $labid + $ref_lab_id + $bid + ' input:checked').each(function () {
                    $ids += $(this).val() + ',';
                })

            } else {
                $labid = 0;
                var $trpr = $(this).parents('tr.parent');
                var $trchild = $('tr.p_' + $trpr.data('p'));
                $('input:checked', $trchild).each(function () {
                    $ids += $(this).val() + ',';
                })
            }
            $('body .loadingbody').remove();
            $('body').append('<div class="loading loadingbody"></div>');
            $.ajax({
                data: {
                    "batch_id": $bid,
                    "lab_id": $labid,
                    "ref_lab_id": $ref_lab_id,
                    "ids": $ids,
                    "trajax": 1,
                    "act": "remove_hold"
                },
                type: 'post',
                dataType: 'json',
                success: function (json) {
                    $('body .loadingbody').remove();
                    for (i in json.ids) {
                        var $id = json.ids[i];
                        var $tr = $('tr.childp[data-id="' + $id + '"]');
                        $tr.removeClass('bs_hold');
                        $('input[type="checkbox"]', $tr).removeAttr('checked');

                    }
                }
            });
            return false;
        })
        .on('click', 'a.fax_pdf', function () {
            var $bid = $(this).data('batch_id');
            var $labid = $(this).data('lab_id');
            var $ref_lab_id = $(this).data('ref_lab_id');
            var $ids = '';
            if ($labid) {
                //find checked
                $('.p_lab_' + $labid + $ref_lab_id + $bid + ' input:checked').each(function () {
                    $ids += $(this).val() + ',';
                })

            } else {
                $labid = 0;
                var $trpr = $(this).parents('tr.parent');
                var $trchild = $('tr.p_' + $trpr.data('p'));
                $('input:checked', $trchild).each(function () {
                    $ids += $(this).val() + ',';
                })
            }
            var $url = '?tr_action=ci_upload_result_pdf_to_efax&batch_id=' + $bid + '&lab_id=' + $labid + '&ref_lab_id=' + $ref_lab_id + '&ids=' + $ids;

            tb_show('Fax PDF', $url + '&TB_iframe=true&width=600&height=500');
            return false;
        }).on('click', 'a.delete_selected', function () {
            if (!confirm('Do You want delete selected?')) return false;
            var $bid = $(this).data('batch_id');
            var $labid = $(this).data('lab_id');
            var $ref_lab_id = $(this).data('ref_lab_id');
            var $ids = '';
            var $rowkey = '.p_lab_' + $labid + $ref_lab_id + $bid;
            if (!$labid || $labid == 0) {
                $rowkey = '.p_batch_' + $bid;
            }
            console.log($rowkey);
            var $childpr = $($rowkey);

            $('input:checked', $childpr).each(function () {
                $ids += $(this).val() + ',';
            });
            if ($ids === '') {
                alert('Please select rows to delete!');
                return false;
            }
            $.ajax({
                data: { trajax: '1', act: 'delete_selected', ids: $ids },
                type: 'post',
                dataType: 'json',
                success: function (json) {
                    if (json.status === 'ok') {
                        for (var i in json.ids) {
                            $('.childp[data-id="' + json.ids[i] + '"]', $childpr).remove();
                        }
                    } else {
                        alert('Error. Please try again!');
                    }
                },
                error: function () {
                    alert('Error. Please try again!');
                }
            })
            return false;
        })
        .on('click', 'a.uploadpdf', function () {
            var $bid = $(this).data('batch_id');
            var $labid = $(this).data('lab_id');
            var $ref_lab_id = $(this).data('ref_lab_id');
            var $ids = '';
            if ($labid) {
                //find checked
                $('.p_lab_' + $labid + $ref_lab_id + $bid + ' input:checked').each(function () {
                    $ids += $(this).val() + ',';
                })

            } else {
                $labid = 0;
                var $trpr = $(this).parents('tr.parent');
                var $trchild = $('tr.p_' + $trpr.data('p'));
                $('input:checked', $trchild).each(function () {
                    $ids += $(this).val() + ',';
                })
            }
            var $url = '?tr_action=ci_upload_result_pdf_to_box&batch_id=' + $bid + '&lab_id=' + $labid + '&ref_lab_id=' + $ref_lab_id + '&kt=<?php echo time()?>&ids=' + $ids;

            tb_show('Upload to Box', $url + '&TB_iframe=true&width=400&height=300');
            return false;
        })
        .on('click', 'a.btn_sell_all', function () {
            var sleat = $(this).attr("sel");
            var $pid = $(this).parents('tr').data('p');

            if (sleat == "true") {
                $(".p_" + $pid + " input:checkbox").prop('checked', true);
                $(this).attr("sel", false);
                $(this).html("Unselect All")
            } else {
                $(".p_" + $pid + " input:checkbox").prop('checked', false);
                $(this).attr("sel", true);
                $(this).html("Select All")
            }
            return false;
        })

})