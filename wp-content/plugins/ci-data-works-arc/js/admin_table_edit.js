jQuery(function ($) {
    $.fn.change_edited = function () {
        var $td = $(this).parents('td.editable');
        var $tr = $td.parent();
        var $prw = $td;
        if ($('span.value', $td).length > 0) {
            $prw = $('span.value', $td);
        }
        var $type = $td.data('type');
        var $field = $td.data('k');
        var $required = $td.data('required');
        $value = $(this).val();
        $id = $tr.data('id');
        if ($required && $value == '') {
            alert('This Field is required');
            return false;
        }
        $prw.append('<div class="sloading"></div>');
        $.ajax({
            type: 'POST',
            dataType: 'json',
            data: {
                trajax: 'ci_save_table_cell',
                id: $id,
                value: $value,
                field: $field
            },
            success: function (rs) {
                $td.data('value', $value);
                if ($type == 'select') {
                    $value = $options[$field][$value];
                }
                $prw.html($value);

                $td.removeClass('editing');
            }
        })
    }
    $('body').on('click', 'td.editable', function () {
        if ($(this).hasClass('editing')) {
            return;
        }
        $('td.editable.editing input').trigger($.Event('keyup', { keyCode: 27 }));
        $('td.editable.editing select').trigger('closeedit');

        var $pr = $(this);
        if ($('span', $(this)).length > 0) {
            $pr = $('span', $(this));
        }
        var $vl = $pr.html();
        var $type = $(this).data('type');
        var $field = $(this).data('k');
        $(this).data('value', $vl).addClass('editing');
        if ($type == 'select') {
            var $html = '<select>';
            var $value = $(this).data('value');
            for (i in $options[$field]) {
                var selected = i == $value ? 'selected' : '';
                $html += '<option value="' + i + '"' + selected + '>' + $options[$field][i] + '</option>';
            }
            $html += '</select>';
            $pr.html($html);
        } else {
            $pr.html('<input type="text" value="' + $vl + '" title="Enter to save"/>');
            $('input', $pr).focus().select();
        }
        return false;

    }).on('keyup', '.editable input', function (e) {
        var code = e.keyCode || e.which;

        if (code == 27) {
            var $td = $(this).parents('td.editable');
            var $prw = $td;
            if ($('span.value', $td).length > 0) {
                $prw = $('span.value', $td);
            }
            $td.removeClass('editing');
            $prw.html($td.data('value'));
        }
        else if (code == 13) {
            $(this).change_edited();
        }
    }).on('change', '.editable select', function (e) {
        $(this).change_edited();
    }).on('closeedit', '.editable select', function (e) {
        var $td = $(this).parents('td.editable');
        var $prw = $td;
        if ($('span.value', $td).length > 0) {
            $prw = $('span.value', $td);
        }
        $td.removeClass('editing');
        $prw.html($td.data('value'));
    }).on('click', 'table', function (e) {

        if (e.target.tagName == 'INPUT' || e.target.tagName == 'SELECT') return;
        $('td.editable.editing input').trigger($.Event('keyup', { keyCode: 27 }));
        $('td.editable.editing select').trigger('closeedit');
    })
})