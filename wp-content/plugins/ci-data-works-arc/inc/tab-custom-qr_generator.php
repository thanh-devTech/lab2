<?php
$qr_dir = WP_CONTENT_DIR . '/qrimages/';
if (!file_exists($qr_dir)) {
    wp_mkdir_p($qr_dir);
}
$images = scandir($qr_dir);
$site_url = get_bloginfo('url');
?>


<input type="text" placeholder="url" id="qr_url" style="width:70%" />
<select id="qr_size" style="width:100px">
    <?php
    foreach (array(100, 200, 300, 400, 500, 600) as $size) {
        echo '<option value="' . $size . '">' . $size . '</option>';
    }
    ?>
</select>
<input type="button" class="button" id="generator_btn" value="Generate" />

<div id="qr_imageview"></div>
<div id="qr_imagelink"></div>

<br><br><br>
<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
    <thead>
        <tr>
            <th style="width:100px">Image</th>
            <th>Image URL</th>
            <th>URL</th>
            <th style="width:50px">Size</th>
            <th style="width:60px">Actions</th>
        </tr>
    </thead>
    <tbody id="imagesqrs">
        <?php
        foreach ($images as $image) {
            if (strpos($image, '.png') === false || stripos($image, '.txt') !== false) continue;
            $url  = '';
            $size = 100;
            $url_image = $site_url . '/wp-content/qrimages/' . $image;
            $logurl = $qr_dir . $image . '.txt';
            if (file_exists($logurl)) {
                $filedata = file_get_contents($qr_dir . $image . '.txt');
                list($url, $size) = explode('|', $filedata);
            }


            $url = urldecode($url);
        ?>
            <tr>
                <td><img style="width:100px" src="<?php echo $url_image ?>" /></td>
                <td><input type="text" class="url_image" readonly value="<?php echo $url_image ?>" /></td>
                <td><input type="text" readonly value="<?php echo $url ?>" /></td>
                <td><?php echo $size ?></td>
                <td><a class="delete" href="#">Delete</a></td>
            </tr>
        <?php
        }
        ?>
    </tbody>
</table>
<style>
    #imagesqrs input[type="text"] {
        width: 100%;
    }
</style>
<script>
    jQuery(function($) {
        $('#generator_btn').click(function() {
            var $vl = $('#qr_url').val();
            var $size = $('#qr_size').val();
            if ($vl == '') return false;
            $('#qr_url').val('');

            $.ajax({
                type: 'post',
                data: {
                    tr_action: 'ci_qr_generator',
                    save: 1,
                    url: $vl,
                    s: $size
                },
                dataType: 'json',
                success: function(json) {
                    var $html = '<tr>';
                    $html += '<td><img style="width:100px" src="' + json.imageurl + '"/></td>';
                    $html += '<td><input type="text" readonly value="' + json.imageurl + '"/></td>';
                    $html += '<td><input type="text" readonly value="' + $vl + '"/></td>';
                    $html += '<td>' + $size + '</td>';
                    $html += '<td><a href="#" >Delete</a></td>';
                    $html += '</tr>';
                    $('#imagesqrs').append($html);
                    $('#qr_imageview').html('<img style="max-height:200px" src="' + json.imageurl + '"/>');
                    $('#qr_imagelink').html('<a href="' + json.imageurl + '" target="_blank">Download</a>');
                },
                error: function() {
                    alert('Error.Please reload');
                }
            });
            return false;
        })
        $('body').on('focus', '.imagesqrs input[type="text"]', function() {
            $(this).select();
        }).on('click', '#imagesqrs a.delete', function() {
            if (confirm('Do you want delete?') == false) return false;
            var $tr = $(this).parents('tr');
            var $imageurl = $('input.url_image', $tr).val();
            $.ajax({
                type: 'post',
                data: {
                    tr_action: 'ci_qr_generator',
                    delete: 1,
                    imageurl: $imageurl
                },
                dataType: 'json',
                success: function(json) {
                    $tr.remove();
                },
                error: function() {
                    aler('Error. Please reload');
                }
            });
            return false;
        })
    })
</script>