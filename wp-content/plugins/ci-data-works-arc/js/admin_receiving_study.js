
function quoteattr(s, preserveCR) {
    preserveCR = preserveCR ? '&#13;' : '\n';
    return ('' + s) /* Forces the conversion to string. */
        .replace(/&/g, '&amp;') /* This MUST be the 1st replacement. */
        .replace(/'/g, '&apos;') /* The 4 other predefined entities, required. */
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        /*
        You may add other replacements here for HTML only 
        (but it's not necessary).
        Or for XML, only if the named entities are defined in its DTD.
        */
        .replace(/\r\n/g, preserveCR) /* Must be before the next replacement. */
        .replace(/[\r\n]/g, preserveCR);
    ;
}
jQuery(function ($) {
    var $barcode_doing = false;
    var $ajax_requested = 0;
    var $ajax_done = 0;


    $('body').on('click', '.warning_duplicate', function () {
        $(this).html('').removeClass('error');
    })
        .on('click', '.remove_item', function () {
            if (confirm('Do you want delete?') == false) return false;

            var $pr = $(this).parents('tr.childp');
            var $id = $pr.data('id');
            var $tbl = $pr.parents('table.batch_tbl');
            var $inum = $(this).parents('tr.child').data('id');
            var $retr = $('tr.parent_c[data-p="batch_' + $inum + '"]');
            var $re_ID = $('input[data-fname="re_ID"]', $retr).val();
            var $key_pos = $pr.data('key_pos');
            $pr.addClass('bg_remove');

            $ajax_requested++;
            $.ajax({
                data: { trajax: 1, act: 'remove_receiving', ID: $id, re_ID: $re_ID },
                dataType: 'json',
                type: 'post',
                success: function (rs) {
                    $ajax_done++;
                    if ($pr.hasClass('has_childp')) {
                        $pr.next('.rchildcontent').remove();
                    }
                    $pr.remove();
                    //update qty
                    var $min_pos = -10;
                    $('[data-key_pos="' + $key_pos + '"]', $tbl).each(function () {
                        var $pos = parseInt($('.col-position input', $(this)).val());
                        if ($pos < $min_pos) {
                            $min_pos = $pos;
                        }
                    })
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

            if (typeof window.tr_hold_print_button === 'function') {
                if (!window.tr_hold_print_button($this, 'Printing...')) return false;
            } else {
                if ($this.html() == 'Loading') return false;
                $this.data('oldval', $this.html());
                $this.html('Printing...');
            }

            var $print_ids = '';
            var $print_data = [];
            var $print2labels = $('#form_receiving').data('print2');
            if ($(this).hasClass('reprint')) {
                $print2labels = false;
            }
            $('table.report_tbl tr.childp', $(this).parent()).each(function () {
                $print_ids += $(this).data('id') + ',';
                var $pr = $(this);
                var $barcode = $('td.assen', $pr).html();
                var $patient_name = $('td.patient_name', $pr).length > 0 ? $('td.patient_name', $pr).html() : '';
                var $position = $('td.col-position input', $pr).val();
                var $dob = $pr.data('dob');
                if ($dob && typeof $dob != undefined) {
                    $patient_name += '  ' + $dob;
                }

                var $row = {
                    barcode: $barcode,
                    patient_name: $patient_name + '',
                    position: $position
                };
                $print_data.push($row);
                if ($print2labels) {
                    $print_data.push($row);
                }
            })
            var $url = '?tr_action=ci_print_barcodes&ids=' + $print_ids + '&btype=study&print=1';
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
        })
        .on('click', '.print_barcode', function () {
            var $this = $(this);
            var $pr = $(this).parents('tr.childp');
            var $id = $pr.data('id');
            var $url = '?tr_action=ci_print_barcodes&ids=' + $id + '&btype=study&print=1&always=1';

            if (typeof window.tr_hold_print_button === 'function') {
                if (!window.tr_hold_print_button($this, 'Printing...')) return false;
            } else {
                if ($this.html() == 'Loading') return false;
                $(this).html('Printing...');
            }
            var $print2labels = $('#form_receiving').data('print2');

            if ($use_zebra) {
                try {
                    var $assen = $('td.assen', $pr).html();
                    var $patient_name = $('td.patient_name', $pr).length > 0 ? $('td.patient_name', $pr).html() : '';
                    var $position = $('td.col-position input', $pr).val();
                    var $dob = $pr.data('dob');
                    var $doc = $pr.find('td.doc').text();
                    if ($dob) {
                        $patient_name += '  ' + $dob;
                    }
                    printLabel($assen, $patient_name, $position, $doc, '', '', function () {
                        if (!$print2labels && typeof window.tr_release_print_button === 'function') {
                            window.tr_release_print_button($this);
                        }
                    }, function () {
                        if (typeof window.tr_release_print_button === 'function') {
                            window.tr_release_print_button($this);
                        }
                    });
                    if ($print2labels) {
                        printLabel($assen, $patient_name, $position, $doc, '', '', function () {
                            if (typeof window.tr_release_print_button === 'function') {
                                window.tr_release_print_button($this);
                            }
                        }, function () {
                            if (typeof window.tr_release_print_button === 'function') {
                                window.tr_release_print_button($this);
                            }
                        });
                    }

                    setTimeout(function () {
                        if (typeof window.tr_release_print_button === 'function') {
                            window.tr_release_print_button($this);
                        } else {
                            $this.html('Print');
                        }
                    }, 1000);

                } catch (e) {
                    alert('error:' + e.getMessage());
                    if (typeof window.tr_release_print_button === 'function') {
                        window.tr_release_print_button($this);
                    }
                }
            } else {
                $('#reprint_frame').attr('src', $url);
                setTimeout(function () {
                    if (typeof window.tr_release_print_button === 'function') {
                        window.tr_release_print_button($this);
                    } else {
                        $this.html('Print');
                    }
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

            if (code == 13 && $vl != '' && !$barcode_doing) {

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
                                    "ReScan": function () {
                                        $this.data('skipcheckkey', false);
                                        $(this).dialog("close");
                                        $this.val('').focus();
                                    }
                                }
                            });

                            $('.receivingwarning button:contains("Accept")').focus();
                            $this.removeAttr('readonly');
                            return false;
                        }
                    }
                } catch (e) { }


                $('input,select', $retr).each(function () {
                    var $fname = $(this).data('fname');
                    $parentdata[$fname] = $(this).val();
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
                        trajax: 1,
                        act: 'search_barcode_receiving',
                        code: $vl,
                        id: $id,
                        ii: $ii,
                        next_positions: $next_positions,
                        exist_positions: $exist_positions,
                        btype: $btype,
                        parentdata: $parentdata
                    },
                    type: 'post',
                    dataType: 'json',
                    success: function (rs) {
                        $ajax_done++;
                        $barcode_doing = false;
                        $this.removeAttr('readonly');

                        if (rs.re_ID > 0) {
                            $re_ID = rs.re_ID;
                            $('input[data-fname="re_ID"]', $retr).val(rs.re_ID);
                        }

                        if (rs.status != 'ok') {
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
            var $id = $battr.data('id');
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
                    data: { trajax: 1, act: 'ci_update_position', id: $id, position: $pos, re_ID: $re_ID },
                    type: 'post',
                    dataType: 'json',
                    success: function (json) {
                        $ajax_done++;

                        if (json.status == 'ok') {

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
        })
        .on('keypress', '.col-position input', function (e) {
            var code = e.keyCode || e.which;
            if (code == 13) {
                $(this).change();
            }
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

            if ($fieldname.indexOf('re_qty_') > -1 && parseInt($val) > 200) {
                setTimeout(function () {
                    $this.val('').focus();
                }, 200);
                return false;
            }
            if ($val == '' || $this.data('lastval') == $val) return;
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

                    if (json.status == 'ok') {
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

                $('body,html').scrollTop($(this).offset().top - 90);
            } else {
                $('tr.p_' + $(this).data('p')).hide();
                $(this).data('showed', '0');
                $(this).removeClass('active');
            }
            return false;
        })
        .on('reloadchild', 'tr.openpr', function () {
            var $this = $(this);
            var $child = $this.next();
            var $id = $this.data('id');
            $('td', $child).html('<div class="loading center"></div>');
            $.ajax({
                type: 'post',
                dataType: 'json',
                data: { trajax: 1, act: 'receiving_req_form', id: $id },
                success: function ($json) {
                    $('td', $child).html($json.html);

                    $('.has_date', $child).datepicker({ dateFormat: 'yy-mm-dd' });
                    $('.has_time', $child).timepicker({ timeFormat: 'H:mm', dropdown: true, scrollbar: true });
                    $('select.list_study_assign', $child).trigger('build_assign');
                },
                error: function () {
                    $this.trigger('reloadchild');
                }
            });
        })
        .on('click', '.reloadf', function () {
            var $pr = $(this).parents('tr.rchildcontent').prev();
            $pr.trigger('reloadchild');
            return false;
        })
        .on('click', '.closechildcontent', function () {
            var $child = $(this).parents('tr.rchildcontent');
            var $pr = $child.prev();
            $pr.removeClass('active');
            $child.hide();
        })
        .on('click', 'tr.openpr', function (e) {
            if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;
            var $this = $(this);
            if (!$this.hasClass('has_childp')) {
                var $count_td = $('td', $this).length;
                $('<tr class="rchildcontent"><td colspan="' + $count_td + '"></td></tr>').insertAfter($this);
                $this.addClass('has_childp');
                $this.trigger('reloadchild');
            }
            var $child = $this.next();
            if ($this.hasClass('active')) {
                $this.removeClass('active');
                $child.hide();
            } else {
                $this.addClass('active');
                $child.show();
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
        })
        .on('click', 'a.add_new_row', function () {
            var $newp = '<tr class="parent_c" data-p="batch_' + $new_id + '">' + $('#rec_tbl_add > tbody > tr.first').html() + '</tr>';
            $newp = $newp.replace(/\[\d\]/g, '[' + $new_id + ']');
            $newp = $($newp);
            $newp.find('input:not(.date)').val('');
            // $newp.find('select').val('');
            $newp.find('select[data-fname*="re_priority"]').val('low');
            $newp.find('select[data-fname*="re_timing"]').val('72');
            $newp.find('input[data-fname*="re_qty_"]').attr('value', '0');
            $newp.find('input[data-fname*="re_collector"]').val('N/A');
            $newp.find('input[data-fname*="re_today"]').val($today);

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
        .on('submit', 'form.receiving_req_form', function () {
            var $this = $(this);
            var $child = $(this).parents('tr.rchildcontent');
            var $btn = $('[type="submit"]', $this);
            if ($btn.val() == 'Saving...') return false;
            $btn.data('oldtext', $btn.val());
            $btn.val('Saving...');
            var $pr = $child.prev();
            $.ajax({
                type: 'post',
                dataType: 'json',
                data: $this.serialize(),
                success: function ($json) {
                    $btn.val($btn.data('oldtext'));
                    if ($json.status == 'ok') {
                        if ($json.data) {
                            for (var i in $json.data) {
                                $('td.' + i, $pr).html($json.data[i]);
                            }
                        }
                    } else {
                        alert('Error: '.$json.message);
                    }

                },
                error: function () {
                    $btn.val($btn.data('oldtext'));
                    setTimeout(function () {
                        $('form.receiving_req_form').submit();
                    }, 1000);
                }
            })

            return false;
        })
        .on('change', 'select.list_study_assign', function () {
            var $id = $(this).val();
            var $child = $(this).parents('tr.rchildcontent');
            $(this).val('');
            if ($id > 0) {
                var $studies = $('select.list_study_assign', $child).data('list');
                for (var j in $studies) {
                    if ($studies[j].ID == $id) {
                        $('.study_assigned', $child).trigger('add_item', $studies[j]);
                        break;
                    }
                }
            }
            $(this).trigger('build_assign');

            return false;
        })
        .on('build_assigned', '.study_assigned', function () {
            var $this = $(this);
            var $ids = $(this).data('assigned');
            var $timepoints = $(this).data('assigned_timepoints');
            var $conditions = $(this).data('assigned_conditions');

            var $child = $this.parents('tr.rchildcontent');
            if ($ids) {
                for (var i in $ids) {
                    var $id = $ids[i];
                    var $studies = $('select.list_study_assign', $child).data('list');
                    for (var j in $studies) {
                        if ($studies[j].ID == $id) {
                            $studies[j].timepoint = $timepoints[i];
                            $studies[j].condition = $conditions[i];
                            $('.study_assigned', $child).trigger('add_item', $studies[j]);
                            break;
                        }
                    }
                }
            }
        })
        .on('add_item', '.study_assigned', function (e, obj) {

            var $this = $(this);
            if ($this.html() == '') {
                $this.append('<div class="frow bold">' +
                    '<div class="col3 center"><label>Assigned to</label></div>' +
                    '<div class="col7 center"><label>Qty Citrate</label></div>' +
                    '<div class="col7 center"><label>Qty EDTA</label></div>' +
                    '<div class="col7 center"><label>Qty SST tube</label></div>' +
                    '<div class="col5 center"><label>Time Point</label></div>' +
                    '<div class="col6 center"><label>Condition</label></div>' +
                    '</div>');
            }
            var $num = $this.data('num') + 1;
            $this.data('num', $num);
            var $html = '<div class="item frow normal">';
            $html += '<div class="col3" title="' + obj.assen + '">';
            $html += '<input type="hidden" data-edta="' + obj.num_ml_edta + '" data-citrate="' + obj.num_ml_citrate + '" data-ss_tube="' + obj.num_sst_tube + '" name="study_assigned[' + $num + ']" value="' + obj.ID + '">';
            $html += '<div class="bold">' + obj.project_name + '</div>';
            $html += '<div class="small">' + obj.assen + '</div>';
            $html += '</div>';
            $html += '<div class="col7 center">' + obj.num_ml_citrate + '</div>';
            $html += '<div class="col7 center">' + obj.num_ml_edta + '</div>';
            $html += '<div class="col7 center">' + obj.num_sst_tube + '</div>';
            $html += '<div class="col5 center"><select name="timepoint[' + $num + ']" class="timepoint">';

            for (var i in obj.time_points) {
                var $timmep = obj.time_points[i];
                var $conditions = JSON.stringify($timmep.conditions);
                $selected = obj.timepoint == $timmep.name ? 'selected' : '';
                $html += '<option value="' + $timmep.name + '" ' + $selected + ' data-conditions="' + quoteattr($conditions) + '">' + $timmep.name + '</option>';
            }
            $html += '</select></div>';
            $html += '<div class="col6 center"><select name="condition[' + $num + '][]" multiple class="condition js_select2" data-value="' + obj.condition + '">';
            $html += '</select></div>';
            $html += '<a class="remove_study_item button right">-</a>';
            $html += '</div>';

            var $row = $($html);
            $this.append($row);
            $('.js_select2', $this).select2();
            $('select.timepoint', $row).change();
        })
        .on('click', '.item a.remove_study_item', function () {
            var $pr = $(this).closest('.item');
            $pr.remove();
            return false;
        })
        .on('change', 'select.timepoint', function () {
            var $opt = $('option:selected', $(this));
            var $item = $(this).closest('div.item');
            var $cond_select = $('select.condition', $item);
            var $conditions = $opt.data('conditions');
            var $conds = $cond_select.data('value').split(',');
            var $html = '';
            for (var i in $conditions) {
                $selected = $conds.includes($conditions[i].name) ? 'selected' : '';
                $html += '<option value="' + $conditions[i].name + '" ' + $selected + '>' + $conditions[i].name + '</option>';
            }

            $cond_select.html($html).select2();
        })
        .on('build_assign', 'select.list_study_assign', function () {
            var $this = $(this);
            var $child = $this.parents('tr.rchildcontent');
            var $studies = $this.data('list');
            var $assigned_studies = [];
            var $current_edta = 0;
            var $current_citrate = 0;
            var $current_sst_tube = 0;
            if ($('.study_assigned .item input', $child).length == 0) {
                $('.study_assigned', $child).trigger('build_assigned');
            }
            $('.study_assigned .item input', $child).each(function () {
                $assigned_studies.push($(this).val());
                $current_edta += parseInt($(this).data('edta'));
                $current_citrate += parseInt($(this).data('citrate'));
                $current_sst_tube += parseInt($(this).data('sst_tube'));
            });
            console.log($assigned_studies);
            var $edta = $('[name="formdata[edta]"]', $child).val();
            if ($edta == '') $edta = 0;
            else $edta = parseInt($edta);

            var $edta_value = $('[name="formdata[edta_value]"]', $child).val();
            if ($edta_value == '') {
                $edta_value = 0;
            } else {
                $edta_value = parseInt($edta_value);
            }

            var $citrate = $('[name="formdata[citrate]"]', $child).val();
            if ($citrate == '') {
                $citrate = 0;
            } else {
                $citrate = parseInt($citrate);
            }

            var $citrate_value = $('[name="formdata[citrate_value]"]', $child).val();
            if ($citrate_value == '') {
                $citrate_value = 0;
            } else {
                $citrate_value = parseInt($citrate_value);
            }


            var $sst_tube = $('[name="formdata[sst_tube]"]', $child).val();
            if ($sst_tube == '') {
                $sst_tube = 0;
            } else {
                $sst_tube = parseInt($sst_tube);
            }

            var $sst_tube_value = $('[name="formdata[sst_tube_value]"]', $child).val();
            if ($sst_tube_value == '') {
                $sst_tube_value = 0;
            } else {
                $sst_tube_value = parseInt($sst_tube_value);
            }

            var $edta_total = $edta * $edta_value;
            var $citrate_total = $citrate * $citrate_value;
            var $sst_tube_total = $sst_tube * $sst_tube_value;

            console.log('edta: ' + $edta_total + '. assigned: ' + $current_edta);
            console.log('citrate: ' + $citrate_total + '. assigned: ' + $current_citrate);
            console.log('sst_tube: ' + $sst_tube_total + '. assigned: ' + $current_sst_tube);

            $this.html('<option value="">select</option>');
            for (var i in $studies) {
                $study = $studies[i];
                if ($assigned_studies.includes($study['ID'])) continue;

                var $edtavl = parseInt($study['num_ml_edta']);
                var $citratevl = parseInt($study['num_ml_citrate']);
                var $sst_tubevl = parseInt($study['num_sst_tube']);

                if ($edta_total < $current_edta + $edtavl) {
                    console.log('skip: ' + $study['project_name'] + ' by edta: ' + ($current_edta + $edtavl));
                    continue;
                }
                if ($citrate_total < $current_citrate + $citratevl) {
                    console.log('skip: ' + $study['project_name'] + ' by citrate: ' + ($current_citrate + $citratevl));
                    continue;
                }

                if ($sst_tube_total < $current_sst_tube + $sst_tubevl) {
                    console.log('skip: ' + $study['project_name'] + ' by sst tube: ' + ($current_sst_tube + $sst_tubevl));
                    continue;
                }

                $this.append('<option value="' + $study['ID'] + '" >' + $study['project_name'] + '</option>');
            }
        })
        .on('change', '.rebuild_assign', function () {
            var $child = $(this).parents('tr.rchildcontent');
            $('select.list_study_assign', $child).trigger('build_assign');
        })
        .on('click', 'a.addslide', function () {
            var $pr = $(this).parents('tr.rchildcontent');
            var $num = $(this).data('num');
            var $max = $(this).data('max');
            var $code = $(this).data('code');
            if ($num >= $max) {
                alert('Maximum Slide');
                return false;
            }
            $num++;
            $(this).data('num', $num);
            var $slidediv = $('#listslides', $pr);
            var $assen = $code + '-' + ($num < 10 ? '0' : '') + $num;
            var $html = '<div class="item">' + $('.item:first-child', $slidediv).html() + '</div>';
            var $item = $($html);
            $('input', $item).val('');
            $('.assen', $item).val($assen);
            $('input', $item).removeAttr('id');
            $('.has_date', $item).removeClass('hasDatepicker').datepicker({ dateFormat: 'yy-mm-dd' });
            $('.has_time', $item).timepicker({ timeFormat: 'H:mm', dropdown: true, scrollbar: true });
            var $count = $slidediv.append($item);
            //change count
            var $tr = $pr.prev('tr.childp');
            $('td.qty_slides', $tr).html($('.item', $slidediv).length);
        })
        .on('click', '.targetselect', function () {
            var $pr = $(this).parent();
            var $sel = $('select', $pr);
            if ($(this).is(':checked')) {
                $sel.val('1');
            } else {
                $sel.val('0');
            }
        })
        .on('click', 'input.autocodecb', function () {
            var $this = $(this);
            var $pr = $(this).parents('tr.child');
            var $bc = $('.barcode_auto', $pr);
            console.log($bc.val());
            if ($bc.val() == '') {
                var $type = $this.val();
                console.log($type);
                $.ajax({
                    type: 'post',
                    dataType: 'json',
                    data: { trajax: 1, act: 'autocode', type: $type },
                    success: function (rs) {
                        $bc.val(rs.code).focus();
                    }
                });
            }
            return false;
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
    setTimeout(function () {
        $('body,html').scrollTop($('#form_receiving').offset().top - 90);
    }, 1000);
})
