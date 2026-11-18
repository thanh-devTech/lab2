<?php
$enable_bc_test_popup = !empty($settings['enable_bc_test_popup']);
if ($enable_bc_test_popup) {
?>
    <style>
        .bc-test-popup-enabled select[name="dc_tests[]"],
        .bc-test-popup-enabled .select2-container {
            display: none !important;
        }

        .bc-open-test-popup {
            font-weight: normal;
            text-decoration: none;
        }

        .bc-selected-tests-summary {
            margin-top: 6px;
            min-height: 32px;
            max-height: 96px;
            overflow: auto;
            padding: 5px;
            border: 1px solid #ddd;
            background: #fff;
        }

        .bc-selected-tests-summary.empty {
            color: #777;
            font-style: italic;
        }

        .bc-selected-test-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin: 2px;
            padding: 3px 5px 3px 7px;
            border: 1px solid #b9d3ee;
            border-radius: 3px;
            background: #eef6ff;
            color: #1d4f7a;
            line-height: 1.4;
        }

        .bc-selected-test-remove {
            width: 16px;
            height: 16px;
            padding: 0;
            border: 0;
            background: transparent;
            color: #1d4f7a;
            cursor: pointer;
            line-height: 1;
        }

        .bc-selected-test-remove svg {
            display: block;
            width: 14px;
            height: 14px;
        }

        .bc-test-modal {
            display: none;
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, .45);
        }

        .bc-test-modal-inner {
            background: #fff;
            width: min(980px, calc(100% - 40px));
            max-height: calc(100vh - 60px);
            margin: 30px auto;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .3);
        }

        .bc-test-modal-head,
        .bc-test-modal-foot {
            padding: 12px 16px;
            border-bottom: 1px solid #ddd;
        }

        .bc-test-modal-foot {
            border-top: 1px solid #ddd;
            border-bottom: 0;
            text-align: right;
        }

        .bc-test-modal-body {
            padding: 14px;
            overflow: auto;
        }

        .bc-test-modal-tools {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-bottom: 12px;
        }

        .bc-test-modal-search {
            width: 320px;
        }

        .bc-test-list {
            column-count: 3;
            column-gap: 14px;
            font-size: 12px;
        }

        .bc-test-group {
            break-inside: avoid;
            margin: 0 0 12px;
            border: 1px solid #ddd;
            background: #f8f8f8;
        }

        .bc-test-group-title {
            display: block;
            padding: 7px 9px;
            background: #e6e6e6;
            font-weight: bold;
        }

        .bc-test-group-body {
            padding: 8px;
        }

        .bc-test-child-title {
            margin: 7px 0 4px;
            font-weight: bold;
            text-align: center;
        }

        .bc-test-item {
            display: block;
            padding: 3px 0;
        }

        .bc-test-item input {
            margin-right: 5px;
        }

        @media (max-width: 900px) {
            .bc-test-list {
                column-count: 2;
            }
        }

        @media (max-width: 640px) {
            .bc-test-list {
                column-count: 1;
            }
        }
    </style>
    <script>
        jQuery(function($) {
            var $bcTestPopupEnabled = true;

            function bcEscapeHtml($txt) {
                return ($txt + '').replace(/[&<>"']/g, function($m) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    } [$m];
                });
            }

            function getBcSelectedTests() {
                var $selected = $('[name="dc_tests[]"]').val() || [];
                return $selected.map(function($id) {
                    return $id + '';
                });
            }

            function updateBcTestPopupSummary() {
                var $select = $('[name="dc_tests[]"]');
                var $tests = [];
                $('option:selected', $select).each(function() {
                    $tests.push({
                        id: $(this).val(),
                        label: $(this).text()
                    });
                });
                if ($tests.length) {
                    var $html = '';
                    for (var i = 0; i < $tests.length; i++) {
                        $html += '<span class="bc-selected-test-chip">' + bcEscapeHtml($tests[i].label);
                        $html += '<button type="button" class="bc-selected-test-remove" data-test-id="' + bcEscapeHtml($tests[i].id) + '" title="Remove test" aria-label="Remove test">';
                        $html += '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4.22 3.16 8 6.94l3.78-3.78 1.06 1.06L9.06 8l3.78 3.78-1.06 1.06L8 9.06l-3.78 3.78-1.06-1.06L6.94 8 3.16 4.22z"/></svg>';
                        $html += '</button></span>';
                    }
                    $('.bc-selected-tests-summary').removeClass('empty').html($html);
                } else {
                    $('.bc-selected-tests-summary').addClass('empty').text('No tests selected');
                }
            }

            function renderBcTestPopup() {
                var $btype = $('[name="drug_class[dc_type]"]').val();
                var $selected = getBcSelectedTests();
                var $groups = {};
                var $groupOrder = [];

                for (var i in $all_tests) {
                    var $t = $all_tests[i];
                    if ($t.te_type != $btype) continue;
                    var $groupKey = ($t.group_parent > 0 ? $t.group_parent : $t.te_group_id) + '';
                    var $groupName = $t.tg_name || 'Tests';
                    var $childName = $t.group_name || $groupName;
                    if (!$groups[$groupKey]) {
                        $groups[$groupKey] = {
                            name: $groupName,
                            children: {},
                            childOrder: []
                        };
                        $groupOrder.push($groupKey);
                    }
                    if (!$groups[$groupKey].children[$childName]) {
                        $groups[$groupKey].children[$childName] = [];
                        $groups[$groupKey].childOrder.push($childName);
                    }
                    $groups[$groupKey].children[$childName].push($t);
                }

                var $html = '';
                for (var g = 0; g < $groupOrder.length; g++) {
                    var $gid = $groupOrder[g];
                    var $group = $groups[$gid];
                    $html += '<div class="bc-test-group" data-group-name="' + bcEscapeHtml($group.name.toLowerCase()) + '">';
                    $html += '<label class="bc-test-group-title"><input type="checkbox" class="bc-test-group-check" /> ' + bcEscapeHtml($group.name) + '</label>';
                    $html += '<div class="bc-test-group-body">';
                    for (var c = 0; c < $group.childOrder.length; c++) {
                        var $childName = $group.childOrder[c];
                        if ($childName && $childName != $group.name) {
                            $html += '<div class="bc-test-child-title">' + bcEscapeHtml($childName) + '</div>';
                        }
                        var $tests = $group.children[$childName];
                        for (var t = 0; t < $tests.length; t++) {
                            var $test = $tests[t];
                            var $tid = $test.te_ID + '';
                            var $checked = $selected.indexOf($tid) > -1 ? ' checked' : '';
                            $html += '<label class="bc-test-item" data-test-name="' + bcEscapeHtml(($test.te_name + ' ' + $group.name + ' ' + $childName).toLowerCase()) + '">';
                            $html += '<input type="checkbox" class="bc-test-check" value="' + bcEscapeHtml($tid) + '"' + $checked + ' /> ';
                            $html += bcEscapeHtml($test.te_name);
                            $html += '</label>';
                        }
                    }
                    $html += '</div></div>';
                }

                $('.bc-test-list').html($html || '<p>No tests found for this type.</p>');
                $('.bc-test-group').each(function() {
                    var $checks = $('.bc-test-check', this);
                    $('.bc-test-group-check', this).prop('checked', $checks.length > 0 && $checks.length == $checks.filter(':checked').length);
                });
                $('.bc-test-modal-title-type').text($('[name="drug_class[dc_type]"] option:selected').text());
            }

            $('#col_tests').addClass('bc-test-popup-enabled');
            var $testLabel = $('#col_tests label').first();
            if (!$testLabel.find('.bc-open-test-popup').length) {
                $testLabel.append(' - <a href="#" class="bc-open-test-popup">select tests</a>');
            }
            if (!$('#col_tests .bc-selected-tests-summary').length) {
                $('[name="dc_tests[]"]').after('<div class="bc-selected-tests-summary">No tests selected</div>');
            }
            $('body').append(
                '<div class="bc-test-modal">' +
                '<div class="bc-test-modal-inner">' +
                '<div class="bc-test-modal-head"><strong>TEST LIST: <span class="bc-test-modal-title-type"></span></strong></div>' +
                '<div class="bc-test-modal-body">' +
                '<div class="bc-test-modal-tools">' +
                '<input type="text" class="bc-test-modal-search" placeholder="Search tests..." />' +
                '<button type="button" class="button bc-test-clear">Clear</button>' +
                '</div>' +
                '<div class="bc-test-list"></div>' +
                '</div>' +
                '<div class="bc-test-modal-foot">' +
                '<button type="button" class="button bc-test-cancel">Cancel</button> ' +
                '<button type="button" class="button-primary bc-test-apply">Apply</button>' +
                '</div>' +
                '</div>' +
                '</div>'
            );

            $('body').on('click', '.bc-open-test-popup', function($e) {
                $e.preventDefault();
                renderBcTestPopup();
                $('.bc-test-modal-search').val('');
                $('.bc-test-modal').show();
            });

            $('body').on('click', '.bc-selected-test-remove', function($e) {
                $e.preventDefault();
                $e.stopPropagation();
                var $removeId = $(this).data('test-id') + '';
                var $select = $('[name="dc_tests[]"]');
                var $selected = ($select.val() || []).filter(function($id) {
                    return ($id + '') !== $removeId;
                });
                $select.val($selected).trigger('change');
                updateBcTestPopupSummary();
            });

            $('body').on('click', '.bc-test-cancel', function() {
                $('.bc-test-modal').hide();
            });

            $('body').on('click', '.bc-test-modal', function($e) {
                if ($e.target === this) {
                    $('.bc-test-modal').hide();
                }
            });

            $('body').on('change', '.bc-test-group-check', function() {
                $('.bc-test-check:visible', $(this).closest('.bc-test-group')).prop('checked', $(this).is(':checked'));
            });

            $('body').on('change', '.bc-test-check', function() {
                var $group = $(this).closest('.bc-test-group');
                var $checks = $('.bc-test-check:visible', $group);
                $('.bc-test-group-check', $group).prop('checked', $checks.length > 0 && $checks.length == $checks.filter(':checked').length);
            });

            $('body').on('click', '.bc-test-clear', function() {
                $('.bc-test-check, .bc-test-group-check').prop('checked', false);
            });

            $('body').on('keyup', '.bc-test-modal-search', function() {
                var $q = $(this).val().toLowerCase();
                $('.bc-test-item').each(function() {
                    $(this).toggle(!$q || ($(this).data('test-name') + '').indexOf($q) > -1);
                });
                $('.bc-test-group').each(function() {
                    $(this).toggle($('.bc-test-item:visible', this).length > 0 || !$q || ($(this).data('group-name') + '').indexOf($q) > -1);
                });
            });

            $('body').on('click', '.bc-test-apply', function() {
                var $selected = [];
                $('.bc-test-check:checked').each(function() {
                    $selected.push($(this).val());
                });
                $('[name="dc_tests[]"]').val($selected).trigger('change');
                updateBcTestPopupSummary();
                $('.bc-test-modal').hide();
            });

            $('[name="dc_tests[]"], [name="drug_class[dc_type]"]').on('change', function() {
                window.setTimeout(updateBcTestPopupSummary, 0);
            });

            $('.need_validate').on('submit', function() {
                if ($('#col_tests').is(':visible') && (getBcSelectedTests().length == 0)) {
                    alert('Please select at least one test.');
                    return false;
                }
            });
            $('[name="drug_class[dc_type]"]').change(function() {
                updateBcTestPopupSummary();
            });

            updateBcTestPopupSummary();
        });
    </script>
<?php
}
?>
