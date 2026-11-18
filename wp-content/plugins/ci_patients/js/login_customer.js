var boxHelp = null;

jQuery(function ($) {
    var action = new URLSearchParams(window.location.search).get('action');
    window.$ = jQuery;
    $("#nav").html("");
    $("#nav").append("<a href='/otpa/passwordless-login/'>← Back to phone login</a> ");
    $("#nav").append("  •  ");
    $("#nav").append("<a href='/wp-login.php?login_pass=1&action=lostpassword'>Forgot my password</a> ");
    $("#login").append("<p class='form_footer'>© 2026 igniteLIS</p>");
    $("#nav").before(`<p class="sub_btn_frm"><a id="login_btn_help">Help</a> • <a id="btn_ip">What is my IP?</a></p>`);
    $("#login_btn_help").click(function () {
        show_help();
    });

    $("#btn_ip").click(function () {
        alert("Your IP address is:" + MY_IP_DATA.ip);
    });

    if(action=='lostpassword'){
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
       <h2 class="text-xl font-semibold text-[#3C3D59] text-center">Sign In to Your Patient Portal</h2>
       <p class="text-sm text-gray-600 text-center mt-1 mb-2">Access your lab results securely</p> 
       <br/><br/>
        `);

    $("#lostpasswordform").prepend(`
        <h2 class="text-xl font-semibold text-[#3C3D59] text-center">Reset Your Password</h2>
       <p class="text-sm text-gray-600 text-center mt-1 mb-2">We'll send you instructions to reset your password</p> 
       <br/><br/>
    `);

    $('#loginform a:not(.cursor-pointer)').remove();
    $('#lostpasswordform a:not(.cursor-pointer)').remove();

    $('a').filter(function () { return $(this).text().toLowerCase().includes('mobile phone authentication'); }).remove();
});

function show_help() {
    boxHelp = $(`
        <div id='bg_white_login' onclick="close_help();return false">
            <div id="box_info">
                <div class="box_info_header">
                    <h3>Need Help Logging In?</h3>
                    <svg class="icon_close" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x w-6 h-6" aria-hidden="true"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                </div>
                <div class="box_info_body">
                    <p>
                        If you’re having trouble accessing your account, try the tips below.
                    </p>
                    <div class="panel">
                        <strong class="panel_title">Login tips</strong>
						<ul>
						<li>• Use the same mobile number you gave your clinic</li>
						<li>• Don’t see your code? Check recent texts - it may come from an unfamiliar number.</li>
						<li>• Enter your date of birth exactly (MM/DD/YYYY)</li>
						</ul>
                    </div>

                    <div class="panel">
                        <strong class="panel_title">Still Need Help?</strong>
                        Contact our Patient Support Team:
                        <br/><br/>
                        <span class="text-h">Phone: 1-800-IGNITE-1</span><br/>
                        <span class="text-h">Email: support@ignitelis.com</span><br/>
                        <span class="text-h">Hours: Mon-Fri 8AM-6PM EST</span><br/>
                    </div>

					<button class="cursor-pointer w-full bg-[#D83D00] hover:bg-[#c03600] text-white py-2.5 rounded-lg transition-all">Close</button>
                </div>
            </div>
        </div>
    `);

    $("body").append(boxHelp);
}

function close_help() {
    boxHelp.remove();
}
