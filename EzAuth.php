<?php
namespace elmyrockers;

use \RedBeanPHP\R as R;
use elmyrockers\EzFlash;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as Mail_Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

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
		
		// Setup default email templates
			$defaultEmailConfig = [
				'template_dir' => '',
				'templates' => [
					'email_verification' => [ 'Your Verification Link', 'email_verification' ],
					'reset_password' => [ 'Reset Password Request', 'reset_password' ]
				]
			];
			$this->config[ 'email' ][ 'template_dir' ] = $email['template_dir'] ?? $defaultEmailConfig['template_dir'];
			$this->config[ 'email' ][ 'templates' ] = array_merge( $defaultEmailConfig['templates'], $email['templates'] );


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
					'reset_password' => '', // Specify page for reset password
					'secret_key' => '' // 256-bit ( bin2hex(random_bytes(32)) )
			];
			if ( !empty($auth) ) $auth = array_merge( $defaultAuthConfig, $auth );
			$this->config[ 'auth' ] = $auth;

		// Make sure session has been started
			if ( !session_id() ) session_start();
	}

	private function _callback( $callback, $user, $errorInfo = null )
	{
		if ( $errorInfo ) return false;
	}

	private function _sendMail( $to, $emailType, $vars, &$errorInfo ) // For register & forgot_password
	{
		# Email Configuration
			$template_dir = $this->config[ 'email' ][ 'template_dir' ];
			$template = $this->config[ 'email' ][ 'templates' ][ $emailType ];

		# Get email body
			$loader = new \Twig\Loader\FilesystemLoader( $template_dir );
			$twig = new \Twig\Environment($loader);
			$html = $twig->render( "{$template[1]}.html.twig", $vars );
			$plain = $twig->render( "{$template[1]}.txt.twig", $vars );

		# Send email
			$mailer = $this->mailer;
			try {
				//Recipient
					if ( count($to)>1 ) {
						$mailer->addAddress( $to[0], $to[1] );
					} elseif ( !empty($to[0]) ) {
						$mailer->addAddress( $to[0] );
					} elseif ( is_string($to) ) {
						$mailer->addAddress( $to );
					}
					
				//Content
					$mailer->isHTML( true ); //Set email format to HTML
					$mailer->Subject = $template[0];
					$mailer->Body    = $html;
					$mailer->AltBody = $plain;

					return $mailer->send();
			} catch (Exception $e) {
				$errorInfo = $mailer->ErrorInfo;
				return false;
			}
		return true;
	}

	public function flashMessage()
	{
		return $this->flash;
	}

	public function register( $callback = null )
	{
		# Make sure register form has been sent first
			if ( $_SERVER[ 'REQUEST_METHOD' ] != 'POST' ) return;

		# Start form input validations. With symfony/validator
			unset( $_POST['confirm_password'] );

		# Make sure the account does not yet exists
			$user = R::findOne( 'user', 'username=? OR email=?', [$_POST['username'],$_POST['email']] );
			if ( $user ) return $this->_callback( $callback, $user, 'E-mail or username already exists in our system' );

		# If user's email has to be validated, generate secret code
			$code = null; $email_verified = 1;
			$verify_email = $this->config[ 'auth' ][ 'verify_email' ] ?? null ;
			if ( $verify_email ) {
				$code = bin2hex(random_bytes(16));
				$email_verified = 0;
			}

		# Save user data into 'user' table in database
			$user = R::dispense( 'user' )->import( $_POST );
			$user[ 'password' ] = password_hash( $user['password'], PASSWORD_DEFAULT ); // For password, store only its hash for security
			$user[ 'code' ] = $code;
			$user[ 'email_verified' ] = $email_verified;
			$user[ 'role' ] = 0;

			$now = R::isoDateTime();
			$user[ 'created' ] = $now;
			$user[ 'modified' ] = $now;
			$userID  = R::store( $user );
			if ( !$userID ) return $this->_callback( $callback, null, 'Failed to register the user. Please try again.' );
			
			// dd( $user );

		# Send a link contain secret code to user's email
			if ( $verify_email ) {
				// Make sure there is secret key
					$secretKey = $this->config['auth']['secret_key'];
					if ( !$secretKey ) throw new \Exception( 'Missing secret key' );
					
				// Generate token and verification link
					$domain = $this->config[ 'auth' ][ 'domain' ];
					$now = time();
					$payload = [
						'iss' => $domain,
						'aud' => $domain,
						'iat' => $now,
						'exp' => $now + 3600, // Valid for 1 hour
						'email' => $user[ 'email' ],
						'code' => $code
					];
					$token = JWT::encode( $payload, $secretKey, 'HS256' );
					$verificationLink = "{$domain}/{$verify_email}?token={$token}";

				// Send email
					$to = [ $user['email'],
							$user['username'] ];
					$vars = compact('verificationLink','user');
					$result = $this->_sendMail( $to, 'email_verification', $vars, $mailErrorInfo );
					// dd( $result );

				if ( !$result ) {
					// $user->delete();
					$this->_callback( $callback, null, "Message could not be sent. Mailer Error: {$mailErrorInfo}" ); return;
				} else {
					$this->flash[ 'success' ] = 'A verification link has been sent to your email.';
					$this->_callback( $callback, $user );
					return;
				}
			}

		# Redirect user to other page & display success message
			$this->flash[ 'success' ] = 'You have been registered successfully' ;
			$this->_callback( $callback, $user );
	}

	public function verify_email( $callback = null )
	{
		# Validate token
			$token = $_GET[ 'token' ] ?? null;
			if ( !$token ) throw new \Exception( "Token does not exist" );

			// Make sure there is secret key
				$secretKey = $this->config['auth']['secret_key'];
				if ( !$secretKey ) throw new \Exception( 'Missing secret key' );


			$payload = JWT::decode( $token, new Key($secretKey,'HS256') );

			dd( $payload );
			return;

		# Make sure url query - email and code does exist
			if ( empty($_GET['email']) || empty($_GET['code']) ) {
				$this->_redirectCallback( $redirectTo, null, 'Invalid verification link' ); return;
			}
			$email = $_GET[ 'email' ];
			$code = $_GET[ 'code' ];

		# Make sure the email format is valid
			$email = filter_var( $email, FILTER_VALIDATE_EMAIL );
			if ( !$email ) {
				$this->_redirectCallback( $redirectTo, null, 'Invalid email in verification link' ); return;
			}
			// if (!checkdnsrr($domain, 'MX')) {
			// 	// domain is not valid
			// }

		# Make sure the email exists in our database
			$user = $this->db->user[[ 'email'=>$email ]];
			if ( !$user ) {
				$this->_redirectCallback( $redirectTo, null, 'The email does not exist in our database' ); return;
			}
			// var_dump($user);
		
		# Make sure created time does not exceed 23 hours 59 minutes
			$registeredTime = new \DateTime( $user['created'] );
			$now = new \DateTime();
			$diff = $registeredTime->diff( $now );
			$days = $diff->d;
			$hours = $diff->h;
			$totalOfHours = ($days*24) + $hours;
			if ( $totalOfHours >= 24 ) {
				$this->_redirectCallback( $redirectTo, $user, 'Your verification link has expired' ); return;
			}

		# Make sure the code is valid
			if ( $code != $user['code'] ) {
				$this->_redirectCallback( $redirectTo, $user, 'Invalid code in verification link' ); return;
			}

		# Make sure the email did not yet verified
			// var_dump( $user['verified'] );
			if ( $user['verified'] ) {
				$this->flash[ 'success' ] = 'Your email has been verified';
				$this->_redirectCallback( $redirectTo, $user );
				return;
			}

		# Verification link is valid. Then, set 'verified' column to 1
		# Redirect user to other page and display result message
			$result = $user->update([ 'verified'=>1 ]);
			if ( !$result ) {
				$this->_redirectCallback( $redirectTo, $result, 'Failed to update verification data' ); return;
			}

		# Redirect user or execute callback
			$this->flash[ 'success' ] = 'Your email has been verified successfully';
			$this->_redirectCallback( $redirectTo, $user );
	}
}