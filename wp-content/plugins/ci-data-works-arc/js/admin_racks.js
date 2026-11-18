if (typeof $use_zebra === 'undefined') {
    var $use_zebra = false;
}

jQuery(function ($) {
    var $barcode_doing = false;
    var $ajax_requested = 0;
    var $ajax_done = 0;
    var $doing_change_reid = false;

    function tr_check_mixed_panels($retr) {
        try {
            var $bid = $retr.data('p');
            var $tr = $('tr.child.p_' + $bid);
            var $btype = $('select.btype', $tr).val();
            var $pntype = $('select[data-fname="re_panel_id"]', $retr).val();
            if ($pntype == -300 && $btype.indexOf('pcr') > -1) {
                $('select.bpanelid', $tr).show();
            } else {
                $('select.bpanelid', $tr).hide();
            }
        } catch (e) {
            console.log(e);
        }
    }

    function request_barcode($barcode, $rack, $btype, $this, $parentdata, $duplicate, $pnid, $mixedvp, $parts) {
        $barcode_doing = true;
        $ajax_requested++;
        var $pr = $this.parent();
        var $id = $this.parents('tr').data('id');
        var $retr = $('tr.parent_c[data-p="batch_' + $id + '"]');
        var $tbl = $pr.find('table tbody');
        var $new_well = $('.add_new_well', $pr).data('new_well');
        $('.showloading', $pr).html('<span class="smallloading"></span>');
        $.ajax({
            data: {
                trajax: '1',
                act: 'ci_search_barcode_rack',
                code: $barcode,
                rack: $rack,
                btype: $btype,
                parentdata: $parentdata,
                duplicate: $duplicate ? '1' : '0',
                new_well: $new_well ? 1 : 0,
                pnid: $pnid,
                mixedvp: $mixedvp,
                parts: $parts
            },
            type: 'post',
            dataType: 'json',
            success: function (rs) {
                $('.add_new_well', $pr).data('new_well', false);
                if (rs.use_rack4) {
                    $('.add_new_well', $pr).removeClass('hide');
                }

                $this.val('');
                try {
                    if ($('select.mixedvp', $pr)) {
                        $('select.mixedvp', $pr).val('auto');
                    }
                } catch (e) { }
                $('.showloading', $pr).html('');
                $ajax_done++;
                $barcode_doing = false;

                if (rs.re_ID > 0) {
                    $('input[data-fname="re_ID"]', $retr).val(rs.re_ID);
                    $parentdata.re_ID = rs.re_ID;
                }
                if (rs.status == 'rerun' || rs.status == 'duplicate') {
                    if (rs.ask_reason_rerun) {
                        $('#dialog_warning').remove();
                        var $html = '<div id="dialog_warning" style="display:none">';
                        $html += '<p>' + rs.message + '</p>';
                        if (rs.list_reason) {
                            $html += '<lable>Reason:</lable>';
                            $html += '<select id="reason_rerun">';
                            if (rs.reason_rerun) {
                                $html += '<option value="' + rs.reason_rerun + '">' + rs.reason_rerun + '</option>';
                            }
                            else {
                                $html += '<option value="">Select</option>';
                            }

                            for (i in rs.list_reason) {
                                $html += '<option value="' + rs.list_reason[i] + '">' + rs.list_reason[i] + '</option>';
                            }
                            $html += '</select>';
                        }
                        $html += '</div>';
                        $('body').append($html);
                        $("#dialog_warning").dialog({
                            modal: true,
                            title: 'Warning',
                            dialogClass: 'dialog_warning',
                            width: '420px',
                            buttons: {
                                "Accept": function () {
                                    $parentdata.reason_rerun = $('#reason_rerun').val();
                                    request_barcode($barcode, $rack, $btype, $this, $parentdata, true, $pnid, $mixedvp, $parts);
                                    $(this).dialog("close");
                                },
                                "Cancel": function () {
                                    $(this).dialog("close");
                                }
                            }
                        });
                    }
                    else if (confirm(rs.message)) {
                        request_barcode($barcode, $rack, $btype, $this, $parentdata, true, $pnid, $mixedvp, $parts);
                    }
                }
                else if (rs.status != 'ok') {
                    document.getElementById('beep_player').play();
                    if (rs.message && rs.message != '') {
                        alert(rs.message);
                    } else {
                        alert("Not found");
                    }
                    document.getElementById('beep_player').pause();
                } else {

                    if (rs.add_barcode && rs.add_to) {
                        var $td = $('tr.rbid_' + rs.add_to + ' > td.assennum_' + rs.child_pos, $tbl);
                        $td.attr('data-mid', rs.mid);
                        $td.attr('data-dob', rs.dob);
                        $td.attr('data-patient_name', rs.patient_name);
                        $td.html(rs.add_barcode);
                    }
                    else if (rs.html) {
                        var $new = $(rs.html);
                        $tbl.append($new);
                    }

                    if (rs.sync_plate) {
                        $('.addplatebtn', $pr).data('sync_plate', true).click();
                    }

                }

            },
            error: function () {
                $barcode_doing = false;
                $ajax_done++;
                $('.showloading', $pr).html('');
                alert('Error, Please try again');
            }
        });
    }

    $('a.add_new_row').click(function () {

        var $newp = '<tr class="parent_c" data-p="batch_' + $new_id + '">' + $('#rec_tbl_add > tbody > tr.first').html() + '</tr>';
        $newp = $newp.replace(/\[\d\]/g, '[' + $new_id + ']');
        $newp = $($newp);
        $newp.find('input:not(.date)').val('');
        $newp.find('input[data-fname="re_subtitle"]').val('');
        $newp.find('select[data-fname*="re_priority"]').val('low');
        $newp.find('select[data-fname*="re_kind"]').val('0');
        $newp.find('select[data-fname*="re_rack"]').val($new_id);
        var $re_panel_id = $newp.find('select[data-fname*="re_panel_id"]');
        if ($re_panel_id.length > 0 && $('option', $re_panel_id).length > 1) {
            $re_panel_id.val('');
        }


        if ($today) {
            $newp.find('input[data-fname*="re_today"]').val($today);
        }

        $newp.find('.racknum').html($new_id);

        var $newc = '<tr class="child p_batch_' + $new_id + '" data-id="' + $new_id + '">' + $('#rec_tbl_add > tbody > tr.first_child').html() + '</tr>';
        $newc = $($newc);
        $('tbody', $newc).html('');

        $('span.select2', $newp).remove();
        $('span.select2', $newc).remove();
        $('a.add_new_well', $newc).addClass('hide');
        $('select', $newp).removeClass('select2-hidden-accessible');
        $('select', $newc).removeClass('select2-hidden-accessible');
        $('.hasdate', $newp).removeClass('hasDatepicker').attr('id', 'date_' + $new_id);
        $('.hasdate', $newp).each($.tr_setup_datef);
        $('input', $newp).each(function () {
            if ($(this).data('default')) {
                $(this).val($(this).data('default'));
            }
        })

        //check type 18 & relate
        var $last18 = $('#rec_tbl_add tr.parent_c[data-type="18"]:last');
        if ($last18 && $last18.length > 0) {
            var $relateid = $last18.data('relate_id');
            if (!$relateid || $relateid == 0) {
                if (confirm('it should be part of the Accufill plate done previously?')) {
                    var $lastid = $('input[data-fname="re_ID"]', $last18).val();
                    $('td:first-child', $newp).append('<input type="hidden" data-fname="re_relate_id" value="' + $lastid + '">');
                }
            }
        }


        $('#rec_tbl_add > tbody').append($newp);
        $('#rec_tbl_add > tbody').append($newc);
        $newp.trigger('click');
        //save prev
        var $prev_rack = $new_id - 1;

        $('tr[data-rack="' + $prev_rack + '"] select').change();

        $.setup_select2();

        $new_id++;
    })

    $('body').on('click', '.warning_duplicate', function () {
        $(this).html('').removeClass('error');
    }).on('click', '.remove_item', function () {
        if (confirm('Do you want delete?') == false) return false;

        var $pr = $(this).parents('tr.childp');
        var $mid = $('input.mid', $pr).val();
        var $tbl = $pr.parents('table.batch_tbl');
        var $id = $(this).parents('tr.child').data('id');
        var $retr = $('tr.parent_c[data-p="batch_' + $id + '"]');
        var $re_ID = $('input[data-fname="re_ID"]', $retr).val();
        $pr.addClass('bg_remove');

        $ajax_requested++;
        $.ajax({
            data: { tr_action: 'ci_remove_rack', mid: $mid, re_ID: $re_ID },
            dataType: 'json',
            type: 'post',
            success: function (rs) {
                $ajax_done++;

                $pr.remove();
                //update qty

            },
            error: function () {
                $ajax_done++;
                alert('Remove Failed, Please try again!');
                $pr.removeClass('bg_remove');
            }
        });

        return false;

    }).on('click', '.printall_btn', function () {
        var $this = $(this);

        if (typeof window.tr_hold_print_button === 'function') {
            if (!window.tr_hold_print_button($this, 'Printing...')) return false;
        } else {
            if ($this.html() == 'Loading') return false;
            $this.data('oldval', $this.html());
            $this.html('Printing...');
        }

        var $print_mids = '';
        var $print_data = [];
        $('table.report_tbl tr.childp', $(this).parents('td')).each(function () {
            var $tr = $(this);
            console.log($tr);
            $('td.assen', $tr).each(function () {
                var $td = $(this);
                var $barcode = $td.html().trim();
                console.log($barcode);
                if ($barcode != '') {
                    var $patient_name = $td.data('patient_name');
                    var $position = $td.data('position');
                    var $dob = $td.data('dob');
                    var $panels = $td.data('panels_print');

                    if ($dob) {
                        $patient_name += '  ' + $dob;
                    }
                    var $row = {
                        barcode: $barcode,
                        patient_name: $patient_name,
                        position: $position,
                        panels: $panels
                    };
                    console.log($row);
                    $print_data.push($row);

                    $print_mids += $td.data('mid') + ',';
                }

            })

        })
        var $url = '?tr_action=ci_print_barcodes&mids=' + $print_mids + '&print=1';
        if ($(this).hasClass('reprint')) {
            $url += '&reprint=1';
        }


        if ($use_zebra) {
            printLabels($print_data, 0, function () {
                if (typeof window.tr_release_print_button === 'function') {
                    window.tr_release_print_button($this);
                } else {
                    $this.html($this.data('oldval'));
                }
            });
        }
        else {
            $('#reprint_frame').attr('src', $url);
            setTimeout(function () {
                if (typeof window.tr_release_print_button === 'function') {
                    window.tr_release_print_button($this);
                } else {
                    $this.html($this.data('oldval'));
                }
            }, 2000);

        }
        return false;
    }).on('click', '.unrackbtn', function () {
        var $this = $(this);
        var $pr = $(this).parent();
        var $tr_child = $(this).parents('tr');
        var $id = $tr_child.data('id');
        var $retr = $('tr.parent_c[data-p="batch_' + $id + '"]');
        var $re_ID = $('input[data-fname="re_ID"]', $retr).val();
        if (confirm('Are you sure Unrack #' + $id + '?')) {
            $this.html('Removing...');
            $.ajax({
                data: { trajax: 1, act: 'remove_rack', re_ID: $re_ID },
                type: 'post',
                dataType: 'json',
                success: function (json) {

                    if (json.status == 'ok') {
                        $retr.remove();
                        $tr_child.remove();
                    } else {
                        alert('Error: ' + json.message);
                        $this.html('Unrack');
                    }
                }
            });
        }
        return false;
    }).on('click', 'input.select_all', function () {
        var $tr_child = $(this).parents('tr');
        $('tbody td input[type="checkbox"]', $tr_child).prop('checked', $(this).is(':checked'));
    }).on('click', '.removesamplebtn', function () {
        var $this = $(this);
        var $pr = $(this).parent();
        var $tr_child = $(this).parents('tr.child');
        var $id = $tr_child.data('id');
        var $retr = $('tr.parent_c[data-p="batch_' + $id + '"]');
        var $re_ID = $('input[data-fname="re_ID"]', $retr).val();
        var $rbids = [];
        $('tbody td input[type="checkbox"]:checked', $tr_child).each(function () {
            var $tr = $(this).parents('tr.childp');
            $rbids.push($tr.data('rbid'));
        })
        $('input.select_all', $tr_child).prop('checked', false);
        if ($rbids.length == 0) {
            return false;
        }
        if (!confirm('Are you sure delete selected?')) return false;
        if ($this.html() == 'Deleting') return false;
        $this.data('oldval', $this.html());
        $this.html('Deleting');
        $.ajax({
            data: { trajax: 1, act: 'remove_batches', re_ID: $re_ID, rbids: $rbids },
            type: 'post',
            dataType: 'json',
            success: function (json) {

                $this.html($this.data('oldval'));
                if (json.status == 'ok') {
                    $('.subretbl tbody', $tr_child).html(json.batches);
                    if (json.sync_plate) {
                        $('.addplatebtn', $tr_child).data('sync_plate', true).click();
                    }
                } else {
                    alert('Error: ' + json.message);
                }
            },
            error: function () {
                $this.html($this.data('oldval'));
                alert('Error. Please try again!');
            }
        });
        return false;
    }).on('click', '.addplatebtn', function () {
        var $this = $(this);
        var $pr = $(this).parent();
        var $id = $(this).parents('tr').data('id');
        var $sync_plate = $this.data('sync_plate');
        var $retr = $('tr.parent_c[data-p="batch_' + $id + '"]');
        var $re_ID = $('input[data-fname="re_ID"]', $retr).val();

        if ($sync_plate) {
            $this.data('sync_plate', false);
        } else {
            $this.html('Adding...');
        }

        $.ajax({
            data: { trajax: 1, act: 'add_plate', re_ID: $re_ID },
            type: 'post',
            dataType: 'json',
            success: function (json) {

                if (json.status == 'ok') {
                    if (!$sync_plate) {
                        $this.html('Add Plate');
                        alert('Added to Plate Successfully!');
                    }

                } else {
                    if (!$sync_plate) {
                        alert('Error: ' + json.message);
                        $this.html('Add Plate');
                    } else {
                        setTimeout(function () {
                            $this.data('sync_plate', true).click();
                        }, 1000);
                    }
                }
            }
        });
    }).on('keypress', '.barcode_auto', function (e) {
        var code = e.keyCode || e.which;
        var $barcode = $(this).val().trim();
        if (code == 13 && $barcode != '' && !$barcode_doing) {
            var $this = $(this);
            var $pr = $this.parent();
            var $can_submit = true;

            var $btype = $('.btype', $pr).val();
            var $pnid = $('select.bpanelid', $pr).val();
            var $mixedvp = $('select.mixedvp', $pr).val();
            var $tbl = $pr.find('table tbody');
            var $warning = $('.warning_duplicate', $this.parent());
            var $id = $this.parents('tr').data('id');
            var $retr = $('tr.parent_c[data-p="batch_' + $id + '"]');
            var $parentdata = {};
            var $re_ID = 0;
            var $rack = $retr.data('rack');
            var $parts = {};

            $('select.rackparts', $pr).each(function () {
                var $pk = $(this).data('key');
                $parts[$pk] = $(this).val();
            })
            console.log($parts);

            var $count = $('tr.childp', $tbl).length;

            if ($count >= 95) {
                alert('Rack is Full!');
                return;
            }
            if ($doing_change_reid) {
                alert('Busy. Please try again!');
                return;
            }

            //check exists
            $warning.html('').removeClass('error');
            var $is_duplicate = $('.assen_' + $barcode, $pr).length > 0 && $barcode.toLowerCase() != 'placeholder';
            if ($('select.showformixed', $pr).length > 0) {
                $is_duplicate = false;
            }
            if ($is_duplicate) {
                $warning.html('Duplicate barcode').addClass('error');
                $this.val('');
                return false;
            }
            $('input,select', $retr).each(function () {
                var $fname = $(this).data('fname');
                $parentdata[$fname] = $(this).val();
                if ($fname == 're_ID') {
                    $re_ID = parseInt($(this).val());
                }

                var $requ = $(this).attr('required');
                if ($can_submit && typeof $requ !== typeof undefined && $requ !== false && $(this).val() == '') {
                    $can_submit = false;
                    var $msgalert = 'Please enter required field!';
                    if ($fname == 're_panel_id') {
                        $msgalert = 'Please select Panel!';
                    }
                    alert($msgalert);
                    $(this).focus();
                }
            });

            if (!$can_submit) {
                return false;
            }

            request_barcode($barcode, $rack, $btype, $this, $parentdata, false, $pnid, $mixedvp, $parts);

            return false;
        }

    }).on('change', 'tr.parent_c input, tr.parent_c select', function () {

        if ($(this).data('withoutupdate')) {
            $(this).data('withoutupdate', false);
            return;
        }

        var $this = $(this);
        var $retr = $(this).parents('tr.parent_c');
        if ($retr.hasClass('sending')) {
            return;
        }
        var $parentdata = {};
        $('input,select', $retr).each(function () {
            var $fname = $(this).data('fname');
            $parentdata[$fname] = $(this).val();
        });
        if (!$parentdata.re_ID || $parentdata.re_ID == '') {
            $doing_change_reid = true;
        }

        $ajax_requested++;
        $retr.addClass('sending');
        $.ajax({
            data: {
                trajax: '1',
                act: 'ci_update_rack',
                data: $parentdata,
            },
            type: 'post',
            dataType: 'json',
            success: function (json) {
                $ajax_done++;

                if (json.status == 'ok') {
                    if (json.re_ID > 0) {
                        $('input[data-fname="re_ID"]', $retr).val(json.re_ID);
                    }
                    try {
                        if (json.newdata) {
                            for (var ik in json.newdata) {
                                $('input[data-fname="' + ik + '"]', $retr).val(json.newdata[ik]).trigger('changeajax');
                            }
                        }
                    } catch (e) { }
                } else {
                    if (json.status == 'error_kind') {
                        $('select[name*="re_kind"]', $retr).val(json.kind).change();
                    }
                    alert('Error: ' + json.html);
                }
                $doing_change_reid = false;
                $retr.removeClass('sending');
            },
            error: function () {
                $ajax_done++;
                $retr.removeClass('sending');
                $doing_change_reid = false;
                alert('Error. Please refresh page!');
            }
        });
    }).on('change', 'tr.child .subretbl input, tr.child .subretbl select', function () {

        if ($(this).data('withoutupdate')) {
            $(this).data('withoutupdate', false);
            return;
        }

        var $this = $(this);
        var $retr = $(this).parents('tr.childp');


        var $bdata = {};
        $('input,select', $retr).each(function () {
            var $fname = $(this).data('fname');
            if ($fname) {
                $bdata[$fname] = $(this).val();
            }
        });
        $bdata['rbid'] = $retr.data('rbid');


        $ajax_requested++;
        $.ajax({
            data: {
                trajax: '1',
                act: 'ci_update_batch_rack',
                updateby: $this.data('fname'),
                subtype: $retr.data('subtype'),
                data: $bdata,
                rerun: $this.data('rerun') ? 1 : 0,
                reason_rerun: $this.data('reason_rerun')
            },
            type: 'post',
            dataType: 'json',
            success: function (rs) {
                $ajax_done++;
                $this.data('reason_rerun', '');
                $this.data('rerun', '');
                if (rs.status == 'rerun') {
                    $('#dialog_warning').remove();
                    var $html = '<div id="dialog_warning" style="display:none">';
                    $html += '<p>' + rs.message + '</p>';
                    if (rs.list_reason) {
                        $html += '<lable>Reason:</lable>';
                        $html += '<select id="reason_rerun">';
                        if (rs.reason_rerun) {
                            $html += '<option value="' + rs.reason_rerun + '">' + rs.reason_rerun + '</option>';
                        }
                        else {
                            $html += '<option value="">Select</option>';
                        }

                        for (i in rs.list_reason) {
                            $html += '<option value="' + rs.list_reason[i] + '">' + rs.list_reason[i] + '</option>';
                        }
                        $html += '</select>';
                    }
                    $html += '</div>';
                    $('body').append($html);
                    $("#dialog_warning").dialog({
                        modal: true,
                        title: 'Warning',
                        dialogClass: 'dialog_warning',
                        width: '420px',
                        buttons: {
                            "Accept": function () {
                                $this.data('rerun', true);
                                $this.data('reason_rerun', $('#reason_rerun').val());
                                $this.change();
                                $(this).dialog("close");
                            },
                            "Cancel": function () {
                                $this.val('');
                                $(this).dialog("close");
                            }
                        }
                    });
                } else if (rs.status == 'error') {

                    alert('Error: ' + rs.message);
                } else {
                    if (rs.removererun) {
                        var $ion = $this.parent().find('.scircle');
                        if ($ion.hasClass('scircle')) {
                            $ion.remove();
                        }
                    } else if (rs.addrerun) {
                        $this.after('<span class="scircle greencircle" title="Rerun : ' + rs.reason_rerun + '">R</span>');
                    }
                }
            },
            error: function () {
                $ajax_done++;
                alert('Error. Please refresh page!');
            }
        });
    }).on('click', 'tr.parent_c', function (e) {
        if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;
        $('tr.child').hide();
        $('#rec_tbl_add').removeClass('show_all');
        if ($(this).data('showed') != '1') {
            $('tr.p_' + $(this).data('p')).show();
            $('tr.parent_c').data('showed', '0');
            $(this).data('showed', '1');
            $(this).addClass('active');

            $('body,html').scrollTop($(this).offset().top - 90);
        } else {
            $('tr.p_' + $(this).data('p')).hide();
            $(this).data('showed', '0');
            $(this).removeClass('active');
        }
        return false;
    }).on('click', function () {
        document.getElementById('beep_player').pause();
    }).on('click', '.show_hide_all', function () {
        var $tbl = $('#rec_tbl_add');
        var $this = $(this);
        if ($tbl.hasClass('show_all')) {
            $tbl.removeClass('show_all');
            $this.html('Show All');
        } else {
            $tbl.addClass('show_all');
            $this.html('Hide All');
        }
    }).on('change', 'select.btype', function () {
        var $this = $(this);
        var $pr = $this.parent();
        if (!$this.data('byfocus')) {
            $this.data('byfocus', false);
            $('.barcode_auto', $pr).focus();
        }
        var $tr = $this.parents('tr.child');

        var $id = $tr.data('id');
        var $retr = $('tr.parent_c[data-p="batch_' + $id + '"]');
        var $parentdata = [];
        if ($retr.hasClass('sending')) {
            return;
        }
        $('input,select', $retr).each(function () {
            var $fname = $(this).data('fname');
            if ($fname && $fname != undefined) {
                $parentdata[$fname] = $(this).val();
            }

        });

        if ($parentdata.length == 0) {
            return;
        }
        $parentdata.re_rack_type = $this.val();
        if (!$parentdata.re_ID || $parentdata.re_ID == '') {
            $doing_change_reid = true;
        }

        $ajax_requested++;
        $retr.addClass('sending');
        $.ajax({
            data: {
                trajax: '1',
                act: 'ci_update_rack',
                data: $parentdata
            },
            type: 'post',
            dataType: 'json',
            success: function (json) {
                if (json.status == 'ok') {
                    if (json.re_ID > 0) {
                        $('input[data-fname="re_ID"]', $retr).val(json.re_ID);
                    }
                } else {
                    alert('Error: ' + json.html);
                }
                $doing_change_reid = false;
                $this.data('called_change', true);
                $ajax_done++;
                $retr.removeClass('sending');
            },
            error: function () {
                $retr.removeClass('sending');
                $doing_change_reid = false;
                $ajax_done++;
            }
        });
        tr_check_mixed_panels($retr);
    }).on('click', 'a.add_new_well', function () {
        $(this).data('new_well', true);
        $(this).addClass('hide');
        $(this).parent().find('.barcode_auto').focus();
        return false;
    }).on('click', '.export_btn', function () {
        var $id = $(this).parents('tr').data('id');
        var $retr = $('tr.parent_c[data-p="batch_' + $id + '"]');
        var $re_ID = $('input[data-fname="re_ID"]', $retr).val();
        location.href = location.href + '&trajax=1&act=export_rack&id=' + $re_ID;
        return false;
    }).on('click', '.barcode_auto', function () {
        var $trc = $(this).parents('tr.child');
        var $bts = $('select.btype', $trc);
        var $called = $bts.data('called_change');
        if (!$called) {
            $bts.data('byfocus', true);
            $bts.change();
        }
    }).on('change', 'form input.submitchange', function () {
        $(this).parents('form').submit();
    }).on('change changeajax', '#form_rack input[data-fname="re_subtitle"]', function () {
        var $trp = $(this).parents('tr.parent_c');
        var $trc = $trp.next('tr.child');
        var $mixedvp = $('select.mixedvp', $trc);
        if ($mixedvp) {
            if ($(this).val().substring(0, 1) == 'M') {
                $mixedvp.show().addClass('showformixed');
            } else {
                $mixedvp.hide().removeClass('showformixed');
            }
        }
    }).on('change', '#form_rack select[data-fname="re_panel_id"]', function () {
        var $trp = $(this).parents('tr.parent_c');
        var $trc = $trp.next('tr.child');
        var $cpid = $(this).val();

        $('.rackparts', $trc).hide();
        $('.parts_' + $cpid, $trc).show();
    })


    $(window).bind('beforeunload', function () {
        console.log('$ajax_requested: ' + $ajax_requested);
        console.log('$ajax_done: ' + $ajax_done);
        if ($ajax_requested > $ajax_done) {
            return "Do you want to leave this page without saving?";
        }
    });


    $('#form_rack').show();
    $('#loading_form').hide();
})
