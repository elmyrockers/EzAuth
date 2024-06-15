<?php
require_once '../vendor/autoload.php';
use elmyrockers\EzAuth;
use \RedBeanPHP\R as R;
use Symfony\Component\ErrorHandler\Debug;

Debug::enable();



# FOR DEBUGGING PURPOSE
$database = [
	'dsn' => 'mysql:host=localhost;dbname=ezauth',
	'username' => 'root',
	'password' => ''
];
$email = [
	// 'smtp_settings' => [
	// 	'debug' => true,
	// 	'host' => 'amazonaws.com',
	// 	'username' => 'username',
	// 	'password' => 'password',
	// 	'port' => '587',
	// 	'encryption' => 'starttls' // starttls / smtps
	// ],
	'from' => [ 'no-reply@yoursite.com', 'EzAuth' ],
	'reply_to' => [ 'admin@yoursite.com', 'Admin' ],
	'template_dir' => 'views/emails/',
	'templates' => [
		// 'email_verification' => [ 'subject', 'filename' ], // filename without extension (for html and plain text)
		// 'reset_password' => [ 'subject', 'filename' ],
	]
];
$message = [
		// 'language' => [ 'en', '/optional/path/to/language/directory/' ],
		'template' => [
			'success' => '<div class="alert alert-success">{$message}</div>',
			'error' => '<div class="alert alert-danger">{$message}</div>'
		]
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
		'secret_key' => '427a656ece850f275ae8fc5cc50b6d6a25b2b8b3b09925d6fab93cf062d015c8' // 256-bit ( bin2hex(random_bytes(32)) )
];
$ezauth = new EzAuth(compact( 'database', 'email', 'message', 'auth' ));





// $_SERVER[ 'REQUEST_METHOD' ] = 'POST';
// $_POST[ 'username' ] = 'elmyrockers';
// $_POST[ 'email' ] = 'elmyrockers@gmail.com';
// $_POST[ 'password' ] = '12345';
// $_POST[ 'confirm_password' ] = '12345';
// $ezauth->register();
// $ezauth->verify_email( 'login.php' );
// dump( $ezauth );

// $secretKey = bin2hex( random_bytes( 32 ));
// dump( $secretKey );