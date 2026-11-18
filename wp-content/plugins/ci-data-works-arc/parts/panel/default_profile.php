<?php
$custom_profiles = array(
    '' => 'Please select',
    'basic' => 'Low Risk',
    'standard' => 'Moderate Risk',
    'highrisk' => 'High Risk',
    'comprehensive' => 'Comprehensive'

);
?>



<h2>Default Profile</h2>
<div class="wrap">
    <form method="post" enctype="multipart/form-data">
        <label>Default Profile</label>
        <?php
        tr_select_option('tox_df_profile', $custom_profiles, '');
        ?>
        <h4>Individual Therapeutic Drugs or Therapeutic Drug Class Section.</h4>
        <div class="frow blabel default_requests  masonry" id="profile_custom_div" style="font-size:12px;">
            <?php
            $default_tests = [];
            foreach (tr_pdd_get_profiles_tests_requesteds('tox', true) as $group_id => $vl) {
                $group = tr_pdd_get_panel_groups($group_id);
                echo '<div class="groupparent item">';
                echo '<label class="block" style="padding: 0 0 4px 2px;"><input class="check-all" type="checkbox" data-groupID="' . $group_id . '" />' . '<strong> ' . $group['label'] . '</strong></label>';
                echo '<div class="frow">';
                foreach ($vl as $k => $r) {
                    $key = strtoupper($r->key);
                    $txt = $r->te_name;
                    $checked = @in_array($key, $default_tests);
            ?>
                    <label>
                        <input type="checkbox" class="<?php echo $r->class ?> child" data-groupID="<?php echo $group_id ?>" value="<?php echo $r->te_ID ?>" />
                        <?php echo $txt ?></label>
            <?php

                }
                echo '</div>';
                echo '</div>';
            }
            ?>
        </div>
        <input type="hidden" id="all_basic" name="te_basic" value="" />
        <input type="hidden" id="all_standard" name="te_standard" value="" />
        <input type="hidden" id="all_highrisk" name="te_highrisk" value="" />
        <input type="hidden" id="all_comprehensive" name="te_comprehensive" value="" />
        <div>
            <input type="submit" class="button-primary" value="Submit">
            <input type="hidden" name="tr_action" value="ci_save_form_default_profile" />
        </div>
    </form>
</div>
<script>
    jQuery(function($) {
        var $bychangeall = false;
        $('[name="tox_df_profile"]').change(function() {
            var $tmp = $(this).val().toLowerCase();

            $('#profile_custom_div input').removeAttr('checked');
            var $test_str = $('#all_' + $tmp).val();
            if ($test_str) {

                var $tests = $test_str.split(',');
                for (i in $tests) {

                    $('#profile_custom_div input[value="' + $tests[i] + '"]').prop('checked', true);
                }
                $('#profile_custom_div input').each(function() {
                    var $vl = $(this).val();
                    if ($vl.indexOf(',') > 0 && $test_str.indexOf($vl) > -1) {
                        $(this).prop('checked', true);
                    }
                })
            } else {
                $('#profile_custom_div input.te_' + $tmp).prop('checked', true);
                $bychangeall = true;
                $('#profile_custom_div input.te_' + $tmp + ':first-child').change();
                $bychangeall = false;

            }
        })
        $('.groupparent .check-all').change(function(e) {
            const checked = $(this).is(':checked');
            const groupID = $(this).data('groupid');

            $('#profile_custom_div input.child')
                .filter(function() {
                    return $(this).data('groupid') == groupID;
                })
                .prop('checked', checked);
            const defaultProfile = $('[name="tox_df_profile"]').val().toLowerCase();
            const tests = [];
            $('#profile_custom_div input.child:checked').each(function() {
                tests.push($(this).val());
            })
            $('#all_' + defaultProfile).val(tests.toString());
        })

        $('#profile_custom_div input.child').change(function(e) {
            var $tmp = $('[name="tox_df_profile"]').val().toLowerCase();
            var $tests = [];

            if ($tmp == 'comprehensive' && !$bychangeall) {
                alert('You cant change Comprehensive');
                return false;
            }

            $('#profile_custom_div input.child:checked').each(function() {
                $tests.push($(this).val());
            })
            $('#all_' + $tmp).val($tests.toString());
        })

        $('[name="cus[cus_testing_profile]"]:checked').change();

        $('.testing_profile').click(function() {
            tb_show('', '?tr_action=show_basic_info&id=' + $(this).data('id') + '&width=1000&height=600');
        });
        $('.psych_panel').click(function() {
            tb_show('', '?tr_action=show_basic_info&file=psych_1.jpg&id=' + $(this).data('id') + '&width=800&height=600');
        });
    })
</script>