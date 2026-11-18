<?php
global $wpdb;
if (empty($tes_type) || $tes_type != 'pcr') {
    return;
}


$tabs = array(
    'quantification' => 'QUANTIFICATION OF GROWTH',
    'comments' => 'Comments'
);
$tab        = isset($_REQUEST['bitab']) && !empty($_REQUEST['bitab']) ? $_REQUEST['bitab'] : key($tabs);

if (!isset($tabs[$tab])) {
    $tab = key($tabs);
}

$option_key = 'ci_biomic_messages_' . $tab;

wp_enqueue_script('jquery-ui-sortable');

include_once(TRCPDD_PATH . 'pages/formdata.php');

if (isset($_POST['import_settings'])) {
    if (isset($_FILES['settings_import_file']) && !empty($_FILES['settings_import_file']['tmp_name'])) {
        $import = json_decode(file_get_contents($_FILES['settings_import_file']['tmp_name']), true);
        $import_options = null;

        if (is_array($import) && isset($import['option_key']) && $import['option_key'] == $option_key && isset($import['options']) && is_array($import['options'])) {
            $import_options = $import['options'];
        }

        if (is_array($import_options)) {
            $logdata = $import_options;
            $logdata['old'] = get_option($option_key, array());
            update_option($option_key, $import_options);
            trpdd_log_user(0, 'import_biomic_' . $tab, $logdata);
            Tr_Session_Class::add('Imported Successfully!');
        } else {
            Tr_Session_Class::add('Invalid import file.');
        }
    } else {
        Tr_Session_Class::add('Please select an import file.');
    }
}

if (isset($_POST['save'])) {
    $items = array();
    $settings = isset($_POST['settings']) && is_array($_POST['settings']) ? $_POST['settings'] : array();

    foreach ($settings as $k => $data) {
        $data['title'] = isset($data['title']) ? trim($data['title']) : '';

        $items[] = $data;
    }

    update_option($option_key, $items);

    trpdd_log_form('biomic_messages', $settings);

    trpdd_log_user(get_current_user_id(), 'save_biomic', '');
    Tr_Session_Class::add('Saved Successfully!');
}

$items = get_option($option_key, array());
$panels = CI_Base_Panel::list_active('pcr');

?>
<div class="wrap">
    <h2 class="nav-tab-wrapper">
        <?php

        foreach ($tabs as $k => $txt) {
            $class = $tab == $k ? 'nav-tab-active' : '';
            $link  = Tr_Base_Class_V6::link(array(
                'bitab'     => $k,
                'act'     => '',
                'id'      => '',
                's'       => '',
                'search'  => '',
                'paged'   => '',
                'orderby' => '',
                'order'   => '',
                'gr'      => '',
                'type'    => ''
            ), false);
            echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
        }
        ?>
    </h2>
</div>
<div class="wrap">
    <h2>Biomic Messages
        <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'tabtype' => '', 'bitab' => '')) ?>" class="add-new-h2">All Tests</a>
        <a href="#" class="add-new-h2 trpdd-settings-import-export">Export/Import</a>
    </h2>
    <?php Tr_Session_Class::show() ?>

    <div id="trpdd_settings_import_export_modal" style="display:none;position:fixed;z-index:100000;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.35);">
        <div style="background:#fff;width:420px;max-width:90%;margin:120px auto 0;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.2);">
            <h3 style="margin-top:0;">Export/Import Biomic Messages</h3>
            <p>
                <a href="#" class="button button-primary trpdd-export-settings">Export</a>
            </p>
            <form method="post" enctype="multipart/form-data">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <input type="file" name="settings_import_file" accept="application/json,.json" />
                    <input type="submit" name="import_settings" class="button" value="Import" />
                    <a href="#" class="button trpdd-close-settings-import-export">Close</a>
                </div>
            </form>
        </div>
    </div>

    <form method="post">

        <div class="group_type">
            <div class="frow">
                <div class="col4">
                    <h4>Title</h4>
                </div>
                <div class="col3">
                    <h4>Message</h4>
                </div>
                <div class="col4">
                    <h4>Panel</h4>
                </div>
                <div class="col8">
                    <h4>Action</h4>
                </div>
            </div>
            <div id="type_items" data-items="<?php echo esc_attr(json_encode($items)) ?>"></div>
            <a class="button add_item">+</a>
        </div>
        <div class="frow actions" style="padding-top:20px">
            <input type="submit" name="save" value="Save" class="button-primary" />
        </div>
    </form>
</div>

<div style="display: none" id="default_item">
    <div class="item frow">
        <div class="col4">
            <input type="text" class="title" />
        </div>
        <div class="col3">
            <textarea type="text" class="message"></textarea>
        </div>
        <div class="col4">
            <?php CI_Base_Panel::panel_options(array('btype' => 'pcr', 'class' => 'panel', 'blank_text' => 'All Panels')); ?>
        </div>
        <div class="col7">
            <a class="button remove_item">-</a>
            <span class="dashicons dashicons-menu"></span>
        </div>
    </div>
</div>

<style>
    .ui-sortable-handle {
        cursor: move;
    }
</style>
<script>
    jQuery(function($) {
        var trpddSettingsExport = <?php echo wp_json_encode(array(
            'option_key' => $option_key,
            'type' => 'pcr',
            'tabtype' => 'biomic_messages',
            'bitab' => $tab,
            'exported_at' => current_time('mysql'),
            'options' => is_array($items) ? $items : array(),
        ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        var trpddSettingsExportFilename = <?php echo wp_json_encode('pcr-biomic-messages-' . $tab . '-' . date_i18n('Ymd-His') . '.json') ?>;
        var $trow = 0;

        function add_item($data, $next) {
            var $default_html = $($('#default_item').html());
            var $tid = $data['ID'];
            $trow++;
            if (!$tid) {
                $tid = 'new_' + $trow;
            }
            $default_html.data('id', $tid);
            $('.title', $default_html).attr('name', 'settings[' + $tid + '][title]').val($data['title']);
            $('.message', $default_html).attr('name', 'settings[' + $tid + '][message]').val($data['message']);
            $('.panel', $default_html).attr('name', 'settings[' + $tid + '][panel]').val($data['panel']);

            $('#type_items').append($default_html);
        }

        function init_items() {
            var $type_items = $('#type_items');
            var $items = $type_items.data('items');
            for (var i in $items) {
                add_item($items[i]);
            }
        }
        $('body').on('click', 'a.add_item', function() {
            add_item({});
        }).on('click', 'a.add_next', function() {
            var $pr = $(this).parents('.item');
            add_item({}, $pr);
        }).on('click', 'a.remove_item', function() {
            if (confirm('Do you want remove?')) {
                var $this = $(this);
                var $pr = $(this).parents('.item');
                var $id = $pr.data('id');
                $this.html('Removing...');
                $pr.remove();
            }
            return false;
        }).on('click', '.trpdd-settings-import-export', function(e) {
            e.preventDefault();
            $('#trpdd_settings_import_export_modal').show();
        }).on('click', '.trpdd-close-settings-import-export', function(e) {
            e.preventDefault();
            $('#trpdd_settings_import_export_modal').hide();
        }).on('click', '#trpdd_settings_import_export_modal', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        }).on('click', '.trpdd-export-settings', function(e) {
            e.preventDefault();
            var blob = new Blob([JSON.stringify(trpddSettingsExport, null, 2)], {
                type: 'application/json'
            });
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = trpddSettingsExportFilename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        })

        init_items();
        $('#type_items').sortable();
    })
</script>
