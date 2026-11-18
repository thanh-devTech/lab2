<div>
    <?php
    if(isset($_POST['settings'])){
        update_option('trfix_imagealts',$_POST['settings']);
    }
    $settings = get_option('trfix_imagealts');
    if(empty($settings['alt_value'])){
        $settings['alt_value'] = '%category - %name';
    }
    ?>
    <form method="post">
        <br><br><br>
        <table style="width:100%">
            <tr>
                <th>Enable Filter</th>
                <td>
                    <input type="hidden" name="settings[enable]" value="0"/>
                    <input type="checkbox" name="settings[enable]" value="1" <?php checked($settings['enable'])?>>
                </td>
            </tr>
            <tr>
                <th style="vertical-align: top">Alt attribute value</th>
                <td><input type="text" name="settings[alt_value]" value="<?php echo $settings['alt_value']?>">
                    <p>The Alt attributes will be dynamically replaced by the above value.</p>

                    <div>%name - Will insert post title.</div>
                    <div>%title- Will insert image name.</div>
                    <div>%category - Will insert post categories</div>
                </td>
            </tr>
            <tr>
                <th></th>
                <td><br><br>
                    <label>
                        <input type="hidden" name="settings[onlynotag]" value="0"/>
                        <input type="checkbox" name="settings[onlynotag]" value="1" <?php checked($settings['onlynotag'])?>>
                        Only Replace if image has no alt tag
                    </label>
                </td>

            </tr>
            <tr>
                <td colspan="2">
                    <input type="submit" value="Save" class="button-primary"/>

                </td>
            </tr>
        </table>
    </form>

    <div id="display_log"></div>
</div>

<style>
    table tr th{
        text-align: right;
        padding-right:10px;
    }
</style>

<script>
    jQuery(function ($) {
        $('.run_fix_alt').click(function () {
            var $this = $(this);
            if(!$this.data('oldtxt')){
                $this.data('oldtxt',$this.html());
            }
            if($this.html()=='Running...')return false;
            $this.html('Running...');

            $.ajax({
                data:{tr_action:'ci_fix_empty_alt'},
                type:'post',
                success:function(rs)
                {
                    $this.html($this.data('oldtxt'));
                    $('#display_log').html(rs);
                },
                error:function () {
                    $this.html($this.data('oldtxt'));
                }
            })
        })
    })
</script>