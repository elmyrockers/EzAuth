<?php
namespace elmyrockers;

use \RedBeanPHP\R as R;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as Mail_Exception;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Translation\Translator;
// use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\FileLoader;
use Illuminate\Filesystem\Filesystem;

use elmyrockers\EzFlash;
use elmyrockers\EzAuthPresenceVerifier;
use elmyrockers\EzAuthRememberMeInterface;
use elmyrockers\EzAuthRememberMe;


/**
 * 
 */
class EzAuth
{
	private $config;
	
	private $mailer;
	private $flash;

	private $validatorFactory;
	private $remember;

	public function __construct( $config )
	{
		$this->config = $config;
		extract( $config ); // $database, $email, $message, $auth

		// Setup database first
			if ( empty($database['dsn']) || empty($database['username']) || !isset($database['password']) ) {
				throw new \Exception( "Missing Database Config" );
			}
			R::setup( $database['dsn'], $database['username'], $database['password'] );
			$this->config[ 'database' ][ 'user_table' ] = $database[ 'user_table' ] ?? 'user';
			$this->config[ 'database' ][ 'remember_table' ] = $database[ 'remember_table' ] ?? 'remember';

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
			$email[ 'templates' ] = $email['templates'] ?? [];
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
					'id_field' => 'email', // username / email / phone - follow table columns (its value must be unique)
					'signup_fields' => [
						'email' => [ 'required|string|email|max:255|unique:user,email', FILTER_SANITIZE_EMAIL ],
						'password' => [['required','string','min:8','regex:/[!@#$%^&*(),.?":{}|<>]/']],
						'confirm_password' => [ 'required|same:password' ],
					],
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
			// dump( $auth );

		// Set up translation and validation factory -----------------------------------------------------------------------------------
			// $loader = new ArrayLoader();
			$loader = new FileLoader(new Filesystem(), __DIR__ . '/lang');
			$translator = new Translator( $loader, 'en' );
			$this->validatorFactory = new ValidatorFactory( $translator );

			// Define custom validation rules
				$this->validatorFactory->setPresenceVerifier( new EzAuthPresenceVerifier() ); // For 'unique' and 'exists' rules
			

		// Make sure session has been started
			if ( !session_id() ) session_start();

		// Extend default class for remember me feature
			$this->extendRememberMe( new EzAuthRememberMe );
	}

	private function _callback( $callback, $user, $errorInfo = null )
	{
		//Set error message
			$status = true;
			if ( $errorInfo ) {
				$this->flash[ 'danger' ] = $errorInfo;
				$status = false;
			}

		//Redirect to
			if ( is_string($callback) && !empty($callback) ) { // string
				header( "Location: $callback" ); exit;
		//Callback
			} elseif ( is_callable($callback) ) { // callable
				$url = $callback( $user );//redirect to
				if ( is_string($url) ) {
					header( "Location: $url" ); exit;
				}
			}

		return [ $status, $this->flash, 'csrfToken' ];
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
			} catch (Mail_Exception $e) {
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

		# Start form input validations. With 'illuminate/validation'
			$signupFields = $this->config[ 'auth' ][ 'signup_fields' ];
			
			// Get list of inputs, filters and validation rules
				$inputs = [];
				$filters = [];
				$validationRules = [];
				foreach ( $signupFields as $fieldName => $info ) {
					if ( !is_array($info) ) throw new \Exception( "Error: Configuration value for 'auth.signup_fields.$fieldName' field must be of type array." );
					
					$inputs[ $fieldName ] = $_POST[ $fieldName ] ?? null;
					$validationRules[ $fieldName ] = array_shift( $info ) ?? [];
					if ( $info ) $filters[ $fieldName ] = $info;
				}
				// dd( $inputs, $filters, $validationRules );

			// Sanitation
				foreach ( $filters as $fieldName => $filterIDs ) {
					foreach ( $filterIDs as $i => $filterID ) {
						$inputs[ $fieldName ] = filter_var( $inputs[$fieldName], $filterID );
					}
				}
				// dd( $filters, $inputs );

			// Validation
				$validator = $this->validatorFactory->make( $inputs, $validationRules );
				if ( $validator->fails() ) {
					$errors = $validator->errors()->all();
					$errors = join( '<br>', $errors );
					return $this->_callback( null, null, $errors );
				}
				unset( $inputs['confirm_password'] );
			
			


		# If user's email has to be validated, generate confirmation code
			$code = null; $email_verified = 1;
			$verify_email = $this->config[ 'auth' ][ 'verify_email' ] ?? null;
			if ( $verify_email ) {
				$code = bin2hex(random_bytes(16));
				$email_verified = 0;
			}

		# Save user data into 'user' table in database
			$user = R::dispense( 'user' )->import( $inputs );
			$user[ 'password' ] = password_hash( $user['password'], PASSWORD_DEFAULT ); // For password, store only its hash for security
			$user[ 'code' ] = $code;
			$user[ 'email_verified' ] = $email_verified;
			$user[ 'role' ] = 0;

			$now = R::isoDateTime();
			$user[ 'created' ] = $now;
			$user[ 'modified' ] = $now;
			try {
				R::store( $user );
			} catch (\Exception $e ) {
				return $this->_callback( null, null, "Error: {$e->getMessage()}" );
			}

		# Send a link contain confirmation code to user's email
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
					try {
						$token = JWT::encode( $payload, $secretKey, 'HS256' );
					} catch (\Exception $e ) {
						return $this->_callback( null, null, "Error: Failed to create token. {$e->getMessage()}" );
					}
					$verificationLink = "{$domain}/{$verify_email}?token={$token}";

				// Send email
					$to[] = $user['email'];
					if ( !empty($user['username']) ) $to[] = $user[ 'username' ]; //<----------------------------- look weird
					$vars = compact('verificationLink','user');
					$result = $this->_sendMail( $to, 'email_verification', $vars, $mailErrorInfo );

				if ( !$result ) {
					$user->delete();
					return $this->_callback( null, null, "Message could not be sent. Mailer Error: {$mailErrorInfo}" );
				} else {
					$this->flash[ 'success' ] = 'A verification link has been sent to your email.';
					return $this->_callback( $callback, $user );
				}
			}

		# Redirect user to other page & display success message
			$this->flash[ 'success' ] = 'You have been registered successfully' ;
			return $this->_callback( $callback, $user );
	}

	public function verify_email( $callback = null )
	{
		# Validate token
			$token = $_GET[ 'token' ] ?? null;
			if ( !$token ) throw new \Exception( "Token does not exist" );

			// Make sure there is secret key
				$secretKey = $this->config['auth']['secret_key'];
				if ( !$secretKey ) throw new \Exception( 'Missing secret key' );
				try {
					$payload = (array) JWT::decode( $token, new Key($secretKey,'HS256') );
				} catch (\Exception $e ) {
					return $this->_callback( null, null, "Error: Failed to verify token. {$e->getMessage()}" );
				}

		# Sanitize payload
			$payload[ 'email' ] = filter_var( $payload['email'], FILTER_SANITIZE_EMAIL );
			$payload[ 'code' ] = filter_var( $payload['code'], FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		# Validate email and confirmation code
			$userTable = $this->config['database']['user_table'];
			$validator = $this->validatorFactory->make( $payload,[
							'email' => "required|email|max:255|exists:{$userTable},email",
							'code' => 'required|size:32|regex:/^[a-f0-9]{32}$/i'
						]);
			if ( $validator->fails() ) {
				// $messages = $validator->errors()->all();
				return $this->_callback( null, null, 'Invalid verification link.' );
			}

		# Make sure email does not verified
			$user = R::findOne( $userTable, 'email=?', [$payload['email']] );
			$hasVerified = (bool) $user['email_verified'];
			if ( $hasVerified ) {
				return $this->_callback( null, null, 'Your email has been verified previously.' );
			}

		# Make sure the 'confirmation code' is valid
			$invalid = $payload['code'] != $user['code'];
			if ( $invalid ) return $this->_callback( null, null, 'Invalid confirmation code.' );
			

		# Verification link is valid. Then, set 'email_verified' column to 1
		# Redirect user to other page and display result message
			$user[ 'email_verified' ] = 1;
			try {
				R::store( $user );
			} catch(\Exception $e ) {
				return $this->_callback( null, null, "Error: Failed to update email as verified. {$e->getMessage()}" );
			}

		# Redirect user or execute callback
			$this->flash[ 'success' ] = 'Your email has been verified successfully';
			return $this->_callback( $callback, $user );
	}

	public function extendRememberMe(EzAuthRememberMeInterface $remember )
	{
		$this->remember = $remember;
		$remember->initialize( $this->config );
	}

	public function login( $callback = null )
	{
		# Make sure login form has been sent first
			if ( $_SERVER[ 'REQUEST_METHOD' ] != 'POST' ) return;

		# Sanitize 'ID Field'. Then, set validation rules
			$userTable = $this->config['database']['user_table'];
			$idField = $this->config[ 'auth' ][ 'id_field' ];
			if ( $idField == 'email' ) {
				$_POST[ 'email' ] = filter_var( $_POST['email'], FILTER_SANITIZE_EMAIL );
				$validationRules = "required|string|max:255|email|exists:{$userTable},email";
			} else {
				$_POST[ $idField ] = filter_var( $_POST[$idField], FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$validationRules = "required|string|max:255|alpha_dash|exists:{$userTable},{$idField}";
			}

		# Validate ID and password
			$validator = $this->validatorFactory->make( $_POST,[
							$idField => $validationRules,
							'password' => 'required'
						]);
			if ( $validator->fails() ) {
				$errors = $validator->errors()->all();
				$errors = join( '<br>', $errors );
				return $this->_callback( null, null, $errors );
			}

		# Does email has been verified?
			$user = R::findOne( $userTable, "$idField=?", [$_POST[$idField]] );
			$unverifiedEmail = empty($user['email_verified']);
			if ( $unverifiedEmail ) return $this->_callback( null, null, 'Unverified email' );

		# Verify password
			$invalidPassword = !password_verify( $_POST['password'], $user['password'] );
			if ( $invalidPassword ) return $this->_callback( null, null, 'Invalid password' );

		# Give the user permission to access member area (access card)
			$_SESSION[ 'auth' ] = [
									'id' => $user[ 'id' ],
									$idField => $_POST[$idField],
									'role' => $user[ 'role' ]
									];

		# Save cookie token in user's browser
			if ( !empty($_POST['remember']) ) {
				$this->remember->generateToken( $user );
			}

		# Redirect user or execute callback
			return $this->_callback( function($user) use ($callback) {
				# Execute callback first
					// Redirect to
						if ( is_string($callback) && !empty($callback) ) { // string
							return $callback;
					// Callback
						} elseif ( is_callable($callback) ) { // callable
							$url = $callback( $user );//redirect to
							if ( is_string($url) ) return $url;
						}

				# No callback
					$role = $user[ 'role' ];
					$memberArea = $this->config[ 'auth' ][ 'member_area' ];
					if ( !$memberArea ) throw new \Exception( "Error: Configuration value for 'auth.member_area' cannot be empty." );

					if ( is_callable($memberArea) ) return $memberArea( $user );
					if ( is_array($memberArea) ) return $memberArea[ $role ];
					if ( is_string($memberArea) ) return $memberArea;
			}, $user );
	}



	public function recoverPassword( $callback = null ) //need email input
	{
		dd( $this->config['auth']['reset_password'] );
		if ( !$resetPasswordPage ) {
			throw new Exception( '$resetPasswordPage parameter can\'t be empty', 1 );
		}

		# Make sure forgot password form has been sent first
			if ( $_SERVER[ 'REQUEST_METHOD' ] != 'POST' ) return;
			$email = $_POST[ 'email' ];

		# Make sure the account does really exists
			$user = $this->db->user[compact('email')];
			if ( !$user ) {
				$this->_redirectCallback( $redirectTo, null, 'Your email does not exists in our system' ); return;
			}

		# Make sure email has been verified first. If not, give notice to the user
			if ( !$user['verified'] ) {
				$this->_redirectCallback( $redirectTo, $user, 'Unverified account. Please check your email for verification link.' ); return;
			}

		# Generate new secret code
			$username = $user[ 'username' ];
			$code = hash( 'sha256', $username.$email.microtime() ); //sha256 algorithm

		# Save that secret code into database
			$saved = $user->update(compact('code'));
			if ( !$saved ) {
				$this->_redirectCallback( $redirectTo, $user, 'Failed to reset your password. Please try again.' ); return;
			}

		# Send a link contain secret code to the user's email
			$resetPasswordLink = "{$resetPasswordPage}?email={$email}&code={$code}";
			$result = $this->_sendMail( [ $email, $username ], 'reset_password', compact('resetPasswordLink','user'), $mailErrorInfo );
			if ( !$result ) {
				$this->_redirectCallback( $redirectTo, $user, "Message could not be sent. Mailer Error: {$mailErrorInfo}" ); return;
			} else {
				$this->flash[ 'success' ] = 'A link to reset your password has been sent to your email.';
				$this->_redirectCallback( $redirectTo, $user );
			}

		# Redirect user or execute callback
			$this->_redirectCallback( $redirectTo, $user );//----------------------------check this
	}
}