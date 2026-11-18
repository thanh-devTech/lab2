if (typeof $use_zebra === 'undefined') {
    var $use_zebra = false;
}
if (typeof $use_zebra_size === 'undefined') {
    var $use_zebra_size = 2;
}

if (typeof $add_doc_to_label === 'undefined') {
    var $add_doc_to_label = false;
}

if (typeof $disable_successful_print_popup === 'undefined') {
    var $disable_successful_print_popup = false;
}

var selected_device;
var $time_setup_printer = null;
var $printer_request_in_progress = false;
var $printer_waiters = [];
var $printer_permission_checked = false;
var $printer_permission_state = 'prompt';
var $printer_guide_open = false;
var $printer_guide_status = null;
var $printer_guide_dismissed = false;
var $printer_guide_last_shown_at = 0;
var $printer_request_id = 0;
var $active_printer_request_id = 0;
var $printer_guide_flow_id = 0;
var $local_network_permission_result = null;
var $local_network_permission_change_handler = null;
var $local_network_permission_supported = null;
var $printer_success_notice_timeout = null;
var $printer_success_notice_last_at = 0;
var $printer_bulk_printing = false;
var $printer_button_lock_ms = 3000;

function should_skip_permission_check() {
    try {
        return typeof window !== 'undefined'
            && window.location
            && window.location.protocol
            && window.location.protocol.toLowerCase() !== 'https:';
    } catch (e) {
        return false;
    }
}

function trpdd_formatdate($date, $usetime) {
    try {
        if ($date.indexOf('-') > -1) {
            var arr = $date.split('-');
            console.log(arr);
            if (arr.length === 3) {
                var arr2 = arr[2].split(' ');
                arr[2] = arr2[0];
                var $out = arr[1] + '/' + arr[2] + '/' + arr[0];
                if ($usetime && arr2[1]) {
                    $out += ' ' + arr2[1];
                }
                return $out;
            }
        }
        var $d = new Date($date);
        var dd = String($d.getDate()).padStart(2, '0');
        var mm = String($d.getMonth() + 1).padStart(2, '0'); //January is 0!
        var yyyy = $d.getFullYear();

        $date = mm + '/' + dd + '/' + yyyy;
        if ($usetime) {
            var arr2 = $date.split(' ');
            if (arr2[1]) {
                $date += ' ' + arr2[1];
            }
        }
    } catch (e) {
        console.log(e);
    }
    return $date;
}

function tr_hold_print_button($button, printingText) {
    if (typeof jQuery !== 'function' || !$button || !$button.length) {
        return true;
    }
    var now = Date.now();
    var lockedUntil = parseInt($button.data('tr_print_locked_until') || 0);
    if (lockedUntil > now) {
        return false;
    }
    if (typeof $button.data('tr_print_old_text') === 'undefined') {
        $button.data('tr_print_old_text', $button.html());
    }
    $button
        .data('tr_print_locked_until', now + $printer_button_lock_ms)
        .attr('aria-disabled', 'true')
        .addClass('disabled');
    if ($button.is('button,input')) {
        $button.prop('disabled', true);
    }
    $button.html(printingText || 'Printing...');
    return true;
}

function tr_release_print_button($button, resetText) {
    if (typeof jQuery !== 'function' || !$button || !$button.length) {
        return;
    }
    var unlock = function () {
        var oldText = typeof resetText !== 'undefined' ? resetText : $button.data('tr_print_old_text');
        if (typeof oldText !== 'undefined') {
            $button.html(oldText);
        }
        $button
            .removeData('tr_print_old_text')
            .removeData('tr_print_locked_until')
            .removeAttr('aria-disabled')
            .removeClass('disabled');
        if ($button.is('button,input')) {
            $button.prop('disabled', false);
        }
    };
    var remaining = parseInt($button.data('tr_print_locked_until') || 0) - Date.now();
    setTimeout(unlock, remaining > 0 ? remaining : 0);
}

if (typeof window !== 'undefined') {
    window.tr_hold_print_button = tr_hold_print_button;
    window.tr_release_print_button = tr_release_print_button;
}

var errorCallback = function (errorMessage) {
    if (window.console && typeof window.console.warn === 'function') {
        window.console.warn('Printer error:', errorMessage);
    }
}
var print_ok_callback = function () {
}

function setup_printer(onReady, onError) {
    try {

        BrowserPrint.getDefaultDevice("printer", function (device) {
            selected_device = device;
            if (typeof onReady === 'function') {
                onReady(device);
            }
        }, function (error) {
            BrowserPrint.getLocalDevices(function (listdevice) {
                for (var i in listdevice) {
                    selected_device = listdevice[i][0];
                    break;
                }
                if (selected_device) {
                    if (typeof onReady === 'function') {
                        onReady(selected_device);
                    }
                    return;
                }
                if (typeof onError === 'function') {
                    onError(error);
                }
            })
        })

    } catch (e) {
        if (typeof onError === 'function') {
            onError(e);
        }
    }
}

function try_setup_printer(onReady, onError) {
    if ($time_setup_printer != null) {
        clearTimeout($time_setup_printer);
        $time_setup_printer = null;
    }
    setup_printer(onReady, onError);
}

function close_printer_permission_guide() {
    if (!$printer_guide_open) {
        return;
    }
    reset_printer_permission_guide_state();
    if (typeof tb_remove === 'function' && $('#TB_window:visible').length > 0) {
        tb_remove();
        return;
    }
    if ($('body .popupwindow:visible').length > 0 && typeof $.hide_popup === 'function') {
        $.hide_popup();
    }
}

function watch_local_network_permission(permissionResult) {
    if (!permissionResult) {
        return;
    }
    if ($local_network_permission_result === permissionResult) {
        return;
    }

    if ($local_network_permission_result && $local_network_permission_change_handler) {
        if (typeof $local_network_permission_result.removeEventListener === 'function') {
            $local_network_permission_result.removeEventListener('change', $local_network_permission_change_handler);
        } else {
            $local_network_permission_result.onchange = null;
        }
    }

    $local_network_permission_result = permissionResult;
    $local_network_permission_change_handler = function () {
        var status = permissionResult && permissionResult.state ? permissionResult.state : 'prompt';
        set_printer_permission_status(status);
        if (status === 'granted') {
            close_printer_permission_guide();
        }
    };

    if (typeof permissionResult.addEventListener === 'function') {
        permissionResult.addEventListener('change', $local_network_permission_change_handler);
    } else {
        permissionResult.onchange = $local_network_permission_change_handler;
    }
}

function set_printer_permission_status(status) {
    $printer_permission_checked = true;
    $printer_permission_state = status || 'prompt';
    if ($printer_permission_state !== 'granted') {
        selected_device = null;
    } else {
        close_printer_permission_guide();
    }
}

function get_printer_permission_status() {
    if (selected_device) {
        return 'granted';
    }
    if ($printer_request_in_progress) {
        return 'requesting';
    }
    if (!$printer_permission_checked) {
        return 'prompt';
    }
    return $printer_permission_state || 'prompt';
}

function next_printer_guide_flow_id() {
    $printer_guide_flow_id += 1;
    return $printer_guide_flow_id;
}

function is_current_printer_guide_flow_id(flowId) {
    return !flowId || flowId === $printer_guide_flow_id;
}

function query_printer_permission_status(forceRefresh, onDone) {
    var finish = function (status) {
        set_printer_permission_status(status);
        if (typeof onDone === 'function') {
            onDone(status);
        }
    };

    if (should_skip_permission_check()) {
        finish('granted');
        return;
    }

    if (!forceRefresh && $printer_permission_checked) {
        finish(get_printer_permission_status());
        return;
    }

    if (typeof navigator === 'undefined' || !navigator.permissions || typeof navigator.permissions.query !== 'function') {
        $local_network_permission_supported = false;
        finish('granted');
        return;
    }

    try {
        navigator.permissions.query({ name: 'local-network-access' })
            .then(function (result) {
                $local_network_permission_supported = true;
                watch_local_network_permission(result);
                var status = result && result.state ? result.state : 'prompt';
                finish(status);
            })
            .catch(function () {
                $local_network_permission_supported = false;
                finish('granted');
            });
    } catch (e) {
        $local_network_permission_supported = false;
        finish('granted');
    }
}

function start_printer_request(forceRestart, flowId, permissionStatus) {
    if (!is_current_printer_guide_flow_id(flowId)) {
        return;
    }
    if ($printer_request_in_progress) {
        if (!forceRestart) {
            return;
        }
        cancel_printer_request('prompt');
    }

    $printer_request_id += 1;
    var requestId = $printer_request_id;
    $active_printer_request_id = requestId;
    $printer_request_in_progress = true;
    try_setup_printer(function (device) {
        if (!$printer_request_in_progress || requestId !== $active_printer_request_id || !is_current_printer_guide_flow_id(flowId)) {
            return;
        }
        finish_printer_request(device || selected_device, null, requestId, flowId, permissionStatus);
    }, function (error) {
        if (!$printer_request_in_progress || requestId !== $active_printer_request_id || !is_current_printer_guide_flow_id(flowId)) {
            return;
        }
        finish_printer_request(null, error, requestId, flowId, permissionStatus);
    });

    $time_setup_printer = setTimeout(function () {
        if ($printer_request_in_progress && requestId === $active_printer_request_id && !selected_device && is_current_printer_guide_flow_id(flowId)) {
            finish_printer_request(null, 'timeout', requestId, flowId, permissionStatus);
        }
    }, 10000);
}

function cancel_printer_request(status) {
    $printer_request_in_progress = false;
    $active_printer_request_id = 0;
    if ($time_setup_printer != null) {
        clearTimeout($time_setup_printer);
        $time_setup_printer = null;
    }

    set_printer_permission_status(status);
}

function reset_printer_permission_guide_state() {
    $printer_guide_open = false;
    $printer_guide_status = null;
}

function mark_printer_permission_guide_dismissed() {
    $printer_guide_dismissed = true;
    $printer_guide_flow_id += 1;
    reset_printer_permission_guide_state();
}

function sync_printer_permission_guide_state() {
    if (typeof jQuery !== 'function') {
        return;
    }
    var $ = jQuery;
    var thickboxOpen = typeof tb_show === 'function' && $('#TB_window:visible').length > 0;
    var popupOpen = $('body .popupwindow:visible').length > 0;
    if (!thickboxOpen && !popupOpen) {
        reset_printer_permission_guide_state();
    }
}

function parse_printer_error_detail(error) {
    if (typeof error === 'undefined' || error === null) {
        return '';
    }
    if (typeof error === 'string') {
        return error;
    }
    if (typeof error.message === 'string' && error.message) {
        return error.message;
    }
    try {
        return JSON.stringify(error);
    } catch (e) {
        return String(error);
    }
}

function resolve_printer_unavailable_status(error) {
    var detail = parse_printer_error_detail(error).toLowerCase();
    if (detail.indexOf('timeout') > -1) {
        return 'unavailable_timeout';
    }
    if (
        detail.indexOf('browserprint') > -1 ||
        detail.indexOf('localhost') > -1 ||
        detail.indexOf('127.0.0.1') > -1 ||
        detail.indexOf('failed to fetch') > -1 ||
        detail.indexOf('networkerror') > -1
    ) {
        return 'unavailable_service';
    }
    if (
        detail.indexOf('offline') > -1 ||
        detail.indexOf('not found') > -1 ||
        detail.indexOf('not connected') > -1 ||
        detail.indexOf('disconnected') > -1 ||
        detail.indexOf('no such') > -1
    ) {
        return 'unavailable_printer';
    }
    if (detail.indexOf('send') > -1 || detail.indexOf('write') > -1) {
        return 'unavailable_send';
    }
    return 'unavailable';
}

function show_printer_permission_guide(status, flowId, errorDetail) {
    if (typeof jQuery !== 'function') {
        return;
    }
    if (!is_current_printer_guide_flow_id(flowId)) {
        return;
    }

    var now = Date.now();
    sync_printer_permission_guide_state();

    if ($printer_guide_dismissed) {
        return;
    }

    if (!$printer_guide_open && (now - $printer_guide_last_shown_at) < 700) {
        return;
    }

    if ($printer_guide_open && $printer_guide_status === status) {
        return;
    }

    var $ = jQuery;
    var messages = {
        prompt: {
            title: 'Printer Permission',
            heading: 'Click "Allow" to continue printing.',
            body: 'A browser permission prompt should appear. Approve it so this page can connect to BrowserPrint and your Zebra printer.',
            steps: [
                'Keep this page open and look for the permission popup near the address bar.',
                'Click "Allow" when the browser asks to connect to devices on your local network.',
                'Wait a moment. Printing will continue automatically after access is granted.'
            ],
            tip: 'If you do not see the prompt, check whether the browser is hiding it behind the address bar icon.'
        },
        denied: {
            title: 'Printer Permission Needed',
            heading: 'Printer access is blocked.',
            body: 'Permission was denied before, so the browser will not reconnect until you enable it again in site settings.',
            steps: [
                'Click the site controls icon to the left of the address bar.',
                'Open site settings or permissions for this page.',
                'Turn on local network access or "Apps on device", then click "Check Permission Again".'
            ],
            tip: 'After changing the permission, you may need to close the browser prompt and retry Print label once more.'
        },
        requesting: {
            title: 'Waiting For Permission',
            heading: 'Please respond to the browser prompt.',
            body: 'The permission request is still pending. Printing will resume as soon as you allow access.',
            steps: [
                'Do not close this page yet.',
                'Find the browser prompt and click "Allow".',
                'If the prompt disappeared, click "Check Permission Again" to request it once more.'
            ],
            tip: 'Some browsers show the prompt next to the address bar instead of in the middle of the page.'
        },
        unavailable: {
            title: 'Printer Not Available',
            heading: 'No printer connection is available.',
            body: 'The browser could not find BrowserPrint or a Zebra printer after permission was checked.',
            steps: [
                'Confirm BrowserPrint is running on this computer.',
                'Make sure the Zebra printer is powered on and connected.',
                'Then click "Check Permission Again" to retry the connection.'
            ],
            tip: 'If permission is already allowed but no printer is found, the issue is usually BrowserPrint not running or the printer being offline.'
        },
        unavailable_timeout: {
            title: 'Printer Connection Timeout',
            heading: 'Timed out while waiting for printer connection.',
            body: 'The browser waited too long for BrowserPrint or printer response.',
            steps: [
                'Make sure BrowserPrint is open and running.',
                'Check the printer is powered on and connected (USB/LAN).',
                'Click "Check Permission Again" to retry.'
            ],
            tip: 'Timeout usually means the local BrowserPrint service is not reachable or the printer is sleeping/offline.'
        },
        unavailable_service: {
            title: 'BrowserPrint Service Not Reachable',
            heading: 'Cannot connect to BrowserPrint service.',
            body: 'The page cannot reach the local BrowserPrint service required to send print commands.',
            steps: [
                'Start or restart BrowserPrint on this computer.',
                'Allow BrowserPrint through local firewall/antivirus if blocked.',
                'Retry with "Check Permission Again".'
            ],
            tip: 'If BrowserPrint is not running, permission can be granted but printing will still fail.'
        },
        unavailable_printer: {
            title: 'Printer Device Not Found',
            heading: 'BrowserPrint is running but printer was not found.',
            body: 'No active Zebra printer is available from BrowserPrint device list.',
            steps: [
                'Power on the Zebra printer and reconnect cable/network.',
                'Ensure OS can see the printer and printer is not paused.',
                'Click "Check Permission Again" after reconnecting.'
            ],
            tip: 'Try unplugging/replugging USB or restarting printer if it stays offline.'
        },
        unavailable_send: {
            title: 'Printer Send Error',
            heading: 'Failed while sending data to printer.',
            body: 'Printer connection was created but label data could not be sent successfully.',
            steps: [
                'Check printer media status (paper/label/ribbon) and connection stability.',
                'Restart BrowserPrint and the printer.',
                'Click "Check Permission Again" then print again.'
            ],
            tip: 'Intermittent cable/network issues often trigger send errors.'
        }
    };
    var message = messages[status] || messages.unavailable;
    var stepsHtml = '';
    var promptVisualHtml = ''
        + '<div style="background:#f6f9fc;border-radius:14px;padding:0;">'
        + '<svg viewBox="70 55 760 450" width="100%" height="290" preserveAspectRatio="xMidYMin meet" aria-hidden="true">'
        + '<defs><linearGradient id="trPromptBg" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#f6f8fb"></stop><stop offset="100%" stop-color="#eef2f7"></stop></linearGradient></defs>'
        + '<rect width="900" height="620" fill="url(#trPromptBg)"></rect>'
        + '<rect x="70" y="55" width="760" height="450" rx="18" fill="#ffffff" stroke="#d7dee8" stroke-width="2"></rect>'
        + '<rect x="70" y="55" width="760" height="38" rx="18" fill="#e9edf3"></rect>'
        + '<circle cx="100" cy="74" r="6" fill="#ef4444"></circle><circle cx="122" cy="74" r="6" fill="#f59e0b"></circle><circle cx="144" cy="74" r="6" fill="#22c55e"></circle>'
        + '<rect x="180" y="65" width="470" height="20" rx="10" fill="#ffffff" stroke="#d4dbe5"></rect>'
        + '<circle cx="198" cy="75" r="6" fill="#93c5fd"></circle>'
        + '<text x="214" y="80" font-size="13" font-family="Arial, sans-serif" fill="#475569">lab.example.com</text>'
        + '<rect x="95" y="112" width="710" height="368" rx="12" fill="#f8fafc" stroke="#e2e8f0"></rect>'
        + '<text x="120" y="150" font-size="28" font-family="Arial, sans-serif" font-weight="700" fill="#0f172a">Receiving Samples</text>'
        + '<rect x="120" y="176" width="180" height="34" rx="8" fill="#2563eb"></rect>'
        + '<text x="156" y="198" font-size="16" font-family="Arial, sans-serif" font-weight="700" fill="#ffffff">Print label</text>'
        + '<rect x="120" y="235" width="660" height="18" rx="8" fill="#e2e8f0"></rect><rect x="120" y="272" width="620" height="18" rx="8" fill="#e2e8f0"></rect><rect x="120" y="309" width="640" height="18" rx="8" fill="#e2e8f0"></rect>'
        + '<rect x="385" y="122" width="415" height="296" rx="16" fill="#ffffff" stroke="#d7dee8" stroke-width="2"></rect>'
        + '<circle cx="422" cy="162" r="13" fill="#dbeafe"></circle>'
        + '<path d="M416 162 h12 M422 156 v12" stroke="#2563eb" stroke-width="2.2" stroke-linecap="round"></path>'
        + '<text x="450" y="172" font-size="20" font-family="Arial, sans-serif" font-weight="700" fill="#0f172a">lab.example.com wants to:</text>'
        + '<text x="426" y="214" font-size="17" font-family="Arial, sans-serif" fill="#334155">Find and connect to devices on your</text>'
        + '<text x="426" y="238" font-size="17" font-family="Arial, sans-serif" fill="#334155">local network</text>'
        + '<text x="426" y="264" font-size="15" font-family="Arial, sans-serif" fill="#64748b">Needed for BrowserPrint and Zebra label printing.</text>'
        + '<rect x="426" y="305" width="106" height="42" rx="9" fill="#f1f5f9" stroke="#cbd5e1"></rect><text x="458" y="332" font-size="17" font-family="Arial, sans-serif" fill="#334155">Block</text>'
        + '<rect x="550" y="305" width="136" height="42" rx="9" fill="#1a73e8"></rect><text x="592" y="332" font-size="17" font-family="Arial, sans-serif" font-weight="700" fill="#ffffff">Allow</text>'
        + '<rect x="544" y="299" width="148" height="54" rx="12" fill="none" stroke="#ef4444" stroke-width="4"></rect>'
        + '<rect x="538" y="293" width="160" height="66" rx="14" fill="none" stroke="#fca5a5" stroke-width="2" opacity="0.9"></rect>'
        + '<rect x="668" y="262" width="118" height="30" rx="8" fill="#fee2e2" stroke="#fca5a5"></rect>'
        + '<text x="688" y="282" font-size="14" font-family="Arial, sans-serif" font-weight="700" fill="#b91c1c">Click Allow</text>'
        + '<path d="M668 277 C654 284, 646 291, 638 300" fill="none" stroke="#ef4444" stroke-width="4" stroke-linecap="round"></path>'
        + '</svg>'
        + '</div>';
    var deniedVisualHtml = ''
        + '<div style="background:#f6f9fc;border-radius:14px;padding:0;">'
        + '<svg viewBox="70 55 760 450" width="100%" height="290" preserveAspectRatio="xMidYMin meet" aria-hidden="true">'
        + '<defs><linearGradient id="trDeniedBg" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#f6f8fb"></stop><stop offset="100%" stop-color="#eef2f7"></stop></linearGradient></defs>'
        + '<rect width="900" height="620" fill="url(#trDeniedBg)"></rect>'
        + '<rect x="70" y="55" width="760" height="450" rx="18" fill="#ffffff" stroke="#d7dee8" stroke-width="2"></rect>'
        + '<rect x="70" y="55" width="760" height="38" rx="18" fill="#e9edf3"></rect>'
        + '<circle cx="100" cy="74" r="6" fill="#ef4444"></circle><circle cx="122" cy="74" r="6" fill="#f59e0b"></circle><circle cx="144" cy="74" r="6" fill="#22c55e"></circle>'
        + '<rect x="180" y="65" width="470" height="20" rx="10" fill="#ffffff" stroke="#d4dbe5"></rect>'
        + '<g transform="translate(190 68)"><rect x="0" y="0" width="14" height="14" rx="3" fill="#dbeafe" stroke="#60a5fa"></rect><path d="M4 7 h6 M7 4 v6" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round"></path></g>'
        + '<text x="214" y="80" font-size="13" font-family="Arial, sans-serif" fill="#475569">lab.example.com</text>'
        + '<rect x="95" y="112" width="710" height="368" rx="12" fill="#f8fafc" stroke="#e2e8f0"></rect>'
        + '<text x="120" y="150" font-size="28" font-family="Arial, sans-serif" font-weight="700" fill="#0f172a">Print label blocked</text>'
        + '<rect x="120" y="176" width="320" height="36" rx="8" fill="#fee2e2"></rect><text x="140" y="200" font-size="16" font-family="Arial, sans-serif" font-weight="700" fill="#b91c1c">Permission denied previously</text>'
        + '<rect x="260" y="142" width="360" height="288" rx="16" fill="#ffffff" stroke="#d7dee8" stroke-width="2"></rect>'
        + '<text x="286" y="180" font-size="19" font-family="Arial, sans-serif" font-weight="700" fill="#0f172a">Site settings</text>'
        + '<text x="300" y="224" font-size="16" font-family="Arial, sans-serif" fill="#334155">Apps on device</text>'
        + '<rect x="500" y="210" width="92" height="30" rx="15" fill="#1a73e8"></rect><circle cx="576" cy="225" r="10" fill="#ffffff"></circle>'
        + '<text x="300" y="272" font-size="16" font-family="Arial, sans-serif" fill="#334155">Local network</text>'
        + '<rect x="500" y="258" width="92" height="30" rx="15" fill="#1a73e8"></rect><circle cx="576" cy="273" r="10" fill="#ffffff"></circle>'
        + '<text x="300" y="320" font-size="16" font-family="Arial, sans-serif" fill="#334155">Pop-ups and redirects</text><text x="542" y="320" font-size="14" font-family="Arial, sans-serif" fill="#64748b">Ask</text>'
        + '<line x1="298" y1="342" x2="600" y2="342" stroke="#e2e8f0"></line>'
        + '<text x="300" y="374" font-size="13" font-family="Arial, sans-serif" fill="#64748b">After enabling these toggles, go back to this page</text>'
        + '<text x="300" y="394" font-size="13" font-family="Arial, sans-serif" fill="#64748b">then click "Check Permission Again".</text>'
        + '<path d="M205 78 C225 105, 250 130, 290 170" fill="none" stroke="#dc2626" stroke-width="6" stroke-linecap="round"></path>'
        + '<path d="M289 170 l-16 -2 M289 170 l-6 -15" fill="none" stroke="#dc2626" stroke-width="6" stroke-linecap="round"></path>'
        + '<path d="M654 226 C702 226, 722 226, 590 226" fill="none" stroke="#dc2626" stroke-width="6" stroke-linecap="round"></path>'
        + '<path d="M592 226 l15 -9 M592 226 l15 9" fill="none" stroke="#dc2626" stroke-width="6" stroke-linecap="round"></path>'
        + '</svg>'
        + '</div>';
    var timeoutVisualHtml = ''
        + '<div style="background:#f8fafc;border-radius:14px;padding:0;">'
        + '<svg viewBox="0 0 800 380" width="100%" height="290" preserveAspectRatio="xMidYMin meet" aria-hidden="true">'
        + '<rect x="20" y="20" width="760" height="340" rx="18" fill="#ffffff" stroke="#dbe3ee" stroke-width="2"></rect>'
        + '<rect x="20" y="20" width="760" height="42" rx="18" fill="#eef2f7"></rect>'
        + '<circle cx="50" cy="41" r="6" fill="#ef4444"></circle><circle cx="72" cy="41" r="6" fill="#f59e0b"></circle><circle cx="94" cy="41" r="6" fill="#22c55e"></circle>'
        + '<text x="130" y="46" font-size="15" font-family="Arial, sans-serif" fill="#334155">Printer Connection Timeout</text>'
        + '<rect x="60" y="96" width="680" height="210" rx="14" fill="#fff7ed" stroke="#fdba74"></rect>'
        + '<circle cx="120" cy="150" r="28" fill="#fed7aa"></circle>'
        + '<path d="M120 132 v22" stroke="#c2410c" stroke-width="6" stroke-linecap="round"></path>'
        + '<circle cx="120" cy="164" r="3.8" fill="#c2410c"></circle>'
        + '<text x="165" y="142" font-size="24" font-family="Arial, sans-serif" font-weight="700" fill="#9a3412">No response from printer service</text>'
        + '<text x="165" y="176" font-size="17" font-family="Arial, sans-serif" fill="#7c2d12">BrowserPrint did not return an active device in time.</text>'
        + '<rect x="165" y="204" width="210" height="40" rx="10" fill="#ea580c"></rect>'
        + '<text x="200" y="230" font-size="17" font-family="Arial, sans-serif" font-weight="700" fill="#ffffff">Retry Connection</text>'
        + '<rect x="390" y="204" width="170" height="40" rx="10" fill="#fff" stroke="#fb923c"></rect>'
        + '<text x="435" y="230" font-size="16" font-family="Arial, sans-serif" fill="#9a3412">Check Cable</text>'
        + '</svg>'
        + '</div>';
    var serviceVisualHtml = ''
        + '<div style="background:#f8fafc;border-radius:14px;padding:0;">'
        + '<svg viewBox="0 0 800 380" width="100%" height="290" preserveAspectRatio="xMidYMin meet" aria-hidden="true">'
        + '<rect x="20" y="20" width="760" height="340" rx="18" fill="#ffffff" stroke="#dbe3ee" stroke-width="2"></rect>'
        + '<rect x="20" y="20" width="760" height="42" rx="18" fill="#eef2f7"></rect>'
        + '<circle cx="50" cy="41" r="6" fill="#ef4444"></circle><circle cx="72" cy="41" r="6" fill="#f59e0b"></circle><circle cx="94" cy="41" r="6" fill="#22c55e"></circle>'
        + '<text x="130" y="46" font-size="15" font-family="Arial, sans-serif" fill="#334155">BrowserPrint Service</text>'
        + '<rect x="60" y="96" width="680" height="210" rx="14" fill="#eff6ff" stroke="#93c5fd"></rect>'
        + '<rect x="100" y="126" width="190" height="136" rx="14" fill="#dbeafe" stroke="#60a5fa"></rect>'
        + '<rect x="132" y="156" width="126" height="12" rx="6" fill="#93c5fd"></rect>'
        + '<rect x="132" y="182" width="100" height="12" rx="6" fill="#93c5fd"></rect>'
        + '<path d="M310 194 h78" stroke="#2563eb" stroke-width="6" stroke-linecap="round"></path>'
        + '<path d="M375 182 l22 12 l-22 12" fill="none" stroke="#2563eb" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"></path>'
        + '<rect x="420" y="126" width="250" height="136" rx="14" fill="#ffffff" stroke="#60a5fa" stroke-width="2"></rect>'
        + '<circle cx="450" cy="159" r="10" fill="#ef4444"></circle>'
        + '<path d="M445 154 l10 10 M455 154 l-10 10" stroke="#fff" stroke-width="2.5"></path>'
        + '<text x="472" y="164" font-size="19" font-family="Arial, sans-serif" font-weight="700" fill="#1e3a8a">Cannot reach BrowserPrint</text>'
        + '<text x="450" y="194" font-size="15" font-family="Arial, sans-serif" fill="#334155">Start/restart local BrowserPrint service.</text>'
        + '<text x="450" y="216" font-size="15" font-family="Arial, sans-serif" fill="#334155">Then click Check Permission Again.</text>'
        + '</svg>'
        + '</div>';
    var printerVisualHtml = ''
        + '<div style="background:#f8fafc;border-radius:14px;padding:0;">'
        + '<svg viewBox="0 0 800 380" width="100%" height="290" preserveAspectRatio="xMidYMin meet" aria-hidden="true">'
        + '<rect x="20" y="20" width="760" height="340" rx="18" fill="#ffffff" stroke="#dbe3ee" stroke-width="2"></rect>'
        + '<rect x="20" y="20" width="760" height="42" rx="18" fill="#eef2f7"></rect>'
        + '<circle cx="50" cy="41" r="6" fill="#ef4444"></circle><circle cx="72" cy="41" r="6" fill="#f59e0b"></circle><circle cx="94" cy="41" r="6" fill="#22c55e"></circle>'
        + '<text x="130" y="46" font-size="15" font-family="Arial, sans-serif" fill="#334155">Printer Device Status</text>'
        + '<rect x="60" y="96" width="680" height="210" rx="14" fill="#fef2f2" stroke="#fca5a5"></rect>'
        + '<rect x="110" y="130" width="220" height="132" rx="14" fill="#fee2e2" stroke="#f87171"></rect>'
        + '<rect x="132" y="152" width="176" height="72" rx="10" fill="#fff" stroke="#fca5a5"></rect>'
        + '<circle cx="220" cy="238" r="9" fill="#ef4444"></circle>'
        + '<rect x="364" y="132" width="320" height="130" rx="14" fill="#ffffff" stroke="#fca5a5"></rect>'
        + '<text x="390" y="166" font-size="23" font-family="Arial, sans-serif" font-weight="700" fill="#991b1b">Printer Offline / Not Found</text>'
        + '<text x="390" y="196" font-size="16" font-family="Arial, sans-serif" fill="#7f1d1d">Turn on printer and reconnect USB/LAN.</text>'
        + '<text x="390" y="218" font-size="16" font-family="Arial, sans-serif" fill="#7f1d1d">Verify OS can detect this Zebra printer.</text>'
        + '</svg>'
        + '</div>';
    var sendErrorVisualHtml = ''
        + '<div style="background:#f8fafc;border-radius:14px;padding:0;">'
        + '<svg viewBox="0 0 800 380" width="100%" height="290" preserveAspectRatio="xMidYMin meet" aria-hidden="true">'
        + '<rect x="20" y="20" width="760" height="340" rx="18" fill="#ffffff" stroke="#dbe3ee" stroke-width="2"></rect>'
        + '<rect x="20" y="20" width="760" height="42" rx="18" fill="#eef2f7"></rect>'
        + '<circle cx="50" cy="41" r="6" fill="#ef4444"></circle><circle cx="72" cy="41" r="6" fill="#f59e0b"></circle><circle cx="94" cy="41" r="6" fill="#22c55e"></circle>'
        + '<text x="130" y="46" font-size="15" font-family="Arial, sans-serif" fill="#334155">Send Label Data</text>'
        + '<rect x="60" y="96" width="680" height="210" rx="14" fill="#fefce8" stroke="#fde047"></rect>'
        + '<rect x="104" y="132" width="230" height="130" rx="14" fill="#fef9c3" stroke="#facc15"></rect>'
        + '<text x="130" y="172" font-size="16" font-family="Courier New, monospace" fill="#713f12">^XA ... ^XZ</text>'
        + '<path d="M350 196 h84" stroke="#ca8a04" stroke-width="6" stroke-linecap="round"></path>'
        + '<path d="M420 184 l22 12 l-22 12" fill="none" stroke="#ca8a04" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"></path>'
        + '<rect x="438" y="130" width="252" height="132" rx="14" fill="#ffffff" stroke="#facc15"></rect>'
        + '<circle cx="466" cy="162" r="10" fill="#f59e0b"></circle>'
        + '<path d="M466 156 v8" stroke="#fff" stroke-width="2.4" stroke-linecap="round"></path>'
        + '<circle cx="466" cy="169" r="1.8" fill="#fff"></circle>'
        + '<text x="488" y="167" font-size="18" font-family="Arial, sans-serif" font-weight="700" fill="#713f12">Send failed</text>'
        + '<text x="466" y="194" font-size="13" font-family="Arial, sans-serif" fill="#713f12">Check media and printer</text>'
        + '<text x="466" y="211" font-size="13" font-family="Arial, sans-serif" fill="#713f12">connection stability.</text>'
        + '<text x="466" y="232" font-size="13" font-family="Arial, sans-serif" fill="#713f12">Restart printer, then retry print.</text>'
        + '</svg>'
        + '</div>';
    var visualHtml = promptVisualHtml;
    if (status === 'denied') {
        visualHtml = deniedVisualHtml;
    } else if (status === 'unavailable_timeout') {
        visualHtml = timeoutVisualHtml;
    } else if (status === 'unavailable_service') {
        visualHtml = serviceVisualHtml;
    } else if (status === 'unavailable_printer' || status === 'unavailable') {
        visualHtml = printerVisualHtml;
    } else if (status === 'unavailable_send') {
        visualHtml = sendErrorVisualHtml;
    }
    var helpTitle = status === 'prompt' || status === 'denied' || status === 'requesting'
        ? 'How to allow printer access'
        : 'How to fix printer connection';
    var detailText = parse_printer_error_detail(errorDetail);
    var detailHtml = '';
    if (detailText) {
        detailHtml = '<div style="margin-top:10px;padding:10px 12px;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;color:#9a3412;font-size:12px;line-height:1.5;"><strong>Technical detail:</strong> '
            + $('<div/>').text(detailText).html()
            + '</div>';
    }
    var i;

    for (i = 0; i < message.steps.length; i++) {
        stepsHtml += ''
            + '<div style="display:flex;align-items:flex-start;gap:10px;margin-top:8px;">'
            + '<div style="width:24px;height:24px;border-radius:50%;background:#1d4ed8;color:#fff;font-size:13px;font-weight:700;line-height:24px;text-align:center;flex:0 0 24px;">' + (i + 1) + '</div>'
            + '<div style="color:#334155;font-size:14px;line-height:1.5;">' + message.steps[i] + '</div>'
            + '</div>';
    }

    $('#tr_printer_permission_guide').remove();
    $('body').append(
        '<div id="tr_printer_permission_guide" style="display:none;">'
        + '<div style="max-width:920px;font-family:Arial,sans-serif;">'
        + '<div style="color:#b91c1c;font-size:24px;font-weight:700;line-height:1.3;margin-bottom:8px;">' + message.heading + '</div>'
        + '<div style="color:#334155;font-size:15px;line-height:1.6;margin-bottom:10px;">' + message.body + '</div>'
        + '<div style="margin-bottom:10px;">'
        + '<div style="width:100%;">' + visualHtml + '</div>'
        + '</div>'
        + '<div style="width:100%;">'
        + '<div style="color:#0f172a;font-size:16px;font-weight:700;">' + helpTitle + '</div>'
        + stepsHtml
        + detailHtml
        + '<div style="margin-top:12px;padding:10px 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;color:#1e3a8a;font-size:13px;line-height:1.5;">' + message.tip + '</div>'
        + '</div>'
        + '<div style="margin-top:18px;">'
        + '<a href="#" class="button button-primary tr_printer_permission_retry">Check Permission Again</a> '
        + '<a href="#" class="button tr_printer_permission_close">Close</a>'
        + '</div>'
        + '</div>'
        + '</div>'
    );

    $('body')
        .off('click.tr_printer_permission_retry')
        .on('click.tr_printer_permission_retry', '.tr_printer_permission_retry', function () {
            $printer_guide_dismissed = false;
            var flowId = next_printer_guide_flow_id();
            if (typeof tb_remove === 'function') {
                reset_printer_permission_guide_state();
                tb_remove();
            }
            query_printer_permission_status(true, function (status) {
                if (!is_current_printer_guide_flow_id(flowId)) {
                    return;
                }
                if (status === 'granted') {
                    start_printer_request(false, flowId, status);
                    return;
                }
                if (status === 'denied') {
                    cancel_printer_request('denied');
                    show_printer_permission_guide('denied', flowId);
                    return;
                }
                show_printer_permission_guide('prompt', flowId);
                start_printer_request(true, flowId, status);
            });
            return false;
        })
        .off('click.tr_printer_permission_close')
        .on('click.tr_printer_permission_close', '.tr_printer_permission_close', function () {
            mark_printer_permission_guide_dismissed();
            if (typeof tb_remove === 'function') {
                tb_remove();
            }
            return false;
        })
        .off('click.tr_printer_permission_force_dismiss')
        .on('click.tr_printer_permission_force_dismiss', '#TB_closeWindowButton, #TB_overlay', function () {
            if ($printer_guide_open) {
                mark_printer_permission_guide_dismissed();
            }
        })
        .off('keyup.tr_printer_permission_force_dismiss')
        .on('keyup.tr_printer_permission_force_dismiss', function (event) {
            if (event && event.key === 'Escape' && $printer_guide_open) {
                mark_printer_permission_guide_dismissed();
            }
        });

    if (typeof tb_show === 'function' && $('#TB_window:visible').length > 0 && $('#TB_ajaxContent').length > 0) {
        $('#TB_ajaxContent').html($('#tr_printer_permission_guide').html());
        $printer_guide_open = true;
        $printer_guide_status = status;
        $printer_guide_last_shown_at = now;
    } else if (typeof tb_show === 'function') {
        tb_show(message.title, '#TB_inline?inlineId=tr_printer_permission_guide');
        $printer_guide_open = true;
        $printer_guide_status = status;
        $printer_guide_last_shown_at = now;
    }
}

function show_printer_connection_error_guide(reason) {
    $printer_guide_dismissed = false;
    selected_device = null;
    set_printer_permission_status('unavailable');
    show_printer_permission_guide(resolve_printer_unavailable_status(reason), next_printer_guide_flow_id(), reason);
    if (window.console && typeof window.console.log === 'function') {
        window.console.log('Printer connection issue:', reason);
    }
}

function show_printer_send_success_notice(detailText, forceShow) {
    if ($disable_successful_print_popup) {
        return;
    }
    if (typeof jQuery !== 'function' || typeof tb_show !== 'function') {
        return;
    }
    if ($printer_bulk_printing && !forceShow) {
        return;
    }
    var now = Date.now();
    if (!forceShow && (now - $printer_success_notice_last_at) < 1200) {
        return;
    }
    var $tbVisible = $('#TB_window:visible');
    if ($tbVisible.length > 0 && $('#TB_ajaxContent .tr-printer-success-notice').length === 0) {
        return;
    }
    $printer_success_notice_last_at = now;
    var detail = parse_printer_error_detail(detailText);
    var safeDetail = detail ? $('<div/>').text(detail).html() : '';
    $('#tr_printer_success_notice').remove();
    $('body').append(
        '<div id="tr_printer_success_notice" style="display:none;">'
        + '<div class="tr-printer-success-notice" style="max-width:620px;font-family:Arial,sans-serif;">'
        + '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:12px;padding:12px 14px;">'
        + '<div style="display:flex;align-items:center;gap:9px;color:#166534;font-size:21px;font-weight:700;line-height:1.25;">'
        + '<span style="display:inline-flex;width:30px;height:30px;border-radius:999px;background:#22c55e;color:#fff;align-items:center;justify-content:center;font-size:20px;">&#10003;</span>'
        + '<span>Print data sent successfully</span>'
        + '</div>'
        + '<div style="margin-top:5px;color:#166534;font-size:14px;line-height:1.45;">'
        + 'Label data has been sent to printer.'
        + '</div>'
        + (safeDetail ? ('<div style="margin-top:8px;padding:7px 9px;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:8px;color:#166534;font-size:12px;line-height:1.35;"><strong>Detail:</strong> ' + safeDetail + '</div>') : '')
        + '</div>'
        + '</div>'
        + '</div>'
    );
    if ($('#TB_window:visible').length > 0 && $('#TB_ajaxContent .tr-printer-success-notice').length > 0) {
        $('#TB_ajaxContent').html($('#tr_printer_success_notice').html());
    } else {
        tb_show('', '#TB_inline?inlineId=tr_printer_success_notice');
    }
    setTimeout(function () {
        if ($('#TB_window:visible').length > 0 && $('#TB_ajaxContent .tr-printer-success-notice').length > 0) {
            $('#TB_ajaxContent').css({
                height: 'auto',
                minHeight: '0',
                maxHeight: '180px',
                paddingTop: '8px',
                paddingBottom: '8px',
                overflow: 'hidden'
            });
            $('#TB_window').css({
                maxHeight: '230px'
            });
        }
    }, 20);
    if ($printer_success_notice_timeout != null) {
        clearTimeout($printer_success_notice_timeout);
        $printer_success_notice_timeout = null;
    }
    $printer_success_notice_timeout = setTimeout(function () {
        if ($('#TB_window:visible').length > 0 && $('#TB_ajaxContent .tr-printer-success-notice').length > 0 && typeof tb_remove === 'function') {
            tb_remove();
        }
        $printer_success_notice_timeout = null;
    }, 1000);
}

function finish_printer_request(device, error, requestId, flowId, permissionStatus) {
    if (requestId && requestId !== $active_printer_request_id) {
        return;
    }
    var waiters = $printer_waiters.slice(0);
    $printer_waiters = [];
    $printer_request_in_progress = false;
    $active_printer_request_id = 0;
    if ($time_setup_printer != null) {
        clearTimeout($time_setup_printer);
        $time_setup_printer = null;
    }

    var allowDevice = should_skip_permission_check() || permissionStatus === 'granted' || get_printer_permission_status() === 'granted';
    if (device && allowDevice) {
        set_printer_permission_status('granted');
        if (typeof tb_remove === 'function') {
            reset_printer_permission_guide_state();
            tb_remove();
        }
    } else {
        var blockedStatus = permissionStatus || 'prompt';
        if (!allowDevice) {
            selected_device = null;
            set_printer_permission_status(blockedStatus);
            if (blockedStatus === 'denied') {
                show_printer_permission_guide('denied', flowId, 'permission-denied');
            } else {
                show_printer_permission_guide('prompt', flowId, 'permission-pending');
            }
            error = 'permission-' + blockedStatus;
        } else {
            set_printer_permission_status('unavailable');
            show_printer_permission_guide(resolve_printer_unavailable_status(error), flowId, error);
        }
    }

    for (var i = 0; i < waiters.length; i++) {
        var waiter = waiters[i];
        if (device && allowDevice && typeof waiter.ready === 'function') {
            waiter.ready(device);
        } else if (typeof waiter.error === 'function') {
            waiter.error(error);
        }
    }
}

function ensure_printer(onReady, onError) {
    $printer_guide_dismissed = false;
    var flowId = next_printer_guide_flow_id();

    if (selected_device) {
        if (typeof onReady === 'function') {
            onReady(selected_device);
        }
        return;
    }

    $printer_waiters.push({
        ready: onReady,
        error: onError
    });

    if (should_skip_permission_check()) {
        start_printer_request(false, flowId, 'granted');
        return;
    }

    if ($printer_request_in_progress) {
        show_printer_permission_guide('requesting', flowId);
        start_printer_request(true, flowId, get_printer_permission_status());
        return;
    }

    query_printer_permission_status(true, function (status) {
        if (!is_current_printer_guide_flow_id(flowId)) {
            return;
        }
        if (status === 'granted') {
            start_printer_request(false, flowId, status);
            return;
        }
        if (status === 'denied') {
            cancel_printer_request('denied');
            show_printer_permission_guide('denied', flowId);
            for (var i = 0; i < $printer_waiters.length; i++) {
                if (typeof $printer_waiters[i].error === 'function') {
                    $printer_waiters[i].error('permission-denied');
                }
            }
            $printer_waiters = [];
            return;
        }
        show_printer_permission_guide('prompt', flowId);
        start_printer_request(true, flowId, status);
    });
}

function printLabel($barcode, $patient_name, $position, $doc, $panels, $cul, printedFunc, errorFunc) {
    if (!printedFunc) {
        printedFunc = print_ok_callback;
    }
    if (!errorFunc) {
        errorFunc = errorCallback;
    }
    if (!selected_device) {
        ensure_printer(function () {
            printLabel($barcode, $patient_name, $position, $doc, $panels, $cul, printedFunc, errorFunc);
        }, function (reason) {
            var reasonText = (typeof reason === 'string') ? reason : '';
            if (reasonText.indexOf('permission-') !== 0) {
                show_printer_connection_error_guide(reason);
            }
            if (typeof errorFunc === 'function') {
                errorFunc(reason);
            }
        });
        return;
    }
    var $spbc = 10;
    var $bcsize = 2;
    var $bc_height = 120;
    var $bc_x = 49;
    var $bc_y = 20;
    var $wpatient = 382;
    var $xpatient = 10;
    var $ypatient = 170;
    var $fontpatient = 20;
    var $fontpanel = 30;
    var $fontposition = 30;
    var $xposition = 10;
    var $yposition = 160;
    if ($use_zebra_size == '3x1.5') {
        $spbc = 14;
        $bcsize = 3;
        $bc_height = 140;
        $bc_x = 66;
        $bc_y = 50;
        $wpatient = 578;
        $xpatient = 20;
        $ypatient = 230;
        $fontpanel = 30;
        $fontposition = 30;
        $fontpatient = 30;
        $xposition = 10;
        $yposition = 200;
    }
    else if ($use_zebra_size == '3x2') {
        $spbc = 14;
        $bcsize = 3;
        $bc_height = 200;
        $bc_x = 66;
        $bc_y = 60;
        $wpatient = 578;
        $xpatient = 20;
        $ypatient = 320;
        $fontpanel = 30;
        $fontposition = 30;
        $fontpatient = 30;
        $xposition = 10;
        $yposition = 278;
    }
    else if ($use_zebra_size == '4x2') {
        $spbc = 19;
        $bcsize = 4;
        $bc_height = 200;
        $bc_x = 94;
        $bc_y = 60;
        $wpatient = 770;
        $xpatient = 20;
        $ypatient = 320;
        $fontpanel = 30;
        $fontposition = 30;
        $fontpatient = 38;
        $xposition = 30;
        $yposition = 278;
    }

    var $yculposition = $yposition;

    $bc_x += $spbc * (11 - $barcode.length);

    var $zpl_string = "^XA";
    $zpl_string += "^FX^BY" + $bcsize + ",2," + $bc_height + "^FO" + $bc_x + "," + $bc_y + "^BCN^FD" + $barcode + "^FS";
    $zpl_string += "^FO" + $xpatient + "," + $ypatient + "^A0N,0," + $fontpatient + "^FB" + $wpatient + ",3,0,C,0^FD" + $patient_name + "^FS";

    if ($add_doc_to_label && $doc) {
        $doc = trpdd_formatdate($doc, true);
        $zpl_string += "^FO" + $xpatient + ",8^A0N,0,14^FB" + $wpatient + ",1,0,C,0^FD" + $doc + "^FS";
    }

    if ($position) {
        $zpl_string += "^FO" + $xposition + ",10^A0N,0," + $fontposition + "^FWB^FB" + $yposition + ",3,0,C,0^FD" + $position + "^FS";
    }

    if ($panels && $panels != '') {
        $yculposition += 80;
        var $ypanel = $yposition - ($cul ? 30 : 0);
        var $xpanel = $wpatient - 10;
        $zpl_string += "^FO" + $xpanel + ",10^A0N,0," + $fontpanel + "^FWB^FB" + $ypanel + ",3,0,C,0^FD" + $panels + "^FS";
    }

    if ($cul) {
        var $xpanel = $wpatient - 10;
        $zpl_string += "^FO" + $xpanel + ",10^A0N,0," + $fontpanel + "^FWB^FB" + $yculposition + ",3,0,C,0^FD" + $cul + "^FS";
    }

    $zpl_string += "^XZ";
    try {
        console.log($zpl_string);
        selected_device.send($zpl_string, function () {
            show_printer_send_success_notice($barcode, false);
            if (typeof printedFunc === 'function') {
                printedFunc();
            }
        }, function (sendError) {
            show_printer_connection_error_guide(sendError || 'send-error');
            if (typeof errorFunc === 'function') {
                errorFunc(sendError);
            }
        });
    }
    catch (err) {
        show_printer_connection_error_guide(err);
        console.log('Error: ' + err.message);
        if (typeof errorFunc === 'function') {
            errorFunc(err);
        }
    }
}

function printLabels(data, pos, success, doing) {
    if (!data || !data.length || pos >= data.length) {
        $printer_bulk_printing = false;
        if (typeof success === 'function') {
            success();
        }
        return;
    }
    if (pos === 0) {
        $printer_bulk_printing = data.length > 1;
    }

    if (!selected_device) {
        ensure_printer(function () {
            printLabels(data, pos, success, doing);
        }, function (reason) {
            var reasonText = (typeof reason === 'string') ? reason : '';
            if (reasonText.indexOf('permission-') !== 0) {
                show_printer_connection_error_guide(reason);
            }
            $printer_bulk_printing = false;
            if (typeof success === 'function') {
                success();
            }
        });
        return;
    }
    var row = data[pos];
    if (typeof doing === 'function') {
        doing(row || {});
    }
    if (!row || !row.barcode) {
        printLabels(data, pos + 1, success, doing);
        return;
    }
    var $length = data.length;
    printLabel(row.barcode, row.patient_name, row.position, row.doc, row.panels, row.cul, function () {
        if (pos == $length - 1) {
            $printer_bulk_printing = false;
            show_printer_send_success_notice('Batch print completed', true);
            if (typeof success === 'function') {
                success();
            }
        } else {
            setTimeout(function () {
                printLabels(data, pos + 1, success, doing);
            }, 1500);
        }
    }, function (printError) {
        show_printer_connection_error_guide(printError || 'batch-print-send-error');
        $printer_bulk_printing = false;
        if (typeof success === 'function') {
            success();
        }
    });
}
