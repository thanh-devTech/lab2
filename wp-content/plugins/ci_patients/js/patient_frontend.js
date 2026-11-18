var sidebar_expanded = true;

$(document).ready(function () {
    let is_mobile = window.matchMedia("(max-width: 768px)").matches;
    let $menu = $("#menu_sidebar");
    var sidebar_state = $("#menu_sidebar").data("state");
    var menu = $("#menu_nav_user");
    var wrapper = $("#menu_wrapper");
    let $dialog =null;
    let $d_bk=null;

    function checkDevice() {
        is_mobile = window.matchMedia("(max-width: 768px)").matches;
    }

    checkDevice();

    if (sidebar_state == "expanded")
        sidebar_expanded = true;
    else
        sidebar_expanded = false;

    wrapper.on('mouseenter', function () {
        menu.stop(true, true).show();
    });

    wrapper.on('mouseleave', function () {
        menu.stop(true, true).fadeOut(200);
    });

    $(document).on('click', function () {
        menu.stop(true, true).fadeOut(200);
    });

    wrapper.on('click', function (e) {
        e.stopPropagation();
    });

    function close_menu_sidebar_mobile(){
        $menu.prependTo("#rs_warp");
        if (sidebar_expanded) {
            $menu.addClass("w-[240px]");
        }else{
            $menu.addClass("w-[60px]");
        }
        $dialog.remove();
        $d_bk.remove();
        $menu.addClass("hidden");
    }

    $("#btn_toggle_sidebar").click(function () {
        if(is_mobile){
            if ($menu.hasClass("hidden")) {
                $dialog = $(`<div 
                    role="dialog" id="radix-:r7g:" 
                    aria-describedby="radix-:r7i:" 
                    aria-labelledby="radix-:r7h:" 
                    data-state="open"
                    data-slot="sidebar"
                    class="data-[state=open]:animate-in data-[state=closed]:animate-out fixed z-50 flex flex-col gap-4 shadow-lg transition ease-in-out data-[state=closed]:duration-300 data-[state=open]:duration-500 data-[state=closed]:slide-out-to-left data-[state=open]:slide-in-from-left inset-y-0 left-0 h-full border-r sm:max-w-sm bg-sidebar text-sidebar-foreground w-(--sidebar-width) p-0 [&amp;&gt;button]:hidden"
                    data-sidebar="sidebar" 
                    data-mobile="true" tabindex="-1" style="--sidebar-width: 18rem; pointer-events: auto;height:100%">
                    </div>`);

                $d_bk=$('<div data-state="open" data-slot="sheet-overlay" class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/50" data-aria-hidden="true" aria-hidden="true" style="pointer-events: auto;"></div>');

                $menu.removeClass("hidden");
                $("body").append($d_bk);
                $("body").append($dialog);
                $menu.removeClass("w-[240px]").removeClass("w-[60px]");
                $menu.css('height', '100%');
                $dialog.append($menu);
                $d_bk.click(function(){
                    close_menu_sidebar_mobile();
                });
            } else {
                $menu.addClass("hidden");
                $menu.appendTo("body");
            }
        }else{
            if (sidebar_expanded) {
                sidebar_expanded = false;
                $menu.removeClass("w-[240px]").addClass("w-[60px]");
                $menu.find('button[data-sidebar="menu-button"]').removeClass("px-4").addClass('px-3');
                update_state_sidebar(0);
            } else {
                sidebar_expanded = true;
                $menu.removeClass("w-[60px]").addClass("w-[240px]");
                $menu.find('button[data-sidebar="menu-button"]').removeClass("px-3").addClass('px-4');
                update_state_sidebar(1);
            }
        }
    });

    $(window).on('resize', function () {
        checkDevice();
    });


});

function update_state_sidebar(is_expanded) {
    var user_id = $("#user_id").val();

    $.ajax({
        url: window.location.origin + '/patient_web_api/update_state_sidebar',
        type: 'POST',
        dataType: 'json',
        data: {
            user_id,
            status: is_expanded
        },
        success: function (response) {

        },
        error: function (xhr, status, error) {
            console.error('Error:', error);
        }
    });
}