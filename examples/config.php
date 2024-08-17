<?php
require_once '../vendor/autoload.php';
use elmyrockers\EzAuth;
// use \RedBeanPHP\R as R;
use Symfony\Component\ErrorHandler\Debug;
Debug::enable();



# CONFIGURATIONS - FOR DATABASE, EMAIL AND AUTH
	$database = [
		'dsn' => 'mysql:host=localhost;dbname=ezauth',
		'username' => 'root',
		'password' => ''
	];
	$email = [
		'from' => [ 'no-reply@yoursite.com', 'EzAuth' ],
		'reply_to' => [ 'admin@yoursite.com', 'Admin' ],
		'template_dir' => 'views/emails/'
	];

	const ROLE_USER = 0;
	const ROLE_ADMIN = 1;
	const ROLE_SUPERADMIN = 2;
	$auth = [
			'id_field' => 'email', // username / email / phone - follow table columns (its value must be unique)
			'signup_fields' => [
				// 'username' => [ 'required|string|min:3|max:20|alpha_dash|unique:users,username', FILTER_SANITIZE_FULL_SPECIAL_CHARS ],
				'email' => [ 'required|string|email|max:255|unique:user,email', FILTER_SANITIZE_EMAIL ],
				'password' => [['required','string','min:8','confirmed:confirm_password','regex:/[!@#$%^&*(),.?":{}|<>]/']],
				'confirm_password' => [ 'required_with:password' ],
				'test' => []
			],

			'logout_redirect' => '/auth/login/',
			'member_area' => [
				ROLE_USER => '/member/user/',
				ROLE_ADMIN => '/member/admin/',
				ROLE_SUPERADMIN => '/member/superadmin/'
			],
			'verify_email' => 'elmyrockers/EzAuth/examples/verify_email.php',  // Specify page for email verification
			'reset_password' => '', // Specify page for reset password
			'secret_key' => '427a656ece850f275ae8fc5cc50b6d6a25b2b8b3b09925d6fab93cf062d015c8'
	];
	// $ezauth = new EzAuth(compact( 'database', 'email', 'message', 'auth' ));
	$ezauth = new EzAuth(compact( 'database', 'email', 'auth' ));

// Generate 256-bit Secret Key using these functions
// $secretKey = bin2hex( random_bytes( 32 )); // Cryptographically secure
// dump( $secretKey );