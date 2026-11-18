jQuery(function ($) {
    $('body').on('click', '.single_hold_btn', function () {
        var $tr = $(this).parents('tr');
        var $mid = $tr.data('mid');
        var $btn = $(this);
        var $ishold = $(this).html() == 'Hold';

        $('body .loadingbody').remove();
        $('body #hold_reason_box').remove();

        if (!$ishold) {
            $('body').append('<div class="loading loadingbody"></div>');
            $.ajax({
                data: {
                    'trajax': 1,
                    "mid": $mid,
                    "act": "single_remove_hold"
                },
                type: 'post',
                dataType: 'json',
                success: function (json) {
                    $('body .loadingbody').remove();
                    $tr.removeClass('bs_hold visited');
                    $btn.html('Hold');
                },
                error: function () {
                    $('body .loadingbody').remove();
                    alert('Error. Please try again!');
                }
            });
        } else {
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
                                'trajax': 1,
                                "mid": $mid,
                                "act": "single_change_hold",
                                'reason': $reason,
                                'reason_title': $reason_title
                            },
                            type: 'post',
                            dataType: 'json',
                            success: function (json) {
                                $('body .loadingbody').remove();
                                $tr.addClass('bs_hold');
                                $btn.html('UnHold');
                            },
                            error: function () {
                                $('body .loadingbody').remove();
                                alert('Error. Please try again!');
                            }
                        });
                        $(this).dialog("close");
                    },
                    "Cancel": function () {
                        $(this).dialog("close");
                    }
                }
            });
        }

        return false;
    }).on('click', '.single_cancel_btn', function () {
        var $tr = $(this).parents('tr');
        var $mid = $tr.data('mid');
        var $btn = $(this);
        var $is_undo = $(this).html() == 'Un-Archive' ? 1 : 0;

        $('body .loadingbody').remove();

        $('body').append('<div class="loading loadingbody"></div>');
        $.ajax({
            data: {
                'trajax': 1,
                "mid": $mid,
                "act": "single_cancel_order",
                "undo": $is_undo,
            },
            type: 'post',
            dataType: 'json',
            success: function (json) {
                $('body .loadingbody').remove();
                if ($is_undo) {
                    $tr.removeClass('bs_archived');
                }
                else {
                    $tr.addClass('bs_archived');
                }
                $btn.html($is_undo ? 'Cancel Order' : 'Un-Archive');
            },
            error: function () {
                $('body .loadingbody').remove();
                alert('Error. Please try again!');
            }
        });

        return false;
    })
})