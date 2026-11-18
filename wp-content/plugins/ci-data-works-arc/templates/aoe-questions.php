<?php

/**
 * aoe-questions.php
 * Renders AOE (Additional Order Entry) questions.
 */

global $wpdb;

$headers = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM ci_test_aoe_headers
         WHERE tag_btype = %s
           AND tag_status = 1
         ORDER BY tag_order ASC",
        $reqtype
    )
);

if (count($headers) === 0) {
    return;
}

$group_selected = [];
$groupsed = [];

$TW = [
    'lbl'      => 'block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1 req-lbl',
    'input'    => 'w-full box-sizing:border-box rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition',
    'select'   => 'w-full box-sizing:border-box rounded-lg border border-slate-200 bg-white px-3 py-2 pr-8 text-sm text-slate-800 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition appearance-none',
    'textarea' => 'w-full box-sizing:border-box rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition resize-y min-h-[80px]',
    'chklbl'   => 'inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer select-none',
    'checkbox' => 'h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer flex-shrink-0',
    'radio'    => 'h-4 w-4 border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer flex-shrink-0',
    'fieldset' => 'rounded-lg border border-slate-200 p-3',
    'legend'   => 'px-1 text-xs font-semibold text-slate-500',
    'addbtn'   => 'mt-2 inline-flex items-center gap-1.5 rounded-md border border-dashed border-slate-300 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-700 hover:border-slate-400 transition cursor-pointer',
];

$chevron = '<div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg></div>';

foreach ($headers as $header) {
    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM ci_test_aoe
		  WHERE ta_btype     = %s
		    AND ta_header_id = %d
		  ORDER BY ta_order ASC, ta_ID ASC",
        $reqtype,
        $header->tag_ID
    ));

    if (count($questions) === 0) continue;

    $count_q_showed = 0;
    $fieldnum       = 0;
    $group_adding   = '';
    $fields_html    = '';

    foreach ($questions as $question) {
        $value  = trpdd_get_batch_meta(0, $reqtype, $question->ta_slug);
        $name   = 'batmeta[' . $question->ta_slug . ']';
        $atts   = $question->ta_required ? ' data-required="1"' : '';
        $required = $question->ta_required ? ' <span class="req">*</span>' : '';
        $slug   = esc_attr($question->ta_slug);
        $panels = esc_attr($question->ta_group_ids ?? '');
        $qtype  = $question->ta_question_type;

        /* ── group visibility ──────────────────────────────── */
        $hide_class = '';
        if (!empty($question->ta_group_ids)) {
            $gids  = explode(',', $question->ta_group_ids);
            $found = false;
            foreach ($gids as $gid) {
                if (in_array($gid, $group_selected)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $hide_class = ' hidden aoe-hide-group';
            } else {
                $count_q_showed++;
            }
        }

        $col_num = 3;
        if (in_array($qtype, ['texta', 'cond2', 'cond4', 'cond8', 'mtext', 'mcheckbox'])) $col_num = 1;
        if ($question->ta_cols > 0) $col_num = $question->ta_cols;

        // col_num semantics: 1=full, 2=half, 3=third
        $colspan_map = [1 => 'col-span-3', 2 => 'col-span-2', 3 => 'col-span-1'];
        $colspan     = $colspan_map[$col_num] ?? 'col-span-1';

        $fhtml = '';

        switch ($qtype) {

            /* select ───────────────────────────────────────── */
            case 'select':
                $opts = strpos($question->ta_answers, "\r") > 0
                    ? explode("\r", $question->ta_answers)
                    : explode('|', $question->ta_answers);

                $fhtml .= '<label class="' . $TW['lbl'] . '">' . esc_html($question->ta_question) . $required . '</label>';
                $fhtml .= '<div class="relative">';
                $fhtml .= '<select name="' . $name . '" class="' . $TW['select'] . '"' . $atts . '>';
                $fhtml .= '<option value="">— Select —</option>';
                foreach ($opts as $vl) {
                    $vl = trim($vl);
                    [$k, $txt] = strpos($vl, ',') > 0 ? explode(',', $vl, 2) : [$vl, $vl];
                    $k = trim($k);
                    $fhtml .= '<option value="' . esc_attr($k) . '"' . selected($value, $k, false) . '>' . esc_html($txt) . '</option>';
                }
                $fhtml .= '</select>' . '</div>';
                break;

            /* mselect ──────────────────────────────────────── */
            case 'mselect':
                $opts = strpos($question->ta_answers, "\r") > 0
                    ? explode("\r", $question->ta_answers)
                    : explode(',', $question->ta_answers);

                $adding_slug  = $question->ta_slug . '_data';
                $adding_name  = 'batmeta[' . $adding_slug . ']';
                $adding_value = $question->ta_start_end_date ? trpdd_get_batch_meta(0, $reqtype, $adding_slug) : [];
                $values       = explode('|', $value);

                $fhtml .= '<label class="' . $TW['lbl'] . '">' . esc_html($question->ta_question) . $required . '</label>';
                $fhtml .= '<select name="' . $name . '[]" data-slug="' . $slug . '" multiple '
                    . 'class="js_select2 ' . ($question->ta_start_end_date ? 'has_adding_mselect ' : '') . $TW['select'] . ' min-h-[80px]"' . $atts . '>';
                foreach ($opts as $txt) {
                    $txt    = trim($txt);
                    $fhtml .= '<option value="' . esc_attr($txt) . '"' . (in_array($txt, $values) ? ' selected' : '') . '>' . esc_html($txt) . '</option>';

                    if ($question->ta_start_end_date) {
                        $group_adding .= '<div class="adding_mselect mselect_' . $slug . ' hidden rounded-lg border border-slate-200 bg-slate-50 p-3" data-value="' . esc_attr($txt) . '">';
                        $group_adding .= '<p class="mb-2 text-xs font-semibold text-slate-600">' . esc_html($txt) . '</p>';
                        $group_adding .= '<div class="grid grid-cols-2 gap-3">';
                        $group_adding .= '<div><label class="' . $TW['lbl'] . '">Start</label><input type="text" class="hasdate ' . $TW['input'] . '" name="' . $adding_name . '[' . esc_attr($txt) . '][0]" value="' . esc_attr($adding_value[$txt][0] ?? '') . '"/></div>';
                        $group_adding .= '<div><label class="' . $TW['lbl'] . '">End</label><input type="text" class="hasdate ' . $TW['input'] . '" name="' . $adding_name . '[' . esc_attr($txt) . '][1]" value="' . esc_attr($adding_value[$txt][1] ?? '') . '"/></div>';
                        $group_adding .= '</div>';
                        if ($txt === 'Other') {
                            $group_adding .= '<div class="mt-2"><label class="' . $TW['lbl'] . '">Other (specify)</label><input type="text" class="' . $TW['input'] . '" name="' . $adding_name . '[' . esc_attr($txt) . '][Other]" value="' . esc_attr($adding_value[$txt]['Other'] ?? '') . '"/></div>';
                        }
                        $group_adding .= '</div>';
                    }
                }
                $fhtml .= '</select>';
                break;

            /* checkbox ─────────────────────────────────────── */
            case 'checkbox':
                $fhtml .= '<label class="' . $TW['chklbl'] . ' pt-5">';
                $fhtml .= '<input type="hidden" name="' . $name . '" value="0"/>';
                $fhtml .= '<input type="checkbox" class="' . $TW['checkbox'] . '" name="' . $name . '" value="1"' . checked($value, '1', false) . $atts . '/>';
                $fhtml .= '<span class="font-medium text-slate-700">' . esc_html($question->ta_question) . '</span>';
                $fhtml .= '</label>';
                break;

            /* mcheckbox ────────────────────────────────────── */
            case 'mcheckbox':
                $opts  = strpos($question->ta_answers, "\r") > 0
                    ? explode("\r", $question->ta_answers)
                    : explode(',', $question->ta_answers);
                $value = is_array($value) ? $value : [];

                $fhtml .= '<label class="' . $TW['lbl'] . '">' . esc_html($question->ta_question) . '</label>';
                $fhtml .= '<div class="flex flex-wrap gap-y-2">';
                foreach ($opts as $txt) {
                    $txt      = trim($txt);
                    $checked  = in_array($txt, $value);
                    $fieldnum++;
                    if (isset($only_display_questions) && $only_display_questions && !$checked) continue;

                    $has_other = strtolower($txt) === 'other:' || strtolower($txt) === 'other';
                    $fhtml    .= '<label class="inline-flex items-center gap-2 w-1/4 py-0.5 text-sm text-slate-700 cursor-pointer">';
                    $fhtml    .= '<input type="checkbox" class="' . $TW['checkbox'] . ' aoe-hasother" data-id="' . $fieldnum . '" name="' . $name . '[]" value="' . esc_attr($txt) . '"' . ($checked ? ' checked' : '') . $atts . '/>';
                    $fhtml    .= esc_html(ucfirst($txt));
                    $fhtml    .= '</label>';

                    if ($has_other) {
                        $fhtml .= '<div class="w-full mt-1 hidden aoe-other-field fieldnum_' . $fieldnum . '">';
                        $fhtml .= '<input type="text" class="' . $TW['input'] . '" name="' . $name . '[]" placeholder="' . esc_attr($txt) . ' — please specify"/>';
                        $fhtml .= '</div>';
                    }
                }
                $fhtml .= '</div>';
                break;

            /* cb2 ──────────────────────────────────────────── */
            case 'cb2':
                $values = explode('|', $value);
                $fhtml .= '<fieldset class="' . $TW['fieldset'] . '">';
                $fhtml .= '<legend class="' . $TW['legend'] . '">';
                $fhtml .= '<label class="inline-flex items-center gap-2">';
                $fhtml .= '<input type="hidden" name="' . $name . '[0]" value="0"/>';
                $fhtml .= '<input type="checkbox" class="' . $TW['checkbox'] . '" name="' . $name . '[0]" value="1"' . checked($values[0] ?? '', '1', false) . '/>';
                $fhtml .= '<span class="text-sm font-medium text-slate-700">' . esc_html($question->ta_question) . '</span>';
                $fhtml .= '</label></legend>';
                $fhtml .= '<div class="mt-3 grid grid-cols-2 gap-3">';
                $fhtml .= '<div><label class="' . $TW['lbl'] . '">#</label><input type="text" class="' . $TW['input'] . '" name="' . $name . '[1]" value="' . esc_attr($values[1] ?? '') . '"/></div>';
                $fhtml .= '<div><label class="' . $TW['lbl'] . '">Date</label><input type="text" class="hasdate ' . $TW['input'] . '" name="' . $name . '[2]" value="' . esc_attr($values[2] ?? '') . '"/></div>';
                $fhtml .= '</div></fieldset>';
                break;

            /* date ─────────────────────────────────────────── */
            case 'date':
                $fhtml .= '<label class="' . $TW['lbl'] . '">' . esc_html($question->ta_question) . $required . '</label>';
                $fhtml .= '<input type="text" class="hasdate ' . $TW['input'] . '" name="' . $name . '" value="' . esc_attr($value) . '"' . $atts . '/>';
                break;

            /* radio ────────────────────────────────────────── */
            case 'radio':
                $opts = strpos($question->ta_answers, "\r") > 0
                    ? explode("\r", $question->ta_answers)
                    : explode('|', $question->ta_answers);

                $fhtml .= '<label class="' . $TW['lbl'] . '">' . esc_html($question->ta_question) . $required . '</label>';
                $fhtml .= '<div class="flex flex-wrap gap-x-5 gap-y-2 pt-0.5">';
                foreach ($opts as $vl) {
                    $vl = trim($vl);
                    [$k, $txt] = strpos($vl, ',') > 0 ? explode(',', $vl, 2) : [$vl, $vl];
                    $k = trim($k);
                    $fhtml .= '<label class="' . $TW['chklbl'] . '">';
                    $fhtml .= '<input type="radio" class="' . $TW['radio'] . '" name="' . $name . '" value="' . esc_attr($k) . '"' . ($value == $k ? ' checked' : '') . $atts . '/>';
                    $fhtml .= '<span>' . esc_html($txt) . '</span>';
                    $fhtml .= '</label>';
                }
                $fhtml .= '</div>';
                break;

            /* texta ────────────────────────────────────────── */
            case 'texta':
                $fhtml .= '<label class="' . $TW['lbl'] . '">' . esc_html($question->ta_question) . $required . '</label>';
                $fhtml .= '<textarea name="' . $name . '" class="' . $TW['textarea'] . '">' . esc_textarea($value) . '</textarea>';
                break;

            /* textu  (value + unit) ────────────────────────── */
            case 'textu':
                $values = explode('|', $value);
                if (empty($values[1])) $values[1] = $question->ta_answers;
                $fhtml .= '<label class="' . $TW['lbl'] . '">' . esc_html($question->ta_question) . $required . '</label>';
                $fhtml .= '<div class="flex gap-2">';
                $fhtml .= '<input type="text" class="' . $TW['input'] . ' flex-[3]" name="' . $name . '[0]" value="' . esc_attr($values[0] ?? '') . '"' . $atts . '/>';
                $fhtml .= '<input type="text" class="' . $TW['input'] . ' flex-[1] text-slate-500" name="' . $name . '[1]" value="' . esc_attr($values[1] ?? '') . '"' . $atts . '/>';
                $fhtml .= '</div>';
                break;

            /* cond2 ────────────────────────────────────────── */
            case 'cond2':
                $values  = explode('|', $value);
                $opts    = explode('|', $question->ta_answers);
                $fhtml  .= '<label class="' . $TW['lbl'] . '">' . esc_html($question->ta_question) . '</label>';
                $fhtml  .= '<div class="grid grid-cols-2 gap-3">';
                for ($j = 0; $j < 2; $j++) {
                    $fhtml .= '<div>';
                    if (!empty($opts[$j])) $fhtml .= '<label class="' . $TW['lbl'] . '">' . esc_html($opts[$j]) . '</label>';
                    $fhtml .= '<input type="text" class="' . $TW['input'] . '" name="' . $name . '[' . $j . ']" value="' . esc_attr($values[$j] ?? '') . '"' . $atts . '/>';
                    $fhtml .= '</div>';
                }
                $fhtml .= '</div>';
                break;

            /* cond4 ────────────────────────────────────────── */
            case 'cond4':
                $values = explode('|', $value);
                $opts   = explode('|', $question->ta_answers);
                $fhtml .= '<label class="' . $TW['lbl'] . '">' . esc_html($question->ta_question) . '</label>';
                $fhtml .= '<div class="grid grid-cols-2 gap-4">';
                $key    = 0;
                for ($i = 0; $i < 2; $i++) {
                    $col_label = $opts[$i] ?? '';
                    $fhtml    .= '<fieldset class="' . $TW['fieldset'] . '">';
                    if ($col_label) $fhtml .= '<legend class="' . $TW['legend'] . '">' . esc_html($col_label) . '</legend>';
                    $fhtml .= '<div class="mt-2 grid grid-cols-2 gap-3">';
                    for ($j = 0; $j < 2; $j++) {
                        $fhtml .= '<div><label class="' . $TW['lbl'] . '">' . ($j === 0 ? 'AUC' : 'SD') . '</label>';
                        $fhtml .= '<input type="text" class="' . $TW['input'] . '" name="' . $name . '[' . $key . ']" value="' . esc_attr($values[$key] ?? '') . '"/></div>';
                        $key++;
                    }
                    $fhtml .= '</div></fieldset>';
                }
                $fhtml .= '</div>';
                break;

            /* cond8 ────────────────────────────────────────── */
            case 'cond8':
                $values = explode('|', $value);
                $opts   = explode('|', $question->ta_answers);
                $fhtml .= '<label class="' . $TW['lbl'] . '">' . esc_html($question->ta_question) . '</label>';
                $fhtml .= '<div class="grid grid-cols-2 gap-4">';
                $key    = 0;
                for ($i = 0; $i < 2; $i++) {
                    $col_label = $opts[$i] ?? '';
                    $fhtml    .= '<fieldset class="' . $TW['fieldset'] . '">';
                    if ($col_label) $fhtml .= '<legend class="' . $TW['legend'] . '">' . esc_html($col_label) . '</legend>';
                    $fhtml .= '<div class="mt-2 grid grid-cols-4 gap-2">';
                    for ($j = 0; $j < 4; $j++) {
                        $fhtml .= '<input type="text" class="' . $TW['input'] . ' px-2 text-center" name="' . $name . '[' . $key . ']" value="' . esc_attr($values[$key] ?? '') . '"/>';
                        $key++;
                    }
                    $fhtml .= '</div></fieldset>';
                }
                $fhtml .= '</div>';
                break;

            /* calc_age ─────────────────────────────────────── */
            case 'calc_age':
                $fhtml .= '<label class="' . $TW['lbl'] . '">' . esc_html($question->ta_question) . '</label>';
                $fhtml .= '<input type="number" readonly class="calc ' . $TW['input'] . ' bg-slate-50 cursor-default" name="' . $name . '" value=""/>';
                if (($act ?? '') !== 'print') {
                    $safe_name = addslashes($name);
                    $fhtml    .= '<script>jQuery(function($){$("[name=\'bat[doc]\'],[name=\'batch[doc]\']").on("change",function(){var d=$("[name=\'pa[pa_dob]\'],[name=\'batch[dob]\']").val();if(typeof trdd_dob2ages==="function")$("[name=\'' . $safe_name . '\']").val(trdd_dob2ages(d,$(this).val()));});});</script>';
                }
                break;

            /* mtext ────────────────────────────────────────── */
            case 'mtext':
                $opts      = explode('|', $question->ta_question . '|' . $question->ta_answers);
                $col_count = count($opts);
                $countitem = (isset($value[0]) && is_array($value[0]) && count($value[0]) > 1) ? count($value[0]) : 1;

                $grid_cls = 'grid-cols-' . min($col_count, 6);

                $fhtml .= '<div class="aoe-mtext-wrap space-y-1.5">';
                // header row
                $fhtml .= '<div class="grid ' . $grid_cls . ' gap-2">';
                foreach ($opts as $txt) {
                    $fhtml .= '<span class="' . $TW['lbl'] . '">' . esc_html($txt) . $required . '</span>';
                }
                $fhtml .= '</div>';
                // data rows
                for ($iit = 0; $iit < $countitem; $iit++) {
                    $fhtml .= '<div class="aoe-mtext-row grid ' . $grid_cls . ' gap-2">';
                    foreach ($opts as $i2 => $txt) {
                        $mname = 'batmeta[' . $question->ta_slug . '][' . $i2 . '][]';
                        $vl    = $value[$i2][$iit] ?? '';
                        $fhtml .= '<input type="text" class="' . $TW['input'] . '" name="' . $mname . '" value="' . esc_attr($vl) . '"/>';
                    }
                    $fhtml .= '</div>';
                }
                $fhtml .= '<button type="button" class="aoe-addrow ' . $TW['addbtn'] . '">';
                $fhtml .= '<svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg> Add row';
                $fhtml .= '</button></div>';
                // override colspan to full
                $colspan = 'col-span-3';
                break;

            default:
                $fhtml .= '<label class="' . $TW['lbl'] . '">' . esc_html($question->ta_question) . $required . '</label>';
                $fhtml .= '<input type="text" class="' . $TW['input'] . '" name="' . $name . '" value="' . esc_attr($value) . '"' . $atts . '/>';
                break;
        }

        $fields_html .= '<div class="' . $colspan . ' question_item req-field' . $hide_class . '" '
            . 'data-key="' . $slug . '" data-panels="' . $panels . '">'
            . $fhtml
            . '</div>' . "\n";
    } // end foreach questions
?>
    <div class="aoe-section rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden mb-4"
        data-headerid="<?php echo (int)$header->tag_ID ?>">

        <div class="flex items-center gap-2" style="background-color: #f1f5f9; padding: 8px 12px;">
            <span class="text-sm font-semibold leading-snug">
                <?php echo esc_html($header->tag_header) ?>
            </span>
        </div>

        <div class="grid grid-cols-3 gap-4" style="padding: 14px;">
            <?php echo $fields_html ?>
        </div>

        <?php if (!empty($group_adding)): ?>
            <div class="border-t border-slate-100 bg-slate-50/60 px-5 py-4">
                <div class="grid grid-cols-3 gap-4">
                    <?php echo $group_adding ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
<?php
} // end foreach headers
?>

<script>
    jQuery(function($) {
        function aoeSync() {
            var checked = [];
            document.querySelectorAll('input.batcustom_profile:checked').forEach(function(cb) {
                checked.push(cb.value.replace('GROUP_', ''));
            });

            document.querySelectorAll('.aoe-section').forEach(function(sec) {
                var showed = 0;
                sec.querySelectorAll('.question_item').forEach(function(qi) {
                    var panels = (qi.dataset.panels || '').split(',').map(function(p) {
                        return p.trim();
                    }).filter(Boolean);
                    var key = qi.dataset.key || '';
                    var show = panels.length === 0; // no restriction → always show

                    if (!show) show = panels.some(function(p) {
                        return checked.indexOf(p) !== -1;
                    });

                    /* special: pregnancy */
                    if (show && key === 'pregnancy') {
                        var gEl = document.querySelector('select[name="pa[pa_gender]"]');
                        var dEl = document.querySelector('input[name="pa[pa_dob]"]');
                        var g = gEl ? gEl.value : '';
                        var yr = dEl ? parseInt(dEl.value) : 0;
                        var age = (new Date().getFullYear()) - yr;
                        if (g === 'M' || isNaN(age) || age < 12 || age > 50) show = false;
                    }

                    if (show) {
                        qi.classList.remove('hidden', 'aoe-hide-group');
                        qi.querySelectorAll('input,select,textarea').forEach(function(el) {
                            if (el.dataset.required === '1') el.setAttribute('required', '');
                        });
                        showed++;
                    } else {
                        qi.classList.add('hidden', 'aoe-hide-group');
                        qi.querySelectorAll('input,select,textarea').forEach(function(el) {
                            el.removeAttribute('required');
                        });
                    }
                });
                sec.style.display = showed === 0 ? 'none' : '';
            });
        }

        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.aoe-addrow');
            if (!btn) return;
            var wrap = btn.closest('.aoe-mtext-wrap');
            if (!wrap) return;
            var last = wrap.querySelector('.aoe-mtext-row:last-of-type');
            if (!last) return;
            var row = last.cloneNode(true);
            row.querySelectorAll('input').forEach(function(inp) {
                inp.value = '';
            });
            btn.insertAdjacentElement('beforebegin', row);
        });

        document.addEventListener('change', function(e) {
            var cb = e.target;
            if (!cb.classList.contains('aoe-hasother')) return;
            var id = cb.dataset.id;
            var div = document.querySelector('.fieldnum_' + id);
            if (div) div.classList.toggle('hidden', !cb.checked);
        });

        document.addEventListener('change', function(e) {
            var t = e.target;
            if (t.classList.contains('batcustom_profile') ||
                t.name === 'pa[pa_gender]' ||
                t.name === 'pa[pa_dob]') aoeSync();
        });

        aoeSync();
    });
</script>