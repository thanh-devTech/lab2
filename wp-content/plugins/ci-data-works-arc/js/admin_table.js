jQuery(function ($) {
    $('body').on('click', '.report_tbl tr.parent', function (e) {

        if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;
        var $this = $(this);
        var $grid = $this.data('grid');

        if ($this.hasClass('active')) {
            $('tr.child').hide();
            $this.removeClass('active');
        } else {
            $('tr.parent').removeClass('active');
            $('tr.child').hide();

            $this.addClass('active');
            $('tr.child[data-grid="' + $grid + '"]').show();
            $.setup_select2();
        }
        return false;
    })
    $('body select.js_select2').each(function () {
        $(this).select2();
    });
})

var $ = jQuery;
var Tr_Table = function (wrapid, $tableconfig) {
    var $wrap = $('#' + wrapid);
    var $table = null;
    var $sorting = false;
    var $last_number_id = 1;
    var editor_id = 'default_editor';
    var $form_actions_wrap = '';
    var $sort_state = {
        column: null,
        direction: 'desc'
    };

    function create_table() {
        $form_actions_wrap = $('<div class="form_actions"></div>');
        var $table_style = '';
        var $tbl_class = 'edit_tbl wp-list-table widefat striped posts hover_tbl';
        if ($tableconfig['stickyfc'] || typeof $tableconfig['stickyfc'] == 'string') {
            $tbl_class += ' stickyfc';
            $tbl_class += ' stickyfc_' + $tableconfig['stickyfc'];
        }

        if ($tableconfig['column_border']) {
            $tbl_class += ' column_border';
        }

        if ($tableconfig['read_only']) {
            $tbl_class += ' read_only';
        }

        if ($tableconfig['width'] && $tableconfig['width'] > 500) {
            $table_style += 'width:' + $tableconfig['width'] + 'px;';
        }
        var $html = '<table style="' + $table_style + '" data-countcol="' + Object.keys($tableconfig['columns']).length + '" class="' + $tbl_class + '">';
        $html += '<thead class="sticky"><tr>';
        if ($tableconfig['row_header']) {
            try {
                for (var ht in $tableconfig['row_header']) {
                    var $col1 = $tableconfig['row_header'][ht];
                    var $colspan = $col1['colspan'] ? $col1['colspan'] : 1;
                    $html += '<th colspan="' + $colspan + '" class="center ' + ($colspan > 1 ? 'hasgroup' : '') + '">' + ($col1['title'] ? $col1['title'] : '') + '</th>';
                }
                $html += '</tr><tr class="subheader">';
            } catch (e) { console.error(e); }
        }

        for (i in $tableconfig['columns']) {

            var $cellarr = $tableconfig['columns'][i];
            var $col_style = '';
            var $col_class = 'colt_' + $cellarr['type'] + ' ';
            if ($cellarr['width'] && $cellarr['width'] > 10) {
                $col_style += 'width:' + $cellarr['width'] + 'px;';
            }
            if ($cellarr['class']) {
                $col_class += ' ' + $cellarr['class'];
            }
            if ($cellarr['sortable'] && ['text', 'desc', 'select', 'select1', 'checkbox'].includes($cellarr['type'])) {
                $col_class += ' sortable';
            }
            $html += '<th class="' + $col_class + '" data-col="' + i + '" style="' + $col_style + '">';
            $html += '<span>' + ($cellarr['label'] ? $cellarr['label'] : '') + '</span>';
            if ($cellarr['sortable'] && ['text', 'desc', 'select', 'select1', 'checkbox'].includes($cellarr['type'])) {
                $html += '<span class="sort-icon"><i class="dashicons dashicons-sort"></i></span>';
            }
            if ($cellarr['checkall']) {
                $html += '<div><input type="checkbox" class="checkall" title="Check All"/></div>';
            }
            $html += '</th>';
        }
        $html += '</tr></thead>';
        $html += '<tbody></tbody>';
        $html += '</table>';
        $table = $($html);
        $wrap.html('');
        $wrap.append($table);


        if ($tableconfig['new_row']) {
            var $button = $('<a class="button">+</a>');
            $form_actions_wrap.append($button);
            $button.on('click', function () {
                add_row([]);
                $('html, body').animate({
                    scrollTop: $("tr.is_new_row:last-child").offset().top - 80
                }, 1000);
            })
        }

        if ($tableconfig['save_all'] && $tableconfig['save_all'] != 'no') {
            var $button_save = $('<a class="button-primary save">Save</a>');
            $form_actions_wrap.append($button_save);
            $button_save.on('click', function () {
                if ($button_save.html() == 'Saving...') {
                    return false;
                }
                $button_save.data('oldtext', $button_save.html());
                $button_save.html('Saving...');
                $dataform = {};
                $('tr.tbl_row', $wrap).each(function () {
                    var $tr = $(this);
                    var $trid = $tr.data('id');
                    var $bytrid = $tableconfig['new_row'] || !$tableconfig['save_map'];
                    if ($tableconfig['field_full_name']) {
                        $bytrid = false;
                    }

                    if ($bytrid) {
                        $dataform[$trid] = {};
                    }
                    $('input,select,textarea', $tr).each(function () {
                        var $this = $(this);
                        var $ty = $this.prop('tagName') == 'INPUT' && $this.attr('type');
                        var $vl = $this.val();
                        var $name = $this.data('name');
                        if (!$name || typeof $name == 'undefined') return;
                        if ($ty == 'checkbox') {
                            $vl = $this.is(':checked') ? '1' : '0';
                        } else if ($ty == 'radio') {
                            if ($bytrid) {
                                $vl = $dataform[$trid][$name] ? $dataform[$trid][$name] : '';
                            } else {
                                $vl = $dataform[$name] ? $dataform[$name] : '';
                            }
                            if ($this.is(':checked')) {
                                $vl = $this.val();
                            }
                        }
                        if ($bytrid) {
                            $dataform[$trid][$name] = $vl;
                        } else {
                            $dataform[$name] = $vl;
                        }

                    })
                })


                $.ajax({
                    url: $tableconfig['url'],
                    type: 'post',
                    dataType: 'json',
                    data: { trajax: 'save_table', 'fajax': $tableconfig['fajax'], 'table': $tableconfig['table'], save_all: true, 'data': JSON.stringify($dataform) },
                    success: function (json) {
                        $button_save.html($button_save.data('oldtext'));
                        $('tr.is_new_row', $wrap).each(function () {
                            var $row = $(this);
                            var $id = $row.data('id');
                            var $newid = json.IDs[$id];
                            $row.data('id', $newid);
                            $row.removeClass('is_new_row');
                        })
                        if (json['removed_IDs']) {
                            for (var ir in json['removed_IDs']) {
                                $('tr.tbl_row[data-id="' + json['removed_IDs'][ir] + '"]').remove();
                            }
                        }
                    },
                    error: function () {
                        $button_save.html($button_save.data('oldtext'));
                        alert('Error. Please try again!');
                    }
                });
                return false;
            })
        }
        if ($tableconfig['actions']) {
            try {
                for (let ai in $tableconfig['actions']) {
                    let $action_btn = $('<a class="button table_action action_' + ai + '" data-action="' + ai + '" >' + $tableconfig['actions'][ai] + '</a>');
                    $form_actions_wrap.append($action_btn);
                }
            } catch (e) { console.error(e); }
        }

        if ($('a', $form_actions_wrap).length > 0) {
            $wrap.append($form_actions_wrap);
        }

        try {
            if ($tableconfig['sortable']) {
                $('tbody', $table).sortable({
                    cancel: 'tr.actions,input',
                    handle: 'td[data-col="position"]',
                    start: function (e, ui) {
                        $sorting = true;
                        $('tr', $table).removeClass('editing');
                        $('tr.actions', $table).remove();
                        ui.placeholder.height(ui.helper.outerHeight());
                        console.log('start');
                    },
                    stop: function (e, ui) {
                        $sorting = false;
                    },
                    update: function () {
                        let $curpos = 1;
                        $('tr.tbl_row', $table).each(function () {
                            let $tr = $(this);
                            $('input[data-name="position"]', $tr).val($curpos++);
                        })
                    }
                });
                $table.addClass('is_sortable');
            }
        } catch (e) {
            console.error(e);
        }

    }
    function fill_data() {

        if ($tableconfig['data']) {
            try {
                let $count = 0;
                for (i in $tableconfig['data']) {
                    add_row($tableconfig['data'][i]);
                    $count++;
                    if ($tableconfig['pagelimit'] && $count >= $tableconfig['pagelimit']) break;
                }
            } catch (e) { console.log(e) }
        }

    }
    function add_row($row, $only_html) {
        let $tr;
        let $row_class = 'tbl_row';
        if (!$row['ID']) {
            $row['ID'] = 'new' + $last_number_id;
            $last_number_id++;
            $row_class += ' is_new_row';
        }
        if ($row['class']) {
            $row_class += ' ' + $row['class'];
        }

        let $html = '<tr class="' + $row_class + '" data-id="' + ($row['ID'] ? $row['ID'] : '') + '">';
        for (let ckey in $tableconfig['columns']) {
            let $cellarrdf = $tableconfig['columns'][ckey];
            let fields = {};

            if (typeof $cellarrdf['fields'] == 'undefined') {
                fields[ckey] = $cellarrdf;
            } else {
                fields = $cellarrdf['fields'];
            }
            let countfields = Object.keys(fields).length;

            let $col_class = 'row_' + ckey;
            if ($cellarrdf['class']) {
                $col_class += ' ' + $cellarrdf['class'];
            }
            if ($cellarrdf['hideeditor']) {
                $col_class += '  hideeditor';
            }

            $html += '<td data-col="' + ckey + '" class="' + $col_class + '">';

            for (let fslug in fields) {
                let $fname = fslug;
                let $cellarr = fields[fslug];
                let $type = $cellarr['type'];
                let $eid = $row['ID'] + '-' + $fname;

                if ($cellarr['name']) {
                    $fname = $cellarr['name'];
                }
                let $vl = typeof $row[$fname] != "undefined" && $row[$fname] != null ? $row[$fname] : '';
                if ($vl == 'null') $vl = '';
                if ($vl == '' && $cellarr['defaut_col']) {
                    $vl = typeof $row[$cellarr['defaut_col']] != "undefined" && $row[$cellarr['defaut_col']] != null ? $row[$cellarr['defaut_col']] : '';
                }

                if ((!$tableconfig['new_row'] && $tableconfig['save_map']) && $tableconfig['save_all']) {

                    $fname = $row['ID'] + '-' + $fname;
                } else if ($tableconfig['field_full_name']) {
                    $fname = $row['ID'] + '-' + $fname;
                }

                let $attrs = ' data-name="' + $fname + '" ';

                if ($cellarr['readonly']) {
                    let fnro = $row['ID'].indexOf('new') > -1 ? 'data-name' : 'data-name2';
                    $attrs = ' ' + fnro + '="' + $fname + '" ';
                    $attrs += ' readonly="true" ';
                }

                if ($cellarr['allowempty']) {
                    $attrs += ' data-allowempty="true" ';
                }
                if ($cellarr['options_by']) {
                    $attrs += ' data-options_by="' + $cellarr['options_by'] + '" ';
                    if ($row[$cellarr['options_by']]) $cellarr['optionkey'] = $row[$cellarr['options_by']];
                    $attrs += ' data-optionkey="' + $cellarr['optionkey'] + '" ';
                }


                if ($cellarr['group']) {
                    $attrs += ' data-group="' + $row['ID'] + '-' + $cellarr['group'] + '" ';
                }
                else if ($row[fslug + '-group']) {
                    $attrs += ' data-groupsame="' + $row[fslug + '-group'] + '" ';
                }

                if ($row[fslug + '-groupunique']) {
                    $attrs += ' data-groupunique="' + $row[fslug + '-groupunique'] + '" ';
                }

                let $div_class = 'divfield tt_' + $type;
                if ($vl == 'hide_field') {
                    $vl = '';
                    $div_class += ' hide_field';
                    $type = 'hide_field';
                }
                else if ($vl == 'na_field') {
                    $vl = 'N/A';
                    $div_class += ' na_field';
                    $type = 'desc';
                }
                if ($cellarr['showby']) {
                    if ($row[$cellarr['showby']] == 0) {
                        $div_class += ' hide_field';
                    }
                    $div_class += ' showby_' + $cellarr['showby'];
                }
                if ($cellarr['class']) {
                    $div_class += ' ' + $cellarr['class'];
                }

                $html += '<div class="' + $div_class + '">';
                let $enddiveditor = '';
                if (countfields > 1) {
                    $html += '<div class="subfieldtt">' + $cellarr['label'] + '</div>';
                    $html += '<div class="subeditor">';
                    $enddiveditor = '</div>';
                }

                if ($type == 'desc') {
                    $html += $vl;
                }
                else if ($type == 'link') {
                    $html += '<a class="cellink" data-link="' + $cellarr['link'] + '">' + $vl + '</a>';
                }
                else if ($type == 'editor') {
                    $html += '<textarea ' + $attrs + ' id="' + $eid + '">' + $vl + '</textarea>';
                    if (countfields < 2) {
                        $html += '<div class="viewtextdata">' + $vl + '</div>';
                    }

                }
                else if ($type == 'textarea') {
                    $html += '<textarea ' + $attrs + '  id="' + $eid + '">' + $vl + '</textarea>';
                } else if ($type == 'text') {
                    $html += '<input type="text" ' + $attrs + '  value="' + $vl + '">';
                } else if ($type == 'color') {
                    $html += '<input type="color" ' + $attrs + '  value="' + $vl + '">';
                } else if ($type == 'checkbox') {
                    $html += '<input type="checkbox" ' + $attrs + '  value="1" ' + ($vl == '1' ? 'checked' : '') + '/>';
                } else if ($type == 'radio') {
                    let $vlk = $cellarr['value'] ? $cellarr['value'] : '1';
                    $html += '<input type="radio" ' + $attrs + ' name="' + $fname + '" value="' + $vlk + '" ' + ($vl == $vlk ? 'checked' : '') + '/>';
                } else if ($type == 'select' || $type == 'select2m' || $type == 'select2' || $type == 'select1') {
                    if (!$cellarr['options'] && $cellarr['slug']) {
                        $cellarr['options'] = $tableconfig[$cellarr['slug'] + '_options'];
                    }
                    if ($type == 'select1') {
                        let $dis = $cellarr['options'] && $cellarr['options'][$vl] ? $cellarr['options'][$vl] : '';
                        $html += '<div class="select1" data-vl="' + $vl + '">' + $dis + '</div>';
                    }
                    if ($type == 'select2m' || $type == 'select2') {
                        $html += '<ul style="list-style: disc; margin-left:10px;" class="select2-preview" ' + $attrs + ' data-value="' + $vl + '" ' + ($type == 'select2m' ? 'multiple="multiple" data-multiple="multiple"' : '') + '>';
                        $html += build_soptions_ul($cellarr, $row, $vl, $attrs);
                        $html += '</ul>';
                    } else {
                        $html += '<select ' + $attrs + ' data-value="' + $vl + '" ' + ($type == 'select2m' ? 'multiple="multiple"' : '') + '>';
                        $html += build_soptions($cellarr, $row, $vl);
                        $html += '</select>';
                    }

                }
                $html += $enddiveditor;
                $html += '</div>';
            }
            $html += '</td>';
        }
        $html += '</tr>';
        if ($only_html) {
            return $html;
        }
        $tr = $($html);
        $tr.data('rowdata', $row);
        $table.append($tr);

        $table.on('click', 'td', function () {
            var $this = $(this);
            var $ul = $('ul.select2-preview', $this);

            if ($ul.length > 0 && !$this.data('editing')) {
                $this.data('editing', true);

                var $multiple = $ul.data('multiple') == "multiple";
                var $attrs = '';
                var $dataName = $ul.data('name');

                $.each($ul.data(), function (key, value) {
                    if (key !== 'multiple' && key !== 'type' && key !== 'value') {
                        $attrs += ' data-' + key + '="' + value + '"';
                    }
                });

                var $currentValues = [];
                $ul.find('li').each(function () {
                    $currentValues.push(`${$(this).data('value')}`);
                });

                var $allOptionsJson = $ul.find('.all-options').data('options');
                var $allOptions = {};
                try {
                    if (typeof $allOptionsJson === 'string') {
                        $allOptions = JSON.parse($allOptionsJson.replace(/&apos;/g, "'"));
                    } else {
                        $allOptions = $allOptionsJson;
                    }
                } catch (e) {
                    console.error('Error parsing options:', e);
                }

                var $select = $('<select class="editfield" ' + $attrs + '></select>');
                if ($multiple) {
                    $select.attr('multiple', 'multiple');
                }
                if ($dataName) {
                    $select.attr('data-name', $dataName);
                }
                for (var optKey in $allOptions) {
                    var selected = $currentValues.includes(optKey) ? 'selected' : '';
                    $select.append('<option value="' + optKey + '" ' + selected + '>' + $allOptions[optKey] + '</option>');
                }

                $ul.replaceWith($select);

                $select.select2().select2('open').focus()

            }
        });
    }

    function build_soptions($cellarr, $row, $vl) {
        let $shtml = '';

        if ($cellarr['options_by']) {
            console.log($cellarr['optionkey']);
            $cellarr['options'] = $cellarr[$cellarr['optionkey'] + '_options'];
        }

        for (j in $cellarr['options']) {
            let $jkey = j.trim();
            try {
                if ($row['exclude'] && $row['exclude'].includes($jkey)) {
                    continue;
                }
            } catch (e) { }
            let $selected = '';
            try {
                if ($cellarr['type'] == 'select2m') {
                    $selected = $vl.includes($jkey) ? 'selected' : '';
                } else {
                    $selected = $jkey == $vl ? 'selected' : '';
                }
            } catch (e) { }
            $shtml += '<option value="' + $jkey + '" ' + $selected + '>' + $cellarr['options'][j] + '</option>';
        }

        return $shtml;
    }
    function build_soptions_ul($cellarr, $row, $vl, $attrs) {
        let $shtml = '';
        if ($cellarr['options_by']) {
            $cellarr['options'] = $cellarr[$cellarr['optionkey'] + '_options'];
        }

        for (j in $cellarr['options']) {
            let $jkey = j.trim();
            try {
                if ($row['exclude'] && $row['exclude'].includes($jkey)) {
                    delete $cellarr['options'][j];
                    continue;
                }
            } catch (e) { }

            let $selected = '';
            try {
                if ($cellarr['type'] == 'select2m') {
                    $selected = $vl.includes($jkey) ? 'selected' : '';
                } else {
                    $selected = $jkey == $vl ? 'selected' : '';
                }
            } catch (e) { }

            if ($selected) {
                $shtml += '<li data-value="' + $jkey + '">' + $cellarr['options'][j] + '</li>';
            }
        }


        let allOptions = JSON.stringify($cellarr['options']);
        $shtml += '<input type="hidden" class="all-options" ' + $attrs + ' value="' + $vl + '" data-options=\'' + allOptions.replace(/'/g, '&apos;') + '\'/>';


        if (!$shtml.includes('<li')) {
            $shtml += '<li class="placeholder" style="color: #999;">Select...</li>';
        }

        return $shtml;
    }

    function trigger() {

        $('body').on('click', function (e) {
            if ($(this).data('targetclosid')) {
                if (e.target.tagName != 'INPUT') {
                    let $obj = $(this).data('targetclosid');
                    $obj.removeClass('activeedit');
                    $(this).data('targetclosid', false);
                    $wrap.removeClass('editingp');
                }

            }
        })

        $wrap.on('click', '.tt_editor', function (e) {
            if (e.target.tagName == 'A') return;
            let $tt = $('.subfieldtt', $(this)).html();
            let $textarea = $('textarea', $(this));
            show_editor($textarea, $tt);
        }).on('click', '.hideeditor .divfield', function (e) {
            if (e.target.tagName != 'DIV' || $wrap.hasClass('editingp')) return;
            if ($(this).hasClass('activeedit')) {
                $(this).removeClass('activeedit');
                $wrap.removeClass('editingp');
            } else {
                $(this).addClass('activeedit');
                $('body').data('targetclosid', $(this));
                $wrap.addClass('editingp');
            }
            return false;
        }).on('click', '.select1', function () {
            let $se = $(this).parent().find('select');
            $se.addClass('showedit');
            $se.prop('size', $('option', $se).length);
            setTimeout(function () {
                let $tt = $('table.edit_tbl').data('toremove');

                if ($tt) {
                    clearTimeout($tt);
                }
            }, 100);
        }).on('click update', '.tt_select1 select', function () {
            $(this).removeClass('showedit');
            let $vl = $('option:selected', $(this)).html();
            $(this).parent().find('.select1').html($vl).attr('data-vl', $(this).val());
        }).on('click', 'table.edit_tbl', function () {
            let $tt = $(this).data('toremove');
            if ($tt) {
                clearTimeout($tt);
            }

            $tt = setTimeout(function () {
                $('.tt_select1 select').removeClass('showedit');
            }, 200);
            $(this).data('toremove', $tt);
        }).on('change', 'select[data-groupsame]', function () {
            let $group = $(this).data('groupsame');
            let $name = $(this).data('name');
            let $vl = $(this).val();

            $('select[data-groupsame="' + $group + '"]').each(function () {

                if ($(this).data('name') != $name && !$(this).parent().hasClass('hide_field')) {
                    $(this).val($vl).trigger('update');
                }
            })

        }).on('change needupdate', 'select[data-groupunique]', function () {
            let $group = $(this).data('groupunique');
            let $name = $(this).data('name');
            let $selected = [];

            $('select[data-groupunique="' + $group + '"] option:selected').each(function () {
                $selected.push($(this).attr('value'));
            })

            $('select[data-groupunique="' + $group + '"] option:not(:selected)').each(function () {

                $(this).prop('disabled', $selected.includes($(this).attr('value')));

            })
            $('select[data-groupunique="' + $group + '"]').select2();

        }).on('mouseover', '.tt_select2m:not(.needupdated)', function () {
            // console.log('abc');
            $(this).addClass('needupdated');
            $('select', $(this)).trigger('needupdate');

        }).on('click', '.tt_checkbox input[type="checkbox"]', function () {
            var $tr = $(this).parents('tr');
            var $name = $(this).data('name');
            var $group = $(this).data('group');
            if ($group) {
                $('.tt_checkbox input[type="checkbox"][data-group="' + $group + '"]').each(function () {
                    if ($(this).data('name') != $name) {
                        $(this).prop('checked', false);
                    }
                })
            }
            //check showby
            if ($('div.showby_' + $name, $tr).length > 0) {
                if ($(this).is(':checked')) {
                    $('div.showby_' + $name, $tr).removeClass('hide_field');
                } else {
                    $('div.showby_' + $name, $tr).addClass('hide_field');
                }
            }
        }).on('click', '.tt_radio input[data-allowempty="true"]', function () {
            var $this = $(this);
            if ($this.is(':checked')) {
                $this.data('tout', setTimeout(function () {
                    $this.prop('checked', false);
                }, 200));
            }
        }).on('change', '.tt_radio input[data-allowempty="true"]', function () {
            clearTimeout($(this).data('tout'));
        }).on('mouseover', 'tr.tbl_row', function () {
            var $tr = $(this);
            var $has_actions = false;

            if ($sorting || $tableconfig['read_only']) return;

            $('tr', $table).removeClass('editing');
            $('tr.actions', $table).remove();
            $tr.addClass('editing');
            var $tra = $('<tr class="actions"><td colspan="' + Object.keys($tableconfig['columns']).length + '" align="right"></td></tr>');

            if (!$tableconfig['save_all'] && !$tableconfig['disable_save']) {
                $has_actions = true;
                var $button_save = $('<a class="button-primary save">save</a>');
                $button_save.on('click', function () {
                    if ($button_save.html() == 'saving...') {
                        return false;
                    }
                    $button_save.data('oldtext', $button_save.html());
                    $button_save.html('saving...');
                    $dataform = { 'ID': $tr.data('id') };
                    $('input,select,textarea', $tr).each(function () {
                        var $this = $(this);
                        var $ty = $this.prop('tagName') == 'INPUT' && $this.attr('type');
                        var $vl = $this.val();
                        var $name = $this.data('name');
                        if (!$name || typeof $name == 'undefined') return;
                        if ($ty == 'checkbox') {
                            $vl = $this.is(':checked') ? '1' : '0';
                        } else if ($ty == 'radio') {
                            if ($dataform[$name]) {
                                $vl = $dataform[$name];
                            }
                            if ($this.is(':checked')) {
                                $vl = $this.val();
                            }
                        }
                        $dataform[$name] = $vl;
                    })


                    $.ajax({
                        url: $tableconfig['url'],
                        type: 'post',
                        dataType: 'json',
                        data: { trajax: 'save_table', 'table': $tableconfig['table'], save_all: false, 'data': $dataform },
                        success: function (json) {
                            $tr.data('id', json['ID']);
                            $button_save.html($button_save.data('oldtext'));
                        },
                        error: function () {
                            $button_save.html($button_save.data('oldtext'));
                            alert('Error. Please try again!');
                        }
                    });
                    return false;
                })
                $('td', $tra).append($button_save);
            }

            if ($tableconfig['new_row'] || $tableconfig['removerow']) {
                $has_actions = true;
                var $button_rm = $('<a class="button remove">remove</a>');
                $('td', $tra).append($button_rm);
                $button_rm.on('click', function () {
                    $id = $tr.data('id');
                    if (!confirm('are you sure remove it?')) {
                        return false;
                    }
                    $button_rm.html('removing...');
                    if ($id) {
                        $.ajax({
                            url: $tableconfig['url'],
                            type: 'post',
                            dataType: 'json',
                            data: { trajax: 'remove_table_row', 'table': $tableconfig['table'], 'ID': $id },
                            success: function (json) {
                                $tr.remove();
                                $tra.remove();
                            },
                            error: function () {
                                $button_rm.html('remove');
                                alert('Error. Please try again!');
                            }
                        });
                    } else {
                        $tr.remove();
                        $tra.remove();
                    }
                });
            }

            if ($has_actions) {
                $tra.insertAfter($tr);
            }
        }).on('change', '.ctrloptions select', function () {
            var $tr = $(this).parents('tr.tbl_row');
            var $vl = $(this).val();
            var $name = $(this).data('name');
            $('select', $tr).each(function () {
                var $by = $(this).data('options_by');
                if ($by && $by == $name) {
                    $(this).data('optionkey', $vl);
                    $(this).trigger('change_options');
                }
            })
        }).on('change_options', 'select', function () {
            var $tr = $(this).parents('tr.tbl_row');
            var ckey = $(this).data('name');
            var $cellarr = $tableconfig['columns'][ckey];
            $cellarr.optionkey = $(this).data('optionkey');
            var $row = $tr.data('rowdata');
            var $vl = '';
            var $html = build_soptions($cellarr, $row, $vl);
            $(this).html($html).change();
        }).on('click', '.edit_tbl input.checkall', function () {
            var $cl = $(this).parents('th.colt_checkbox').data('col');
            var $ic = $(this).is(':checked');
            $('.edit_tbl tr.tbl_row td[data-col="' + $cl + '"] input[type="checkbox"]').prop('checked', $ic);
        }).on('click', '.table_action', function () {
            let $act = $(this).data('action');
            let $url2 = $tableconfig['url'] + '&trajax=' + $act;
            if ($act.indexOf('import') > -1 || $act.indexOf('frame') > -1 || $act.indexOf('tb_') > -1) {
                if ($act.indexOf('frame') > -1) {
                    $url2 += '&TB_iframe=true';
                }
                tb_show($(this).html(), $url2);
            }
            else {
                location.href = $url2;
            }
            return false;
        }).on('click', '.tb_popup', function () {
            let $url2 = $(this).attr('href');
            tb_show($(this).html(), $url2);
            return false;
        }).on('click', 'a.cellink', function () {
            var $tr = $(this).parents('tr.tbl_row');
            var $id = $tr.data('id');
            var url = $tableconfig['url'] + '&trajax=linkact&link=' + $(this).data('link') + '&id=' + $id;
            window.open(url, '_blank');
        }).on('click', 'th.sortable', function (e) {
            if ($(e.target).hasClass('checkall') || $(e.target).is('input[type="checkbox"]')) {
                return;
            }

            var $th = $(this);
            var column = $th.data('col');

            var direction = 'desc';
            if ($sort_state.column === column) {
                direction = $sort_state.direction === 'asc' ? 'desc' : 'asc';
            }

            $sort_state.column = column;
            $sort_state.direction = direction;

            $('th.sortable', $table).removeClass('sort-asc sort-desc');
            $th.addClass('sort-' + direction);

            sort_table(column, direction);
        });
    }

    function show_popup($title, $content, $width = '') {
        remove_popup();
        var $popup = $('<div class="trpopup"><div class="ptitle">' + $title + '</div><div class="pcontent">' + $content + '</div></div>');
        $('body').append($popup);
        $('body').append('<div class="troverlay"></div>');
        $width = $width > 0 ? $width : 800;
        $popup.css('width', $width + 'px');
        $popup.css('margin-left', (0 - ($width / 2)) + 'px');
        return $popup;
    }
    function remove_popup() {
        $('.trpopup').remove();
        $('.troverlay').remove();
    }
    function show_editor($textarea, $tt) {

        wp.editor.remove(editor_id);
        show_popup($tt, '<textarea id="default_editor"></textarea><br><div class="frow center"><a class="button-primary" id="done_editor_btn">Update</a> <a class="button-primary" id="close_editor_btn">Cancel</a></div>');

        $('#default_editor').val($textarea.val());
        $('#done_editor_btn').click(function () {
            var $pr = $textarea.parent();
            var $content = tinymce.editors.default_editor.getContent();
            $('div.viewtextdata', $pr).html($content);
            $textarea.val($content);
            remove_popup();
        })
        $('#close_editor_btn').click(function () {
            remove_popup();
        })

        wp.editor.initialize(
            editor_id,
            {
                tinymce: {
                    wpautop: true,
                    plugins: 'charmap colorpicker compat3x directionality fullscreen hr image lists media paste tabfocus textcolor wordpress wpautoresize wpdialogs wpeditimage wpemoji wpgallery wplink wptextpattern wpview',
                    toolbar1: 'bold italic underline strikethrough | bullist numlist | blockquote hr wp_more | alignleft aligncenter alignright | link unlink | fullscreen | wp_adv',
                    toolbar2: 'formatselect alignjustify forecolor | pastetext removeformat charmap | outdent indent | undo redo | wp_help'
                },
                quicktags: true,
                mediaButtons: true,
            }
        );
    }
    function sort_table(column, direction) {
        var $rows = $('tbody tr.tbl_row', $table).get();
        var $cellarr = $tableconfig['columns'][column];

        $rows.sort(function (a, b) {
            var aValue, bValue;
            if ($cellarr['type'] === 'checkbox') {
                var aCheckbox = $(a).find('td[data-col="' + column + '"] input[type="checkbox"]');
                var bCheckbox = $(b).find('td[data-col="' + column + '"] input[type="checkbox"]');

                aValue = aCheckbox.is(':checked') ? 1 : 0;
                bValue = bCheckbox.is(':checked') ? 1 : 0;
            } else {
                aValue = $(a).find('td[data-col="' + column + '"]').text().trim().toLowerCase();
                bValue = $(b).find('td[data-col="' + column + '"]').text().trim().toLowerCase();
                if (!isNaN(aValue) && !isNaN(bValue) && aValue !== '' && bValue !== '') {
                    aValue = parseFloat(aValue);
                    bValue = parseFloat(bValue);
                }
            }

            if (direction === 'asc') {
                return aValue > bValue ? 1 : aValue < bValue ? -1 : 0;
            } else {
                return aValue < bValue ? 1 : aValue > bValue ? -1 : 0;
            }
        });

        $.each($rows, function (index, row) {
            $('tbody', $table).append(row);
        });
    }



    $wrap.append('<div class="loading"></div>').addClass('wraptbl');
    create_table();
    fill_data();
    trigger();

}
