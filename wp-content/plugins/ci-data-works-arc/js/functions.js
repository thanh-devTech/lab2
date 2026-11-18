function tralert($msg) {
	if (Swal) {
		Swal.fire({
			title: '',
			html: '<div style="text-align: left;">' + $msg.replaceAll("\n", '<br>') + '</div>',
			allowOutsideClick: true,
			allowEscapeKey: true
		});
	} else {
		alert(rs.msg);
	}
}

function setCookie(name, value, days) {
	var expires = "";
	if (days) {
		var date = new Date();
		date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
		expires = "; expires=" + date.toGMTString();
	}

	document.cookie = name + "=" + value + expires + "; path=/";
}

function getCookie(name) {
	var nameEQ = name + "=";

	var ca = document.cookie.split(";");
	for (var i = 0; i < ca.length; i++) {
		var c = ca[i];
		while (c.charAt(0) === ' ') c = c.substring(1, c.length);
		if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
	}
	return null;
}

function eraseCookie(name) { setCookie(name, "", -1); }

function trdd_dob2ages($dob, $now) {
	var today = $now == '' ? new Date() : new Date($now);
	var birthDate = new Date($dob);
	var age = today.getFullYear() - birthDate.getFullYear();
	var m = today.getMonth() - birthDate.getMonth();
	if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
		age--;
	}
	return age;
}
