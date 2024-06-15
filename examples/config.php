<?php
require_once '../vendor/autoload.php';
use elmyrockers\EzAuth;
use \RedBeanPHP\R as R;
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
			'id_fields' => [ 'email' ], // username / email / phone - follow table columns (its value must be unique)
			'allowed_fields' => [ 'username','email', 'password', 'confirm_password' ],

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
	$ezauth = new EzAuth(compact( 'database', 'email', 'message', 'auth' ));

// Generate 256-bit Secret Key using these functions
// $secretKey = bin2hex( random_bytes( 32 )); // Cryptographically secure
// dump( $secretKey );