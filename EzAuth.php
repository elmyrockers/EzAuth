<?php
namespace elmyrockers;

use \RedBeanPHP\R as R;
use elmyrockers\EzFlash;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as Mail_Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\PresenceVerifierInterface;

use Illuminate\Translation\Translator;
// use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\FileLoader;
use Illuminate\Filesystem\Filesystem;


/**
 * 
 */
class EzAuth
{
	private $config;
	
	private $mailer;
	private $flash;

	private $validatorFactory;

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

		// Set up translation and validation factory
			// $loader = new ArrayLoader();
			$loader = new FileLoader(new Filesystem(), __DIR__ . '/lang');
			$translator = new Translator( $loader, 'en' );
			$this->validatorFactory = new ValidatorFactory( $translator );

			// Define custom validation rules
				$this->validatorFactory->setPresenceVerifier( new EzAuthPresenceVerifier() ); // For 'unique' and 'exists' rules
				$this->validatorFactory->extend( 'unverified_email', function( $attribute, $value, $parameters, $validator ) {
					$email = $validator->getData()[ 'email' ] ?? null; //Retrieve the email from the input data
					if (!$email) return false;

					// Check the 'user' table
					$user = R::findOne( 'user', 'email=?', [$email] );
					// dd( !$user['email_verified'] );

					return !$user['email_verified'];
				}, 'Verified email' );
				$this->validatorFactory->extend( 'confirm_code', function( $attribute, $value, $parameters, $validator ) {
					$email = $validator->getData()[ 'email' ] ?? null; //Retrieve the email from the input data
					if (!$email) return false;

					// Check the 'user' table
					$user = R::findOne( 'user', 'email=? AND code=?', [$email, $value] );

					return $user !== null;
				}, 'Invalid confirmation code' );
			

		// Make sure session has been started
			if ( !session_id() ) session_start();
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

		# Validate email and confirmation code
				$validator = $this->validatorFactory->make( $payload,[
								'email' => "required|email|max:255|exists:{$this->config['database']['user_table']},email|unverified_email",
								'code' => 'required|size:32|regex:/^[a-f0-9]{32}$/i|confirm_code'
							]);
				if ( $validator->fails() ) {
					// $messages = $validator->errors()->all();
					return $this->_callback( null, null, 'Invalid verification link.' );
				}

		# Verification link is valid. Then, set 'email_verified' column to 1
		# Redirect user to other page and display result message
			try {
				$user = R::findOne( 'user', 'email=?', [$payload['email']] );
				$user[ 'email_verified' ] = 1;
				R::store( $user );
			} catch(\Exception $e ) {
				return $this->_callback( null, null, "Error: Failed to update email as verified. {$e->getMessage()}" );
			}

		# Redirect user or execute callback
			$this->flash[ 'success' ] = 'Your email has been verified successfully';
			return $this->_callback( $callback, $user );
	}
}

class EzAuthPresenceVerifier implements PresenceVerifierInterface {
	public function getCount( $collection, $column, $value, $excludeId = null, $idColumn = 'id', array $extra = [] )
	{
		// Build the query
		$query = R::findAll( $collection, "{$column} = ?", [$value] );

		// Exclude the specified ID if provided
		if ($excludeId !== null) {
			$query = array_filter($query, function ($item) use ($excludeId, $idColumn) {
				return $item->{$idColumn} !== $excludeId;
			});
		}

		return count($query);
	}

	public function getMultiCount( $collection, $column, array $values, $excludeId = null, $idColumn = 'id', array $extra = [] )
	{
		// Build the query
		$query = R::findAll( $collection, "{$column} IN (" . implode(',', array_fill(0, count($values), '?')) . ")", $values );

		// Exclude the specified ID if provided
		if ($excludeId !== null) {
			$query = array_filter($query, function ($item) use ($excludeId, $idColumn) {
				return $item->{$idColumn} !== $excludeId;
			});
		}

		return count($query);
	}
}