var boxHelp = null;

jQuery(function ($) {
    var action = new URLSearchParams(window.location.search).get('action');
    window.$ = jQuery;
    $("#nav").html("");
    $("#nav").append("<a href='/wp-login.php?login_pass=1&action=lostpassword'>Forgot my password</a> ");
    $("#nav").after('<div id="txt_my_ip" style="color:#d83d00;font-weight:600" class="w-full text-center text-red-600 mt-2">My IP: '+MY_IP_DATA.ip+'<div>');
    $("#login").append("<p class='form_footer'>© " + MY_IP_DATA.current_year + " <a href='https://ignitemedicaltech.com/' target='_blank'>igniteLIS</a></p>");
    $("#login_btn_help").click(function () {
        show_help();
    });

    if (action == 'lostpassword') {
        $("#btn_nav_lostpassword").removeClass("bg-white/10").addClass("hover:bg-white/20").addClass("bg-[#D83D00]").addClass("text-white");
        $("#wp-submit").val("Send Reset Instructions");
        $('label[for="user_login"]').text('Username or Email Address');
        $(".notice-info").hide();
        $('#lostpasswordform').append(`
            <div style="display: inline-block;width: 100%;" class="mt-6 pt-6 border-t border-gray-200 text-center">
                <p class="text-sm text-gray-600">Remember your password? 
                <button onclick="window.location='wp-login.php?login_pass=1'" class="cursor-pointer text-[#D83D00] hover:underline font-medium">Sign in here</button></p>
            </div> 
        `);
        $("#nav").hide();
        $(".sub_btn_frm").hide();
    } else {
        $("#btn_nav_login").removeClass("bg-white/10").addClass("hover:bg-white/20").addClass("bg-[#D83D00]").addClass("text-white");
        $("#wp-submit").val("Sign In");
        $('label[for="user_login"]').text('Username or Email Address');
    }

    $("#loginform").prepend(`
       <h2 class="text-xl font-semibold text-[#3C3D59] text-center">Sign in to Your LIS</h2>
       <br/><br/>
        `);

    $("#lostpasswordform").prepend(`
        <h2 class="text-xl font-semibold text-[#3C3D59] text-center">Reset Your Password</h2>
       <p class="text-sm text-gray-600 text-center mt-1 mb-2">We'll send you instructions to reset your password</p> 
       <br/><br/>
    `);

    $('a').filter(function () { return $(this).text().toLowerCase().includes('mobile phone authentication'); }).remove();
});
