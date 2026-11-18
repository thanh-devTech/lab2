
var is_show_alert = false;
var is_show_whats_new = false;
var headerBarNotificationMeta = {};
var notificationCacheTimer = (3 * 60 * 1000);
const cacheKey = "notification_cache";
var cacheNoti = null;

function isOverflowingViewport(el) {
    let $el = $(el);
    if (!$el.length || $el.outerHeight() === 0) return false;
    if ($(window).width() < 1024) return false;

    let elTop = $el.offset().top;
    let elBottom = elTop + $el.outerHeight();
    let viewportTop = $(window).scrollTop();
    let viewportBottom = viewportTop + $(window).height();

    if (elBottom > viewportBottom) return "bottom";
    if (elTop < viewportTop) return "top";
    return false;
}

function adjustBoxPosition($submenu, $parentItem) {
    if (!$submenu.length || !$parentItem.length) return;

    let submenuHeight = $submenu.outerHeight();
    let parentOffset = $parentItem.offset();
    let parentHeight = $parentItem.outerHeight();
    let viewportTop = $(window).scrollTop();
    let viewportHeight = $(window).height();

    // Canh giữa parent item
    let top = parentOffset.top + (parentHeight / 2) - (submenuHeight / 2);

    // Giữ submenu không tràn viewport
    if (top < viewportTop + 5) {
        top = viewportTop + 5;
    } else if (top + submenuHeight > viewportTop + viewportHeight - 5) {
        top = viewportTop + viewportHeight - submenuHeight - 5;
    }

    // Chuyển sang top relative với offset parent (menu container)
    let parentWrapOffset = $submenu.offsetParent().offset().top;
    $submenu.css('top', (top - parentWrapOffset) + 'px');
}


jQuery(function ($) {
    window.$ = jQuery;

    function isMobileSidebar() {
        return window.matchMedia('(max-width: 960px)').matches;
    }

    function normalizeTimestamp(value) {
        var timestamp = parseInt(value, 10);
        return isNaN(timestamp) ? 0 : timestamp;
    }

    function createNotificationCacheState() {
        return {
            count: 0,
            data: null,
            data_alert: null,
            data_whats_new: null,
            time: 0,
            user_loaded_at: 0,
            last_changed_at: 0
        };
    }

    function persistNotificationCache() {
        localStorage.setItem(cacheKey, JSON.stringify(cacheNoti));
    }

    function loadNotificationCache() {
        var cachedData = localStorage.getItem(cacheKey);
        var defaultState = createNotificationCacheState();

        if (!cachedData) {
            return defaultState;
        }

        try {
            return $.extend({}, defaultState, JSON.parse(cachedData));
        } catch (error) {
            console.error("Notification cache parse error:", error);
            localStorage.removeItem(cacheKey);
            return defaultState;
        }
    }

    function showAlertHeaderBar() {
        if (!cacheNoti.data_alert) {
            return;
        }

        $("#header_bar_content").html(cacheNoti.data_alert).show();
        load_alert_emp();
        is_show_alert = true;
        is_show_whats_new = false;
    }

    function updateNotificationCounter() {
        $("#txtNcount").html((parseInt(cacheNoti.count, 10) || 0));
    }

    function shouldForceRefreshNotifications() {
        var lastChangedAt = normalizeTimestamp(headerBarNotificationMeta.lastChangedAt);
        var userLoadedAt = normalizeTimestamp(headerBarNotificationMeta.userLoadedAt);
        var cacheUserLoadedAt = normalizeTimestamp(cacheNoti.user_loaded_at);
        var cacheLastChangedAt = normalizeTimestamp(cacheNoti.last_changed_at);

        if (lastChangedAt > userLoadedAt) {
            return true;
        }

        if (!cacheNoti.time || !cacheNoti.data_alert || cacheUserLoadedAt < userLoadedAt) {
            return true;
        }

        if (cacheLastChangedAt < lastChangedAt) {
            return true;
        }

        return false;
    }

    function fetchNotifications(options) {
        options = options || {};

        $.ajax({
            data: {
                tr_action: 'dashboard_ajax',
                func: 'count_notification'
            },
            dataType: 'json',
            success: function (rs) {
                cacheNoti.count = parseInt(rs.total_alert, 10) || 0;
                cacheNoti.data_alert = rs.data_alert;
                cacheNoti.data_whats_new = rs.data_whats_new;
                cacheNoti.time = Date.now();
                cacheNoti.user_loaded_at = normalizeTimestamp(rs.user_loaded_at) || Date.now();
                cacheNoti.last_changed_at = normalizeTimestamp(rs.last_changed_at) || 0;

                headerBarNotificationMeta.userLoadedAt = cacheNoti.user_loaded_at;
                headerBarNotificationMeta.lastChangedAt = cacheNoti.last_changed_at;
                persistNotificationCache();
                updateNotificationCounter();

                if (options.openAlertAfterFetch && cacheNoti.count > 0) {
                    showAlertHeaderBar();
                }
            },
            error: function (xhr, status, err) {
                console.error("AJAX error:", status, err);
                $("#txtNcount").html("0");
            }
        });
    }

    function checkNotificationMeta(options) {
        options = options || {};
        var n_last_changed_at=$("#header_bar").attr("n_last_changed_at");
        var user_n_loaded_at=$("#header_bar").attr("user_n_loaded_at");

        headerBarNotificationMeta.lastChangedAt = normalizeTimestamp(n_last_changed_at);
        headerBarNotificationMeta.userLoadedAt = normalizeTimestamp(user_n_loaded_at);

        if (shouldForceRefreshNotifications()) {
            fetchNotifications({ openAlertAfterFetch: !!options.openAlertAfterFetch });
        } else if (options.refreshCounter !== false) {
            updateNotificationCounter();
        }
    }


    //Check Sub Menu
    $('#adminmenu li').hover(function () {
        let $li = $(this);
        if ($li.hasClass('wp-has-current-submenu')) return;
        let $submenu = $li.find('.wp-submenu');
        $submenu.show();
        // Canh giữa và tránh tràn
        adjustBoxPosition($submenu, $li);
    }, function () {
        let $li = $(this);
        if ($li.hasClass('wp-has-current-submenu')) return;
        $li.find('.wp-submenu').hide();
    });

    $(document).on('click', '#adminmenu li.wp-has-submenu > a.menu-top', function (e) {
        if (!isMobileSidebar()) return;

        const $menuLink = $(this);
        const $menuItem = $menuLink.parent('li.wp-has-submenu');
        const $submenu = $menuItem.children('.wp-submenu');

        if (!$menuItem.length || !$submenu.length) return;

        const href = ($menuLink.attr('href') || '').trim();
        const hasNavigableHref = href !== '' && href !== '#' && href.indexOf('javascript:') !== 0;
        const isOpen = $menuItem.hasClass('opensub');

        // First tap on mobile opens submenu (no hover on touch devices).
        if (!isOpen) {
            e.preventDefault();
            e.stopPropagation();

            $('#adminmenu li.wp-has-submenu.opensub').not($menuItem).removeClass('opensub').children('.wp-submenu').hide();
            $menuItem.addClass('opensub');
            $submenu.show();
            return;
        }

        // If the parent menu has no real URL, keep tap behavior as toggle.
        if (!hasNavigableHref) {
            e.preventDefault();
            e.stopPropagation();
            $menuItem.removeClass('opensub');
            $submenu.hide();
        }
    });

    cacheNoti = loadNotificationCache();

    $("#header_bar_content").hide();
    $("#btn_toggle_sidebar").click(function () {
        return $('#collapse-button').trigger('click');
    });

    function load_alert_emp() {
        $(".btn_del_alert").click(function (e) {
            e.preventDefault();
            e.stopPropagation();
            var id_alert = $(this).attr("id-alert");
            var id_db = $(this).attr("id-db");
            $(this).html("⌛️");
            $.ajax({
                data: {
                    tr_action: 'dashboard_ajax',
                    func: 'remove_notification',
                    id_notification: id_db,
                    type: 'a'
                },
                success: function (data) {
                    if (data.status == "ok") {
                        $("#" + id_alert).remove();
                        if ($(".item_alert_header").length === 0) {
                            $("#alert_body").html(
                                '<div class="text-center py-12"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert h-12 w-12 mx-auto mb-4 text-gray-300" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg><h4 class="font-medium text-gray-900 mb-2">No Active Alerts</h4><p class="text-gray-600">All alerts have been cleared. Great job!</p></div>'
                            );
                        }
                        clear_cache();
                    } else {
                        alert(data.msg);
                    }
                },
                error: function (xhr, status, err) {
                    console.error("AJAX error:", status, err);
                }
            });
        });

        $(".btn_clear_all_alert").click(function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(".btn_clear_all_alert").html("Loading...");
            $.ajax({
                data: {
                    tr_action: 'dashboard_ajax',
                    func: 'clear_notification',
                    type: 'a'
                },
                success: function (data) {
                    if (data.status == "ok") {
                        $("#alert_body").html(
                            '<div class="text-center py-12"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert h-12 w-12 mx-auto mb-4 text-gray-300" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg><h4 class="font-medium text-gray-900 mb-2">No Active Alerts</h4><p class="text-gray-600">All alerts have been cleared. Great job!</p></div>'
                        );
                        clear_cache();
                    } else {
                        $(".btn_clear_all_alert").html("Clear All Alerts");
                        alert(data.msg);
                    }
                },
                error: function (xhr, status, err) {
                    console.error("AJAX error:", status, err);
                    $(".btn_clear_all_alert").html("Clear All Alerts");
                }
            });
        });

        $(".btn_close_alert").click(function (e) {
            is_show_alert = false;
            $("#header_bar_content").html('').hide();
        });
    }

    $("#btn_header_notification").click(function () {
        is_show_whats_new = false;
        if (is_show_alert == false) {
            if (shouldForceRefreshNotifications()) {
                fetchNotifications({ openAlertAfterFetch: true });
            } else if (cacheNoti.data_alert) {
                showAlertHeaderBar();
            }
        } else {
            close_header_box();
        }
    });

    function countNotification() {
        updateNotificationCounter();

        if (shouldForceRefreshNotifications() || (Date.now() - cacheNoti.time > notificationCacheTimer)) {
            fetchNotifications();
            return;
        }

        checkNotificationMeta({ refreshCounter: false });
    }

    function clear_cache() {
        localStorage.removeItem(cacheKey);
        cacheNoti = createNotificationCacheState();
        fetchNotifications();
    }

    function close_header_box() {
        is_show_alert = false;
        is_show_whats_new = false;
        $("#header_bar_content").hide();
    }

    $('#btn_header_bar_setting').click(function (e) {
        e.stopPropagation();
        $("#menu_header_bar_setting").toggle();
    });

    $('#header_bar_setting_wrapper').hover(
        function () {
            $(this).find('#menu_header_bar_setting').stop(true, true).slideDown(80);
        },
        function () {
            $(this).find('#menu_header_bar_setting').stop(true, true).slideUp(80);
        }
    );

    $(document).on("click", function (e) {
        if (!$(e.target).closest("#btn_header_bar_setting, #menu_header_bar_setting").length) {
            $("#menu_header_bar_setting").hide();
        }

        if ($("#header_bar_content").is(":visible") &&
            !$(e.target).closest("#header_bar_content, #btn_header_notification").length
        ) {
            close_header_box();
        }

        if (!$(e.target).closest("#header_bar_menu_mobile, #btn_header_bar_setting").length) {
            $("#menu_header_bar_mobile").hide();
        }

    });

    $("#btn_list_whats_new,#btn_what_new_mobile").click(function () {

        $("#menu_header_bar_mobile").hide();
        is_show_alert = false;
        if (is_show_whats_new) {
            $("#btn_list_whats_new txtShow").html("What's New");
            close_header_box();
            return false;
        }

        if (cacheNoti.data_whats_new) {
            setTimeout(() => {
                $("#header_bar_content").html(cacheNoti.data_whats_new).show();
                load_alert_emp();
                is_show_whats_new = true;
            }, 200);
        }
    });

    $("#header_bar_menu_mobile").click(function () {
        $("#menu_header_bar_mobile").toggle();
    });

    $(window).on('focus', function () {
        checkNotificationMeta();
    });

    countNotification();
});
