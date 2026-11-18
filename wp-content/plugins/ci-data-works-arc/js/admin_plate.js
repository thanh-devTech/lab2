jQuery(function ($) {
    function move_ntc($tbl, $tr, $ntcnow) {
        var $block = $tbl.parents('.plateblock');
        var $position = $('.position', $tr).html();
        var $pl_ID = $block.data('id');

        var $assen = $('.assen input', $tr).val();
        var $position_new = '';
        var $input_new = '';
        var $ntcposold = $('tr.ntc' + $ntcnow + ' .position', $tbl).html();

        $('tr', $tbl).each(function () {
            var $as = $('.assen input', $(this)).val();
            if ($position_new == '') {
                var $posthis = $('.position', $(this)).html();
                if ($as == '' || $ntcposold == $posthis) {
                    $position_new = $posthis;
                    $input_new = $('.assen input', $(this));
                }

            }
        })
        if ($position_new != '') {
            $.ajax({
                data: {
                    trajax: 1,
                    act: 'add_ntc',
                    ntc: $ntcnow,
                    ntc_pos: $position,
                    pl_ID: $pl_ID,
                    newpos: $position_new
                },
                type: 'post',
                dataType: 'json',
                success: function (json) {
                    if (json.status == 'ok') {
                        var $oldntc = $('tr.ntc' + $ntcnow, $tbl);
                        $oldntc.removeClass('ntc' + $ntcnow);
                        $('.assen input', $oldntc).val('');

                        $tr.addClass('ntc' + $ntcnow);
                        $input_new.val($assen);
                        $('.assen input', $tr).val(json.ntcname ? json.ntcname : 'NTC');
                    } else {
                        alert('Error:' + json.msg);
                    }
                }
            })
        }
    }
    $('body').on('click', '.plateblock:not(.notmoventc) td.position', function () {
        var $tbl = $(this).parents('table.platetbl');
        var $tr = $(this).parent();
        if ($tbl.data('type') == -2 || $tbl.hasClass('notmoventc')) return false;

        if ($tr.hasClass('ntc1') || $tr.hasClass('ntc2')) return;
        $("#dialog-confirm-ntc").dialog({
            resizable: false,
            height: "auto",
            width: 400,
            modal: true,
            buttons: {
                "NTC #1": function () {
                    move_ntc($tbl, $tr, 1);
                    $(this).dialog("close");
                },
                "NTC #2": function () {
                    move_ntc($tbl, $tr, 2);
                    $(this).dialog("close");
                },
                Cancel: function () {
                    $(this).dialog("close");
                }
            }
        });
    })
        .on('click', '.combine384btn', function () {
            var $btn = $(this);
            var $pl_IDs = [];
            var $combinesort = $('#combinesort').val();
            $('.plateblock').each(function () {
                if ($(this).hasClass('locked')) {
                    //$('input[type="checkbox"]',$(this)).removeAttr('checked');
                }
                if ($('input[type="checkbox"]', $(this)).is(':checked')) {
                    $pl_IDs.push($(this).data('id'));
                }
            })

            if ($pl_IDs.length == 0) {
                alert('Please select Plates!');
                return false;
            } else if ($pl_IDs.length > 4) {
                alert('Max 4 Plates!');
                return false;
            }
            if ($btn.html() == 'Loading...') return false;
            $btn.data('oldtxt', $btn.html());
            $btn.html('Loading...');

            $.ajax({
                data: {
                    trajax: 1,
                    act: 'combine384',
                    pl_IDs: $pl_IDs,
                    combinesort: $combinesort
                },
                type: 'post',
                dataType: 'json',
                success: function (json) {
                    $('.plateblock input[type="checkbox"]').prop('checked', false);
                    $btn.html($btn.data('oldtxt'));
                    if (json.status == 'ok') {
                        $('.combinedblock input[type="checkbox"]').prop('checked', false);
                        $('.plateblock input[type="checkbox"]').prop('checked', false);
                        location.reload();
                    } else {
                        alert('Error');
                    }
                },
                error: function () {
                    $btn.html($btn.data('oldtxt'));
                    alert('Error');
                }
            })
            return false;
        })
        .on('click', '.combinefull96btn', function () {
            var $btn = $(this);
            var $pl_IDs = [];
            var $combinesort = $('#combinesort').val();

            $('.plateblock').each(function () {
                if ($(this).hasClass('locked')) {
                    //$('input[type="checkbox"]',$(this)).removeAttr('checked');
                }
                if ($('input[type="checkbox"]', $(this)).is(':checked')) {
                    $pl_IDs.push($(this).data('id'));
                }
            })

            if ($pl_IDs.length < 2) {
                alert('Please select Plates!');
                return false;
            }
            if ($btn.html() == 'Loading...') return false;
            $btn.data('oldtxt', $btn.html());
            $btn.html('Loading...');

            $.ajax({
                data: {
                    trajax: 1,
                    act: 'combinefull96',
                    pl_IDs: $pl_IDs,
                    combinesort: $combinesort
                },
                type: 'post',
                dataType: 'json',
                success: function (json) {
                    $('.plateblock input[type="checkbox"]').prop('checked', false);
                    $btn.html($btn.data('oldtxt'));
                    if (json.status == 'ok') {
                        $('.combinedblock input[type="checkbox"]').prop('checked', false);
                        $('.plateblock input[type="checkbox"]').prop('checked', false);
                        location.reload();
                    } else {
                        alert('Error');
                    }
                },
                error: function () {
                    $btn.html($btn.data('oldtxt'));
                    alert('Error');
                }
            })
            return false;
        })
        .on('click', '.combine96btn', function () {
            var $btn = $(this);
            var $pl_IDs = [];
            var $combinesort = $('#combinesort').val();

            $('.plateblock').each(function () {
                if ($(this).hasClass('locked')) {
                    //$('input[type="checkbox"]',$(this)).removeAttr('checked');
                }
                if ($('input[type="checkbox"]', $(this)).is(':checked')) {
                    $pl_IDs.push($(this).data('id'));
                }
            })

            if ($pl_IDs.length < 2) {
                alert('Please select Plates!');
                return false;
            }
            if ($pl_IDs.length > 5) {
                alert('Please max 5 Plates!');
                return false;
            }
            if ($btn.html() == 'Loading...') return false;
            $btn.data('oldtxt', $btn.html());
            $btn.html('Loading...');

            $.ajax({
                data: {
                    trajax: 1,
                    act: 'combine96',
                    pl_IDs: $pl_IDs,
                    combinesort: $combinesort
                },
                type: 'post',
                dataType: 'json',
                success: function (json) {
                    $('.plateblock input[type="checkbox"]').prop('checked', false);
                    $btn.html($btn.data('oldtxt'));
                    if (json.status == 'ok') {
                        $('.combinedblock input[type="checkbox"]').prop('checked', false);
                        $('.plateblock input[type="checkbox"]').prop('checked', false);
                        location.reload();
                    } else {
                        alert('Error');
                    }
                },
                error: function () {
                    $btn.html($btn.data('oldtxt'));
                    alert('Error');
                }
            })
            return false;
        })
        .on('click', '.expandbtn', function () {
            if (!confirm('Do you Expand this Plate?')) return false;
            var $btn = $(this);
            var $pr = $(this).parents('.combinedblock');
            var $id = $pr.data('id');
            if ($btn.html() == 'Loading...') return false;
            $btn.data('oldtxt', $btn.html());
            $btn.html('Loading...');

            $.ajax({
                data: {
                    trajax: 1,
                    act: 'expand',
                    ID: $id
                },
                type: 'post',
                dataType: 'json',
                success: function (json) {
                    $btn.html($btn.data('oldtxt'));
                    if (json.status == 'ok') {
                        location.reload();
                    } else {
                        alert('Error');
                    }
                },
                error: function () {
                    $btn.html($btn.data('oldtxt'));
                    alert('Error');
                }
            })
            return false;
        })
        .on('click', '.exportbtn', function () {
            var $btn = $(this);
            var $pr = $(this).parents('.combinedblock');
            var $pl_IDs = [];
            var $com_IDs = [];
            $('.plateblock').each(function () {
                if ($(this).hasClass('locked')) {
                    //$('input[type="checkbox"]',$(this)).removeAttr('checked');
                }
                if ($('input[type="checkbox"]', $(this)).is(':checked')) {
                    $pl_IDs.push($(this).data('id'));
                }
            })
            $('.combinedblock').each(function () {
                if ($(this).hasClass('locked')) {
                    $('input[type="checkbox"]', $(this)).removeAttr('checked');
                }
                if ($('input[type="checkbox"]', $(this)).is(':checked')) {
                    $com_IDs.push($(this).data('id'));
                }
            })

            if ($pl_IDs.length + $com_IDs.length > 1) {
                alert('Only export one same time!');
                return false;
            }

            if ($pl_IDs.length == 0 && $com_IDs.length == 0) {
                alert('Please select Plates!');
                return false;
            }
            if ($btn.html() == 'Loading...') return false;
            $btn.data('oldtxt', $btn.html());
            $btn.html('Loading...');
            $pl_IDs = $pl_IDs.join(',');
            $com_IDs = $com_IDs.join(',');
            var $url = location.href + '&trajax=1&act=export&pl_IDs=' + $pl_IDs + '&com_IDs=' + $com_IDs;
            $('#print_frame_mu').attr('src', $url);

            $('.combinedblock input[type="checkbox"]').prop('checked', false);
            $('.plateblock input[type="checkbox"]').prop('checked', false);
            setTimeout(function () {
                $btn.html($btn.data('oldtxt'));

            }, 2000);
            return false;
        })
        .on('click', '.deletebtn', function () {
            var $btn = $(this);
            var $pl_IDs = [];
            var $com_IDs = [];
            $('.plateblock').each(function () {
                if ($(this).hasClass('locked')) {
                    $('input[type="checkbox"]', $(this)).removeAttr('checked');
                }
                if ($('input[type="checkbox"]', $(this)).is(':checked')) {
                    $pl_IDs.push($(this).data('id'));
                }
            })

            if ($pl_IDs.length == 0) {
                alert('Please select Plates!');
                return false;
            }
            if (!confirm('Are you sure delete Plates?')) {
                return false;
            }
            if ($btn.html() == 'Deleting...') return false;
            $btn.data('oldtxt', $btn.html());
            $btn.html('Deleting...');

            $.ajax({
                data: {
                    trajax: 1,
                    act: 'delete_plates',
                    pl_IDs: $pl_IDs
                },
                type: 'post',
                dataType: 'json',
                success: function (json) {
                    $btn.html($btn.data('oldtxt'));
                    if (json.status == 'ok') {
                        for (i in $pl_IDs) {
                            var pid = $pl_IDs[i];
                            $('.plateblock[data-id="' + pid + '"]').remove();
                        }

                        $('.combinedblock input[type="checkbox"]').prop('checked', false);
                        $('.plateblock input[type="checkbox"]').prop('checked', false);
                    } else {
                        alert('Error');
                    }
                },
                error: function () {
                    $btn.html($btn.data('oldtxt'));
                    alert('Error');
                }
            })
            return false;
        })
        .on('click', '.pl_biorad_exportbtn', function () {
            var $btn = $(this);
            if ($(this).hasClass('disabled')) {
                return false;
            }
            var $block = $(this).parents('.plateblock');
            var $pl_ID = $block.data('id');
            $btn.data('oldtxt', $btn.html());
            $btn.html('Loading...');
            var $url = location.href + '&trajax=1&act=biorad_export&pl_IDs=' + $pl_ID;
            if ($(this).data('blockid')) {
                $url += '&blockid=' + $(this).data('blockid');
            }
            $('#print_frame_mu').attr('src', $url);
            setTimeout(function () {
                $btn.html($btn.data('oldtxt'));
            }, 2000);
            return false;
        })
        .on('click', '.pl_qtower_exportbtn', function () {
            var $btn = $(this);
            if ($(this).hasClass('disabled')) {
                return false;
            }
            var $block = $(this).parents('.plateblock');
            var $pl_ID = $block.data('id');
            $btn.data('oldtxt', $btn.html());
            $btn.html('Loading...');
            var $url = location.href + '&trajax=1&act=qtower_export&pl_IDs=' + $pl_ID;
            if ($(this).data('blockid')) {
                $url += '&blockid=' + $(this).data('blockid');
            }
            $('#print_frame_mu').attr('src', $url);
            setTimeout(function () {
                $btn.html($btn.data('oldtxt'));
            }, 2000);
            return false;
        })
        .on('click', '.pl_studio_exportbtn', function () {
            var $btn = $(this);
            if ($(this).hasClass('disabled')) {
                return false;
            }
            var $block = $(this).parents('.plateblock');
            var $pl_ID = $block.data('id');
            $btn.data('oldtxt', $btn.html());
            $btn.html('Loading...');
            var $url = location.href + '&trajax=1&act=studio_export&pl_IDs=' + $pl_ID;
            $('#print_frame_mu').attr('src', $url);
            setTimeout(function () {
                $btn.html($btn.data('oldtxt'));
            }, 2000);
            return false;
        })
        .on('click', '.com_biorad_exportbtn', function () {
            var $btn = $(this);
            var $block = $(this).parents('.combinedblock');
            var $com_ID = $block.data('id');
            var $temp = $(this).data('tem') | '';
            $btn.data('oldtxt', $btn.html());
            $btn.html('Loading...');
            var $url = location.href + '&trajax=1&act=biorad_export&com_IDs=' + $com_ID + '&tem=' + $temp;
            $('#print_frame_mu').attr('src', $url);
            setTimeout(function () {
                $btn.html($btn.data('oldtxt'));
            }, 2000);
            return false;
        })
        .on('click', '.com_studio_exportbtn', function () {
            var $btn = $(this);
            var $block = $(this).parents('.combinedblock');
            var $com_ID = $block.data('id');
            var $temp = $(this).data('tem') | '';
            $btn.data('oldtxt', $btn.html());
            $btn.html('Loading...');
            var $url = location.href + '&trajax&act=studio_export&com_IDs=' + $com_ID + '&tem=' + $temp;
            $('#print_frame_mu').attr('src', $url);
            setTimeout(function () {
                $btn.html($btn.data('oldtxt'));
            }, 2000);
            return false;
        })
        .on('click', '.com_pl_qtower_exportbtn', function () {
            var $btn = $(this);
            var $block = $(this).parents('.combinedblock');
            var $com_ID = $block.data('id');
            var $temp = $(this).data('tem') | '';
            $btn.data('oldtxt', $btn.html());
            $btn.html('Loading...');
            var $url = location.href + '&trajax=1&act=qtower_export&com_IDs=' + $com_ID + '&tem=' + $temp;
            $('#print_frame_mu').attr('src', $url);
            setTimeout(function () {
                $btn.html($btn.data('oldtxt'));
            }, 2000);
            return false;
        })
        .on('click', '.ntc2 input', function () {
            var $this = $(this);
            if ($this.val() === 'HSC') {
                return;
            }
            $(this).removeAttr('readonly').val('');
            $(this).autocomplete({
                source: $ntc2_texts,
                minLength: 0,
                select: function () {
                    setTimeout(function () {
                        $this.change();
                    }, 500);
                }
            }).autocomplete("search", "");
        })
        .on('change focusout', '.ntc2 input', function () {
            var $block = $(this).parents('.plateblock');
            var $pl_ID = $block.data('id');
            var $vl = $(this).val();
            $(this).attr('readonly');
            if ($vl != '') {
                $.ajax({
                    data: {
                        trajax: 1,
                        act: 'ntc2_change_text',
                        pl_ID: $pl_ID,
                        text: $vl
                    },
                    type: 'post',
                    dataType: 'json',
                    success: function (json) {
                        if (json.status == 'ok') {

                        } else {
                            alert('Error:' + json.msg);
                        }
                    },
                    error: function () {
                        alert('Error');
                    }
                })
            }
        })
        .on('click', '.lockbtn', function () {
            var $btn = $(this);
            var $pl_IDs = [];
            var $com_IDs = [];
            $('.plateblock').each(function () {
                if ($('input[type="checkbox"]', $(this)).is(':checked')) {
                    $pl_IDs.push($(this).data('id'));
                }
            })
            $('.combinedblock').each(function () {
                if ($('input[type="checkbox"]', $(this)).is(':checked')) {
                    $com_IDs.push($(this).data('id'));
                }
            })

            if ($pl_IDs.length + $com_IDs.length == 0) {
                alert('Please select Plates!');
                return false;
            }
            if (!confirm('Are you sure Lock Plates?')) {
                return false;
            }
            if ($btn.html() == 'Locking...') return false;
            $btn.data('oldtxt', $btn.html());
            $btn.html('Locking...');

            $.ajax({
                data: {
                    trajax: 1,
                    act: 'lock_plates',
                    pl_IDs: $pl_IDs,
                    com_IDs: $com_IDs
                },
                type: 'post',
                dataType: 'json',
                success: function (json) {
                    $btn.html($btn.data('oldtxt'));
                    if (json.status == 'ok') {
                        for (i in $pl_IDs) {
                            var pid = $pl_IDs[i];
                            var block = $('.plateblock[data-id="' + pid + '"]');
                            block.addClass('locked');
                        }

                        $('.combinedblock input[type="checkbox"]').prop('checked', false);
                        $('.plateblock input[type="checkbox"]').prop('checked', false);
                    } else {
                        alert('Error');
                    }
                },
                error: function () {
                    $btn.html($btn.data('oldtxt'));
                    alert('Error');
                }
            })
            return false;
        })
        .on('click', '.unlockbtn', function () {
            var $btn = $(this);
            var $pl_IDs = [];
            var $com_IDs = [];
            $('.plateblock').each(function () {
                if ($('input[type="checkbox"]', $(this)).is(':checked')) {
                    $pl_IDs.push($(this).data('id'));
                }
            })
            $('.combinedblock').each(function () {
                if ($('input[type="checkbox"]', $(this)).is(':checked')) {
                    $com_IDs.push($(this).data('id'));
                }
            })

            if ($pl_IDs.length + $com_IDs.length == 0) {
                alert('Please select Plates!');
                return false;
            }
            if (!confirm('Are you sure UnLock Plates?')) {
                return false;
            }
            if ($btn.html() == 'Locking...') return false;
            $btn.data('oldtxt', $btn.html());
            $btn.html('Locking...');

            $.ajax({
                data: {
                    trajax: 1,
                    act: 'unlock_plates',
                    pl_IDs: $pl_IDs,
                    com_IDs: $com_IDs
                },
                type: 'post',
                dataType: 'json',
                success: function (json) {
                    $btn.html($btn.data('oldtxt'));
                    if (json.status == 'ok') {
                        for (i in $pl_IDs) {
                            var pid = $pl_IDs[i];
                            var block = $('.plateblock[data-id="' + pid + '"]');
                            block.removeClass('locked');
                        }
                        $('.combinedblock input[type="checkbox"]').prop('checked', false);
                        $('.plateblock input[type="checkbox"]').prop('checked', false);
                    } else {
                        alert('Error');
                    }
                },
                error: function () {
                    $btn.html($btn.data('oldtxt'));
                    alert('Error');
                }
            })
            return false;
        }).on('mouseover', 'td.assen input', function () {

            if ($(this).val().indexOf(',') > -1) {
                $(this).parent().append('<div class="tooltip">' + $(this).val() + '</div>');
            }
        }).on('mouseout', 'td.assen input', function () {
            $(this).parent().find('.tooltip').remove();
        }).on('change', 'form input.submitchange', function () {
            $(this).parents('form').submit();
        }).on('click', '.upload_plate_file', function () {
            var $pr = $(this).parents('.plateblock');
            var $pl_ID = $pr.data('id');
            var $type = $(this).data('type');
            if ($(this).html() != 'Uploading...') {
                var $fname = 'input.plate_input_file.inputfilefile_' + $type + $pl_ID;
                $($fname).remove();
                $('body').append('<input type="file" class="plate_input_file inputfilefile_' + $type + $pl_ID + '" data-id="' + $pl_ID + '" data-type="' + $type + '" accept="text/csv" style="display:none"/>');
                $($fname).click();
            }
        }).on('change', '.plate_input_file', function () {
            if ($(this).val() == '') return;
            var $pl_ID = $(this).data('id');
            var $type = $(this).data('type');
            var $btn = $('.upload_plate_file_' + $type + '_' + $pl_ID);
            if ($btn.html() == 'Uploading...') return false;
            $btn.data('oldtxt', $btn.html());
            $btn.html('Uploading...');

            var fd = new FormData();
            var files = $(this)[0].files[0];

            fd.append('file', files);
            fd.append('trajax', '1');
            fd.append('act', 'upload_' + $type);
            fd.append('pl_ID', $pl_ID);
            $(this).remove();
            $.ajax({
                data: fd,
                type: 'post',
                dataType: 'json',
                contentType: false,
                processData: false,
                success: function (json) {
                    if (json.status != 'ok') {
                        alert('Error:' + json.msg);
                    } else {

                        location.reload();
                    }
                    $btn.html($btn.data('oldtxt'));
                },
                error: function () {
                    alert('Error');
                    $btn.html($btn.data('oldtxt'));
                }
            });
        }).on('click', '.pass_qc', function () {
            var $pr = $(this).parents('.plateblock');
            var $tr = $(this).parents('tr.dx11_fail');
            var $pl_ID = $pr.data('id');
            var $qc = $(this).data('qc');
            var $well = $('td.well span.wellnum', $tr).html();
            var $assen = $('td.assen input', $tr).val();
            $("#dialog_pass_qc").dialog({
                resizable: false,
                height: "auto",
                width: 400,
                modal: true,
                buttons: {
                    "Submit": function () {
                        var $reason = $('#reason_pass').val();
                        if ($reason != '') {
                            $.ajax({
                                data: { trajax: 1, act: 'pass_qc', qc: $qc, pl_ID: $pl_ID, assen: $assen, well: $well, reason: $reason },
                                type: 'post',
                                dataType: 'json',
                                success: function (json) {
                                    if (json.status != 'ok') {
                                        alert('Error:' + json.msg);
                                    } else {

                                        location.reload();
                                    }
                                },
                                error: function () {
                                    alert('Error');
                                }
                            });
                            $(this).dialog("close");
                        }

                    },
                    Cancel: function () {
                        $(this).dialog("close");
                    }
                }
            });
            return false;
        });
})