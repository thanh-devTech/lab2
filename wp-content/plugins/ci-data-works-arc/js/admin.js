
if (typeof $use_zebra === 'undefined') {
    var $use_zebra = false;
}
if (typeof $use_zebra_size === 'undefined') {
    var $use_zebra_size = 2;
}

if (typeof $add_doc_to_label === 'undefined') {
    var $add_doc_to_label = false;
}

String.prototype.trcapitalize = function () {
    return this.charAt(0).toUpperCase() + this.slice(1);
}


jQuery(function ($) {
    var $print_loading = false;

    function tr_setup_practice_auto(labid) {
        $('.requesting_practice_auto').each(function () {
            $(this).autocomplete({
                source: ajaxurl + '?action=ajax&autocomplete=requesting_practice_auto&labid=' + labid,
                minLength: 1,
                select: function (event, ui) {
                    if (ui.item) {
                        $('[name="bat[cli_ID]"]').data('name', ui.item.value);
                        $('[name="bat[cli_ID]"]').data('pbtn', ui.item.pbtn);
                        $('[name="bat[cli_ID]"]').val(ui.item.id).change();
                        if (ui.item.cli_default_doc_ID > 0) {
                            $.ajax({
                                url: ajaxurl + '?action=ajax&autocomplete=get_doctor_info&doc_id=' + ui.item.cli_default_doc_ID,
                                success: function (data) {
                                    if (data.success) {
                                        $('[name="bat[requesting_physician]"]').val(data.doctor_name);
                                        $('[name="bat[doc_ID]"]').val(ui.item.cli_default_doc_ID);
                                    }
                                }
                            });
                        }
                        tr_setup_physician_auto($('[name="bat[cli_ID]"]').val());
                    }
                }
            });
        })
    };

    function tr_setup_physician_auto(cli_id) {
        $('.requesting_physician_auto').each(function () {
            var $this = $(this);
            $(this).autocomplete({
                source: ajaxurl + '?action=ajax&autocomplete=requesting_physician_auto&cli_id=' + cli_id,
                minLength: 1,
                select: function (event, ui) {
                    if (ui.item) {
                        for (i in ui.item) {
                            if (i == 'id' || i == 'value' || i == 'label') continue;
                            $('[name="bat[doc_ID]"]').data(i, ui.item[i]);
                        }
                        $('[name="bat[doc_ID]"]').val(ui.item.id).change();
                        $('[name="bat[doc_ID]"]').data('name', ui.item.value);
                    }
                }
            });
        })
    };

    function tr_check_dagnostic_required($pr) {
        if ($pr.data('required')) {
            if ($('li', $pr).length > 0) {
                $('select', $pr).removeAttr('required');
            } else {
                $('select', $pr).attr('required', 'true');
            }
        }

    }
    $.errorCallback = function (errorMessage) {
        alert('Error. ' + errorMessage);
    }

    $.print_ok_callback = function () {
    }

    $.tr_print_barcode = function ($assen, $patient_name, $position, $doc, $panels, $cul, okcallback, errcallback) {
        if ($use_zebra) {
            return $.printLabel($assen, $patient_name, $position, $doc, $panels, $cul, okcallback, errcallback);
        }
        var $url = 'admin.php?&tr_action=show_qrcode&text=' + $assen + '&moretext=' + $patient_name + '&print=1';
        $('iframe#print_frame_mu').attr('src', $url);
        return true;
    }

    $.ensure_printer = function (onReady, onError) {
        if (typeof window.ensure_printer === 'function') {
            return window.ensure_printer(onReady, onError);
        }
        if (typeof onError === 'function') {
            onError('unavailable');
        }
        return false;
    }

    $.printLabel = function ($barcode, $patient_name, $position, $doc, $panels, $cul, printedFunc, errorFunc) {
        if (typeof window.printLabel === 'function') {
            return window.printLabel($barcode, $patient_name, $position, $doc, $panels, $cul, printedFunc, errorFunc);
        }
        if (typeof errorFunc === 'function') {
            errorFunc('Printer bridge is not available.');
        } else {
            $.errorCallback('Printer bridge is not available.');
        }
        return false;
    }

    $.printLabels = function (data, pos, success, doing) {
        if (typeof window.printLabels === 'function') {
            return window.printLabels(data, pos, success, doing);
        }
        if (typeof success === 'function') {
            success();
        }
        return false;
    }

    $.trpdd_formatdate = function ($date, $usetime) {

        try {
            var $dinput = $date;
            if ($date.indexOf('-') > -1) {
                var arr = $date.split('-');
                console.log(arr);
                if (arr.length === 3) {
                    var arr2 = arr[2].split(' ');
                    arr[2] = arr2[0];
                    var $out = arr[1] + '/' + arr[2] + '/' + arr[0];
                    if ($usetime && arr2[1]) {
                        $out += ' ' + arr2[1];
                    }
                    return $out;
                }
            }
            var $d = new Date($date);
            var dd = String($d.getDate()).padStart(2, '0');
            var mm = String($d.getMonth() + 1).padStart(2, '0'); //January is 0!
            var yyyy = $d.getFullYear();

            $date = mm + '/' + dd + '/' + yyyy;
            if ($usetime) {
                var arr2 = $dinput.split(' ');
                if (arr2[1]) {
                    $date += ' ' + arr2[1];
                }
            }
        } catch (e) {
            console.log(e);
        }
        return $date;
    }

    $.tr_setup_datef = function () {
        var $this = $(this),
            format = 'yy-mm-dd';
        var formatstr = 'yyyy-mm-dd';
        if ($this.val() === '0000-00-00') {
            $this.val('');
        }
        if ($this.data('format')) {
            format = $this.data('format');
            formatstr = format.replace('yy', 'yyyy');
        }
        var $opts = { showButtonPanel: true, dateFormat: format, changeMonth: true, changeYear: true };
        if ($this.hasClass('limitfuture')) {
            $opts.maxDate = new Date;
        }
        $this.datepicker($opts);
        if (!$(this).data('write')) {
            $this.attr('readonly', 'readonly');
        } else {
            $this.change(function () {
                if ($this.val() !== '') {
                    //format first
                    var $vl = $this.val();
                    if ($vl.length === 8) {
                        $vl = $vl.substring(4, 9) + '-' + $vl.substring(0, 2) + '-' + $vl.substring(2, 4);
                        $this.val($vl);
                    }
                    var mydate = new Date($this.val());
                    var str = mydate.toString(format);
                    if (str === 'Invalid Date') {
                        $this.val('');
                        $this.focus();
                        alert('Please Enter Date format: ' + formatstr);
                    }
                }
            }).keyup(function () {
                var $vl = $this.val();
                if ($vl.indexOf('/') > -1 && $vl.length >= 9) {
                    var $arr = $vl.split('/');
                    $vl = $arr[2] + '-' + $arr[0] + '-' + $arr[1];

                    $this.val($vl);
                }
            })
        }
        if (!$(this).attr('placeholder')) {
            $this.attr('placeholder', formatstr);
        }
        if ($this.hasClass('today') && $this.val() === '') $this.datepicker('setDate', new Date());
        var today = new Date();
        var year = today.getFullYear();
        if ($this.hasClass('birthday')) {

            $this.datepicker("option", "yearRange", (year - 120) + ":" + year);
        } else {
            $this.datepicker("option", "yearRange", (year - 300) + ":" + (year + 300));
        }
    }
    $.tr_setup_datetimef = function () {
        var $this = $(this),
            format = 'yy-mm-dd';
        var $opts = {
            showButtonPanel: true,
            dateFormat: format,
            timeFormat: 'hh:mm:ss',
            changeMonth: true,
            changeYear: true,
            onSelect: function (vl) {
                if (vl.indexOf('00:00:00') > -1) {
                    //$this.datetimepicker('setTime', (new Date()));
                }
            }
        };


        if ($this.hasClass('limitfuture')) {
            $opts.maxDate = new Date;
        }
        $this.datetimepicker($opts);
        if (!$(this).data('write')) {
            $this.attr('readonly', 'readonly');
        } else {
            $this.change(function () {
                $date = $this.val().split(' ');
                var mydate = new Date($date[0]);
                var str = mydate.toString("yy-mm-dd");
                if (str == 'Invalid Date' && $this.val() != '') {
                    $this.focus();
                    alert('Please Enter Date format: YYYY-MM-DD hh:mm:ss');
                }
            })
        }
        if (!$(this).attr('placeholder')) {
            $this.attr('placeholder', 'YYYY-MM-DD hh:mm:ss');
        }
        if ($this.hasClass('today') && $this.val() == '') $this.datetimepicker('setDate', new Date());

    }
    $.tr_setup_timef = function () {
        var $this = $(this);
        var $opts = {
            showButtonPanel: true,
            timeFormat: 'hh:mm'
        };

        $this.timepicker($opts);
        if (!$(this).data('write')) {
            $this.attr('readonly', 'readonly');
        }
        if (!$(this).attr('placeholder')) {
            $this.attr('placeholder', 'hh:mm');
        }
    }

    $.tr_setup_the_form = function () {
        $('.hasdate').each($.tr_setup_datef);
        $('.hasdatetime').each($.tr_setup_datetimef);
        $('.hastime').each($.tr_setup_timef);
        $("form.need_validate").each(function () {
            var $this = $(this);
            var $hitted = false;
            console.log('need_validate');

            $this.on('keypress', 'input', function (e) {
                var code = e.keyCode || e.which;
                if (code == 13) {
                    $hitted = true;
                    setTimeout(function () { $hitted = false }, 500);
                    e.preventDefault();
                }
            });
            $this.submit(function () {
                if ($hitted) {
                    console.log('not click submit');
                    $('[type="submit"]', $this).removeClass('submitting');
                    $('.loading', $this).remove();
                    return false;
                }
            })
            try {
                $(this).validate({
                    invalidHandler: function (event, validator) {
                        // 'this' refers to the form
                        var errors = validator.numberOfInvalids();
                        if (errors && !$hitted) {
                            alert('Please complete all required fields! ');
                        }
                    }
                });
            } catch (e) { }
            $('form .only_save_form').on('click', function () {
                $('form [required]').removeAttr('required');
            })
        });
        $.setup_select2();
    }
    $.setup_select2 = function () {
        $('.js_select2, .njs_select2').each(function () {
            if (!$(this).hasClass('select2-hidden-accessible')) {
                var $s2opts = { minimumResultsForSearch: 8, tags: false };
                if ($(this).hasClass('editable')) {
                    $s2opts['tags'] = true;
                    $s2opts['minimumResultsForSearch'] = 0;
                }
                if ($(this).hasClass('showid')) {
                    $s2opts['templateSelection'] = function (state) {
                        let stateTemplate = jQuery("<span></span>");

                        if (state.newTag) {
                            stateTemplate.addClass("custom");
                        } else {
                            stateTemplate.addClass("normal");
                        }
                        stateTemplate.text(state.id);

                        return stateTemplate;
                    }
                }

                try {
                    $(this).select2($s2opts);
                    if ($(this).is(':required')) {
                        $(this).next('.select2').addClass('required');
                    }
                } catch (e) {
                    console.log(e);
                }
            }
        })
    }
    $.tr_fill_value = function ($k, $value) {
        var $el = $($k);

        if ($el.attr('type') == 'radio') {
            $($k + '[value="' + $value + '"]').prop('checked', true);
        }
        else if ($el.prop('tagName') == 'SELECT') {
            if ($($k + ' [value="' + $value + '"]').length > 0) {
                $($k + ' [value="' + $value + '"]').attr('selected', 'selected');
            } else {
                $el.append("<option value='" + $value + "' selected>" + $value + "</option>");
            }

            $el.val($value).change();
        }
        else {
            $el.val($value).change();
        }
    }
    $.tr_setup_the_form();

    $('input.hswitch').each(function () {
        var $this = $(this);
        var $vl = $(this).val() == '1' ? '1' : '0';
        var $html = $('<a class="hswitch button"><span class="swapswitch"><span class="status_0">Closed</span><span class="status_1">Open</span></span></a>');
        $this.after($html);
        if ($vl == '1') {
            $html.addClass('active');
        } else {
            $html.removeClass('active');
        }

        $html.click(function () {
            var $n = $(this).hasClass('active') ? '0' : '1';
            if ($n == '1') {
                $html.addClass('active');
            } else {
                $html.removeClass('active');
            }
            $this.val($n);
        })

    })


    $.tiout = null;
    $.show_popup = function (html, type, timeout) {
        $('body .popupwindow').remove();
        var $class = '';
        if (typeof type != undefined && type) {
            $class += type;
        }
        var $html = $('<div class="popupwindow ' + $class + '"><div class="popupwrap">' + html + '</div></div>');
        $('body').append($html);
        if (typeof timeout != undefined && timeout > 1000) {
            if ($.tiout != null) clearTimeout($.tiout);
            $.tiout = setTimeout(function () {
                $.hide_popup();
                $.tiout = null;
            }, timeout);
        } else {
            $('body').click(function (e) {
                $.hide_popup();
            });
        }

    }
    $.hide_popup = function () {
        $('body .popupwindow').remove();
    }

    $.print_Pdf = function (pdfUrl, $obj) {

        try {
            if ($('body #print_frame_mu').length == 0) {
                $('body').append('<iframe id="print_frame_mu" name="print_frame_mu" style="width:300px;height:300px;position:absolute;left:-99999px" src="about:blank"></iframe>');
            }

            var $style = '<style>@page { margin: 0; size: 21.59cm 27.94cm;}body,html { margin: 0; } .canvaspdf{margin:0 auto;display:block;max-width:99.8%;height:auto}.break{page-break-after: always;}</style>';

            window.frames["print_frame_mu"].document.body.innerHTML = $style;

            PDFJS.getDocument(pdfUrl).then(function (pdf) {
                var currentPage = 1;

                function getPage() {
                    pdf.getPage(currentPage).then(function (page) {
                        var scale = 2,
                            viewport = page.getViewport(scale),
                            canvas = document.createElement('canvas'),
                            context = canvas.getContext('2d'),
                            renderContext = {
                                canvasContext: context,
                                viewport: viewport,
                                intent: 'print'
                            };

                        canvas.setAttribute('class', 'canvaspdf');
                        canvas.id = 'canvas' + currentPage;
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;

                        page.render(renderContext).then(function (page) {
                            window.frames["print_frame_mu"].document.body.appendChild(canvas);
                            if (currentPage < pdf.numPages) {
                                var div = document.createElement('div');
                                div.setAttribute('class', 'break');
                                window.frames["print_frame_mu"].document.body.appendChild(div);
                                currentPage++;
                                getPage();
                            } else {
                                setTimeout(function () {
                                    window.frames["print_frame_mu"].focus();
                                    window.frames["print_frame_mu"].print();
                                    try {
                                        $($obj).trigger('printed');
                                    } catch (e) { }

                                }, 500);
                            }
                        });
                    });
                }

                if (currentPage <= pdf.numPages) {
                    getPage();
                }
            });
        } catch (e) {
            console.log(e);
            alert('Error. Please try again');
            try {
                $($obj).trigger('print_error');
            } catch (e) { }
        }
    }


    $.print_multipdfs = function (pdfUrls, $current, $obj) {
        var pdfUrl = pdfUrls[$current];
        $obj.html('Loading... ' + $current + '/' + pdfUrls.length);

        PDFJS.getDocument(pdfUrl).then(function (pdf) {
            var currentPage = 1;

            function getPage() {
                pdf.getPage(currentPage).then(function (page) {
                    var scale = 2,
                        viewport = page.getViewport(scale),
                        canvas = document.createElement('canvas'),
                        context = canvas.getContext('2d'),
                        renderContext = {
                            canvasContext: context,
                            viewport: viewport,
                            intent: 'print'
                        };

                    canvas.setAttribute('class', 'canvaspdf');
                    canvas.id = 'canvas' + currentPage;
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;

                    page.render(renderContext).then(function (page) {
                        window.frames["print_frame_mu"].document.body.appendChild(canvas);

                        if (currentPage < pdf.numPages) {
                            var div = document.createElement('div');
                            div.setAttribute('class', 'break');
                            window.frames["print_frame_mu"].document.body.appendChild(div);
                            currentPage++;
                            getPage();
                        } else {
                            $current++;
                            console.log('$current:' + $current);
                            $obj.html('Loading... ' + $current + '/' + pdfUrls.length);
                            if ($current >= pdfUrls.length) {
                                setTimeout(function () {
                                    window.frames["print_frame_mu"].focus();
                                    window.frames["print_frame_mu"].print();
                                    $print_loading = false;
                                    try {
                                        $($obj).trigger('printed');
                                    } catch (e) { }

                                }, 500);
                            }
                            else {
                                $.print_multipdfs(pdfUrls, $current, $obj);
                            }

                        }
                    });
                });
            }

            if (currentPage <= pdf.numPages) {
                getPage();
            }
        });
    }
    $.print_Pdfs = function (pdfUrls, $obj) {

        if ($print_loading) {
            alert('Loading... Please waiting...');
            return false;
        }
        $obj.html('Loading');
        $print_loading = true;
        try {
            if ($('body #print_frame_mu').length == 0) {
                $('body').append('<iframe id="print_frame_mu" name="print_frame_mu" style="width:300px;height:300px;position:absolute;left:-99999px" src="about:blank"></iframe>');
            }
            var $style = '<style>@page { margin: 0; size: 21.59cm 27.94cm;}body,html { margin: 0; } .canvaspdf{margin:0 auto;display:block;max-width:99.8%;height:auto}.break{page-break-after: always;}</style>';

            window.frames["print_frame_mu"].document.body.innerHTML = $style;

            console.log('pdfUrls count:' + pdfUrls.length);
            $.print_multipdfs(pdfUrls, 0, $obj);

        } catch (e) {
            $print_loading = false;
            console.log(e);
            alert('Error. Please try again');
            try {
                $($obj).trigger('print_error');
            } catch (e) { }
        }
    }

    $('body').on('change', 'select[name="ins1[ins_company]"]', function () {
        var $this = $(this);
        var $ins = $(this).val();
        if (!$ins || $ins.length <= 3) return;
        var $pa_ssn = $('[name="pa[pa_ssn]"]').val();
        var $pa_ID = $('[name="pa[pa_ID]"]').val();
        $.ajax({
            url: '?action=ajax&autocomplete=check_insurance_limit&term=' + $ins + '&pa_ssn=' + $pa_ssn + '&pa_id=' + $pa_ID,
            dataType: 'json',
            success: function (rs) {
                if (rs.limited) {
                    alert(rs.msg);
                }
                if (rs.ins) {
                    for (var i in rs.ins) {
                        if (rs.ins[i] == '') rs.ins[i] = 'N/A';
                        $('[name="ins1[' + i + ']"]').val(rs.ins[i]);
                        if (i == 'ins_state') {
                            $('[name="ins1[' + i + ']"]').change();
                        }
                    }
                }
            },
            error: function () {
                console.log("error change company");
                $this.change();
            }
        })
    })
        .on('change', 'input[name="ins1[ins_company]"]', function () {

            var $this = $(this);
            var $ins = $(this).val();
            if ($ins.length <= 2) return;
            var $pa_ssn = $('[name="pa[pa_ssn]"]').val();
            var $pa_ID = $('[name="pa[pa_ID]"]').val();
            $.ajax({
                url: '?action=ajax&autocomplete=check_insurance_limit&term=' + $ins + '&pa_ssn=' + $pa_ssn + '&pa_id=' + $pa_ID,
                dataType: 'json',
                success: function (rs) {
                    if (rs.limited) {
                        alert(rs.msg);
                    }
                },
                error: function () {
                    console.log("error change company");
                    $this.change();
                }
            })
        })
        .on('click', 'a.rm_doctor_row', function () {
            var $pr = $(this).parents('.pdoctorrow');
            if ($pr.hasClass('default') == false) {
                var $doc_id = $('[name*="][doc_ID]"]', $pr).val();
                $("#doctors_dv").trigger("remove_row", [$doc_id]);
                $pr.remove();
            } else {
                $('input', $pr).val('');
                $('textarea', $pr).val('');
            }

            return false;
        })
        .on('click', 'a.rm_co_ll_row', function () {
            var $pr = $(this).parents('.pcorow');
            if ($pr.hasClass('default') == false) {
                var $col_id = $('[name*="][col_ID]"]', $pr).val();
                $("#coll_dv").trigger("remove_row", [$col_id]);
                $pr.remove();
            } else {
                $('input', $pr).val('');
            }

            return false;
        })
        .on('click', 'a.add_doctor_row', function () {
            var $cr = parseInt($(this).data('id'));
            var $new = $('#doctors_dv .frow.default').html();
            $new = $new.replace(/\[\d*\]/g, '[' + $cr + ']');
            $new = $('<li class="frow blabel pdoctorrow">' + $new + '</li>');
            $new.find('input').val('').removeAttr('id');
            $new.find('textarea').val('').removeAttr('id');
            $new.find('select').val('');
            $('.hasdate', $new).removeClass('hasDatepicker').each($.tr_setup_datef);
            $('select', $new).removeClass('select2-hidden-accessible').val('');
            $('.select2-container', $new).remove();

            $('#doctors_dv').append($new);
            $(this).data('id', $cr + 1);
            $("#doctors_dv").trigger("new_row");
            $('select.js_select2', $new).each(function () {
                $(this).select2();
            });
            return false;
        })
        .on('click', 'a.add_coll_row', function () {
            var $cr = parseInt($(this).data('id'));
            var $new = $('#coll_dv .frow.default').html();
            $new = $new.replace(/\[\d*\]/g, '[' + $cr + ']');
            $new = $('<li class="frow blabel pcorow">' + $new + '</li>');
            $new.find('input').val('');
            $('#coll_dv').append($new);
            $(this).data('id', $cr + 1);
            $("#coll_dv").trigger("new_row");
            return false;
        })
        .on('click', 'a.add_multi_row', function () {
            var $cr = parseInt($(this).data('id'));
            var $rpkey = $(this).data('key');
            var $targetid = $(this).data('target');
            var $target = $targetid ? $('#' + $targetid) : $(this).prev();

            var $new = $('li.frow:first-child', $target).html();

            if ($new.indexOf($rpkey + '[0]') !== -1) {
                // Trường hợp manager[0][ma_manager] → manager[N][ma_manager]
                var re = new RegExp($rpkey + '\\[\\d+\\]', 'g');
                $new = $new.replace(re, $rpkey + '[' + $cr + ']');
            } else {
                // Trường hợp manager][0][ma_manager] → manager][N][ma_manager]
                var re = new RegExp($rpkey + '\\]\\[\\d+\\]', 'g');
                $new = $new.replace(re, $rpkey + '][' + $cr + ']');
            }

            $new = $('<li class="frow blabel pcorow secondrow">' + $new + '</li>');
            $('input:not([type="checkbox"])', $new).val('');
            $('input[type="checkbox"]', $new).prop('checked', false);
            $('select', $new).removeClass('select2-hidden-accessible').val('');
            $('li.secondrow', $new).remove();
            $('.select2-container', $new).remove();
            $target.append($new);

            try {
                $('.js_select2', $new).each(function () {
                    $(this).select2();
                });
            } catch (e) { }

            $(this).data('id', $cr + 1);
            $target.trigger("new_row");
            return false;
        })
        .on('click', 'a.remove_multi_row', function () {
            var $pr = $(this).closest('li.frow');
            var $target = $pr.closest('ol.multirows');
            if ($('li.frow', $target).length > 1) {
                $target.trigger("remove_row");
                $pr.remove();
            } else {
                $('input', $pr).val('').change();
                $('select', $pr).val('').change();
            }

            return false;
        })
        .on('click', 'a.add_insurance_network', function () {
            var $cr = parseInt($(this).data('id')) + 2;
            var $new = $('#insurance_network .frow:first-child').html();
            $new = $new.replace(/\[\n\]/g, '[' + $cr + ']');
            $new = $('<div class="frow">' + $new + '</div>');
            $new.find('label').remove();
            $new.find('input').val('');
            $new.find('span.num').html($cr);
            $('#insurance_network').append($new);
            $(this).data('id', $cr + 1);
            return false;
        })
        .on('click', 'a.add_more_current_medication', function () {
            var $new = $('#more_current_medication_div .default').html();
            $new = $('<div class="col3">' + $new + '</div>');
            $new.find('input').val('');
            $('#more_current_medication_div').append($new);
            return false;
        })
        .on('change', 'input.payor_part', function () {
            var $percent = 0;
            $('input.payor_part').each(function () {
                $percent += parseFloat(0 + $(this).val());
            });
            $('input.payor_total').val($percent);
        })
        .on('click', '.card_file > .remove', function () {
            if (confirm('Do you want Clear?') === false) return false;
            var $pr = $(this).parents('.card_file');

            $('input[type="hidden"]', $pr).val('').change();
            $('input[type="file"]', $pr).val('').change();

            $('img', $pr).remove();
            if ($('span.view', $pr).length > 0) {
                $('span.view', $pr).html('');
            } else {
                $('span', $pr).html('');
            }

            $('.selectfile', $pr).show();
            $('.signaturepad', $pr).show();
            try {
                var $pr2 = $(this).parents('.card_file').parent();
                if ($('.signature_canvas', $pr2).length > 0) {
                    $('.signature_canvas', $pr2).data('signaturepad').clear();
                }
            } catch (e) { }
        })
        .on('change', '.card_file input[type="file"]', function (event) {
            var $carf = $(this).parent();
            var $img = $(this).val();
            var $pr = $(this).parents('.card_file');
            if ($img == '') return;
            if ($img.indexOf('/') > 0) {
                var $urls = $img.split('/');
                $img = $urls[$urls.length - 1];
            }
            else if ($img.indexOf('\\') > 0) {
                var $urls = $img.split('\\');
                $img = $urls[$urls.length - 1];
            }
            if ($(this).val().indexOf('.jpg') > 0 || $(this).val().indexOf('.png') > 0 || $(this).val().indexOf('.jpeg') > 0) {
                $img = $('<div><img src="' + URL.createObjectURL(event.target.files[0]) + '" style="max-width:300px"></div>');
            }
            if ($('span.view', $pr).length > 0) {
                $('span.view', $pr).html($img);
            } else {
                $('span', $pr).html($img);
            }

            $('input[type="hidden"]', $carf).val($(this).val()).change();
            try {
                var $pr2 = $(this).parents('.card_file').parent();
                if ($('.signature_canvas', $pr2).length > 0) {
                    $('.signature_canvas', $pr2).removeClass('required');
                    $('input.hide_sign', $pr2).removeAttr('required');
                }

            } catch (e) { }
        })
        .on('click', 'input[name="onfile"]', function () {
            if ($(this).is(':checked')) {
                $('.patient_signature').removeClass('required');
                $('.patient_signature input.hide_sign').removeAttr('required');
            }
        })
        .on('click', '.req_result_pdf > a.addfile', function () {
            var $wrap = $(this).parents('.req_result_pdf');
            var $fname = $(this).data('fname') ? $(this).data('fname') : 'result_pdfs';
            if ($('.listresultfiles li.emptyvalue', $wrap).length > 0) {
                $('.listresultfiles li.emptyvalue [type="file"]', $wrap).click();
                return false;
            }
            var $accept = '';
            if ($fname == 'result_pdfs') {
                $accept = ' accept="application/pdf,.pdf"';
            } else if ($fname == 'original_reqs') {
                $accept = ' accept="application/pdf,image/*"';
            }
            var $li = $('<li class="emptyvalue" style="display:none"><input type="file" name="' + $fname + '[]" multiple' + $accept + ' style="display:none" />' +
                '<span class="filename"></span> - <a class=" remove_result_pdf" href="#">Remove</a></li>');
            var $fileRow = function (file) {
                var $row = $('<li class="result_pdf_view"><input type="file" name="' + $fname + '[]"' + $accept + ' style="display:none" />' +
                    '<span class="filename"></span> - <a class=" remove_result_pdf" href="#">Remove</a></li>');

                if (file && window.DataTransfer) {
                    var dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    $('[type="file"]', $row)[0].files = dataTransfer.files;
                }
                $('.filename', $row).text(file ? file.name : '');
                return $row;
            };
            $('.listresultfiles', $wrap).append($li);
            $('[type="file"]', $li).change(function () {
                var $files = this.files || [];
                var $names = [];
                if ($files.length > 0) {
                    for (var i = 0; i < $files.length; i++) {
                        if ($fname == 'result_pdfs' && !/\.pdf$/i.test($files[i].name)) {
                            alert('Please choose PDF files only.');
                            $(this).val('');
                            $li.remove();
                            return;
                        }
                        $names.push($files[i].name);
                    }
                    if ($files.length > 1 && window.DataTransfer) {
                        for (var k = 0; k < $files.length; k++) {
                            $('.listresultfiles', $wrap).append($fileRow($files[k]));
                        }
                        $li.remove();
                        return;
                    }
                } else {
                    var $vl = $(this).val();
                    var $split = '\\';
                    if ($vl.indexOf('/') > 0) {
                        $split = '/';
                    }
                    var $arr = $vl.split($split);
                    for (var j in $arr) {
                        if ($arr[j].indexOf('.pdf') > 0) {
                            $names.push($arr[j]);
                            break;
                        }
                    }
                    if ($names.length == 0 && $arr.length > 0 && $fname != 'result_pdfs') {
                        $names.push($arr[$arr.length - 1]);
                    }
                }
                $('.filename', $li).text($names.join(', '));
                $li.show();
                $li.removeClass('emptyvalue').addClass('result_pdf_view');
            }).click();
            return false;
        })
        .on('click', '.remove_result_pdf', function () {

            var $wrap = $(this).parents('.req_result_pdf');
            var $pr = $(this).parents('.result_pdf_view');
            var $rmfile = $pr.data('f');
            if ($rmfile) {
                var $allfiles = $('.result_value', $wrap).val();
                var $arr = $allfiles.split(',');
                $allfiles = [];
                for (i in $arr) {
                    if ($arr[i] != $rmfile) {
                        $allfiles.push($arr[i]);
                    }
                }
                $allfiles = $allfiles.join(',');
                $('.result_value', $wrap).val($allfiles);
            }

            $pr.remove();
            return false;
        })
        .on('change', 'select#cli_change', function () {
            $('#cli_change_div').append('<div class="loading loadingbox"></div>');
            $.ajax({
                url: location.href,
                type: 'post',
                data: { clinic_id: $(this).val() },
                success: function (rs) {
                    $('#cli_change_div').html($(rs).find('#cli_change_div').html());
                    $new = $(rs);
                    $('[id*="_change_div"]', $new).each(function () {
                        var $id = $(this).attr('id');
                        $('#' + $id).html($(this).html());
                    });
                    $.tr_setup_the_form();
                    $('#cli_change_div .loading').remove();
                    if ($('[name="bat[doc_ID]"]').val() != '') {
                        $('[name="bat[doc_ID]"]').trigger('change');
                    }

                }
            });
            //check ref lab
            var $refid = $('option:selected', $(this)).data('reflab');
            console.log($refid);
            if ($refid && $refid > 0) {
                $('select#batref_lab_id').val($refid).change();
            }
        })
        .on('change', 'select#doc_change', function () {
            if ($(this).val() == '') return;
            $.ajax({
                url: location.href,
                type: 'post',
                data: { doc_id: $(this).val() },
                success: function (rs) {
                    $('#doc_change_div').html($(rs).find('#doc_change_div').html());
                    $new = $(rs);
                    $('[id*="_change_div"]', $new).each(function () {
                        var $id = $(this).attr('id');
                        $('#' + $id).html($(this).html());
                    });
                    $.tr_setup_the_form();
                }
            });
        })
        .on('change', 'select#lab_change', function () {
            $.ajax({
                url: location.href,
                type: 'post',
                data: { lab_id: $(this).val() },
                success: function (rs) {
                    $new = $(rs);
                    $('[id*="_change_div"]', $new).each(function () {
                        var $id = $(this).attr('id');
                        $('#' + $id).html($(this).html());
                    });
                    $.tr_setup_the_form();
                }
            });
        })
        .on('change', '#select-cmpd', function () {
            if ($(this).val() != "") {
                var obj = jQuery.parseJSON($(this).children('option:selected').attr('data-arr'));
                var cutoff = parseFloat(obj.cutoff);

                $('#select-group').val(obj.group);
                $('#cutoff-value').val(cutoff);
            } else {
                $('#select-group').val('');
                $('#cutoff-value').val('');
            }
        })
        .on('change', '#select-lab', function () {
            if ($(this).val() != '') {
                var obj = jQuery.parseJSON($(this).children('option:selected').attr('data-arr'));
                $('#save-settings').val('Save');
                $('#h-labname').val(obj.lab);
                $('#h-abbr').val(obj.abbr);
                $('#logo-preview').attr('src', $('#wp_logo_dir').val() + '/' + obj.logo).show();
                $('#h-addr1').val(obj.addr1);
                $('#h-addr2').val(obj.addr2);
                $('#h-addr3').val(obj.addr3);
                $('#h-addr4').val(obj.addr4);
                $('#h-addr5').val(obj.addr5);
                $('#h-addr6').val(obj.addr6);
                $('#h-additional').val(obj.additional);
                $('#f-footer1').val(obj.footer1);
                $('#f-footer2').val(obj.footer2);
                $('#f-footer3').val(obj.footer3);
            } else {
                $('#save-settings').val('Add New');
                $('.settings-form input[type=text]').val('');
                $('#logo-preview').hide();
            }
        })
        .on('change', '#select-matrix', function () {
            if ($(this).val() != "") {
                $('select .hidden').css('display', 'block');
                var obj = jQuery.parseJSON($('#hidden-' + $(this).val()).val());
                var html;
                $('#select-cmpd').html('<option value=""> -- Select a compound -- </option>');
                for (var i = 0; i < obj.length; i++) {
                    html = "<option data-arr='" + JSON.stringify(obj[i].data) + "' value='" + obj[i].cmpd + "'>" + obj[i].cmpd + "</option>";
                    $('#select-cmpd').append(html);
                }
            } else {
                $('select .hidden').css('display', 'none');
                $('#select-cmpd').val('');
                $('#select-group').val('');
                $('#cutoff-value').val('');
            }
        })
        .on('change', '.cfname', function () {
            $(this).val($(this).val().trcapitalize());
        })
        .on('click', '.selectall', function () {
            $('#' + $(this).data('target') + ' input').prop('checked', true).change();
            return false;
        })
        .on('click', '.unselectall', function () {
            $('#' + $(this).data('target') + ' input').removeAttr('checked').change();
            return false;
        })
        .on('change', '.has_phone', function () {
            var $vl = $(this).val();
            var $num = $vl.replace(/\D/g, '');
            var $num = $num.replace(/(\d{3})(\d{0,6})/, '$1-$2');
            var $num = $num.replace(/(\d{3})-(\d{3})(\d{0,4})/, '$1-$2-$3');

            $(this).val($num);
        })
        .on('change', 'input[name="bat[actual_temperature]"]', function () {
            if ($(this).val().length > 0) {
                $('input[name="bat[temperature]"]').removeAttr('checked');
            }
        })
        .on('change', 'input[name="bat[temperature]"]', function () {
            if ($(this).is(':checked')) {
                $('input[name="bat[actual_temperature]"]').val('');
            }
        })
        .on('click', '[name="bat[doc_ID]"]', function () {
            if ($(this).find('option').length <= 1) {
                if ($('[name="bat[cli_ID]"]').val() == '') {
                    alert('Please select clinic first');
                } else {
                    alert('Physician Custom Request Form not Completed - Please contact Sales Rep.');
                    if ($('#profile_link') && $('#profile_link').val()) {
                        location = $('#profile_link').val() + '&cli=' + $('[name="bat[cli_ID]"]').val();
                    }
                }

            }
        })
        .on('keyup', '#pa_ssn', function () {
            var $vl = $(this).val();
            var $num = $vl.replace(/-/g, '');
            var $num = $num.replace(/(\d{3})(\d{0,6})/, '$1-$2');
            var $num = $num.replace(/(\d{3})-(\d{2})(\d{0,4})/, '$1-$2-$3');

            $(this).val($num);
        })
        .on('change', '[name="ins1[ins_relation]"]', function () {
            if (!$(this).is(':checked')) return;
            var $dob_ins1 = $('#dob_ins1');
            var $dobin1 = $('input', $dob_ins1);
            if ($(this).val() != 'Self') {
                $dob_ins1.show();

                if (!$dobin1.hasClass('notrequired')) {
                    $dobin1.attr('required', 'required').attr('old_required', 1);
                }
                $('label span', $dob_ins1).html($(this).val());
            } else {
                $dob_ins1.hide();
                $dobin1.removeAttr('required');
                var $ins1last = $('[name="ins1[ins_last]"]');
                var $ins1first = $('[name="ins1[ins_first]"]');
                if ($ins1last.val() == '') {
                    $ins1last.val($('[name="pa[pa_last]"]').val())
                }
                if ($ins1first.val() == '') {
                    $ins1first.val($('[name="pa[pa_first]"]').val())
                }
            }
        })
        .on('change', '[name="ins2[ins_relation]"]', function () {
            if (!$(this).is(':checked')) return;
            var $dob_ins2 = $('#dob_ins2');
            if ($(this).val() != 'Self') {
                $dob_ins2.show();
                $('label span', $dob_ins2).html($(this).val());
            } else {
                $dob_ins2.hide();
            }
        })
        .on('change', '[name="bat[ins_comp]"]', function () {
            var $vl = $(this).val();
            if ($(this).is(':checked') == false) return;
            if ($vl == 'ins') {
                $('#use_required_ins [old_required]').attr('required', 'required');
                $('#use_required_com [old_required]').removeAttr('required').removeClass('error').removeAttr('aria-required');;
                $('#use_required_com [data-required]').removeAttr('required').removeClass('error').removeAttr('aria-required');;

                if ($('[name="ins_card_front"]').data('required')) {
                    $('[name="ins_card_front"]').attr('required', 'required');
                    $('[name="ins_card_front"]').parent().find('a').addClass('required');
                }
                $('[name="no_insurance"]').removeAttr('disabled');

            } else {
                $('#use_required_com [old_required]').attr('required', 'required');
                $('#use_required_com [data-required]').attr('required', 'required');
                $('#use_required_ins [old_required]').removeAttr('required').removeClass('error').removeAttr('aria-required');
                $('[name="ins_card_front"]').removeAttr('required');
                $('[name="ins_card_front"]').parent().find('a').removeClass('required');
                $('[name="no_insurance"]').removeAttr('checked');
                $('[name="no_insurance"]').attr('disabled', 'disabled');
            }
        })
        .on('change', '[name="no_insurance"],[name="ins1[ins_cares_act]"],[name="client_bill"]', function () {
            var $noinsurance = $('[name="no_insurance"]').is(':checked') || $('[name="ins1[ins_cares_act]"]').is(':checked') || $('[name="client_bill"]').is(':checked');
            if ($noinsurance) {
                $('#use_required_ins [old_required]:not(.has_required)').removeAttr('required').removeClass('error');
                $('#use_required_ins [old_required]:not(.has_required)').removeAttr('aria-required');
                if ($('[name="ins1[ins_last]"]').val() == '' || $('[name="ins1[ins_first]"]').val() == '') {
                    $('[name="ins1[ins_last]"]').val($('[name="pa[pa_last]"]').val());
                    $('[name="ins1[ins_first]"]').val($('[name="pa[pa_first]"]').val());
                }
            } else {
                $('#use_required_ins [old_required]:not(.has_required)').attr('required', 'required');
            }
        })
        .on('change', '[name="pa[pa_last]"],[name="pa[pa_first]"]', function () {
            if ($('[name="no_insurance"]').is(':checked')) {
                $('[name="no_insurance"]').change();
            }
        })
        .on('click', '.dagnostic_code li span', function () {
            var $pr = $(this).parents('.dagnostic_code');
            $(this).parent().remove();
            tr_check_dagnostic_required($pr);
        })
        .on('click', 'a.find_patient', function () {
            var $ssn = $('#pa_ssn').val();
            var $med_number = $('#med_number').val();
            var $stype = $(this).data('type');
            var $btype = $('form.main_form').data('btype');
            var $cli_ID = $('[name="bat[cli_ID]"]').val();
            if ($cli_ID == 0 || $cli_ID == '') {
                alert('Please Select Clinic First');
                return false;
            } else if ($ssn == '' && $med_number == '') {
                return false;
            }
            var $this = $(this);
            $this.html('Find...');
            $.ajax({
                url: ajaxurl + '?action=ajax&autocomplete=find_patient&term=ajax',
                type: 'post',
                dataType: 'json',
                data: { ssn: $ssn, med_number: $med_number, stype: $stype, cli_ID: $cli_ID, btype: $btype },
                success: function (rs) {
                    if (rs.status == 'notfound') {
                        alert(rs.alert);
                        $this.html('Find');
                    } else {
                        for (k in rs.data) {
                            $.tr_fill_value('[name="pa[' + k + ']"]', rs.data[k]);
                        }
                        if (rs.ins1) {
                            for (k in rs.ins1) {
                                $.tr_fill_value('[name="ins1[' + k + ']"]', rs.ins1[k]);
                            }
                        }
                        if (rs.ins2) {
                            for (k in rs.ins2) {
                                $.tr_fill_value('[name="ins2[' + k + ']"]', rs.ins2[k]);
                            }
                        }
                        if (rs.images) {
                            for (k in rs.images) {
                                var vl = rs.images[k];
                                if (vl !== '') {
                                    var $obj = $('[name="img_' + k + '"]');
                                    var $pr = $obj.parents('.card_file');
                                    $obj.val(vl);
                                    $('span', $pr).html('<div><img src="' + rs.card_url + '/' + vl + '"></div>');
                                }
                            }
                        }
                        $this.html('Found');
                        $('[name="pa[pa_ID]"]').change();
                        try {
                            if (rs.data['pa_bill_notes']) {
                                $this.trigger("bill_notes", [rs]);
                            }
                        } catch (e) {
                            console.log(e);
                        }
                        try {
                            if (rs.medication) {
                                let $meds = rs.medication.split(',');
                                for (let i2 in $meds) {
                                    $('input[name="bat[current_medication][]"][value="' + $meds[i2] + '"]').prop('checked', true);
                                }
                            }
                            if (rs.diagnostic) {
                                var $codes = rs.diagnostic.split(',');
                                var $list = $('#list_dagnostic_codes');
                                $list.empty();
                                for (var i in $codes) {
                                    var $code = $.trim($codes[i]);
                                    if ($code === '') continue;
                                    $list.append(
                                        '<li>' +
                                        '<input type="hidden" name="bat[dagnostic_codes][]" value="' + $code + '" />' +
                                        $code +
                                        ' <span></span>' +
                                        '</li>'
                                    );
                                }
                            }
                        } catch (e) {
                            console.log(e);
                        }

                        try {
                            $this.trigger("found_patient", [rs]);
                        } catch (e) {
                            console.log(e);
                        }
                    }
                }
            });
            return false;
        })
        .on('change', '.patient_last_auto,.patient_first_auto', function () {
            var $vl = $('[name="pa[pa_ID]"]').data('name');
            if ($vl != $('[name="pa[pa_last]"]').val() + $('[name="pa[pa_first]"]').val()) {
                $('[name="pa[pa_ID]"]').val('');
            }
        })
        .on('change', '.ref_lab_auto', function () {
            var $vl = $('[name="ref_lab[lab_ID]"]').data('name');
            if ($vl.toLowerCase() != $(this).val().toLowerCase()) {
                $('[name="ref_lab[lab_ID]"]').val('');
            }
        })
        .on('change', '.lab_auto', function () {
            var $vl = $('[name="lab[lab_ID]"]').data('name');
            if ($vl.toLowerCase() !== $(this).val().toLowerCase() || $('[name="lab[lab_ID]"]').val() == '') {
                $(this).val('');
                $('[name="lab[lab_ID]"]').val('');
                tr_setup_practice_auto('no lab');
            }
        })
        .on('change', '.requesting_practice_auto', function () {
            //check lab first
            if ($('[name="bat[lab_id]"]').val() == '') {
                alert('Please enter lab name first');
                $(this).val('');
                return false;
            }
            $vl = $('[name="bat[cli_ID]"]').data('name');
            if (!$vl) $vl = '';
            if ($vl.toLowerCase() != $(this).val().toLowerCase()) {
                $('[name="bat[cli_ID]"]').val('');
                $(this).val('');
                tr_setup_physician_auto('');
            }
        })
        .on('change', '.requesting_physician_auto', function () {
            var $vl = $('[name="bat[doc_ID]"]').data('name');
            if (!$vl) $vl = '';
            if ($vl.toLowerCase() != $(this).val().toLowerCase()) {
                $('[name="bat[doc_ID]"]').val('');
                //need show popup
                $(this).data('vl', $(this).val());
                $(this).val('');
                //$cli_id = $('[name="bat[cli_ID]"]').val();
                //tb_show("Alert", "?tr_action=ci_show_doctor_new_form&cli_id="+$cli_id);
            }
        })
        .on('change', '[name="bat[lab_id]"]', function () {
            tr_setup_practice_auto($(this).val());
        })
        .on('click', '.use_new_doctor', function () {
            var $t = $('.requesting_physician_auto');
            $t.val($t.data('vl'));
            tb_remove();
        })
        .on('click', 'a.iprint', function () {
            var $this = $(this);
            if ($this.html() != 'loading...') {
                $this.data('oldtext', $this.html());
                $this.html('loading...');
            }

            var $url = $(this).data('href');
            if (!$url) {
                $url = $(this).attr('href');
            }
            if ($('body iframe#print_frame_mu').length > 0) {
                $('body iframe#print_frame_mu').attr('src', $url);
            }
            else {
                $('body').append('<iframe id="print_frame_mu" name="print_frame_mu" style="width:300px;height:300px;position:absolute;left:-99999px" src="' + $url + '"></iframe>');
            }

            setTimeout(function () {
                $this.html($this.data('oldtext'));
            }, 5000);
            return false;
        })
        .on('click', 'a.pdelete', function () {
            var $warning = $(this).data('warning') || 'Do you want delete ?';
            if (confirm($warning) == false) return false;
            var $id = $(this).data('id');
            var $f = $('<form method="post"><input type="hidden" name="act" value="pdelete"/>' +
                '<input type="hidden" name="idd" value="' + $id + '"/></form> ');
            $('body').append($f);
            $f.submit();
            return false;
        })
        .on('click', 'a.deleteajax', function () {
            var $this = $(this);
            var $assen = $this.data('assen') || '';
            var $warning = $this.data('warning') || 'Do you want delete ' + $assen + '?';
            if (confirm($warning) == false) return false;
            var $id = $this.data('id');
            var $tr = $this.parents('tr');

            $.ajax({
                type: 'post',
                data: { trajax: 1, act: 'delete', id: $id },
                dataType: 'json',
                success: function (rs) {
                    if (rs.status == 'ok') {
                        $tr.remove();
                        if (typeof showToast === "function") {
                            showToast('success', rs.message, '', 4000, "top_right");
                        } else {
                            alert(rs.message);
                        }
                    } else {
                        alert(rs.message);
                    }
                },
                error: function () {
                    alert('Error on request');
                }
            })
            return false;
        })
        .on('click', 'a.approvebtn', function () {
            var $this = $(this);
            var $tr = $this.parents('tr.childp');
            var $textpopup = $tr.data('popupapprove');
            var $text = $this.html();
            var $newtext = $text == 'Approve' ? 'Reverse' : 'Approve';
            if ($text == 'Loading...') {
                return;
            }
            if ($text == 'Approve' && $textpopup && $textpopup.length > 5 && !$this.hasClass('pass_approve')) {
                //show popup
                try {
                    $('#dialog_approve').remove();
                    $('body').append('<div id="dialog_approve">' + $textpopup + '</div>');
                    $("#dialog_approve").dialog({
                        resizable: false,
                        height: "auto",
                        width: 400,
                        modal: true,
                        buttons: {
                            "Approve Anyway": function () {
                                $this.addClass('pass_approve');
                                $(this).dialog("close");
                                $this.click();
                            },
                            "Do Not Approve": function () {
                                $(this).dialog("close");
                            }
                        }
                    });
                    return false;
                } catch (e) {
                    if (!confirm($textpopup)) {
                        return false;
                    }
                }
            } else {
                $this.removeClass('pass_approve');
            }

            $this.html('Loading...');
            var $action = $text == 'Approve' ? 'results_approve' : 'results_reverse';
            var $id = $(this).data('id');
            $.ajax({
                type: 'post',
                data: { trajax: 1, act: $action, id: $id },
                success: function (rs) {
                    $this.html($newtext);
                    $this.attr('text', $newtext);
                    if (rs.indexOf('updated') > -1) {
                        if ($newtext == 'Approve') {
                            $('.editlink', $tr).show();
                            $('.pdflink', $tr).hide();
                            $('.viewBtn', $tr).hide();
                            $('td.statuscol', $tr).html('M').addClass('result_M').removeClass('result_C');
                        } else {
                            $('.editlink', $tr).hide();
                            $('.pdflink', $tr).show();
                            $('.viewBtn', $tr).show();
                            $('td.statuscol', $tr).html('C').addClass('result_C').removeClass('result_M');
                            $('span.faxed', $tr).remove();
                        }
                        var $rs = $(rs);

                        $('.adding_html', $rs).each(function () {
                            var $id = $(this).attr('id');

                            $('.' + $id + ' .adding_html', $tr).remove();
                            $('.' + $id, $tr).append('<span class="adding_html">' + $(this).html() + '<span>');
                        })
                    }
                    else {
                        alert($(rs).find('.message_alert').text());
                        $this.html($text);
                    }
                }
            })
            return false;

        })
        .on('click', 'a.finalbtn', function () {
            var $this = $(this);
            var $text = $this.html();
            if ($text == 'Loading...') {
                return;
            }
            $this.data('oldtext', $this.html());
            $this.html('Loading...');
            var $id = $(this).data('id');
            $.ajax({
                type: 'post',
                data: { trajax: 1, act: 'results_final', id: $id },
                success: function (rs) {

                    if (rs.indexOf('updated') > -1) {
                        $this.remove();
                    }
                    else {
                        $this.html($this.data('oldtext'));
                        alert($(rs).find('.message_alert').text());
                    }
                },
                error: function () {
                    $this.html($this.data('oldtext'));
                }
            })
            return false;

        })
        .on('click', 'a.verifybtn', function () {
            var $this = $(this);
            var $tr = $this.parents('tr.childp');
            var $text = $this.html();
            var $newtext = $text == 'Approve' ? 'Reverse' : 'Approve';
            if ($text == 'Loading...') {
                return;
            }
            $this.html('Loading...');
            var $action = $text == 'Approve' ? 'verify_approve' : 'verify_reverse';
            var $id = $(this).data('id');
            $.ajax({
                type: 'post',
                data: { trajax: 1, act: $action, id: $id },
                success: function (rs) {
                    $this.html($newtext);
                    if ($newtext == 'Approve') {
                        $('.editlink', $tr).show();
                        $('.pdflink', $tr).hide();
                        $('td.statuscol', $tr).html('M').addClass('result_M').removeClass('result_C');
                    } else {
                        $('.editlink', $tr).hide();
                        $('.pdflink', $tr).show();
                        $('td.statuscol', $tr).html('C').addClass('result_C').removeClass('result_M');
                    }

                }
            })
            return false;
        })
        .on('click', '.print_assen_btn', function () {
            var $this = $(this);
            if (typeof window.tr_hold_print_button === 'function' && !window.tr_hold_print_button($this, 'Printing...')) {
                return false;
            }
            if (typeof window.tr_hold_print_button !== 'function') {
                if ($(this).html() == 'Printing...') {
                    return false;
                }
                $(this).data('oldtext', $(this).html());
                $(this).html('Printing...');
            }

            var $print_times = 1;
            var $cli_opt = $('[name="bat[cli_ID]"] option:selected');
            if ($cli_opt.length > 0 && $cli_opt.data('print2')) {
                $print_times = 2;
            }
            var $assen = '';
            var $assens = [];
            var $assens_onetimes = [];
            var $printdata = [];
            var $panels = [];
            var $patient_name = '';
            var $dob = '';
            var $cul = '';
            var $position = '';
            var $doc = '';


            if ($('#number_assen').length > 0) {
                $assen = $('#number_assen').val();
                $assens.push($assen);
                $patient_name = ($('[name="pa[pa_last]"]').val() + ', ' + $('[name="pa[pa_first]"]').val());
                $dob = ($('[name="pa[pa_dob]"]').val());
                $cul = $('[name="bat[culture]"]:checked').length > 0 ? 'CU' : '';
                $position = $('#position') ? $('#position').val() : '';
                $doc = $('[name="bat[doc]"]').val();

                $('.group_print_labels:checked').each(function () {
                    var $vl = $(this).data('grouprint');
                    if ($vl && $vl.length > 0) {
                        $panels.push($vl);
                    }
                })
                $panels = $panels.join('|');

                if ($this.data('print_times') && $this.data('print_times') > 0) {
                    $print_times = $this.data('print_times');
                }

                if (typeof print_assen_specimen !== 'undefined' && print_assen_specimen) {
                    $spectyps = [];
                    $('.cb_test:checked').each(function () {
                        var $spec = $(this).data('te_type_id');
                        if (samtypes[$spec] && !$spectyps.includes(samtypes[$spec])) {
                            $spectyps.push(samtypes[$spec]);
                        }
                    })
                    var $prikey = $assen.substring(0, 2);
                    var $assenum = $assen.substring(3);
                    for (i in $spectyps) {
                        var $spec = 'C';
                        if ($spectyps[i] == 'blood') $spec = 'W';
                        if ($spectyps[i] == 'urine') $spec = 'U';
                        var $assk = $prikey + $spec;
                        var $newassen = $assk + $assenum;

                        if ($newassen != $assen) {
                            $assens.push($newassen);
                            if ($spec == 'W' || $spec == 'U') {
                                $assens_onetimes.push($newassen);
                            }
                        }

                    }

                }
            }
            else {
                var $tr = $(this).parents('tr');
                $assen = $('.assen', $tr).html();
                $assens.push($assen);
                $patient_name = '';
                if ($('.col-patient_name', $tr).length > 0) {
                    $patient_name = $('.col-patient_name', $tr).html();
                } else if ($tr.data('patient_name')) {
                    $patient_name = $tr.data('patient_name');
                } else if ($('.col-pa_first', $tr).length > 0) {
                    $patient_name = $('.col-pa_last', $tr).html() + ', ' + $('.col-pa_first', $tr).html();
                }
                $dob = $('.col-dob', $tr).length > 0 ? $('.col-dob', $tr).html() : $tr.data('dob');
                $panels = $tr.data('panels_print') || '';

                $cul = $tr.data('cul') ? 'CU' : '';
                $position = $tr.data('position') ? $tr.data('position') : '';
                $doc = $tr.data('doc') ? $tr.data('doc') : '';
                $printdata = $tr.data('printdata') ? $tr.data('printdata') : [];
                if ($tr.data('print_times') && $tr.data('print_times') > 0) {
                    $print_times = $tr.data('print_times');
                }
            }

            if ($dob) {
                $dob = $.trpdd_formatdate($dob);
                $patient_name += '  ' + $dob;
            }

            var $printsdatas = [];

            if ($print_times > 0) {
                var $arrpanels = $panels ? $panels.split('|') : [''];
                for (i2 in $assens) {
                    var $assen2 = $assens[i2];
                    var $asstime = $print_times;
                    if ($asstime > 1 && $assens_onetimes.includes($assen2)) {
                        $asstime = 1;
                    }
                    for (j in $arrpanels) {
                        for (i = 1; i <= $asstime; i++) {
                            var $prd = {
                                'assen': $assen2,
                                'patient_name': $patient_name,
                                'position': $position,
                                'doc': $doc,
                                'panel': $arrpanels[j],
                                'cul': $cul
                            };
                            $printsdatas.push($prd);
                        }
                    }
                }
            }

            for (i in $printdata) {
                var $pd = $printdata[i];
                var $prd = {
                    'assen': $pd.assen,
                    'patient_name': $patient_name,
                    'position': $pd.position || $position,
                    'doc': $pd.doc || $doc,
                    'panel': $pd.panel,
                    'cul': $pd.cul || $cul
                };
                $printsdatas.push($prd);
            }


            if ($printsdatas.length === 0) {
                if (typeof window.tr_release_print_button === 'function') {
                    window.tr_release_print_button($this);
                } else {
                    $this.html($this.data('oldtext'));
                }
                return false;
            }

            var inow = 0;
            var printNext = function () {
                if (inow >= $printsdatas.length) {
                    if (typeof window.tr_release_print_button === 'function') {
                        window.tr_release_print_button($this);
                    } else {
                        $this.html($this.data('oldtext'));
                    }
                    return;
                }
                var $prd = $printsdatas[inow++];
                var doneCalled = false;
                var onDone = function () {
                    if (doneCalled) return;
                    doneCalled = true;
                    printNext();
                };
                var onError = function () {
                    if (doneCalled) return;
                    doneCalled = true;
                    if (typeof window.tr_release_print_button === 'function') {
                        window.tr_release_print_button($this);
                    } else {
                        $this.html($this.data('oldtext'));
                    }
                };
                var started = $.tr_print_barcode($prd.assen, $prd.patient_name, $prd.position, $prd.doc, $prd.panel, $prd.cul, onDone, onError);
                if (!$use_zebra) {
                    setTimeout(onDone, 120);
                } else if (started === false) {
                    onError();
                }
            };

            printNext();
            return false;
        })
        .on('click', '#print_barcodes_btn', function () {
            var $btn = $(this);
            var $items = [];
            var $is_selectedtbl = $('table.selecttbl').length > 0;
            if ($is_selectedtbl) {
                $('table.selecttbl tr.selected').each(function () {
                    $items.push($(this));
                })

            } else {
                $('tr td.col-mid input:checked').each(function () {
                    $items.push($(this).parents('tr'));
                })
            }

            if ($items.length == 0) {
                alert('You must select least 1 item');
                return false;
            } else {

                var $tt = $items.length;
                var $ci = 0;
                if (typeof window.tr_hold_print_button === 'function' && !window.tr_hold_print_button($btn, 'Printing...(' + ($ci + 1) + '/' + $tt + ')')) {
                    return false;
                }
                if (typeof window.tr_hold_print_button !== 'function') {
                    if ($btn.html().indexOf('Printing...') > -1) {
                        return false;
                    }
                    $btn.data('oldtext', $btn.html());
                    $btn.html('Printing...(' + ($ci + 1) + '/' + $tt + ')');
                }
                var printbarcodes = function () {
                    if ($ci >= $tt) {
                        if (typeof window.tr_release_print_button === 'function') {
                            window.tr_release_print_button($btn);
                        } else {
                            setTimeout(function () {
                                $btn.html($btn.data('oldtext'));
                            }, 1500);
                        }
                        return;
                    }
                    $btn.html('Printing...(' + ($ci + 1) + '/' + $tt + ')');
                    var $tr = $items[$ci];
                    var $assen = $('.assen', $tr).html();
                    var $patient_name = $('.col-patient_name', $tr).html();
                    var $dob = $('.col-dob', $tr).html();
                    var $panels = $tr.data('panels_print');
                    var $position = $tr.data('position');
                    var $doc = $tr.data('doc');
                    var $cul = $tr.data('cul') ? 'CU' : '';
                    if ($dob) {
                        $dob = $.trpdd_formatdate($dob);
                        $patient_name += '  ' + $dob;
                    }
                    var $rs = $.tr_print_barcode($assen, $patient_name, $position, $doc, $panels, $cul, function () {
                        //sent
                        $ci++;
                        if ($is_selectedtbl) {
                            $tr.removeClass('selected');
                        } else {
                            $('td.col-mid input:checked', $tr).prop('checked', false);
                        }

                        setTimeout(printbarcodes, 500);
                    }, function (errorMessage) {
                        //error
                        alert('Error. ' + errorMessage + '. Please check printer!');
                        setTimeout(printbarcodes, 1500);
                    });
                    if (!$rs) {
                        setTimeout(printbarcodes, 1500);
                    }
                }
                printbarcodes();

            }
        })
        .on('click', 'input.checkall', function () {
            var $tbl = $(this).parents('table');
            $('td.' + $(this).data('td') + ' input[type="checkbox"]', $tbl).prop('checked', $(this).is(':checked'));
        })
        .on('click', 'table.selecttbl td', function (e) {
            if (e.target.tagName != 'TD') return;
            var $tr = $(this).parent();
            if ($tr.hasClass('selected')) {
                $tr.removeClass('selected');
            } else {
                $tr.addClass('selected');
            }
            var $countitem = $('table.selecttbl tr.selected').length;
            var $html = $countitem > 0 ? $countitem + ' item(s) selected <a class="clearselected" href=#>Clear</a>' : '';
            $('.count_items_selected').html($html);
        })
        .on('click', '.clearselected', function () {
            $('table.selecttbl tr.selected').removeClass('selected');
            $('.count_items_selected').html('');
        })
        .on('click', '#do_bulk_action', function () {
            var $act = $('#bulk_action_select').val();
            var $this = $(this);
            var $ref_lab_id = '';
            if ($act == 'move_to_reflab' && $('#ref_lab_id').val() == '') {
                return false;
            }
            if ($act != '') {
                if ($act == 'move_to_reflab') {
                    $ref_lab_id = $('#ref_lab_id').val();
                }
                var $items = [];
                $('table.selecttbl tr.selected').each(function () {
                    $items.push($(this).data('mid'));
                })
                if ($items.length > 0) {
                    $this.html('Proccessing...');
                    $.ajax({
                        data: { act: $act, 'trajax': 1, items: $items, ref_lab_id: $ref_lab_id },
                        type: 'post',
                        dataType: 'json',
                        success: function (json) {
                            $this.html('Apply');
                            if (json.success) {
                                $('#bulk_action_select').val('');
                                $('table.selecttbl tr.selected').removeClass('selected');
                                $this.html(json.msg);
                                location.reload();
                            } else {
                                alert('Error. Please try again!');
                            }
                        },
                        error: function () {
                            $this.html('Apply');
                            alert('Error. Please try again!');
                        }
                    });
                } else {
                    alert('You must select least 1 item!');
                }
            }
            return false;
        })
        .on('change', '#bulk_action_select', function () {
            if ($(this).val() == 'move_to_reflab') {
                $('#ref_lab_id').show();
            } else {
                $('#ref_lab_id').hide();
            }
        })
        .on('click', '.card_file > a.selectfile', function () {
            var $pr = $(this).parents('.card_file');
            $pr.find('input[type="file"]').click();
            return false;
        })
        .on('change', '.dagnostic_code select', function () {
            if ($(this).val() == '') return;
            var $pr = $(this).parents('.dagnostic_code');
            var $vl = $(this).val();
            var $html = '<li><input type="hidden" name="' + $pr.data('name') + '" value="' + $vl + '"/> ' + $vl + ' <span></span></li>';
            $('ul', $pr).append($html);
            tr_check_dagnostic_required($pr);
            $(this).val('').change();
        })
        .on('keypress', '.dagnostic_code input', function (e) {
            if (e.keyCode == 13) {
                var $vl = $(this).val();
                if ($vl == '') return false;
                var $pr = $(this).parents('.dagnostic_code');
                var $html = '<li><input type="hidden" name="' + $pr.data('name') + '" value="' + $vl + '"/> ' + $vl + ' <span></span></li>';
                $('ul', $pr).append($html);
                $(this).val('');
                tr_check_dagnostic_required($pr);
                return false;
            }
        })
        .on('change', '.dagnostic_code input', function () {
            var $vl = $(this).val();
            if ($vl == '') return false;
            var $pr = $(this).parents('.dagnostic_code');
            var $html = '<li><input type="hidden" name="' + $pr.data('name') + '" value="' + $vl + '"/> ' + $vl + ' <span></span></li>';
            $('ul', $pr).append($html);
            $(this).val('');
            tr_check_dagnostic_required($pr);
        })
        .on('click', 'table.visitrow_tbl tbody tr *', function () {
            var $tbl = $(this).parents('table.visitrow_tbl');
            var $tr = $(this).parents('tr');
            $('tr', $tbl).removeClass('visited');
            $tr.addClass('visited');
        })
        .on('click', '.thickbox2', function () {
            var $title = $(this).attr('title') ? $(this).attr('title') : '';
            tb_show($title, $(this).data('href'));
            return false;
        })
        .on('change', '.checkbox_box input[type="checkbox"]', function () {
            var $pr = $(this).parents('.checkbox_box');
            if ($(this).is(':checked')) {
                $('.show_by_checkbox', $pr).show();
                $('[data-rq="true"]', $pr).prop('required', true);
            } else {
                $('.show_by_checkbox', $pr).hide();
                $('[required]', $pr).prop('required', false);
            }
        }).on('change', 'form input.submitchange', function () {
            $(this).parents('form').submit();
        })
        .on('click', 'input.readonly', function () {
            alert('This field is read only!');
            return false;
        })

    $('.card_file').each(function () {

        if ($('.view_card_file', $(this)).length > 0) {
            $('a.selectfile', $(this)).hide();
        }
    })

    $('.multiselect.auto').each(function () {
        var $this = $(this);
        var $all = $('<a class="selectall">select all</a> ');
        var $dc = $('<a class="severalall">none</a> ');
        $this.after($dc);
        $this.after($all);
        $all.click(function () {
            $this.find('input').prop('checked', true);
        })
        $dc.click(function () {
            $this.find('input').removeAttr('checked');
        })
    });



    $('input[name="no_insurance"]').on('change', function () {
        if (this.checked) {
            $('input[name="client_bill"]').prop('checked', false);
        }
    });

    $('input[name="client_bill"]').on('change', function () {
        if (this.checked) {
            $('input[name="no_insurance"]').prop('checked', false);
        }
    });

    $('#use_required_ins [required]').attr('old_required', '1');
    $('#use_required_com [required]').attr('old_required', '1');

    $('.dagnostic_code').each(function () {
        tr_check_dagnostic_required($(this));
    });
    $('[name="ins1[ins_relation]"]').change();
    $('[name="ins2[ins_relation]"]').change();
    $('[name="bat[ins_comp]"]').change();
    $('[name="no_insurance"]').change();

    tr_setup_practice_auto($('[name="bat[lab_id]"]').val());
    tr_setup_physician_auto($('[name="bat[cli_ID]"]').val());


    $('.ins_company_auto').each(function () {
        var $fname = $(this).attr('name').indexOf('ins2') > -1 ? 'ins2' : 'ins1';
        $(this).autocomplete({
            source: ajaxurl + '?action=ajax&autocomplete=ins_company_auto',
            minLength: 1,
            select: function (event, ui) {
                if (ui.item) {
                    $('[name="' + $fname + '[ins_address]"]').val(ui.item.payer_address);
                    $('[name="' + $fname + '[ins_city]"]').val(ui.item.payer_city);
                    $('[name="' + $fname + '[ins_state]"]').val(ui.item.payer_state).change();
                    $('[name="' + $fname + '[ins_zip]"]').val(ui.item.payer_zip);
                    $('[name="' + $fname + '[ins_phone]"]').val(ui.item.payer_phone);
                    $('[name="' + $fname + '[ins_payer_ID]"]').val(ui.item.id);
                    $('[name="' + $fname + '[ins_mnemonic]"]').val(ui.item.payer_mnemonic);
                }
            }
        });
    });
    $('.ins_company_auto2').each(function () {
        $(this).autocomplete({
            source: ajaxurl + '?action=ajax&autocomplete=ins_company_auto',
            minLength: 1,
            select: function (event, ui) {
                if (ui.item) {
                    $('[name="ins2[ins_phone]"]').val(ui.item.payer_phone);
                    $('[name="ins2[ins_payer_ID]"]').val(ui.item.id);
                }
            }
        });
    });
    $('[name="bat[subscriber_insurance]"]').each(function () {
        $(this).autocomplete({
            source: ajaxurl + '?action=ajax&autocomplete=ins_company_auto',
            minLength: 1,
            select: function (event, ui) {
                if (ui.item) {
                    $('[name="bat[address_of_claim]"]').val(ui.item.payer_address);
                    $('[name="bat[subscriber_zip]"]').val(ui.item.payer_zip);
                    $('[name="bat[attorney_phone]"]').val(ui.item.payer_phone);
                }
            }
        });
    });

    $('.patient_last_auto').each(function () {
        $(this).autocomplete({
            source: ajaxurl + '?action=ajax&autocomplete=patient_last_auto',
            minLength: 1,
            select: function (event, ui) {
                if (ui.item) {
                    $('[name="pa[pa_ID]"]').val(ui.item.id);
                    $('[name="pa[pa_first]"]').val(ui.item.pa_first);
                    $('[name="pa[pa_dob]"]').val(ui.item.pa_dob);
                    $('[name="pa[pa_ID]"]').data('name', ui.item.value + ui.item.pa_first);
                    try {
                        if (ui.item.last_tests) {
                            $('[name="pa[pa_ID]"]').data('last_tests', ui.item.last_tests).change();
                        }
                    } catch (e) { }

                    try {
                        $('a.find_patient').trigger("found_patient", [ui.item]);
                    } catch (e) {
                        console.log(e);
                    }
                }
            }
        });
    })
    $('.patient_first_auto').each(function () {
        $(this).autocomplete({
            source: ajaxurl + '?action=ajax&autocomplete=patient_first_auto',
            minLength: 1,
            select: function (event, ui) {
                if (ui.item) {
                    $('[name="pa[pa_ID]"]').val(ui.item.id);
                    $('[name="pa[pa_last]"]').val(ui.item.pa_last);
                    $('[name="pa[pa_dob]"]').val(ui.item.pa_dob);
                    $('[name="pa[pa_ID]"]').data('name', ui.item.pa_last + ui.item.value);
                    try {
                        if (ui.item.last_tests) {
                            $('[name="pa[pa_ID]"]').data('last_tests', ui.item.last_tests).change();
                        }
                    } catch (e) { }
                }
            }
        });
    })

    $('.ref_lab_auto').each(function () {
        $(this).autocomplete({
            source: ajaxurl + '?action=ajax&autocomplete=ref_lab_auto',
            minLength: 0,
            select: function (event, ui) {
                if (ui.item) {
                    $('[name="ref_lab[lab_ID]"]').val(ui.item.id);
                    $('[name="ref_lab[lab_ID]"]').data('name', ui.item.value);
                }
            }
        });
        $(this).on('focus', function () {
            $(this).autocomplete("search", $(this).val());
        })
    })

    $('.lab_auto').each(function () {
        $(this).autocomplete({
            source: ajaxurl + '?action=ajax&autocomplete=lab_auto',
            minLength: 1,
            select: function (event, ui) {
                if (ui.item) {
                    $('[name="lab[lab_ID]"]').val(ui.item.id);
                    $('[name="lab[lab_ID]"]').data('name', ui.item.value);
                    tr_setup_practice_auto($('[name="lab[lab_ID]"]').val());
                }
            }
        });
    });
    $('.col_auto').each(function () {
        $(this).autocomplete({
            source: ajaxurl + '?action=ajax&autocomplete=col_auto',
            minLength: 1,
            select: function (event, ui) {
                if (ui.item) {
                    $('[name="col[col_ID]"]').val(ui.item.id);
                    $('[name="col[col_phone]"]').val(ui.item.phone);
                }
            }
        });
        $(this).change(function () {
            if ($('[name="col[col_ID]"]').val() == '-1') {
                $('[name="col[col_ID]"]').val('');
            }
        })
    });
    $.setup_select2();
    $('.nav-tab-wrapper a.nav-tab.nav-tab-active').each(function () {
        var $html = $(this).html();
        $('title').html($html);
    });

})


if (typeof showToast !== "function") {
    function showToast(type, title, message, duration = 3000, position = "top_right") {
        const containerId = `toast-container-${position}`;

        let container = document.getElementById(containerId);
        if (!container) {
            container = document.createElement("div");
            container.id = containerId;
            container.className = `toast-container ${position}`;
            document.body.appendChild(container);
        }

        const toast = document.createElement("div");
        toast.className = `toast ${type}`;

        let icon = "";
        if (type === "info") icon = "ℹ️";
        if (type === "success") icon = "✔️";
        if (type === "error") icon = "⚠️";
        if (type === "warning") icon = "⚠️";

        toast.innerHTML = `
    <div class="icon">${icon}</div>
    <div class="content">
      <strong>${title}</strong><br>
      <span>${message}</span>
    </div>
  `;

        container.appendChild(toast);

        setTimeout(() => toast.classList.add("show"), 100);

        if (duration > 0) {
            setTimeout(() => {
                toast.classList.remove("show");
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
        return toast;
    }
}
