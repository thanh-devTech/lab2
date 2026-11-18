if (typeof $use_zebra === 'undefined') {
    var $use_zebra = false;
}
if (typeof $use_zebra_size === 'undefined') {
    var $use_zebra_size = 2;
}

if (typeof $add_doc_to_label === 'undefined') {
    var $add_doc_to_label = false;
}
if (typeof $df_barcode === 'undefined') {
    var $df_barcode = '';
}

jQuery(function ($) {
    var $barcode_doing = false;
    var $ajax_requested = 0;
    var $ajax_done = 0;
    function ensure_printer(onReady, onError) {
        if (typeof window.ensure_printer === 'function') {
            return window.ensure_printer(onReady, onError);
        }
        if (typeof onError === 'function') {
            onError('unavailable');
        }
        return false;
    }

    function printLabel($barcode, $patient_name, $position, $doc, $panels, $cul, printedFunc, errorFunc) {
        if (typeof window.printLabel === 'function') {
            return window.printLabel($barcode, $patient_name, $position, $doc, $panels, $cul, printedFunc, errorFunc);
        }
        if (typeof errorFunc === 'function') {
            errorFunc('Printer bridge is not available.');
        } else {
            alert('Printer bridge is not available.');
        }
        return false;
    }

    function printLabels(data, pos, success) {
        if (typeof window.printLabels === 'function') {
            return window.printLabels(data, pos, success);
        }
        if (typeof success === 'function') {
            success();
        }
        return false;
    }

    function ci_call_manual_reports(bid) {
        $.ajax({
            data: { trajax: 1, act: 'ci_call_manual_reports', bid: bid },
            type: 'post',
            dataType: 'json',
            success: function (json) {
                if (json.status == 'ok') {
                    console.log('Manual reports called successfully');
                } else {
                    console.log('Failed to call manual reports: ' + (json.message || 'Unknown error'));
                }
            },
            error: function () {
                console.log('Error calling manual reports');
            }
        });
    }

    $('body').on('click', '.warning_duplicate', function () {

        $(this).html('').removeClass('error');
    })
        .on('click', '.remove_item', function () {
            if (confirm('Do you want delete?') == false) return false;

            var $pr = $(this).parents('tr.childp');
            var $mid = $('input.mid', $pr).val();
            var $tbl = $pr.parents('table.batch_tbl');
            var $id = $(this).parents('tr.child').data('id');
            var $retr = $('tr.parent_c[data-p="batch_' + $id + '"]');
            var $re_ID = $('input[data-fname="re_ID"]', $retr).val();
            var $key_pos = $pr.data('key_pos');
            $pr.addClass('bg_remove');

            $ajax_requested++;
            $.ajax({
                data: { tr_action: 'ci_remove_receiving', mid: $mid, re_ID: $re_ID },
                dataType: 'json',
                type: 'post',
                success: function (rs) {
                    $ajax_done++;

                    $pr.remove();
                    //update qty

                    var $qty = $('[data-key_pos="' + $key_pos + '"]', $tbl).length;
                    if ($key_pos != 'pcr33') {
                        $('input[data-fname="re_qty_' + $key_pos + '"]', $retr).val($qty).change();
                    }

                    var $min_pos = -10;
                    $('[data-key_pos="' + $key_pos + '"]', $tbl).each(function () {
                        var $pos = parseInt($('.col-position input', $(this)).val());
                        if ($pos < $min_pos) {
                            $min_pos = $pos;
                        }
                    })
                    $start_positions[$re_ID][$key_pos] = $min_pos;
                },
                error: function () {
                    $ajax_done++;
                    alert('Remove Failed, Please try again!');
                    $pr.removeClass('bg_remove');
                }
            });

            return false;

        })
        .on('click', '.printall_btn', function () {
            var $this = $(this);

            if ($this.html() == 'Loading') return false;
            $this.data('oldval', $this.html());
            $this.html('Loading');

            var $print_mids = '';
            var $print_data = [];
            var $print2labels = $('#form_receiving').data('print2');
            if ($(this).hasClass('reprint')) {
                $print2labels = false;
            }
            $('table.report_tbl tr.childp', $(this).parent()).each(function () {
                $print_mids += $('input.mid', $(this)).val() + ',';
                var $pr = $(this);
                var $barcode = $('td.assen', $pr).html();
                var $btype = $pr.data('btype');
                var $patient_name = $('td.patient_name', $pr).html();
                var $position = $btype == 'tox' ? $('td.col-position input', $pr).val() : '';
                var $panels = $pr.data('panels_print');
                var $cul = $pr.data('cul') && $pr.data('cul') == '1' ? 'CU' : '';
                var $dob = $pr.data('dob');
                if ($dob) {
                    $patient_name += '  ' + $dob;
                }
                var $row = {
                    barcode: $barcode,
                    patient_name: $patient_name,
                    position: $position,
                    panels: $panels,
                    cul: $cul
                };
                $print_data.push($row);
                if ($print2labels) {
                    $print_data.push($row);
                }
            })
            var $url = '?tr_action=ci_print_barcodes&mids=' + $print_mids + '&print=1';
            if ($(this).hasClass('reprint')) {
                $url += '&reprint=1';
            }


            if ($use_zebra) {
                printLabels($print_data, 0, function () {
                    $this.html($this.data('oldval'));
                });
            }
            else {
                $('#reprint_frame').attr('src', $url);
                setTimeout(function () {
                    $this.html($this.data('oldval'));
                }, 2000);

            }


            return false;
        })
        .on('click', '.print_barcode', function () {
            var $this = $(this);
            var $pr = $(this).parents('tr.childp');
            var $mid = $('input.mid', $pr).val();
            var $url = '?tr_action=ci_print_barcodes&mids=' + $mid + '&print=1&always=1';

            if ($this.html() == 'Loading') return false;
            $(this).html('Loading');
            var $print2labels = $('#form_receiving').data('print2');

            if ($use_zebra) {
                try {
                    var $assen = $('td.assen', $pr).html();
                    var $btype = $pr.data('btype');
                    var $patient_name = $('td.patient_name', $pr).html();
                    var $position = $btype == 'tox' ? $('td.col-position input', $pr).val() : '';
                    var $dob = $pr.data('dob');
                    var $panels = $pr.attr('data-panels_print');
                    var $cul = $pr.attr('data-cul') && $pr.attr('data-cul') == '1' ? 'CU' : '';
                    var $tddoc = $pr.find('td.doc');
                    var $doc = $('input', $tddoc).length > 0 ? $('input', $tddoc).val() : $tddoc.text();
                    if ($dob) {
                        $patient_name += '  ' + $dob;
                    }
                    var $queue = [{
                        barcode: $assen,
                        patient_name: $patient_name,
                        position: $position,
                        doc: $doc,
                        panels: $panels,
                        cul: $cul
                    }];
                    if ($print2labels) {
                        $queue.push({
                            barcode: $assen,
                            patient_name: $patient_name,
                            position: $position,
                            doc: $doc,
                            panels: $panels,
                            cul: $cul
                        });
                    }
                    var print_next = function ($idx) {
                        if ($idx >= $queue.length) {
                            $this.html('Print');
                            return;
                        }
                        var $item = $queue[$idx];
                        printLabel($item.barcode, $item.patient_name, $item.position, $item.doc, $item.panels, $item.cul, function () {
                            setTimeout(function () {
                                print_next($idx + 1);
                            }, 400);
                        }, function () {
                            $this.html('Print');
                        });
                    };
                    print_next(0);
                } catch (e) {
                    alert('error:' + e);
                    $this.html('Print');
                }
            } else {
                $('#reprint_frame').attr('src', $url);
                setTimeout(function () {
                    $this.html('Print');
                }, 2000);
            }


            return false;
        })
        .on('keypress', '.barcode_auto', function (e) {
            var $this = $(this);
            var $pr = $(this).parent();
            var code = e.keyCode || e.which;
            var $vl = $(this).val().trim();
            var $can_submit = true;
            console.log('keypress:' + code + ' val:' + $vl);

            if (code == 13 && $vl != '' && !$barcode_doing) {
                var $pass_3days = $this.data('pass_3days');
                var $pass_type = $this.data('pass_type');
                $this.data('pass_3days', '');
                $this.data('pass_type', '');
                $this.attr('readonly', true);

                var $btype = $('.btype', $pr).val();
                var $next_positions = '';
                var $tbl = $pr.find('table tbody');
                var $warning = $('.warning_duplicate', $this.parent());
                var $id = $(this).parents('tr').data('id');
                var $retr = $('tr.parent_c[data-p="batch_' + $id + '"]');
                var $parentdata = {};
                var $re_ID = 0;
                var $qty_type = $btype;
                if ($btype == 'tox') $qty_type = 'urine';
                if ($btype == 'toxo') $qty_type = 'oral';
                var $today = $('input[data-fname="re_today"]', $retr).val();
                var $qty = $('input[data-fname="re_qty_' + $qty_type + '"]', $retr).val();
                var $countsam = $('[data-key_pos="' + $qty_type + '"]', $tbl).length + 1;


                //check keys
                try {
                    var $skipcheckkey = $this.data('skipcheckkey');
                    $this.data('skipcheckkey', false);
                    if ($receiving_keys && !$skipcheckkey) {
                        var $vllower = $vl.toLowerCase();
                        var $has_rekey = false;
                        var $text_keys = $receiving_keys.join(' or ');
                        for (ik in $receiving_keys) {
                            var $rk = $receiving_keys[ik].toLowerCase();
                            if ($vllower.indexOf($rk) == 0) {
                                $has_rekey = true;
                            }
                        }
                        if (!$has_rekey) {
                            $("#dialog_warning").dialog({
                                modal: true,
                                title: 'Warning',
                                dialogClass: 'receivingwarning',
                                buttons: {
                                    "Accept": function () {
                                        $this.data('skipcheckkey', true);
                                        $(this).dialog("close");
                                        $this.focus();
                                        var e = $.Event('keypress');
                                        e.which = 13;
                                        $this.trigger(e);
                                    },
                                    "Cancel": function () {
                                        $(this).dialog("close");
                                        $this.attr('readonly', false);
                                        $this.val('').focus();
                                    }
                                }
                            });
                            $('#dialog_warning').html('Barcode must begin with one of: ' + $text_keys);
                            return false;
                        }
                    }
                } catch (e) {
                    console.log(e);
                }

                if ($barcode_doing) {
                    return false;
                }
                $barcode_doing = true;

                if ($btype == 'pcr4') {
                    $qty_type = 'pcr';
                }

                if ($qty_type == 'pcr') {
                    if ($countsam > 96) {
                        alert('Maximum sample is 96');
                        $barcode_doing = false;
                        $this.removeAttr('readonly');
                        return false;
                    }
                }


                $('input,select', $retr).each(function () {
                    var $fname = $(this).data('fname');

                    if ($(this).attr('type') == 'checkbox') {
                        $parentdata[$fname] = $(this).is(':checked') ? 1 : 0;
                    } else {
                        $parentdata[$fname] = $(this).val();
                    }
                    if ($fname == 're_ID') {
                        $re_ID = parseInt($(this).val());
                    } else if ($fname == 're_qty_' + $qty_type && $qty < $countsam) {
                        $parentdata[$fname] = $countsam;
                    }

                    var $requ = $(this).attr('required');
                    if ($can_submit && typeof $requ !== typeof undefined && $requ !== false && $(this).val() == '') {
                        $can_submit = false;
                        alert('Please enter required field!');
                        $(this).focus();
                    }
                });
                if ($re_ID > 0) {
                    $next_positions = $start_positions[$re_ID];
                }

                if (!$can_submit) {
                    $this.removeAttr('readonly');
                    return false;
                }
                $this.val('');


                //check exists
                $warning.html('').removeClass('error');
                if ($('#rec_tbl_add .assen_' + $vl).length > 0) {
                    $warning.html('Duplicate barcode').addClass('error');
                    $this.removeAttr('readonly');
                    return false;
                } else {
                    //double check
                    var $has_duplicate = false;
                    $('#rec_tbl_add .childp').each(function () {
                        if ($(this).hasClass('assen_' + $vl)) {
                            $has_duplicate = true;
                        }
                    })
                    if ($has_duplicate) {
                        $warning.html('Duplicate barcode').addClass('error');
                        $this.removeAttr('readonly');
                        return false;
                    }
                }

                var $ii = $('.childp', $this.parent()).length + 1;
                var $exist_positions = '';
                $('tr.parent_c').each(function () {
                    var $ptoday = $('input.date', $(this)).val();

                    if ($ptoday == $today) {
                        var $pbid = $(this).data('p');
                        //console.log('$ptoday:'+$ptoday+'pid:'+$pbid);
                        $('tr.child.p_' + $pbid + ' td.col-position input').each(function () {
                            $exist_positions += $(this).val() + ',';
                        })
                    }
                })


                $barcode_doing = true;
                $ajax_requested++;
                $.ajax({
                    data: {
                        tr_action: 'ci_search_barcode_receiving',
                        code: $vl,
                        id: $id,
                        ii: $ii,
                        next_positions: $next_positions,
                        exist_positions: $exist_positions,
                        btype: $btype,
                        parentdata: $parentdata,
                        pass_3days: $pass_3days,
                        pass_type: $pass_type
                    },
                    type: 'post',
                    dataType: 'json',
                    success: function (rs) {
                        $ajax_done++;
                        $barcode_doing = false;
                        $this.data('pass_3days', '');
                        $this.removeAttr('readonly');

                        if (rs.re_ID > 0) {
                            $re_ID = rs.re_ID;
                            $('input[data-fname="re_ID"]', $retr).val(rs.re_ID);
                        }

                        if (rs.status == 'none') {
                            //none
                        }
                        else if (rs.status == 'choose') {
                            $("#dialog_warning_empty").html(rs.message).dialog({
                                modal: true,
                                title: 'Warning',
                                width: rs.width || 400,
                                dialogClass: 'receivingwarning',
                                buttons: {
                                    "Yes": function () {
                                        $this.val($vl);
                                        $this.data('pass_type', rs.choose_type + '|yes');
                                        $(this).dialog("close");
                                        $this.focus();
                                        var e = $.Event('keypress');
                                        e.which = 13;
                                        $this.trigger(e);
                                    },
                                    "No": function () {

                                        $(this).dialog("close");
                                        $this.focus();
                                        if (rs.choose_return_no) {
                                            $this.val('');
                                            return;
                                        }
                                        $this.val($vl);
                                        $this.data('pass_type', rs.choose_type + '|no');
                                        var e = $.Event('keypress');
                                        e.which = 13;
                                        $this.trigger(e);
                                    }
                                }
                            });

                            $('.receivingwarning button:contains("No")').focus();
                        }
                        else if (rs.status == 'm3days') {
                            $("#dialog_warning_empty").html(rs.message).dialog({
                                modal: true,
                                title: 'Warning',
                                dialogClass: 'receivingwarning',
                                buttons: {
                                    "OK": function () {
                                        $this.val($vl);
                                        $this.data('pass_3days', 'yes');
                                        $(this).dialog("close");
                                        $this.focus();
                                        var e = $.Event('keypress');
                                        e.which = 13;
                                        $this.trigger(e);
                                    },
                                    "Cancel": function () {
                                        $this.data('pass_3days', '');
                                        $(this).dialog("close");
                                        $this.val('').focus();
                                    }
                                }
                            });

                            $('.receivingwarning button:contains("Cancel")').focus();
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
                            var $new = $(rs.html);
                            $tbl.prepend($new);

                            $start_positions[$re_ID] = rs.next_positions;

                            if ($countsam > $qty) {
                                $('input[data-fname="re_qty_' + $qty_type + '"]', $retr).val($countsam).data('withoutupdate', true).change()
                            }

                            if (rs.stat_alert) {
                                alert(rs.stat_alert);
                            }
                        }

                    },
                    error: function () {
                        $this.removeAttr('readonly');
                        $barcode_doing = false;
                        $ajax_done++;
                        $this.val($vl);
                        alert('Error, Please try again');
                    }
                });
                return false;
            }

        })
        .on('change', '.col-position input', function () {
            var $exists = false;
            var $this = $(this);
            var $pos = parseInt($this.val());
            var $battr = $(this).parents('tr.childp');
            var $id = $(this).parents('tr.child').data('id');
            var $retr = $('tr.parent_c[data-p="batch_' + $id + '"]');
            var $today = $('input[data-fname="re_today"]', $retr).val();
            var $mid = $('input.mid', $battr).val();
            var $re_ID = $('input[data-fname="re_ID"]', $retr).val();
            if (isNaN($pos)) $pos = '';
            $this.val($pos);
            if ($pos == '' || $this.data('lastval') == $pos) return;
            $this.data('lastval', $pos);

            $('tr.parent_c').each(function () {
                var $ptoday = $('input.date', $(this)).val();

                if ($ptoday == $today) {
                    var $pbid = $(this).data('p');
                    $('tr.child.p_' + $pbid + ' td.col-position input').each(function () {
                        if ($(this).val() == $pos && $this.attr('name') != $(this).attr('name')) {
                            $exists = true;
                        }
                    })
                }
            })

            if ($exists) {
                $this.val('').focus();
                alert('Duplicate Position');
            } else {
                $ajax_requested++;
                $.ajax({
                    data: { trajax: 1, act: 'ci_update_position', mid: $mid, position: $pos, re_ID: $re_ID },
                    type: 'post',
                    dataType: 'json',
                    success: function (json) {
                        $ajax_done++;

                        if (json.status == 'ok') {
                            if (json.prev_pos && json.prev_pos > 0) {
                                $start_positions[$re_ID][json.key_pos] = json.prev_pos;
                            }
                        } else {
                            $this.val('').focus();
                            alert('Duplicate Position');
                        }
                    },
                    error: function () {
                        $ajax_done++;
                        $this.change();
                    }
                });
            }
        }).on('change', '.col-doc input', function () {
            var $this = $(this);
            var $doc = $this.val();
            var $battr = $(this).parents('tr.childp');
            var $id = $(this).parents('tr.child').data('id');
            var $retr = $('tr.parent_c[data-p="batch_' + $id + '"]');
            var $mid = $('input.mid', $battr).val();
            var $re_ID = $('input[data-fname="re_ID"]', $retr).val();


            $ajax_requested++;
            $.ajax({
                data: { trajax: 1, act: 'ci_update_doc', mid: $mid, doc: $doc, re_ID: $re_ID },
                type: 'post',
                dataType: 'json',
                success: function (json) {
                    $ajax_done++;

                    if (json.status == 'ok') {
                    } else {
                        $this.change();
                    }
                },
                error: function () {
                    $ajax_done++;
                    $this.change();
                }
            });
        })
        .on('keypress', '.col-position input', function (e) {
            var code = e.keyCode || e.which;
            if (code == 13) {
                $(this).change();
            }
        })
        .on('click', 'td.spec_type input', function () {
            var $this = $(this);
            var $pr = $(this).parents('tr.childp');
            var $tbl = $pr.parents('table.batch_tbl');
            var $id = $pr.parents('tr.child').data('id');
            var $posfield = $('.col-position input', $pr);
            var $retr = $('tr.parent_c[data-p="batch_' + $id + '"]');
            var $battr = $(this).parents('tr.childp');
            var $mid = $('input.mid', $battr).val();
            var $btype = $battr.data('btype');

            var $re_ID = $('input[data-fname="re_ID"]', $retr).val();
            var $next_positions = $start_positions[$re_ID];
            var $key_pos = $(this).val().toLowerCase();
            var $spectype = $(this).val();
            if ($(this).attr('type') == 'checkbox') {
                var $spectypes = [];
                $('td.spec_type input:checked', $pr).each(function () {
                    $spectypes.push($(this).val());
                })
                $spectype = $spectypes.join(',');
            }

            $ajax_requested++;
            $.ajax({
                data: {
                    trajax: 1,
                    act: 'ci_update_spec_type',
                    mid: $mid,
                    btype: $btype,
                    spec_type: $spectype,
                    re_ID: $re_ID,
                    next_positions: $next_positions
                },
                type: 'post',
                dataType: 'json',
                success: function (json) {
                    $ajax_done++;

                    if (json.status == 'ok') {
                        if (json.position) {
                            $posfield.val(json.position);
                        }
                        var $old_key = $pr.attr('data-key_pos');

                        if ($btype == 'tox' && ($key_pos == 'urine' || $key_pos == 'oral')) {
                            $pr.attr('data-key_pos', $key_pos);

                            var $qty = $('tr[data-key_pos="' + $old_key + '"]', $tbl).length;
                            $('input[data-fname="re_qty_' + $old_key + '"]', $retr).val($qty).change();

                            $qty = $('tr[data-key_pos="' + $key_pos + '"]', $tbl).length;
                            $('input[data-fname="re_qty_' + $key_pos + '"]', $retr).val($qty).change();

                            $start_positions[$re_ID][$key_pos]++;
                            $start_positions[$re_ID][$old_key]--;
                        }
                    } else {
                        $this.click();
                    }
                },
                error: function () {
                    $ajax_done++;
                    $this.click();
                }
            });

        })
        .on('click', 'input.alcohol', function () {
            var $this = $(this);
            var $pr = $(this).parents('.childp');
            var $id = $pr.parents('tr.child').data('id');
            var $retr = $('tr.parent_c[data-p="batch_' + $id + '"]');
            var $battr = $(this).parents('tr.childp');
            var $mid = $('input.mid', $battr).val();

            var $re_ID = $('input[data-fname="re_ID"]', $retr).val();
            var $alcohol = $(this).is(':checked') ? '1' : '0';

            $ajax_requested++;
            $.ajax({
                data: {
                    trajax: 1,
                    act: 'ci_update_alcohol',
                    mid: $mid,
                    alcohol: $alcohol,
                    re_ID: $re_ID
                },
                type: 'post',
                dataType: 'json',
                success: function (json) {
                    $ajax_done++;

                    if (json.status == 'ok') {

                    } else {
                        $this.click();
                    }
                },
                error: function () {
                    $ajax_done++;
                    $this.click();
                }
            });
        })
        .on('click', 'input.culture', function () {
            var $this = $(this);
            var $pr = $(this).parents('.childp');
            var $id = $pr.parents('tr.child').data('id');
            var $retr = $('tr.parent_c[data-p="batch_' + $id + '"]');
            var $battr = $(this).parents('tr.childp');
            var $mid = $('input.mid', $battr).val();

            var $re_ID = $('input[data-fname="re_ID"]', $retr).val();
            var $culture = $(this).is(':checked') ? '1' : '0';

            $ajax_requested++;
            $.ajax({
                data: {
                    trajax: 1,
                    act: 'ci_update_culture',
                    mid: $mid,
                    culture: $culture,
                    re_ID: $re_ID
                },
                type: 'post',
                dataType: 'json',
                success: function (json) {
                    $ajax_done++;

                    if (json.status == 'ok') {

                    } else {
                        $this.click();
                    }
                },
                error: function () {
                    $ajax_done++;
                    $this.click();
                }
            });
        })
        .on('click', 'a.add_all_current_batch', function () {
            var $this = $(this);
            if ($(this).html() == 'Loading') return false;
            var $oldtext = $(this).html();
            $(this).html('Loading');
            $ajax_requested++;
            var $bid = $(this).data('bid');
            var $re_ids = [];
            $('#form_receiving input[data-fname="re_ID"]').each(function () {
                $re_ids.push($(this).val());
            })
            $.ajax({
                data: {
                    trajax: 1,
                    act: 'ci_add_all_current_batch',
                    re_ids: $re_ids,
                    bid: $bid
                },
                type: 'post',
                dataType: 'json',
                success: function (json) {
                    $ajax_done++;

                    $this.html($oldtext);
                    if (json.status == 'ok') {

                        alert('Added All to Current Batch');
                        ci_call_manual_reports($bid);
                    } else {
                        $this.click();
                    }
                },
                error: function () {
                    $ajax_done++;
                    $this.html($oldtext);
                    $this.click();
                }
            });
        })
        .on('click', 'a.add_batch_no', function () {
            var $this = $(this);
            if ($(this).html() == 'Loading') return false;
            if (confirm('Add a new Batch number?') === false) return false;
            var $oldtext = $(this).html();
            $(this).html('Loading');
            $ajax_requested++;
            $.ajax({
                data: {
                    trajax: 1,
                    act: 'add_batch_no'
                },
                type: 'post',
                dataType: 'json',
                success: function (json) {
                    $ajax_done++;

                    $this.html($oldtext);
                    if (json.status == 'ok') {
                        $('#current_batch').html(json.batch_id);
                        $('#form_receiving').html('').hide();
                        $('#loading_form').show();
                        location.reload();
                    } else {
                        $this.click();
                    }
                },
                error: function () {
                    $ajax_done++;
                    $this.html($oldtext);
                    $this.click();
                }
            });
        })
        .on('change', 'tr.parent_c input, tr.parent_c select', function () {

            var $has_readonly = $(this).attr('readonly');
            if (typeof attr !== typeof undefined && attr !== false) {
                return;
            }
            if ($(this).data('withoutupdate')) {
                $(this).data('withoutupdate', false);
                return;
            }

            var $this = $(this);
            var $retr = $(this).parents('tr.parent_c');
            var $re_ID = $('input[data-fname="re_ID"]', $retr).val();
            var $fieldname = $this.data('fname');
            var $val = $(this).val();
            if ($(this).attr('type') == 'checkbox') {
                $val = $(this).is(':checked') ? 1 : 0;
            }

            if ($fieldname.indexOf('re_qty_') > -1 && parseInt($val) > 200) {
                setTimeout(function () {
                    $this.val('').focus();
                }, 200);
                return false;
            }
            if ($val === '' || $this.data('lastval') == $val) return;
            $this.data('lastval', $val);

            var $parentdata = {};
            $('input,select', $retr).each(function () {
                var $fname = $(this).data('fname');
                $parentdata[$fname] = $(this).val();
            });

            $ajax_requested++;
            $.ajax({
                data: {
                    trajax: 1,
                    act: 'ci_update_receiving',
                    fieldname: $fieldname,
                    parentdata: $parentdata,
                    val: $val,
                    re_ID: $re_ID
                },
                type: 'post',
                dataType: 'json',
                success: function (json) {
                    $ajax_done++;

                    if (json.status == 'removed') {
                        $retr.remove();
                    }
                    else if (json.status == 'ok') {
                        if (json.re_ID) {
                            $('input[data-fname="re_ID"]', $retr).val(json.re_ID);
                        }
                    } else {
                        $this.change();
                    }
                },
                error: function () {
                    $ajax_done++;
                    $this.change();
                }
            });
        })
        .on('keypress', 'tr.parent_c input', function (e) {
            var code = e.keyCode || e.which;
            if (code == 13) {
                $(this).change();
            }
        })
        .on('click', 'tr.parent_c', function (e) {
            if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;
            $('tr.child').hide();
            $('#rec_tbl_add').removeClass('show_all');
            if ($(this).data('showed') != '1') {
                $('tr.p_' + $(this).data('p')).show();
                $('tr.parent_c').data('showed', '0');
                $(this).data('showed', '1');
                $(this).addClass('active');

                $('body,html').scrollTop($(this).offset().top - 120);
            } else {
                $('tr.p_' + $(this).data('p')).hide();
                $(this).data('showed', '0');
                $(this).removeClass('active');
            }
            return false;
        })
        .on('click', function () {
            document.getElementById('beep_player').pause();
        })
        .on('change', 'input[data-fname="re_tracking_number_barcode"]', function () {
            var $exists = false;
            var $this = $(this);
            var $vl = $this.val();
            $('input[data-fname="re_tracking_number_barcode"]').each(function () {

                if ($this.attr('name') != $(this).attr('name') && $vl == $(this).val()) {
                    $exists = true;
                }
            });
            if ($exists) {
                $this.val('');
                alert('This Tracking Number is exists');
            }
        })
        .on('focus', 'input[data-fname*="re_qty"]', function () {
            if ($(this).val() == '0' && !$(this).attr('readonly')) {
                $(this).val('');
            }
        })
        .on('change', 'input[data-fname*="re_qty"]', function () {
            if (parseInt($(this).val()) > 0) {
                $(this).addClass('hasvalue');
            } else {
                $(this).removeClass('hasvalue');
            }
        })
        .on('focusout', 'input[data-fname*="re_qty"]', function () {
            if ($(this).val() == '') {
                $(this).val('0');
            }
        })
        .on('click', '.show_hide_all', function () {
            var $tbl = $('#rec_tbl_add');
            var $this = $(this);
            if ($tbl.hasClass('show_all')) {
                $tbl.removeClass('show_all');
                $this.html('Show All');
            } else {
                $tbl.addClass('show_all');
                $this.html('Hide All');
            }
        })
        .on('change', 'select.btype', function () {
            var $pr = $(this).parent();
            $('.barcode_auto', $pr).focus();
        });
    $('a.add_new_row').click(function () {
        var $newp = '<tr class="parent_c" data-p="batch_' + $new_id + '">' + $('#rec_tbl_add > tbody > tr.first').html() + '</tr>';
        $newp = $newp.replace(/\[\d\]/g, '[' + $new_id + ']');
        $newp = $($newp);
        console.log($df_barcode);
        $newp.find('input:not(.date)').val('');
        $newp.find('input[data-fname*="re_tracking_number_barcode"]').val($df_barcode);
        $newp.find('input[data-fname*="re_practice"]').val('');
        $newp.find('input[data-fname*="re_manifest"]').prop('checked', false);
        $newp.find('select[data-fname*="re_priority"]').val('low');
        $newp.find('select[data-fname*="re_timing"]').val('72');
        $newp.find('input[data-fname*="re_qty_"]').attr('value', '0');
        $newp.find('input[data-fname*="re_collector"]').val('N/A');

        var $newc = '<tr class="child p_batch_' + $new_id + '" data-id="' + $new_id + '">' + $('#rec_tbl_add > tbody > tr.first_child').html() + '</tr>';
        $newc = $($newc);
        $('tbody', $newc).html('');
        $new_id++;


        //check time
        if ($hour_next_batch && $hour_next_batch > 0) {
            var today = new Date();
            var $hournow = today.getHours();
            var newdate = false;
            if ($hour_next_batch > 13 && $hournow >= $hour_next_batch) {
                newdate = new Date();
                newdate.setDate(today.getDate() + 1);
            }
            else if ($hour_next_batch < 7 && $hournow < $hour_next_batch) {
                newdate = new Date();
                newdate.setDate(today.getDate() - 1);
            }

            if (newdate !== false) {
                var $tmonth = newdate.getMonth() + 1;
                var $tdate = newdate.getDate();
                if ($tmonth < 10) $tmonth = '0' + $tmonth;
                if ($tdate < 10) $tdate = '0' + $tdate;
                var $nextdate = newdate.getFullYear() + '-' + $tmonth + '-' + $tdate;
                $newp.find('input.date').val($nextdate);
            }
        }

        $('span.select2', $newp).remove();
        $('span.select2', $newc).remove();
        $('select', $newp).removeClass('select2-hidden-accessible');
        $('select', $newc).removeClass('select2-hidden-accessible');

        $('#rec_tbl_add > tbody').append($newp);
        $('#rec_tbl_add > tbody').append($newc);
        $newp.trigger('click');

        $('[data-fname="re_tracking_number_barcode"]', $newp).focus();

        $.setup_select2();
    })

    $('input[data-fname*="re_qty"]').each(function () {
        if (parseInt($(this).val()) > 0) {
            $(this).addClass('hasvalue');
        } else {
            $(this).removeClass('hasvalue');
        }
    });

    $(window).bind('beforeunload', function () {
        console.log('$ajax_requested: ' + $ajax_requested);
        console.log('$ajax_done: ' + $ajax_done);
        if ($ajax_requested > $ajax_done) {
            return "Do you want to leave this page without saving?";
        }
    });


    $('#form_receiving').show();
    $('#loading_form').hide();

})
