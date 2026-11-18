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
            $('tr.child[data-grid="'+$grid+'"]').show();
            $.setup_select2();
        }
        return false;
    })
})

var $ = jQuery;
var Tr_Table = function (wrapid, $tableconfig) {
    var $wrap = $('#' + wrapid);
    var $table = null;
    var $sorting = false;
    var $last_number_id = 1;
    var editor_id = 'default_editor';
    var $form_actions_wrap = '';

    function create_table() {
        $form_actions_wrap = $('<div class="form_actions"></div>');
        var $table_style = '';
        var $tbl_class = 'edit_tbl wp-list-table widefat striped posts hover_tbl';
        if ($tableconfig['stickyfc'] || typeof $tableconfig['stickyfc'] == 'string') {
            $tbl_class += ' stickyfc';
            $tbl_class += ' stickyfc_'+$tableconfig['stickyfc'];
        }
        if($tableconfig['column_border']){
            $tbl_class += ' column_border';
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
            } catch (e) { console.error(e);}
        }

        for (i in $tableconfig['columns']) {

            var $cellarr = $tableconfig['columns'][i];
            var $col_style = '';
            var $col_class = '';
            if ($cellarr['width'] && $cellarr['width'] > 10) {
                $col_style += 'width:' + $cellarr['width'] + 'px;';
            }
            if ($cellarr['class']) {
                $col_class += ' ' + $cellarr['class'];
            }
            $html += '<th class="' + $col_class + '" data-col="' + i + '" style="' + $col_style + '"><span>' + ($cellarr['label'] ? $cellarr['label'] : '') + '</span></th>';
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

        if ($tableconfig['save_all']) {
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
                    if ($tableconfig['new_row']) {
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
                            if ($tableconfig['new_row']) {
                                $vl = $dataform[$trid][$name] ? $dataform[$trid][$name] : '';
                            } else {
                                $vl = $dataform[$name] ? $dataform[$name] : '';
                            }
                            if ($this.is(':checked')) {
                                $vl = $this.val();
                            }
                        }
                        if ($tableconfig['new_row']) {
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
                    data: { trajax: 'save_table','table': $tableconfig['table'], save_all: true, 'data': JSON.stringify($dataform) },
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
                for (var ai in $tableconfig['actions']) {
                    var $action_btn = $('<a class="button action_' + ai + '" data-action="' + ai + '">' + $tableconfig['actions'][ai] + '</a>');
                    $form_actions_wrap.append($action_btn);
                    $action_btn.click(function () {
                        var $act = $(this).data('action');
                        var $url = location.href + '&trajax=' + $act;
                        if ($act.indexOf('import') > -1) {
                            if ($act.indexOf('frame') > -1) {
                                $url += '&TB_iframe=true';
                            }
                            tb_show('', $url);
                        }
                        else {
                            location.href = $url;
                        }
                        return false;
                    })
                }
            } catch (e) {console.error(e); }
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
                        var $curpos = 1;
                        $('tr.tbl_row', $table).each(function () {
                            var $tr = $(this);
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
                var $count = 0;
                for (i in $tableconfig['data']) {
                    add_row($tableconfig['data'][i]);
                    $count++;
                    if ($tableconfig['pagelimit'] && $count >= $tableconfig['pagelimit']) break;
                }
            } catch (e) {console.log(e) }
        }

    }
    function add_row($row, $only_html) {
        var $tr;
        var $row_class = 'tbl_row';
        if (!$row['ID']) {
            $row['ID'] = 'new' + $last_number_id;
            $last_number_id++;
            $row_class += ' is_new_row';
        }
        if ($row['class']) {
            $row_class += ' ' + $row['class'];
        }
        var $html = '<tr class="' + $row_class + '" data-id="' + ($row['ID'] ? $row['ID'] : '') + '">';
        for (var ckey in $tableconfig['columns']) {
            var $cellarrdf = $tableconfig['columns'][ckey];
            var fields = {};
            
            if (typeof $cellarrdf['fields'] == 'undefined') {
                fields[ckey] = $cellarrdf;
            } else {
                fields = $cellarrdf['fields'];
            }
            var countfields = Object.keys(fields).length;
            
            var $col_class = 'row_'+ckey;
            if ($cellarrdf['class']) {
                $col_class += ' ' + $cellarrdf['class'];
            }
            
            $html += '<td data-col="' + ckey + '" class="' + $col_class + '">';

            for (var fslug in fields) {
                var $fname  = fslug;
                var $cellarr = fields[fslug];
                var $type   = $cellarr['type'];
                var $eid    = $row['ID'] + '-' + $fname;

                if ($cellarr['name']) {
                    $fname = $cellarr['name'];
                }
                var $vl = $row[$fname] ? $row[$fname] : '';

                if (!$tableconfig['new_row'] && $tableconfig['save_all']) {

                    $fname = $row['ID'] + '-' + $fname;
                }

                var $attrs = ' data-name="' + $fname + '" ';
                if ($cellarr['allowempty']) {
                    $attrs += ' data-allowempty="true" ';
                }
                if ($cellarr['options_by']) {
                    $attrs += ' data-options_by="'+$cellarr['options_by']+'" ';
                   if( $row[$cellarr['options_by']])$cellarr['optionkey']= $row[$cellarr['options_by']];
                    $attrs += ' data-optionkey="'+$cellarr['optionkey']+'" ';
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
                var $div_class = 'divfield tt_'+$type;
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
                if($cellarr['showby']){
                    if($row[$cellarr['showby']]==0)
                    {
                        $div_class += ' hide_field';
                    }
                    $div_class+=' showby_'+$cellarr['showby'];
                }
                if($cellarr['class']){
                    $div_class+= ' '+$cellarr['class'];
                }

                $html +='<div class="' + $div_class+'">';
                if(countfields>1)
                {
                    $html+='<div class="subfieldtt">'+$cellarr['label']+'</div>';
                }

                if ($type == 'desc') {
                    $html += $vl;
                }
                else if ($type == 'editor') {
                    $html += '<textarea ' + $attrs + ' id="' + $eid + '">' + $vl + '</textarea>';
                    $html += '<div class="viewtextdata">' + $vl + '</div>';
                }
                else if ($type == 'textarea') {
                    $html += '<textarea ' + $attrs + '  id="' + $eid + '">' + $vl + '</textarea>';
                } else if ($type == 'text') {
                    $html += '<input type="text" ' + $attrs + '  value="' + $vl + '">';
                } else if ($type == 'checkbox') {
                    $html += '<input type="checkbox" ' + $attrs + '  value="1" ' + ($vl == '1' ? 'checked' : '') + '/>';
                } else if ($type == 'radio') {
                    var $vlk = $cellarr['value'] ? $cellarr['value'] : '1';
                    $html += '<input type="radio" ' + $attrs + ' name="' + $fname + '" value="' + $vlk + '" ' + ($vl == $vlk ? 'checked' : '') + '/>';
                } else if ($type == 'select' || $type == 'select2m' || $type == 'select2' || $type == 'select1') {
                    if (!$cellarr['options'] && $cellarr['slug']) {
                        $cellarr['options'] = $tableconfig[$cellarr['slug'] + '_options'];
                    }
                    if ($type == 'select1') {
                        var $dis = $cellarr['options'] && $cellarr['options'][$vl] ? $cellarr['options'][$vl] : '';
                        $html += '<div class="select1" data-vl="' + $vl + '">' + $dis + '</div>';
                    }
                    $html += '<select ' + $attrs + ' data-value="' + $vl + '" ' + ($type == 'select2m' ? 'multiple="multiple"' : '') + '>';
                    $html += build_soptions($cellarr, $row, $vl);
                    $html += '</select>';
                }
                $html +='</div>';
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
        $('.tt_select2m:not(.hide_field) select,.tt_select2:not(.hide_field) select', $tr).select2();

    }

    function build_soptions($cellarr, $row, $vl) {
        var $shtml = '';

        if($cellarr['options_by']){
            console.log($cellarr['optionkey']);
            $cellarr['options'] = $cellarr[$cellarr['optionkey']+'_options'];
        }

        for (j in $cellarr['options']) {
            var $jkey = j.trim();
            try {
                if ($row['exclude'] && $row['exclude'].includes($jkey)) {
                    //console.log('exclude: ' + j);
                    continue;
                }
            } catch (e) { }
            var $selected = '';
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

    function trigger() {

        $('body').on('click', '.tt_editor', function (e) {
            if (e.target.tagName == 'A') return;
            var $textarea = $('textarea', $(this));
            show_editor($textarea);
        }).on('click', '.select1', function () {
            var $se = $(this).parent().find('select');
            $se.addClass('showedit');
            $se.prop('size', $('option', $se).length);
            setTimeout(function () {
                var $tt = $('table.edit_tbl').data('toremove');

                if ($tt) {
                    clearTimeout($tt);
                }
            }, 100);
        }).on('click update', '.tt_select1 select', function () {
            $(this).removeClass('showedit');
            var $vl = $('option:selected', $(this)).html();
            $(this).parent().find('.select1').html($vl).attr('data-vl', $(this).val());
        }).on('click', 'table.edit_tbl', function () {
            var $tt = $(this).data('toremove');
            if ($tt) {
                clearTimeout($tt);
            }

            $tt = setTimeout(function () {
                $('.tt_select1 select').removeClass('showedit');
            }, 200);
            $(this).data('toremove', $tt);
        }).on('change', 'select[data-groupsame]', function () {
            var $group = $(this).data('groupsame');
            var $name = $(this).data('name');
            var $vl = $(this).val();

            $('select[data-groupsame="' + $group + '"]').each(function () {

                if ($(this).data('name') != $name && !$(this).parent().hasClass('hide_field')) {
                    $(this).val($vl).trigger('update');
                }
            })

        }).on('change needupdate', 'select[data-groupunique]', function () {
            var $group = $(this).data('groupunique');
            var $name = $(this).data('name');
            var $selected = [];

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
            $('select',$(this)).trigger('needupdate');

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
            if($('div.showby_'+$name,$tr).length>0)
            {
                if($(this).is(':checked')){
                    $('div.showby_'+$name,$tr).removeClass('hide_field');
                }else{
                    $('div.showby_'+$name,$tr).addClass('hide_field');
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

            if ($sorting) return;

            $('tr', $table).removeClass('editing');
            $('tr.actions', $table).remove();
            $tr.addClass('editing');
            var $tra = $('<tr class="actions"><td colspan="' + Object.keys($tableconfig['columns']).length + '" align="right"></td></tr>');

            if (!$tableconfig['save_all']) {
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
                        data: { trajax: 'save_table','table': $tableconfig['table'], save_all: false, 'data': $dataform },
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

            if ($tableconfig['new_row']) {
                $has_actions = true;
                var $button_rm = $('<a class="button remove">remove</a>');
                $('td', $tra).append($button_rm);
                $button_rm.on('click', function () {
                    $id = $tr.data('id');
                    if (!confirm('are you sure remove it?')) {
                        return false;
                    }
                    $button_rm.html('removing...');
                    if ($id > 0) {
                        $.ajax({
                            url: $tableconfig['url'],
                            type: 'post',
                            dataType: 'json',
                            data: { trajax: 'remove_table_row','table': $tableconfig['table'], 'ID': $id },
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
        }).on('change','.ctrloptions select',function(){
            var $tr = $(this).parents('tr.tbl_row');
            var $vl = $(this).val();
            var $name = $(this).data('name');
            $('select',$tr).each(function(){
                var $by = $(this).data('options_by');
                if($by && $by == $name)
                {
                    $(this).data('optionkey', $vl);
                    $(this).trigger('change_options');
                }
            })
        })
        .on('change_options','select',function(){
            var $tr = $(this).parents('tr.tbl_row');
            var ckey= $(this).data('name');
            var $cellarr = $tableconfig['columns'][ckey];
            $cellarr.optionkey =  $(this).data('optionkey');
            var $row = $tr.data('rowdata');
            var $vl = '';
            var $html = build_soptions($cellarr,$row,$vl);
            $(this).html($html).change();
        })
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
    function show_editor($textarea) {

        wp.editor.remove(editor_id);
        show_popup('', '<textarea id="default_editor"></textarea><br><div class="frow center"><a class="button-primary" id="done_editor_btn">Done</a></div>');

        $('#default_editor').val($textarea.val());
        $('#done_editor_btn').click(function () {
            var $pr = $textarea.parent();
            var $content = tinymce.editors.default_editor.getContent();
            $('div.viewtextdata', $pr).html($content);
            $textarea.val($content);
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



    $wrap.append('<div class="loading"></div>').addClass('wraptbl');
    create_table();
    fill_data();
    trigger();

}