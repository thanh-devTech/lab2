<!DOCTYPE html>
<html>

<head>
	<meta charset="utf-8">
	<title>Unlock Form</title>

	<style>
		* {
			box-sizing: border-box;
			font-family: Arial, Helvetica, sans-serif;
		}

		body {
			margin: 0;
			min-height: 100vh;
			display: flex;
			justify-content: center;
			align-items: center;

			background: url('background.jpg') center center/cover no-repeat;
			background-color: #d8e8ee;
		}

		.login-box {
			width: 450px;
			padding: 35px;

			background: rgba(255, 255, 255, 0.85);
			backdrop-filter: blur(8px);

			border-radius: 25px;
			box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
		}

		.title {
			text-align: center;
			font-size: 32px;
			font-weight: bold;
			margin-bottom: 30px;
			color: #3c3c50;
		}

		.form-group {
			margin-bottom: 20px;
		}

		.form-group label {
			display: block;
			margin-bottom: 8px;
			color: #555;
			font-weight: 600;
		}

		.form-control {
			width: 100%;
			height: 50px;
			border: 1px solid #d0d0d0;
			border-radius: 12px;
			padding: 0 15px;
			font-size: 16px;
			background: #fff;
		}

		.form-control:focus {
			outline: none;
			border-color: #f05a00;
			box-shadow: 0 0 5px rgba(240, 90, 0, .3);
		}

		.captcha-box {
			text-align: center;
			margin-top: 10px;
		}

		.btn-submit {
			width: 100%;
			height: 55px;
			border: none;
			border-radius: 12px;
			background: #f05a00;
			color: white;
			font-size: 18px;
			font-weight: bold;
			cursor: pointer;
			transition: .2s;
		}

		.btn-submit:hover {
			background: #d84f00;
		}

		.footer {
			margin-top: 25px;
			text-align: center;
			color: #666;
			font-size: 14px;
		}

		.ip {
			margin-top: 15px;
			color: #f05a00;
			font-weight: bold;
		}
	</style>
</head>

<body>

	<div class="login-box">

		<div class="title">
			Unlock Account
		</div>

		<form method="post" data-type="<?php echo $type ?>">

			<div class="form-group">
				<label>Username / Email Address</label>
				<input
					type="text"
					name="usern"
					class="form-control"
					placeholder="Enter username or email">
			</div>

			<input type="hidden" name="ip" value="ip">
			<input type="hidden" name="unlock_form" value="test">

			<div class="form-group">
				<label>Captcha</label>
				<input
					type="text"
					name="captcha_code"
					id="captcha_code"
					class="form-control"
					placeholder="Enter captcha"
					autocomplete="off">
			</div>

			<div class="captcha-box">
				<?php $this->show_catcha(); ?>
			</div>

			<br>

			<button type="submit" class="btn-submit">
				Unlock
			</button>

			<div class="footer">
				<div class="ip">
					My IP: <?php echo $ip ?>
				</div>
			</div>

		</form>

	</div>

</body>

</html>