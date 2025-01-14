
### REQUIRE:
1. RedbeanPHP 				- Database
2. PHPMailer 				- Mailer
3. Twig 					- Template Engine (for email)
4. EzFlash 					- Flash message display
5. Illuminate/validation 	- Form validations
6. Firebase/php-jwt 		- JWT Encode/Decode








### EXAMPLE:
```php
// Database
	$database = [
		'dsn' =>  'mysql:host=localhost;dbname=ezauth',
		'username' => 'root',
		'password' => '',
		'user_table' => 'user',
		'remember_table' => 'remember'
	];

// E-mail
	$email = [
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
	];

// Flash Message - (success / error)
	$message = [
		'language' => [ 'en', '/optional/path/to/language/directory/' ],
		'template' => [
			'success' => '<div class="alert alert-success">{{ message }}</div>',
			'error' => '<div class="alert alert-danger">{{ message }}</div>'
		]
	];

// Authentication
	$auth = [
		'id_field' => 'email', // username / email / phone - follow table columns (its value must be unique)
		'signup_fields' => [
			'username' => [ 'required|string|min:3|max:20|alpha_dash|unique:user,username', FILTER_SANITIZE_FULL_SPECIAL_CHARS ],
			'email' => [ 'required|string|email|max:255|unique:user,email', FILTER_SANITIZE_EMAIL ],
			'password' => [['required','string','min:8','regex:/[!@#$%^&*(),.?":{}|<>]/']],
			'confirm_password' => [ 'required|same:password' ]
		],
		'domain' => '', // https://yoursite.com
		'logout_redirect' => '/login.php',
		// 'member_area' => [ // Array
		// 	'/member/user/', // 0 - Role: User
		// 	'/member/admin/', // 1 - Role: Admin
		// 	'/member/superadmin/' // 2 - Role: Super-Admin
		// ],
		'member_area' => function( $user ){ // Value for member area can be 'callable'
			$role = $user[ 'role' ];
			if ( $role == 0 ) return '/member/user/'; //0 - Role: User
			if ( $role == 1 ) return '/member/admin/'; //1 - Role: Admin
			if ( $role == 2 ) return '/member/superadmin/'; //2 - Role: Super-Admin
		},
		'verify_email' => '',  // Specify page for email verification
		'reset_password' => '' // Specify page for reset password
	];
$config = compact( 'database', 'email', 'message', 'auth' );
$ezauth = new EzAuth( $config );

$csrfToken = $ezauth->csrfToken();
$flash = $ezauth->flashMessage();

$csrfToken = $ezauth->csrfToken( false ); //Disable CSRF token generation

// When csrfToken() or csrfToken(false) is called, method like register() will return only single data: $status
	$status = $ezauth->register( $successCallback );

list( $status, $flash, $csrfToken ) = $ezauth->register( $successCallback );
list( $status, $flash ) = $ezauth->verifyEmail( $successCallback ) );
list( $status, $flash, $csrfToken ) = $ezauth->login( $successCallback );
list( $status, $flash, $csrfToken ) = $ezauth->recoverPassword( $successCallback );
list( $status, $flash, $csrfToken ) = $ezauth->resetPassword( $successCallback, &$user, &$hasSent );

$user = $ezauth->memberArea( $allowedRoles );
$user = $ezauth->hasLoggedIn();
$ezauth->redirectLoggedInUser();
list( $status, $flash, $csrfToken ) = $ezauth->updatePassword( $successCallback );
list( $status, $flash, $csrfToken ) = $ezauth->updatePasswordAsync();
$ezauth->logout();
```