var $current_member_num = 1;
var gmarkers = [];
var map;

function setCookie(name, value, days) {
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        var expires = "; expires=" + date.toGMTString();
    }
    else var expires = "";
    document.cookie = name + "=" + value + expires + "; path=/";
}

function getCookie(name) {
    var nameEQ = name + "=";

    var ca = document.cookie.split(";");
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}
function isNumericInput(event) {
    var key = event.keyCode;
    return ((key >= 48 && key <= 57) || // Allow number line
        (key >= 96 && key <= 105) // Allow number pad
    );
}

function isModifierKey(event) {
    var key = event.keyCode;
    return (event.shiftKey === true || key === 35 || key === 36) || // Allow Shift, Home, End
        (key === 8 || key === 9 || key === 13 || key === 46) || // Allow Backspace, Tab, Enter, Delete
        (key > 36 && key < 41) || // Allow left, up, right, down
        (
            // Allow Ctrl/Command + A,C,V,X,Z
            (event.ctrlKey === true || event.metaKey === true) &&
            (key === 65 || key === 67 || key === 86 || key === 88 || key === 90)
        )
};

jQuery(function ($) {

    var $location = '';
    var $all_locations = [];
    var $sloc_by_type = false;
    var $ptypeselected = [];

    function tr_goto_step($step, $byhand) {
        var $found_step = false;
        $('.accordions .accordion-section').each(function () {
            var $cstep = $(this).data('step');
            if ($cstep == 10 && !$found_step) {
                $step = 10;
            }
            if ($cstep == $step) {
                $found_step = true;
                $(this).addClass('active');
                if ($(this).hasClass('disabled')) {
                    $step++;
                    return;
                }
                else if ($(this).hasClass('checkout')) {
                    tr_load_checkout();
                } else if ($(this).hasClass('insurancearia')) {
                    tr_auto_insurance();
                } else if ($(this).hasClass('scheduling')) {
                    if ($('#location_div').length == 0) {
                        tr_setup_scheduling($(this));
                    } else {
                        tr_setup_dd_locations();
                    }
                } else if ($(this).hasClass('adding_scheduling')) {
                    tr_setup_scheduling($(this));
                } else if ($(this).hasClass('patient_section')) {
                    tr_auto_patient();
                }
                else if ($(this).hasClass('location_map')) {
                    trlocation_map_init();
                }

                if ($(this).hasClass('selecttestssecond')) {
                    $('.hasselectsecond').removeClass('pass');
                }
                if ($step > 1 || $byhand) {
                    $('html, body').animate({
                        scrollTop: $(this).offset().top - 100
                    }, 300);
                }
            } else {
                $(this).removeClass('active');
            }

            if ($cstep < $step) {
                $(this).addClass('pass');
            } else {
                $(this).removeClass('pass');
            }
        })
    }

    function tr_auto_patient() {
        if ($('[name="location_fields[address]"]').length > 0 && $('[name="pa[pa_address]"]').val() == '') {
            $('[name="pa[pa_address]"]').val($('[name="location_fields[address]"]').val());
            $('[name="pa[pa_city]"]').val($('[name="location_fields[city]"]').val());
            $('[name="pa[pa_state]"]').val($('[name="location_fields[state]"]').val());
            $('[name="pa[pa_zip]"]').val($('[name="location_fields[zip]"]').val());
        }
        try {
            $('.hasdate').dateDropdowns({ displayFormat: 'mdy', minAge: 0, daySuffixValues: ['', '', '', ''] });
        } catch (e) {
            console.log(e);
        }
        if ($('#company_section').length > 0) {
            var $has_company = false;
            $('.btypetbl input[type="checkbox"]:checked').each(function () {
                if ($(this).data('company')) {
                    $has_company = true;
                }
            })
            if ($has_company) {
                $('#company_section').show();
                $('#company_section input').prop('required', true);
            } else {
                $('#company_section').hide();
                $('#company_section input').prop('required', false);
            }
        }
        if ($('#show_confirm').length > 0) {
            if ($('#show_confirm').hasClass('hide')) {
                $('#patient_form').show();
            } else {
                $('#patient_form').hide();
            }
        }
    }

    function trlocation_map_init() {
        // Map Settings
        var $locationbystate = $('.accordion-section.locationt').hasClass('locationbystate');
        var $mapdiv = $('#map');
        var $zoom = $mapdiv.data('zoom') || 8;
        var $pa_state = $('#papa_state').val();
        if (!$mapdiv.hasClass('showedmap')) {
            $mapdiv.addClass('showedmap');
            var $lat = $mapdiv.data('lat');
            var $lng = $mapdiv.data('lng');
            map = new google.maps.Map(document.getElementById("map"), {
                zoom: $zoom,
                center: new google.maps.LatLng($lat, $lng),
                mapTypeId: google.maps.MapTypeId.ROADMAP
            });

            var infowindow = new google.maps.InfoWindow;

            var marker, i;

            for (i = 0; i < locations.length; i++) {

                marker = new google.maps.Marker({
                    position: new google.maps.LatLng(locations[i][1], locations[i][2]),
                    map: map,
                    label: String(locations[i][3])
                });
                gmarkers.push(marker);
                google.maps.event.addListener(marker, 'click', (function (marker, i) {
                    return function () {
                        $('#list_location li').removeClass('active');
                        $('#list_location li.location_' + i).addClass('active');
                        var $key = locations[i][4];
                        $('[name="location"]').val($key).change();
                        infowindow.setContent(locations[i][0]);
                        infowindow.open(map, marker);
                        $('html, body').animate({
                            scrollTop: $mapdiv.offset().top - 90
                        }, 300);
                    }
                })(marker, i));
            }
            $('#list_location li').each(function (i, e) {
                $(e).click(function (i) {
                    $('#list_location li').removeClass('active');
                    $(this).addClass('active');
                    return function (e) {
                        google.maps.event.trigger(gmarkers[i], 'click');
                    }
                }(i));
            });
            $('#list_location li.active').click();
        }

        var $countshow = 0;
        var $loc_show;
        $('[name="location"]').val('').prop('required', true);
        if ($sloc_by_type) {
            var i = 0;
            $('#no_location_text').html('').removeClass('showtext');
            $('#map_result').show();

            $('#list_location li').each(function () {
                $(this).removeClass('hide');
                var $k = $(this).data('key');
                var $kchar = ($k.substring(0, 1)).toUpperCase();
                var $hide = !$sloc_by_type.includes($k);
                for (var il in $sloc_by_type) {
                    var $sloc = $sloc_by_type[il];
                    if ($sloc.indexOf('loc_') > -1) {
                        var arr1 = $sloc.split('_');
                        if (arr1[1] === $kchar) {
                            $hide = false;
                            break;
                        }
                    }
                }

                if ($locationbystate && $(this).data('state') != $pa_state) {
                    $hide = true;
                }

                if ($hide) {
                    $(this).addClass('hide');
                    try {
                        gmarkers[i].setVisible(false);
                    } catch (e) {
                        console.log(e);
                    }
                } else if (locations[i][1] > 0 || locations[i][2] > 0) {
                    map.setCenter({ lat: locations[i][1], lng: locations[i][2] });
                    $loc_show = $(this);
                    $countshow++;
                }

                $(this).removeClass('active');
                i++;
            })
            $('[name="location"]').val('').change();

            console.log('$countshow1=' + $countshow);
            if ($countshow == 1) {
                $loc_show.click();
            } else if ($countshow == 0 && $mapconfig && $mapconfig['no_location_text']) {
                $('#map_result').hide();
                $('#no_location_text').html($mapconfig['no_location_text']).addClass('showtext');
                $('[name="location"]').val('').prop('required', false);
            }
        } else {
            var i = 0;
            $('#no_location_text').html('').removeClass('showtext');
            $('#map_result').show();

            $('#list_location li').each(function () {
                $(this).removeClass('hide');
                var $hide = false;
                var $pids = $(this).data('pids');
                if ($pids && $pids != '' && $ptypeselected && $ptypeselected.length > 0) {
                    $pids = $pids.split(',');
                    for (var pi in $ptypeselected) {
                        if (!$pids.includes($ptypeselected[pi])) {
                            $hide = true;
                            break;
                        }
                    }
                }
                if ($locationbystate && $(this).data('state') != $pa_state) {
                    $hide = true;
                }
                if ($hide) {
                    $(this).addClass('hide');
                    try {
                        gmarkers[i].setVisible(false);
                    } catch (e) {
                        console.log(e);
                    }
                } else if (locations[i][1] > 0 || locations[i][2] > 0) {
                    gmarkers[i].setVisible(true);
                    map.setCenter({ lat: locations[i][1], lng: locations[i][2] });
                    $loc_show = $(this);
                    $countshow++;
                }

                $(this).removeClass('active');
                i++;
            })
            $('[name="location"]').val('').change();
            if ($('#list_location li').length == 1) {
                $('#list_location li').click();
            }

            console.log('$countshow2=' + $countshow);

            if ($countshow == 0 && $mapconfig && $mapconfig['no_location_text']) {
                $('#map_result').hide();
                $('#no_location_text').html($mapconfig['no_location_text']).addClass('showtext');
                $('[name="location"]').val('').prop('required', false);
            }
        }
        //$sloc_by_type = false;
    }

    function trinit_setup_map2() {
        var $lat = $location_2_center.lat;
        var $lng = $location_2_center.lng;
        var gmarkers2 = [];
        // Map Settings
        var map2 = new google.maps.Map(document.getElementById("slocation_map"), {
            zoom: 8,
            center: new google.maps.LatLng($lat, $lng),
            mapTypeId: google.maps.MapTypeId.ROADMAP
        });

        var infowindow2 = new google.maps.InfoWindow;

        var marker2, i2;
        for (i2 = 0; i2 < locations2.length; i2++) {
            marker2 = new google.maps.Marker({
                position: new google.maps.LatLng(locations2[i2][1], locations2[i2][2]),
                map: map2,
                label: String(locations2[i2][3])
            });
            gmarkers2.push(marker2);
            google.maps.event.addListener(marker2, 'click', (function (marker2, i2) {
                return function () {
                    $('#slist_location li').removeClass('active');
                    $('#slist_location li.location_' + i2).addClass('active');
                    infowindow2.setContent(locations2[i2][0]);
                    infowindow2.open(map2, marker2);
                }
            })(marker2, i2));
        }
        $('#slist_location li').each(function (i2, e) {
            $(e).click(function (i2) {
                $('#list_location li').removeClass('active');
                $(this).addClass('active');
                return function (e) {
                    google.maps.event.trigger(gmarkers2[i2], 'click');
                }
            }(i2));
        });
        $('#slist_location li.actived').click();
    }
    if ($('#slocation_map').length > 0) {
        var $setupedmap2 = false;
        $(window).scroll(function () {
            if (!$setupedmap2) {
                $setupedmap2 = true;
                trinit_setup_map2();
            }
        });
    }


    function tr_auto_insurance() {
        var $inf = $('[name="ins1[ins_first]"]');
        var $inl = $('[name="ins1[ins_last]"]');
        if ($inf.val() == '' || $inl.val() == '') {
            $inf.val($('[name="pa[pa_first]"]').val());
            $inl.val($('[name="pa[pa_last]"]').val());
        }

        if ($('.accordions').hasClass('patient_multi')) {

            $('#multi_insurance').html('');
            var $current_ins_num = 1;
            $('#multi_patient .mpitem').each(function () {
                $current_ins_num++;
                var $html = '<div class="mpitem">' + $('#use_required_ins').html() + '</div>';
                $html = $html.replace(/ins1\[/g, 'ins1_muti[m_' + $current_ins_num + '][');
                $html = $html.replace(/ins\_card\_/g, 'ins_card_m_' + $current_ins_num + '_');
                $html = $html.replace(/patient\_id\_/g, 'patient_id_m_' + $current_ins_num + '_');
                $html = $($html);
                $('.mpnum', $html).html($current_ins_num);
                $('input[type="email"]', $html).removeAttr('required');
                $('input[type="text"],input[type="hidden"],input[type="email"],select', $html).val('');

                var $inf = $('input[name*="ins_first"]', $html);
                var $inl = $('input[name*="ins_last"]', $html);
                if ($inf.val() == '' || $inl.val() == '') {
                    $inf.val($('input[name*="[pa_first]"]', $(this)).val());
                    $inl.val($('input[name*="[pa_last]"]', $(this)).val());
                }
                $('.card_file', $html).each(setupcardfiles);

                $('#multi_insurance').append($html);

            })

        }
    }

    function tr_setup_dd_locations() {
        if ($all_locations.length === 0) {
            $('#location_div select option').each(function () {
                var $vl = $(this).attr('value');
                if ($vl != '') {
                    var $oname = $(this).html();
                    var $pids = $(this).data('pids');
                    var $item = { name: $oname, value: $vl, pids: $pids };
                    $all_locations.push($item);
                }
            })
        }
        $sloc_by_type = false;
        $ptypeselected = [];
        $('.btypetbl input[type="checkbox"]:checked').each(function () {
            var $locs_cb = $(this).data('locs');

            if ($locs_cb != '') {
                $locs_cb = $locs_cb.split(',');
                if ($sloc_by_type == false) {
                    $sloc_by_type = $locs_cb;
                } else {
                    var $nlocs = [];
                    for (i in $locs_cb) {
                        if ($sloc_by_type.includes($locs_cb[i])) {
                            $nlocs.push($locs_cb[i]);
                        }
                    }
                    $sloc_by_type = $nlocs;
                }
            }
            $ptypeselected.push($(this).val());
        })

        var $html = '<option value="">Select Location</option>';
        var $count_opt = 0;
        var $one_value = '';
        for (var i in $all_locations) {
            var $loc = $all_locations[i];
            if ($sloc_by_type) {
                if (!$sloc_by_type.includes($loc.value)) {
                    continue;
                }
            }
            if ($loc.pids && $loc.pids != '') {
                var $pids = $loc.pids.split(',');
                var $needcontinue = false;
                for (var pi in $pids) {
                    if (!$ptypeselected.includes($pids[pi])) {
                        $needcontinue = true;
                        break;
                    }
                }
                if ($needcontinue) {
                    continue;
                }
            }
            $html += '<option value="' + $loc.value + '" data-pids="' + $loc.pids + '">' + $loc.name + '</option>';
            $one_value = $loc.value;
            $count_opt++;
        }
        $('#location_div select').html($html);
        if ($count_opt == 1) {
            $('#location_div select').val($one_value).change();
        }
    }

    function tr_setup_scheduling($accordion) {
        if (!$accordion) {
            $accordion = $('.accordion-section.scheduling');
        }
        $('.schedulingsdiv', $accordion).html('');
        $('input.docinput', $accordion).val('');
        var $calenda = $accordion.data('calenda');
        var $escheduling = $accordion.data('key');
        var $dockeys_arr = [];
        if ($escheduling && $escheduling > 0 && $('input.adockeys', $accordion).length > 0) {
            var $dockeys = $('input.adockeys', $accordion).val();
            if ($dockeys !== '') {
                $dockeys_arr = $dockeys.split('*');
            }

        }
        if (!$calenda) {
            var calendardiv = $('.calendardiv', $accordion);
            var $custom_form = $('[name="custom_form"]').val();
            var calendarEl = document.getElementById(calendardiv.attr('id'));
            $calenda = new FullCalendar.Calendar(calendarEl, {
                //timeZone: 'UTC',
                locale: 'en',
                initialView: 'dayGridMonth', // FullCalendar v6 (was v4/v5 'defaultView')
                events: {
                    url: '?tr_act=scheduling_load_events&frontshow=1&custom_form=' + $custom_form + '&escheduling=' + $escheduling,
                    method: 'POST',
                    extraParams: function () {
                        var $loc = $('[name="location"]').length > 0 ? $('[name="location"]').val() : '';
                        var $timefrom = '';
                        var $timeto = '';
                        var $doc1 = '';
                        var $doc2 = '';
                        var $textlimit = '';
                        if ($('[name="bat[adoc][1]"]').length > 0) {
                            $doc1 = $('[name="bat[adoc][1]"]').val();
                            if ($doc1 == '') {
                                $doc2 = $('[name="bat[adoc][2]"]').val();
                            }
                        }
                        $('.btypetbl input[type="checkbox"]:checked').each(function () {

                            var $tfrom = $(this).data('timefrom');
                            var $tto = $(this).data('timeto');
                            if ($dockeys_arr.length > 0) {
                                if ($dockeys_arr.includes($(this).val())) {
                                    if ($tfrom != '') {
                                        $timefrom = $tfrom;
                                    }
                                    if ($tto != '') {
                                        $timeto = $tto;
                                    }
                                    if ($tfrom != '' && $tto != '') {
                                        $textlimit = $(this).parents('tr.cbrow').find('.textlimit').html();
                                        console.log('$textlimit:+' + $textlimit);
                                    }
                                }
                            } else {
                                if ($tfrom != '') {
                                    $timefrom = $tfrom;
                                }
                                if ($tto != '') {
                                    $timeto = $tto;
                                }
                                $textlimit = '';
                            }
                        });
                        $('.schedulingsdiv', $accordion).html('<div class="loading center">Loading...</div>');
                        if ($textlimit != '') {
                            $('.textlimitshow', $accordion).html($textlimit);
                        }
                        return { location: $loc, timefrom: $timefrom, timeto: $timeto, doc1: $doc1, doc2: $doc2 }
                    }
                },
                height: 'auto',
                eventDidMount: function (arg) {
                    if (arg.el) {
                        var $td = $(arg.el).parents('td.fc-daygrid-day');
                        $td.addClass('hasevents');
                        if ($('.aop', $td).length > 0) {
                            $td.addClass('has_aop');
                        }
                        $('.fc-event-time', $td).addClass('notranslate');
                        $('.fc-event-title', $td).addClass('notranslate');
                    }

                    $('.schedulingsdiv', $accordion).html('<div class="center">Select Date</div>');
                },
                loading: function (isLoading) {
                    if (!isLoading) {
                        $('.schedulingsdiv', $accordion).html('<div class="center">Not Found</div>');
                    }
                },
                dateClick: function (info) {
                    var $pr = $(info.dayEl);
                    if (!$pr) return;

                    if ($pr.hasClass('fc-day-past')) {
                        return;
                    }

                    var $html = '';
                    $('.fc-event-title', $pr).each(function () {
                        $html += '<span class="eventtime notranslate" data-date="' + info.dateStr + '">' + $(this).html() + '</span>';
                    })
                    $('.schedulingsdiv', $accordion).html($html);
                    $('.activedate', $accordion).removeClass('activedate');
                    $pr.addClass('activedate');

                    //check addingproductlm
                    try {
                        if ($pr.hasClass('has_aop')) {
                            $('.addingproductlm input', $accordion).prop('checked', true);
                        } else {
                            var $adding_product_days = $accordion.data('adding_product_days');
                            if ($escheduling > 1 && $adding_product_days > 0 && $('.addingproductlm', $accordion).length > 0) {
                                $('.addingproductlm input', $accordion).prop('checked', false);
                                if ($('[name="bat[adoc][1]"]').length > 0) {
                                    var $doc1 = $('[name="bat[adoc][1]"]').val();
                                    $doc1 = $doc1.split(' ');
                                    const date = new Date($doc1[0]);
                                    date.setDate(date.getDate() + $adding_product_days);
                                    var $cpdate = date.toISOString().slice(0, 10);
                                    if ($cpdate == info.dateStr) {
                                        $('.addingproductlm input', $accordion).prop('checked', true);
                                    }
                                }

                            }
                        }

                    } catch (e) { }
                }
            });


            $calenda.render();
            $accordion.data('calenda', $calenda)
        } else {
            $('.calendardiv td', $accordion).removeClass('hasevents activedate has_aop');
            $calenda.refetchEvents();
        }

        try {
            if ($escheduling > 1 && $('[name="bat[adoc][1]"]').length > 0) {
                var $doc1 = $('[name="bat[adoc][1]"]').val();
                if ($doc1 !== '') {
                    var limitd = $accordion.data('limitd');
                    $doc1 = $doc1.split(' ');
                    const date = new Date($doc1[0]);
                    if (limitd > 0) {
                        date.setDate(date.getDate() + limitd);
                    }

                    $calenda.gotoDate(date.toISOString().slice(0, 10));
                }
            }
        } catch (e) { }
        try {
            if ($escheduling > 0 && $dockeys_arr.length > 0) {
                //display tests in tab
                var $html = '';
                $('.btypetbl input[type="checkbox"]:checked').each(function () {
                    if ($dockeys_arr.includes($(this).val())) {
                        $html += '<li>' + $(this).parent().text() + '</li>';
                    }
                })
                $('.before_calendardiv', $accordion).html($html + '<br>');
            }
        } catch (e) { }
    }

    function tr_load_checkout() {
        if ($('.insurancearia.disabled').length > 0) {
            $('.insurancearia.disabled input[type="text"]').val('');
        }
        var $section = $('#checkout_section');
        $section.html('<div class="loading center">Loading</div>');
        $.ajax({
            data: $('#requisition_form').serialize() + '&tr_act=load_checkout_form',
            type: 'post',
            dataType: 'json',
            success: function ($json) {
                if ($json.status === 'ok') {
                    $section.html($json.html);
                    try {
                        if ($('[name="payment_method"]:checked').val() == 'cod') {
                            $('[name="payment_method"]:checked').parent().click();
                        }
                    } catch (e) { }
                } else {
                    setTimeout(function () {
                        tr_load_checkout();
                    }, 2000);
                }
            },
            error: function () {
                setTimeout(function () {
                    tr_load_checkout();
                }, 2000);
            }
        })
    }

    function setupcardfiles() {
        var $pr = $(this);
        var $id = $(this).data('id');
        var $html = '<div class="uploadajax" data-id="' + $id + '">' +
            '<form method="post" enctype="multipart/form-data" target="frame_' + $id + '">' +
            '<input type="file" name="insu_file" accept="image/*"/>' +
            '<input type="hidden" name="tr_act" value="upload_insurance_photos"/>' +
            '<input type="hidden" name="type" value="' + $id + '"/>' +
            '</form>';
        $html += '<iframe id="frame_' + $id + '" name="frame_' + $id + '" src="" style="width:0;height:0;border:0"></iframe>';
        $html = $($html);
        $('body').append($html);
        $('a', $pr).click(function () {
            $('input[type="file"]', $html).click();
            return false;
        })
    }


    $('body').on('click', '.gotostep', function () {
        //check required
        var $this = $(this);
        var $pass = true;
        var $pr = $(this).parents('.accordion-section');

        $('[required]', $pr).each(function () {
            var $type = $(this).attr('type') ? $(this).attr('type') : 'text';
            $(this).removeClass('error');
            if (($type === 'checkbox' || $type === 'radio') && $(this).is(':checked') === false) {
                var $has_checked = false;
                var $cbname = $(this).attr('name');
                $('input[type="' + $type + '"][name="' + $cbname + '"]').each(function () {
                    if ($(this).is(':checked')) {
                        $has_checked = true;
                    }
                })
                if ($has_checked === false) {
                    $pass = false;
                    $(this).addClass('error');
                }

            } else if ($(this).val() === '') {
                $pass = false;
                $(this).addClass('error');
            }
        })

        if ($pr.hasClass('hasselectsecond')) {
            $pass = true;
        }

        if ($this.hasClass('skip_location')) {
            $pass = true;
            $('[name="location"]').val('').prop('required', false);
        }

        if ($(this).hasClass('submit_adoc')) {
            var $docf = $('input.docinput', $pr);
            if ($docf) {
                $docf.removeClass('error');
                if ($docf.val() == '') {
                    $pass = false;
                    $docf.addClass('error');
                }
            }
        }

        if ($pr.hasClass('patient_section') && $('#pa_ssn').data('allow_empty_ssn')) {
            if ($('#pa_ssn').hasClass('error') || $('#pa_ssn').hasClass('ferror')) {
                if (confirm('Do you not have a social security number?')) {
                    $('#pa_ssn').prop('required', false).removeClass('ferror').removeClass('error').val('');
                } else {
                    $('#pa_ssn').focus();
                }
            }
        }

        if ($pr.hasClass('group_list')) {
            var $cb = $('[name="group"]:checked');
            var $pr = $cb.parent();
            var $tests = $cb.data('tests') + "";
            var $testsopt = $cb.data('testsopt') + "";
            $('.text_above_tests').html($('.desc', $pr).html());
            if ($tests && $tests != '') {
                $tests = $tests.split(',');
                $testsopt = $testsopt.split(',');

                $('.btypetbl input[type="checkbox"]').each(function () {
                    var $lb = $(this).parent();
                    var $arr = $(this).val().split('|');
                    var $tr = $lb.parents('tr.cbrow');
                    $(this).removeAttr('onclick');
                    if (!$tests.includes($arr[1])) {
                        if ($testsopt.includes($arr[1])) {
                            $lb.show();
                            $tr.show().removeClass('hidel');
                            $(this).addClass('allowmulti');
                        }
                        else {
                            $lb.hide();
                            $tr.hide().addClass('hidel');
                            $(this).prop('checked', false).removeClass('allowmulti');
                        }
                    } else {
                        $lb.show();
                        $tr.show().removeClass('hidel');
                        $(this).prop('checked', true).addClass('allowmulti');
                        if ($lb.html().indexOf('(2)') < 0) {
                            //$(this).attr('onclick',"return false");
                        }
                    }
                })
            }
        }

        if ($('.ferror', $pr).length > 0) {
            $pass = false;
        }

        if ($pass) {
            if ($(this).hasClass('nextbyproduct')) {
                var schenum = 2;
                $('input.docinput').val('');

                //check product @home
                if ($('.accordion-section.scheduling').length > 0) {
                    $('.accordion-section.scheduling').removeClass('disabled');
                    $('.accordion-section.location_map').removeClass('disabled');

                    var $has_home = false;
                    var $count_selected = 0;
                    $('.btypetbl input[type="checkbox"]:checked').each(function () {
                        $count_selected++;
                        var $lb = $(this).parent();
                        var $lbtext = $lb.text();
                        if ($lbtext.indexOf('@Home') > -1 || $lbtext.indexOf(' Home ') > -1) {
                            $has_home = true;
                        }
                    })
                    if ($count_selected == 1 && $has_home) {
                        $('.accordion-section.scheduling').addClass('disabled');
                        $('.accordion-section.location_map').addClass('disabled');
                        $('[name="bat[doc]"]').val('today');
                    }
                } else {
                    schenum = 1;
                }

                $('.adding_scheduling').each(function () {
                    var $keya = $(this).data('key');
                    if ($('.btypetbl input[data-escheduling="' + $keya + '"][type="checkbox"]:checked').length > 0) {
                        $(this).removeClass('disabled');
                        $('.schenum', $(this)).html(schenum);
                        schenum++;
                    } else {
                        $(this).addClass('disabled');
                    }
                })

            } else if ($(this).hasClass('nextbysymptoms')) {
                var $addingall = $pr.data('addingall');
                if ($addingall && $addingall != '') {
                    var $allcheck = true;
                    $('input.dagnostic_codes_cb', $pr).each(function () {
                        if ($(this).is(':checked') == false) {
                            $allcheck = false;
                        }
                    })
                    if ($allcheck) {
                        $('#symptomslist').prepend('<input type="hidden" name="bat[dagnostic_codes][]" class="addingdagnostic" value="' + $addingall + '"/>');
                    } else {
                        $('#symptomslist .addingdagnostic').remove();
                    }
                }
            }
            tr_goto_step($(this).data('step'), true);
        } else {
            try {
                $('html, body').animate({
                    scrollTop: $pr.offset().top - 100
                }, 300);
            } catch (e) { }
        }

        return false;
    })
        .on('click', '.accordion-section-title', function () {
            var $pr = $(this).parents('.accordion-section');
            var $pass = $pr.hasClass('pass');
            if ($('.selecttestssecond').length > 0) {
                if ($pr.hasClass('hasselectsecond') || $pr.hasClass('selecttestssecond')) {
                    $pass = true;
                }
            }
            if (!$pass) return false;
            tr_goto_step($pr.data('step'), true);
        })
        .on('click', '#place_order2', function () {
            var $btn = $(this);
            var $form = $(this).closest('form');
            if ($form.is('.processing')) {
                return false;
            }
            if ($form.triggerHandler('checkout_place_order') === false
                || $form.triggerHandler('checkout_place_order_' + $('[name="payment_method"]').val()) === false) {
                return false;
            }
            if ($btn.html() === 'Loading...') return false;
            $btn.data('oldtext', $btn.html());
            $btn.html('Loading...');

            $.ajax({
                data: $('#requisition_form').serialize() + '&tr_act=ci_checkout_tests',
                type: 'post',
                dataType: 'json',
                success: function ($json) {
                    $btn.html($btn.data('oldtext'));
                    if ($json.result === 'login') {
                        alert('Your email exist. You need login to continue!');
                    }
                    else if ($json.result === 'success') {
                        if ($json.html) {
                            tr_goto_step(10, true);
                            $('#order_section').html($json.html);
                            $('.accordion-section').removeClass('pass').addClass('notclick');
                        }
                        else if ($json.redirect) {
                            location = $json.redirect;
                        } else {
                            $('#requisition_form').html($json.messages);
                            $('html, body').animate({
                                scrollTop: $("#checkout_alert").offset().top - 50
                            }, 300);
                        }
                    }
                    else if ($json.result === 'failure') {
                        $('#checkout_alert').html($json.messages);
                        $('html, body').animate({
                            scrollTop: $("#checkout_alert").offset().top - 50
                        }, 300);
                    } else {
                        alert('Error. Please Try again!');
                    }
                },
                error: function () {
                    $btn.html($btn.data('oldtext'));
                    alert('Error. Please Try again!');
                }
            })
            return false;
        })
        .on('click', '[name="apply_coupon"]', function () {
            var $btn = $(this);
            if ($btn.html() === 'Loading...') {
                return false;
            }
            $btn.data('oldval', $btn.html());
            $btn.html('Loading...');
            $.ajax({
                data: $('#requisition_form').serialize() + '&tr_act=apply_copupon',
                type: 'post',
                dataType: 'json',
                success: function ($json) {
                    if ($json.status === 'ok' && $json.summary != '') {
                        $('#order_review_table').html($json.summary);
                        //$('.coupon_div').hide();
                    }
                    $('.coupon_alert').html($json.alert);
                    $btn.html($btn.data('oldval'));
                },
                error: function () {
                    $('.coupon_alert').html('<p class="alert error">Error Please Try again!</p>');
                    $btn.html($btn.data('oldval'));
                }
            })
            return false;
        })
        .on('change', '[name="no_insurance"]', function () {
            if ($(this).is(':checked')) {
                $('#use_required_ins [old_required]:not(.has_required)').removeAttr('required').removeClass('error');
                $('#use_required_ins [old_required]:not(.has_required)').removeAttr('aria-required');
            } else {
                $('#use_required_ins [old_required]:not(.has_required)').attr('required', 'required');
            }
        })
        .on('click', '.btypetbl input[type="checkbox"]', function () {
            var $typediv = $('#test_types');
            var $allow_select_pay_ins = $typediv.data('allow_select_pay_ins') == '1';
            var $only_one_per_group = $typediv.data('only_one_per_group') == '1';
            var $btype = $(this).data('type');
            var $vl = $(this).val();
            var $pagroup = '';
            var $group = $(this).data('group');
            var $groupkey = $(this).data('radiogroup');

            if ($allow_select_pay_ins) {
                $pagroup = '[data-group="' + $group + '"]';
            }

            $('.btypetbl input[type="checkbox"]' + $pagroup).each(function () {
                var $crtype = $(this).data('type');
                var $crgroup = $(this).data('group');
                if ($groupkey && $groupkey == 'multi') {

                }
                else if ($(this).is('[readonly]') || $(this).hasClass('allowmulti')) {

                }
                else if ($(this).val() != $vl && ($crtype == $btype)) {
                    $(this).prop('checked', false);
                }
                else if ($only_one_per_group && $crgroup == $group && $(this).val() != $vl) {
                    $(this).prop('checked', false);
                }
            })

            //check group key
            if ($groupkey && $groupkey != '' && $groupkey != 'multi') {
                $('.btypetbl input[type="checkbox"][data-radiogroup="' + $groupkey + '"]').each(function () {
                    if ($(this).val() != $vl) {
                        $(this).prop('checked', false);
                    }
                })
            }

            var $has_insu = $('.withinsurance input[type="checkbox"]:checked').length > 0;
            //console.log("$has_insu:"+$has_insu);

            if ($has_insu) {
                $('.insurancearia').removeClass('disabled');
            } else {
                $('.insurancearia').addClass('disabled');
            }
            if ($('.nonins_cardsid').length > 0) {

                var $has_noneinsu = $('.withnoinsurance input[type="checkbox"]:checked').length > 0;
                if ($('.nonins_cardsid').hasClass('pay_cardsids')) {
                    $has_noneinsu = $('.mainpay input[type="checkbox"]:checked').length > 0;
                }
                if ($has_noneinsu) {
                    $('.nonins_cardsid').removeClass('disabled');
                } else {
                    $('.nonins_cardsid').addClass('disabled');
                }
            }

            var $ssn_required = null;
            if ($('.withnoinsurance').length > 0 && $('.withnoinsurance').data('rssn') == 1) {
                if ($('.withnoinsurance input[type="checkbox"]:checked').length > 0) {
                    $ssn_required = true;
                } else if ($ssn_required === null) {
                    $ssn_required = false;
                }
            }
            if ($('.withinsurance').length > 0 && $('.withinsurance').data('rssn') == 1) {
                if ($('.withinsurance input[type="checkbox"]:checked').length > 0) {
                    $ssn_required = true;
                } else if ($ssn_required === null) {
                    $ssn_required = false;
                }
            }

            if ($ssn_required !== null) {
                if ($ssn_required) {
                    $('[name="pa[pa_ssn]"]').prop('required', true).attr('required', '1');
                } else {
                    $('[name="pa[pa_ssn]').prop('required', false).removeAttr('required');
                }
            }

            if ($('.accordions').data('locfirst') != '1') {
                $sloc_by_type = false;
                $ptypeselected = [];
                $('.btypetbl input[type="checkbox"]:checked').each(function () {
                    var $locs_cb = $(this).data('locs');
                    if ($locs_cb != '') {
                        $locs_cb = $locs_cb.split(',');
                        if ($sloc_by_type == false) {
                            $sloc_by_type = $locs_cb;
                        } else {
                            var $nlocs = [];
                            for (i in $locs_cb) {
                                if ($sloc_by_type.includes($locs_cb[i])) {
                                    $nlocs.push($locs_cb[i]);
                                }
                            }
                            $sloc_by_type = $nlocs;
                        }
                    }
                    $ptypeselected.push($(this).val());
                })
            }

        })
        .on('click', '.schedulingsdiv .eventtime', function () {
            var $accordion = $(this).parents('.accordion-section');
            var $date = $(this).data('date');
            var $time = $(this).html();
            $('input.docinput', $accordion).val($date + ' ' + $time);
            $('.eventtime', $accordion).removeClass('active');
            $(this).addClass('active');
        })
        .on('click', 'input[name="payment_method"]', function (e) {
            e.stopPropagation();

            if ($('.payment_methods input.input-radio').length > 1) {
                var target_payment_box = $('div.payment_box.' + $(this).attr('ID')),
                    is_checked = $(this).is(':checked');

                if (is_checked && !target_payment_box.is(':visible')) {
                    $('div.payment_box').filter(':visible').slideUp(230);

                    if (is_checked) {
                        target_payment_box.slideDown(230);
                    }
                }
            } else {
                $('div.payment_box').show();
            }

            if ($(this).data('order_button_text')) {
                $('#place_order').text($(this).data('order_button_text'));
            } else {
                $('#place_order').text($('#place_order').data('value'));
            }

        })
        .on('click', '.purchase_more', function () {
            location.reload();
            return false;
        })
        .on('change', 'select[name="location"]', function () {
            $location = $(this).val();
            tr_setup_scheduling();
        })
        .on('change', '.uploadajax input', function (event) {

            var $pr = $(this).parents('.uploadajax');
            var $id = $pr.data('id');
            var $carfile = $('.card_file[data-id="' + $id + '"]');
            $('span.preview', $carfile).remove();
            var $img = $('<div class="preview"><img src="' + URL.createObjectURL(event.target.files[0]) + '" style="max-width:300px"></div>');
            $carfile.append($img);
            $(this).parents('form').submit();

        })
        .on('change', '[name="ins1[ins_relation]"]', function () {
            if ($(this).is(':checked')) {
                if ($(this).val() != 'Self') {
                    $('#dob_ins1').show();
                    $('#dob_ins1 input').attr('required', 'required');
                    $('#dob_ins1 label span').html($(this).val());
                } else {
                    $('#dob_ins1').hide();
                    $('#dob_ins1 input').removeAttr('required');
                    if ($('[name="ins1[ins_last]"]').val() == '') {
                        $('[name="ins1[ins_last]"]').val($('[name="pa[pa_last]"]').val())
                    }
                    if ($('[name="ins1[ins_first]"]').val() == '') {
                        $('[name="ins1[ins_first]"]').val($('[name="pa[pa_first]"]').val())
                    }
                }
            }
        }).on('keyup', '.has_phone', function (event) {
            if (isModifierKey(event)) { return; }

            var value1 = $(this).val().replace('+1', '');
            var input = value1.replace(/\D/g, '').substring(0, 10); // First ten digits of input only
            var zip = input.substring(0, 3);
            var middle = input.substring(3, 6);
            var last = input.substring(6, 10);

            if (input.length > 6) { $(this).val('(' + zip + ') ' + middle + ' - ' + last) }
            else if (input.length > 3) { $(this).val('(' + zip + ') ' + middle); }
            else if (input.length > 0) { $(this).val('(' + zip + ') '); }
            $(this).change();
        }).on('keydown', '.has_phone', function () {
            if (!isNumericInput(event) && !isModifierKey(event)) {
                event.preventDefault();
            }
        }).on('click', '#list_location li', function () {

            if ($('.accordions').data('locfirst') == '1') {
                var $k = $(this).data('key');
                var $pids = $(this).data('pids');
                var $pids_arr = [];
                if ($pids && $pids != '') {
                    $pids_arr = $pids.split(',');
                }
                $('.btypetbl label').show();
                $('.btypetbl tr.cbrow').removeClass('hidel').show();
                $('.btypetbl tr.headertr').show();
                $('.btypetbl input[type="checkbox"]').each(function () {
                    var $lb = $(this).parent();
                    var $locs = $(this).data('locs');
                    if ($locs != '') {
                        $locs = $locs.split(',');
                    } else {
                        $locs = false;
                    }
                    var $needhide = false;
                    if ($locs) {
                        if (!$locs.includes($k)) {
                            $needhide = true;
                        }
                    }
                    if ($pids_arr.length > 0 && !$pids_arr.includes($(this).val())) {
                        $needhide = true;
                    }
                    if ($needhide) {
                        $lb.hide();
                        var $tr = $lb.parents('tr.cbrow');
                        var $hid = $tr.data('hid');
                        var $canhideheader = true;
                        $tr.hide().addClass('hidel');
                        $('tr.cbrow[data-hid="' + $hid + '"]').each(function () {
                            if (!$(this).hasClass('hidel')) {
                                $canhideheader = false;
                            }
                        })
                        if ($canhideheader) {
                            $('tr.headertr[data-hid="' + $hid + '"]').hide();
                        }
                    }
                })
            }

            if ($mapconfig && $mapconfig['location_text_selected']) {
                var $num = $(this).data('num');

                var $location_address = locations[$num][6];
                var $location_phone = locations[$num][7];
                var $textbelowmap_new = $mapconfig['location_text_selected'];
                $textbelowmap_new = $textbelowmap_new.replace('[phone]', $location_phone);
                $textbelowmap_new = $textbelowmap_new.replace('[address]', $location_address);
                $('#text_below_map').html($textbelowmap_new).css('color', $mapconfig['location_text_color']);
            }

        }).on('click', '.add_family_member', function () {
            $current_member_num++;
            var $html = '<div class="mpitem">' + $('#patient_form').html() + '</div>';
            $html = $html.replace(/pa\[/g, 'pa_muti[m_' + $current_member_num + '][');
            $html = $($html);
            $('.mpnum', $html).html($current_member_num + ' <a class="removepatient button small">Remove</a>');
            var $hasdate = $('.hasdate', $html);
            var $hasdatepr = $hasdate.parent().parent();
            $hasdatepr.append($hasdate);
            $('.date-dropdowns', $html).remove();
            $('input[type="email"]', $html).removeAttr('required');
            $('input[type="text"],input[type="hidden"],input[type="email"],select', $html).val('');
            $('[name*="pa_address"]', $html).val($('[name="pa[pa_address]"]').val());
            $('[name*="pa_city"]', $html).val($('[name="pa[pa_city]"]').val());
            $('[name*="pa_state"]', $html).val($('[name="pa[pa_state]"]').val());
            $('[name*="pa_zip"]', $html).val($('[name="pa[pa_zip]"]').val());
            $('#multi_patient').append($html);
            try {
                $('.hasdate', $html).dateDropdowns({ displayFormat: 'mdy', minAge: 0, daySuffixValues: ['', '', '', ''] });
            } catch (e) { }
            return false;
        }).on('click', '.removepatient', function () {
            if (confirm('Are you sure remove this member?')) {
                var $pr = $(this).parents('.mpitem');
                $pr.remove();
                $current_member_num--;
            }
            return false;
        }).on('click', '#requisition_form.notpaymentform input[type="submit"]', function () {
            $('.accordion-section.disabled input[required]').removeAttr('required');
            $('.accordion-section.disabled select[required]').removeAttr('required');
            $('input[name="tests[]"]').removeAttr('required');
        }).on('submit', '#requisition_form.notpaymentform', function () {
            var $form = $(this);
            var $btn = $('input[type="submit"]', $(this));
            if ($btn.val() == 'Sending...') return false;
            $btn.data('oldtext', $btn.val());
            $btn.val('Sending...');
            $.ajax({
                data: $form.serialize() + '&tr_act=submit_req_form',
                type: 'post',
                dataType: 'json',
                success: function ($json) {
                    $btn.val($btn.data('oldtext'));
                    if ($json.status == 'ok') {
                        tr_goto_step(10, true);
                        $('#order_section').html($json.html);
                        $('.accordion-section').removeClass('pass').addClass('notclick');
                    } else {
                        if ($json.order_id && $json.order_id > 0) {
                            $('[name="order_id"]', $form).remove();
                            $form.append('<input type="hidden" name="order_id" value="' + $json.order_id + '"/>');
                        }
                        alert('Error. Please try again!');
                    }
                },
                error: function () {
                    alert('Error. Please try again!');
                    $btn.val($btn.data('oldtext'));
                }
            })
            return false;
        }).on('click', 'a.go_product', function () {
            var $id = $(this).attr('id');
            if ($id) {
                $id = $id.substring(2);
                $('input[type="checkbox"].' + $id).prop('checked', true);
            }
        }).on('click', '#btn_patient_no', function () {
            $('#show_confirm').hide().addClass('hide');
            $('#pre_patient').hide();
            $('#patient_form').show();
            $('.patient_section .gotostep').show();
            return false;
        }).on('click', '#btn_patient_yes', function () {
            $('#show_confirm').hide();
            $('#pre_patient').show();
            $('#patient_form').hide();
            $('.patient_section .gotostep').hide();
            return false;
        }).on('click', '#btn_pre_patient_cancel', function () {
            $('#show_confirm').hide().addClass('hide');
            $('#pre_patient').hide();
            $('#patient_form').show();
            $('.patient_section .gotostep').show();
            return false;
        }).on('click', '#btn_pre_patient_next', function () {
            var $this = $(this);
            var $lname = $('#pre_last_name').val();
            var $fname = $('#pre_first_name').val();
            var $dob = $('#pre_pa_dob').val();
            var $phone = $('#pre_pa_phone').val();
            if ($lname.length < 2) {
                alert('Please enter Last Name!');
                return false;
            }
            if ($fname.length < 2) {
                alert('Please enter First Name!');
                return false;
            }
            if ($dob == '') {
                alert('Please enter DOB!');
                return false;
            }
            if ($phone == '') {
                alert('Please enter Your Cell Phone Number!');
                return false;
            }

            $('[name="pa[pa_last]"]').val($lname);
            $('[name="pa[pa_first]"]').val($fname);
            $('[name="pa[pa_dob]"]').val($dob).change();
            $('[name="pa[pa_phone]"]').val($phone);

            var $prpredob = $('#pre_pa_dob').parent();
            if ($prpredob) {
                var $y = $('select.year', $prpredob).val();
                var $m = $('select.month', $prpredob).val();
                var $d = $('select.day', $prpredob).val();

                var $prd = $('[name="pa[pa_dob]"]').parent();
                $('select.year', $prd).val($y)
                $('select.month', $prd).val($m)
                $('select.day', $prd).val($d)
            }

            $('#show_confirm').hide().addClass('hide');
            $this.html('Loading...');

            $.ajax({
                data: { lname: $lname, fname: $fname, dob: $dob, phone: $phone, tr_act: 'ci_search_pre_patient' },
                type: 'post',
                dataType: 'json',
                success: function ($json) {
                    $this.html('Next');
                    if ($json.success) {
                        for (var i in $json.patient) {
                            $('[name="pa[' + i + ']"]').val($json.patient[i]);
                        }
                        if ($json.insurance) {
                            for (var i in $json.insurance) {
                                $('[name="ins1[' + i + ']"]').val($json.insurance[i]);
                            }
                        }
                        if ($json.cards) {
                            for (var i in $json.cards) {
                                if ($json.cards[i]) {
                                    var $carfile = $('.card_file[data-id="' + i + '"]');
                                    $('span.preview', $carfile).remove();
                                    var $img = $('<div class="preview"><img src="' + $json.cards[i] + '" style="max-width:300px"></div>');
                                    $carfile.append($img);
                                }

                            }
                        }
                    }
                    $('#show_confirm').hide();
                    $('#pre_patient').hide();
                    $('#patient_form').show();
                    $('.patient_section .gotostep').show();

                },
                error: function () {
                    $this.html('Next');
                    alert('Error. Please try again!');
                }
            });
            return false;
        }).on('change', '[name="pa[pa_symptomatic]"]', function () {
            var $sd = $('[name="pa[pa_date_of_symptom]"]');
            var $pdr = $sd.parents('.date-dropdowns');
            if ($(this).val() == 'Y') {
                $sd.prop('required', true);
                if ($pdr) $pdr.removeClass('not_required');
            } else {
                $sd.prop('required', false);
                if ($pdr) $pdr.addClass('not_required');
            }
        }).on('keyup change', '#pa_ssn', function () {
            var $vl = $(this).val();
            var $num = $vl.replace(/[^0-9]+/g, '');
            var $num = $num.replace(/(\d{3})(\d{0,6})/, '$1-$2');
            var $num = $num.replace(/(\d{3})-(\d{2})(\d{0,4})/, '$1-$2-$3');
            if ($num.length > 0 && $num.length != 11) {
                $(this).addClass('ferror');
            } else {
                $(this).removeClass('ferror');
            }

            $(this).val($num);
        }).on('click', '.wc_payment_methods .wc_payment_method', function () {
            try {
                setTimeout(function () {
                    var $input = $('[name="payment_method"]:checked');
                    if ($input.val() == 'cod' && $input.parent().find('.payment_method_cod').html().indexOf('Pay at the lab') > -1) {
                        $('p#billing_state_field').hide();
                        $('p#billing_postcode_field').hide();
                        $('#billing_state').val($('[name="pa[pa_state]"]').val());
                        $('#billing_postcode').val($('[name="pa[pa_zip]"]').val());
                        if ($('#billing_state option:selected').length == 0) {
                            $('#billing_state').val('MI');
                        }
                    } else {
                        $('p#billing_state_field').show();
                        $('p#billing_postcode_field').show();
                    }
                }, 200);
            } catch (e) { }
        }).on('click', 'input.hasother', function () {
            var $oid = $(this).data('id');
            if ($(this).is(':checked')) {
                $('.fieldnum_' + $oid).removeClass('hide');
            } else {
                $('.fieldnum_' + $oid).addClass('hide');
            }
        })


    $(document.body).on('checkout_error', function () {
        $('#checkout_alert').append($('.woocommerce-NoticeGroup-checkout'));
        $('#checkout_alert').append($('.woocommerce-NoticeGroup-updateOrderReview'));

    }).on('patient_ordered', function (e, $html) {
        tr_goto_step(10, true);
        $('.accordion-section').removeClass('pass').addClass('notclick');
        $('#order_section').html($html);
    })

    $('.card_file').each(setupcardfiles);

    if ($('.selecttestssecond').length == 0) {
        tr_goto_step(1, false);
    }




    $('[name="ins1[ins_relation]"]').change();
    $('#use_required_ins [required]').attr('old_required', '1');
    if ($('[name="tests[]"]').length == 1 && !$('[name="tests[]"]').attr('checked')) {
        $('[name="tests[]"]').click();
    }



    if ($('#signature_wrap').length > 0) {
        var $swrap = $('#signature_wrap');
        var $input = $('input.signature', $swrap);
        var $cavas = document.querySelector("#signature_canvas");
        if (window.devicePixelRatio) {
            var hidefContext = $cavas.getContext('2d');
            var hidefCanvasWidth = $($cavas).attr('width');
            var hidefCanvasHeight = $($cavas).attr('height');

            $($cavas).attr('width', hidefCanvasWidth * window.devicePixelRatio);
            $($cavas).attr('height', hidefCanvasHeight * window.devicePixelRatio);
            $($cavas).css('width', hidefCanvasWidth);
            $($cavas).css('height', hidefCanvasHeight);
            hidefContext.scale(window.devicePixelRatio, window.devicePixelRatio);
        }

        var signaturePad = new SignaturePad($cavas, {
            onEnd: function () {
                if (!signaturePad.isEmpty()) {
                    $input.val(signaturePad.toDataURL());
                }
                else {
                    $input.val("");
                }
            }
        });

        $(".clear_btn", $swrap).click(function () {
            if (confirm("Are you sure you want to clear signature?") == false) return false;
            signaturePad.clear();
            $input.val("");
        });
    }
});