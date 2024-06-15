
### REQUIRE:
1. RedbeanPHP 			- Database
2. PHPMailer 			- Mailer
3. Twig 				- Template Engine (for email)
4. EzFlash 				- Flash message display
5. Symfony/Validator 	- Form validations









### EXAMPLE:
```php
$config = [
	// Database
	'database' => [
		'dsn' =>  'mysql:host=localhost;dbname=ezauth',
		'username' => 'root',
		'password' => '',
		'user_table' => 'user',
		'remember_table' => 'remember'
	],

	// E-mail
	'email' => [
		'smtp_settings' => [
			'debug' => false,
			'host' => '',
			'username' => '',
			'password' => '',
			'port' => '',
			'encryption' => 'starttls' // starttls / smtps
		],
		'from' => [ 'no-reply@yoursite.com', 'EzAuth' ],
		'reply_to' => [ 'admin@yoursite.com', 'Admin' ],
		'template_dir' => '/views/emails/',
		'templates' => [
			'email_verification' => [ 'subject', 'filename' ], // filename without extension (for html and plain text)
			'reset_password' => [ 'subject', 'filename' ],
		]
	],

	// Flash Message - (success / error)
	'message' => [
		'language' => [ 'en', '/optional/path/to/language/directory/' ],
		'template' => [
			'success' => '<div class="alert alert-success">{{ message }}</div>',
			'error' => '<div class="alert alert-danger">{{ message }}</div>'
		]
	],

	// Authentication
	'auth' => [
		'id_fields' => [ 'email' ], // username / email / phone - follow table columns (its value must be unique)
		'allowed_fields' => [ 'username','email', 'password', 'confirm_password' ],

		'domain' => '', // https://yoursite.com
		'logout_redirect' => '/login.php',
		'member_area' => [
			'/member/user/', // 0 - Role: User
			'/member/admin/', // 1 - Role: Admin
			'/member/superadmin/' // 2 - Role: Super-Admin
		],
		'verify_email' => '',  // Specify page for email verification
		'reset_password' => '' // Specify page for reset password
	]
];
$auth = new EzAuth( $config );
$token = $auth->csrfToken();
$flash = $auth->flashMessage();

list( $status, $flash, $csrfToken ) = $auth->register( $callback );
list( $status, $flash ) = $auth->verifyEmail( $callback ) );
list( $status, $flash, $csrfToken ) = $auth->login( $callback );
list( $status, $flash, $csrfToken ) = $auth->recoverPassword( $callback );
list( $status, $flash, $csrfToken ) = $auth->resetPassword( $callback ); 
$user = $auth->memberArea( $allowedRoles );
$user = $auth->isLoggedIn();
// $auth->redirectLoggedInUser();
$auth->logout();
```