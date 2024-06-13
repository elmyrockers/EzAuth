<?php
namespace elmyrockers;

require_once 'vendor/autoload.php';
use \RedBeanPHP\R as R;
use elmyrockers\EzFlash;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as Mail_Exception;

/**
 * 
 */
class EzAuth
{
	private $config;
	
	private $mailer;
	private $flash;

	public function __construct( $config )
	{
		$this->config = $config;
		extract( $config ); // $database, $email, $message, $auth

		// Setup database first
			if ( empty($database['dsn']) || empty($database['username']) || !isset($database['password']) ) {
				throw new \Exception( "Missing Database Config" );
			}
			R::setup( $database['dsn'], $database['username'], $database['password'] );

		// Setup email settings
			$mailer = new PHPMailer(true);
			$smtp = $email[ 'smtp_settings' ] ?? null;
			if ( $smtp ) {
				$mailer->isSMTP();
				$mailer->SMTPAuth = true;
				if ( !empty($smtp['debug']) ) $mailer->SMTPDebug = SMTP::DEBUG_SERVER;
				if ( !empty($smtp['host']) ) $mailer->Host = $smtp['host'];
				if ( !empty($smtp['username']) ) $mailer->Username = $smtp['username'];
				if ( !empty($smtp['password']) ) $mailer->Password = $smtp['password'];
				if ( !empty($smtp['port']) ) $mailer->Port = $smtp['port'];
				if ( !empty($smtp['encryption']) ) {
					$encryption = $smtp[ 'encryption' ];
					if ( $encryption == 'starttls' ) $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
					elseif ( $encryption == 'smtps' ) $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
				}
			}
			$from = $email[ 'from' ] ?? null;
			if ( !empty($from[0]) ) {
				$fromName = $from[1] ?? '';
				$mailer->setFrom( $from[0], $fromName );
			}
			$reply_to = $email[ 'reply_to' ] ?? null;
			if ( !empty($reply_to[0]) ) {
				$replyToName = $reply_to[1] ?? '';
				$mailer->addReplyTo( $reply_to[0], $replyToName );
			}
			$this->mailer = $mailer;

		// Setup flash message
			$flash = new EzFlash;
			$flash->setTemplate( 'error', '<div class="alert alert-danger">{$message}</div>' ); // default error template
			$template = $message[ 'template' ] ?? [];
			foreach ( $template as $key => $element ) {
				$flash->setTemplate( $key, $element );
			}
			$this->flash = $flash;

		// Setup auth config
			$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
			$current_domain = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
			$defaultAuthConfig = [
					'id_fields' => [ 'email' ], // username / email / phone - follow table columns (its value must be unique)
					'allowed_fields' => [ 'username','email', 'password', 'confirm_password' ],

					'domain' => $current_domain, // Current domain is default
					'logout_redirect' => '/login.php',
					'member_area' => [
						'/member/user/', // 0 - Role: User
						'/member/admin/', // 1 - Role: Admin
					],
					'verify_email' => '',  // Specify page for email verification
					'reset_password' => '' // Specify page for reset password
			];
			if ( !empty($auth) ) $auth = array_merge( $defaultAuthConfig, $auth );
			$this->config[ 'auth' ] = $auth;

		// Make sure session has been started
			if ( !session_id() ) session_start();
	}

	public function register( $callback = null )
	{
		return true;
	}
}





# FOR DEBUGGING PURPOSE
// $database = [
// 	'dsn' => 'mysql:host=localhost;dbname=ezauth',
// 	'username' => 'root',
// 	'password' => ''
// ];
// $email = [
// 	'smtp_settings' => [
// 		'debug' => true,
// 		'host' => 'amazonaws.com',
// 		'username' => 'username',
// 		'password' => 'password',
// 		'port' => '587',
// 		'encryption' => 'starttls' // starttls / smtps
// 	],
// 	'from' => [ 'no-reply@yoursite.com', 'EzAuth' ],
// 	'reply_to' => [ 'admin@yoursite.com', 'Admin' ],
// 	'template_dir' => '/views/emails/',
// 	'templates' => [
// 		'email_verification' => [ 'subject', 'filename' ], // filename without extension (for html and plain text)
// 		'reset_password' => [ 'subject', 'filename' ],
// 	]
// ];
// $message = [
// 		// 'language' => [ 'en', '/optional/path/to/language/directory/' ],
// 		'template' => [
// 			'success' => '<div class="alert alert-success">{$message}</div>',
// 			'error' => '<div class="alert alert-danger">{$message}</div>'
// 		]
// ];

// const ROLE_USER = 0;
// const ROLE_ADMIN = 1;
// const ROLE_SUPERADMIN = 2;
// $auth = [
// 		'id_fields' => [ 'email' ], // username / email / phone - follow table columns (its value must be unique)
// 		'allowed_fields' => [ 'username','email', 'password', 'confirm_password' ],

// 		'logout_redirect' => '/auth/login/',
// 		'member_area' => [
// 			ROLE_USER => '/member/user/',
// 			ROLE_ADMIN => '/member/admin/',
// 			ROLE_SUPERADMIN => '/member/superadmin/'
// 		],
// 		'verify_email' => '',  // Specify page for email verification
// 		'reset_password' => '' // Specify page for reset password
// ];
// $ezauth = new EzAuth(compact( 'database', 'email', 'message', 'auth' ));

// dump( $ezauth );