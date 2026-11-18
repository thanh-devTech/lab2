<?php

global $wpdb;

if (!defined('DEMO_SITE') && DEMO_SITE !== true) {
    echo '<h3>This feature is only available on demo site.</h3>';
    return;
}

if (isset($_POST['trajax']) && $_POST['trajax'] === 'generate_req_report') {

    try {
        $btype = isset($_POST['btype']) ? $_POST['btype'] : '';
        $groups = $wpdb->get_col($wpdb->prepare(
            "SELECT tg_ID FROM ci_test_groups WHERE tg_type = %s AND tg_show_req = 1",
            $btype
        ));

        if (empty($groups)) {
            throw new Exception('No test groups available');
        }
        $group_panels = array();
        $drugs_by_group = array();

        foreach ($groups as $group_id) {
            $panels = $wpdb->get_col($wpdb->prepare(
                "SELECT te_ID FROM ci_test_panels WHERE te_group_id = %d AND te_type = %d",
                $group_id,
                $btype
            ));

            $drugs = $wpdb->get_col($wpdb->prepare(
                "SELECT te_name FROM ci_test_panels WHERE te_group_id = %d AND te_type = %d",
                $group_id,
                $btype
            ));

            if (!empty($panels)) {
                $group_panels[$group_id] = $panels;
                $drugs_by_group[$group_id] = $drugs;
            }
        }

        $batch_tbl = tr_pdd_get_table_by_btype($btype);

        $report_tbl = tr_pdd_get_table_report_by_btype($btype);
        $result_tbl = tr_pdd_get_table_result_by_btype($btype);

        $batchs = $wpdb->get_results("
            SELECT mid, batch_id, assen, panel_tests_requested
            FROM {$batch_tbl}
            ORDER BY doc DESC
        ");
        if (empty($batchs)) {
            throw new Exception('No batches found');
        }

        $lab_id = CI_Base_Lab::get_current_lab_id();
        $current_datetime = date_i18n('Y-m-d H:i:s');
        $current_date = date_i18n('Y-m-d');

        $inserted_count = 0;
        $num_panel = 2;

        foreach ($batchs as $ba) {

            $drugs = array();
            //only update if not enough panels
            if (substr_count($ba->panel_tests_requested, 'GROUP_') < $num_panel) {
                $group_ids = array_keys($group_panels);
                $selected_group_ids = array_rand(array_flip($group_ids), min($num_panel, count($group_ids)));

                if (!is_array($selected_group_ids)) {
                    $selected_group_ids = [$selected_group_ids];
                }

                $panel_tests_parts = array();
                foreach ($selected_group_ids as $group_id) {
                    $panel_tests_parts[] = 'GROUP_' . $group_id;
                    $panel_tests_parts = array_merge($panel_tests_parts, $group_panels[$group_id]);
                    $drugs = array_merge($drugs, $drugs_by_group[$group_id]);
                }
                $panel_tests_requested = implode(',', $panel_tests_parts);

                $wpdb->update(
                    $batch_tbl,
                    array(
                        'panel_tests_requested' => $panel_tests_requested
                    ),
                    array('mid' => $ba->mid),
                    array('%s'),
                    array('%d')
                );
            } else {
                //get drugs from existing panels
                $panel_tests = explode(',', $ba->panel_tests_requested);
                foreach ($panel_tests as $pt) {
                    if (strpos($pt, 'GROUP_') !== false) {
                        $group_id = trim(str_replace('GROUP_', '', $pt));
                        $drugs = array_merge($drugs, $drugs_by_group[$group_id]);
                    }
                }
            }



            $report_data = array(
                'patient_id' => $ba->assen,
                'batch_id' => $ba->batch_id,
                'created' => $current_datetime,
                'created_date' => $current_date,
                'lab_id' => $lab_id,
            );

            $existid = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$report_tbl} WHERE patient_id = %s LIMIT 1",
                $ba->assen
            ));
            if (empty($existid)) {
                $wpdb->insert($report_tbl, $report_data);
                $inserted_count++;
                $existid = $wpdb->insert_id;
            }

            if ($existid > 0) {
                //insert results
                foreach ($drugs as $drug_name) {
                    $rsid = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$result_tbl} WHERE report_id = %d AND drug = %s LIMIT 1",
                        $existid,
                        $drug_name
                    ));
                    $result_data = array(
                        'report_id' => $existid,
                        'drug' => $drug_name,
                        'conc' => rand(1, 44),
                        'pos-neg' => (rand(0, 1) == 1) ? 'POS' : 'NEG',
                    );

                    if (empty($rsid)) {

                        $wpdb->insert($result_tbl, $result_data);
                    } else {
                        $wpdb->update(
                            $result_tbl,
                            $result_data,
                            array('id' => $rsid)
                        );
                    }
                }
            }
        }

        wp_send_json([
            'success' => true,
            'message' => "Successfully generated {$inserted_count} reports"
        ]);
    } catch (Exception $e) {
        wp_send_json([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}



?>

<div class="form-container">
    <h3>Generate req & add report</h3>
    <form id="generate-form">
        <div class="form-group">
            <label>
                Select type
                <select id="btypes" required>
                    <option value="blood">blood</option>
                    <option value="tox">tox</option>
                    <option value="pcr">pcr</option>
                </select>
            </label>
        </div>

        <button type="button" id="generate-btn" class="btn-primary">Generate req & add report</button>
    </form>
</div>
<style>
    .form-group {
        margin-bottom: 15px;
    }

    .form-group input[type="checkbox"] {
        margin-right: 5px;
    }

    .btn-primary {
        background: #0073aa;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }

    .btn-primary:hover {
        background: #005a87;
    }

    .btn-primary:disabled {
        background: #ccc;
        cursor: not-allowed;
    }
</style>

<script>
    jQuery(function($) {
        $('#btypes').select2();
        $('#generate-btn').on('click', function() {
            const $btn = $(this);
            $btn.prop('disabled', true).text('Generating...');
            $.ajax({
                type: 'POST',
                data: {
                    trajax: 'generate_req_report',
                    btype: $('#btypes').val()
                },
                success: function(res) {
                    if (res.success) {
                        alert(res.message || '')
                    } else {
                        alert(res.error || '')
                    }
                },
                error: function(e) {
                    alert('Error');
                    console.log(e);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Generate req & add report');
                }
            });

        });

    });
</script>