<?php
namespace elmyrockers\EzAuth;
//NotORM - For database handling and manipulation
//Smarty - for email body template and
//PHPMailer - to send email
//




// require_once 'config.php';

class EzAuth
{
	public $cookieConfig = [
								'expires' => 0,
								'path' => '/',
								'domain' => 'localhost',
								'secure' => false,
								'httponly' => false,
								'samesite' => false
							];
	private $db;
	private $tpl;
	private $mail;
	function __construct(array $params )
	{
		foreach ( $params as $object ) {
			if ( $object instanceof NotORM ) $this->db = $object;
			elseif ( $object instanceof Smarty ) $this->tpl = $object;
			elseif ( $object instanceof PHPMailer ) $this->mail = $object;
		}

		$this->cookieConfig[ 'expires' ] = time()+(60*60*24*7); //1 week

		# Make sure session has been started
			if ( !session_id() ) session_start();
	}

	private function _generateToken( array $data )
	{
		$salt1 = $data[array_rand($data)];
		$salt2 = $data[array_rand($data)];
		$salt3 = $data[array_rand($data)];

		$str = join( '', $data ).microtime();
		$str1 = $str.$salt1.$salt3;
		$str2 = $str.$salt2.$salt3;
		$str3 = $str.$salt2.$salt1;

		$ripemd = hash( 'ripemd160', $str1.$str2 );
		$sha = hash( 'sha256', $str2.$str3 );
		$hash = $ripemd.$sha;

		$crc1 = hash( 'crc32c', $hash );
		$hash = $crc1.$hash;
		$crc2 = hash( 'crc32c', $hash );
		$token = $hash.$crc2;

		return $token;
	}

	private function _extractToken( $token ) //Will be used by _saveToken, _checkToken method
	{
		# Make sure token is valid
			if ( strlen($token) != 120 ) return false; // Check its length

			# Extract token to 4 different part
				$crc1 = substr( $token, 0, 8 );
				$crc2 = substr( $token, -8 );
				$ripemd = substr( $token, 8, 40 );
				$sha = substr( $token, 48, 64 );

			# Validate checksum 1
				$hash = $crc1.$ripemd.$sha;
				if ( hash('crc32c',$hash) != $crc2 ) return false;

			# Validate checksum 2
				$hash = $ripemd.$sha;
				if ( hash('crc32c',$hash) != $crc1 ) return false;

		# Return 'selector' and 'code'
			$result[ 'selector' ] = $ripemd;
			$result[ 'code' ] = $sha;

		return $result;
	}

	private function _saveToken( $user ) //For login function (Remember Me)
	{
		//1. Generate token
				$user_id = $user[ 'id' ];
				$username = $user[ 'username' ];
				$email = $user[ 'email' ];
				$code = $user[ 'code' ];
			$token = $this->_generateToken(compact( 'user_id','username','email','code' ));

		//2. Extract token to 2 different part:
		//	 a) Selector
		//	 b) Code
			$extractedToken = $this->_extractToken( $token );
			extract( $extractedToken ); //$selector, $code

		//3. Save both 'selector' and 'code' to 'auth_token' table in database
			$code_hash = password_hash( $code, PASSWORD_DEFAULT );
			$expiry = new NotORM_Literal( 'NOW()+INTERVAL 7 DAY' );
			$now = new NotORM_Literal( 'NOW()' );
			$created = $now;
			$modified = $now;
			$authToken = $this->db->auth_token()->insert(compact( 'user_id','selector', 'code_hash', 'expiry', 'created', 'modified' ));
			if ( !$authToken ) return false;
			

		//4. Save token in user's browser as cookie
			$remember = setcookie( 'auth_token', $token, $this->cookieConfig );
			if ( !$remember ) {
				$authToken->delete();
				return false;
			}

		return true;
	}

	private function _refreshToken()
	{
		return true;
	}

	private function _checkToken()
	{
		// 1. Get token from browser's cookie
				if ( empty($_COOKIE['auth_token']) ) return false;
				$token = $_COOKIE[ 'auth_token' ];

		// 2. Extract token to 2 different part:
		//	a) Selector
		//	b) Code
				$extractedToken = $this->_extractToken( $token );
				if ( !$extractedToken ) return false;
				extract( $extractedToken );//$selector, $code

		// 3. Using 'selector', find matches row data in 'auth_token' table
				$authToken = $this->db->auth_token[compact('selector')];
				if ( !$authToken ) return false;

		// 4. Validate 'code'
				$validated = password_verify( $code, $authToken['code_hash'] );
				if ( !$validated ) return false;

		return $authToken;
	}

	private function _removeToken()
	{
		# Make sure auth_token does exists in user's browser
			$authToken = $this->_checkToken();
			if ( !$authToken ) return true;

		# Remove it from database first
			$removed = $authToken->delete();
			if ( !$removed ) return false;

		# Then remove it from user's browser
			$config = $this->cookieConfig;
			$config[ 'expires' ] = time()-3600;
			$removed = setcookie( 'auth_token', '', $config );
			if ( !$removed ) return false;
		return true;
	}



	private function _redirectCallback( $redirectTo, $dbRow = [], $errorInfo = '' )
	{
		//Set error message
			if ( $errorInfo ) flash_error( $errorInfo );

		//Redirect to
			if ( is_string($redirectTo) && !empty($redirectTo) ) { // string
				header( "Location: $redirectTo" ); exit;
		//Callback
			} elseif ( is_callable($redirectTo) ) { // callable
				
				$bound = new stdClass;
				$bound->hasError = (bool) $errorInfo;
				$bound->errorInfo = $errorInfo;
				$bound->get = $_GET;
				$bound->post = $_POST;
				
				$redirectTo = $redirectTo->bindTo( $bound );//bind error details to callback
				$url = $redirectTo( $dbRow );//redirect to
				if ( is_string( $url ) ) {
					header( "Location: $url" ); exit;
				}
			}
	}

	private function _mail( $params, &$errorInfo )
	{
		extract( $params ); //$from, $to, $subject, $body, $altBody, $isHTML

		//Create an instance; passing `true` enables exceptions
			$mail = $this->mail;
			try {
				//Server settings
					// $mail->SMTPDebug  = SMTP::DEBUG_SERVER;						//Enable verbose debug output
					// $mail->isSMTP();												//Send using SMTP
					// $mail->Host 		 = 'smtp.example.com';						//Set the SMTP server to send through
					// $mail->SMTPAuth 	 = true;									//Enable SMTP authentication
					// $mail->Username 	 = 'user@example.com';						//SMTP username
					// $mail->Password 	 = 'secret';								//SMTP password
					// $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;				//Enable implicit TLS encryption
					// $mail->Port 		 = 465;										//TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`


				//Sender
					$mail->setFrom( $from['email'], $from['name'] );

				//Recipient
					if ( count($to)>1 ) {
							$mail->addAddress( $to[0], $to[1] );
					} elseif ( !empty($to[0]) ) {
						$mail->addAddress( $to[0] );
					} elseif ( is_string($to) ) {
						$mail->addAddress( $to );
					}
					
				//Content
					$mail->isHTML( $isHTML );										//Set email format to HTML
					$mail->Subject = $subject;
					$mail->Body    = $body;
					$mail->AltBody = $altBody;

					return $mail->send();
			} catch (Exception $e) {
				$errorInfo = $mail->ErrorInfo;
				return false;
			}
	}

	private function _sendMail( $to, $emailType, $vars, &$errorInfo ) // For register & forgot_password
	{
		# Email Configuration
			$mailConfig[ 'from' ][ 'name' ] = 'Admin';
			$mailConfig[ 'from' ][ 'email' ] = 'admin@myweb.com';

			$mailConfig[ 'email_verification' ][ 'subject' ] = '';
			$mailConfig[ 'email_verification' ][ 'template_path' ][ 'plain' ] = 'Emails/email_verification.txt.tpl';
			$mailConfig[ 'email_verification' ][ 'template_path' ][ 'html' ] = 'Emails/email_verification.html.tpl';

			$mailConfig[ 'reset_password' ][ 'subject' ] = '';
			$mailConfig[ 'reset_password' ][ 'template_path' ][ 'plain' ] = 'Emails/reset_password.txt.tpl';
			$mailConfig[ 'reset_password' ][ 'template_path' ][ 'html' ] = 'Emails/reset_password.html.tpl';

			extract( $mailConfig ); // $from
			extract( $$emailType ); // $subject, $template_path

		# Get email body
			$tpl = $this->tpl;
			$tpl->assign( $vars );
			$html = $tpl->fetch( $template_path[ 'html' ] );
			$plain = $tpl->fetch( $template_path[ 'plain' ] );

		# Send email
			$result = $this->_mail([
										'from' => $from,
										'to' => $to,
										'subject' => $subject,
										'body' => $html,
										'altBody' => $plain,
										'isHTML' => true
									], $errorInfo );
		return $result;
	}

	public function register( $redirectTo, $verifyEmail = false, $verificationPage = '' ) // need username, email & password input
	{
		if ( $verifyEmail && !$verificationPage ) {
			throw new Exception( '$confirmationPage parameter can\'t be empty', 1 );
		}
		
		# Make sure register form has been sent first
			if ( $_SERVER[ 'REQUEST_METHOD' ] != 'POST' ) return;

		# Make sure the account is not yet exists
			$user = $this->db->user( "username=? OR email=?", $_POST['username'], $_POST['email'] )->fetch();
			if ( $user ) {
				$this->_redirectCallback( $redirectTo, $user, 'E-mail or username already exists in our system' ); return;
			}

		# If user's email has to be validated, generate secret code
			if ( $verifyEmail ) $_POST[ 'code' ] = hash( 'sha256', $_POST['username'].$_POST['email'].microtime() );//sha256 algorithm

		# Save user data into 'user' table in database
			$now = new NotORM_Literal( 'NOW()' );
			$_POST[ 'created' ] = $now;
			$_POST[ 'modified' ] = $now;
			$_POST[ 'password' ] = password_hash( $_POST[ 'password' ], PASSWORD_DEFAULT ); // For password, store only its hash for security
			$user = $this->db->user()->insert($_POST);
			if ( !$user ) {
				$this->_redirectCallback( $redirectTo, null, 'Failed to register the user. Please try again.' ); return;
			}

		# Send a link contain secret code to user's email
			if ( $verifyEmail ) {
				extract( $_POST );
				$verificationLink = "{$verificationPage}?email={$email}&code={$code}";
				$result = $this->_sendMail( [ $email, $username ], 'email_verification', compact('verificationLink','user'), $mailErrorInfo );
				if ( !$result ) {
					$user->delete();
					$this->_redirectCallback( $redirectTo, null, "Message could not be sent. Mailer Error: {$mailErrorInfo}" ); return;
				} else {
					flash_success( 'A verification link has been sent to your email.' );
					$this->_redirectCallback( $redirectTo, $user );
					return;
				}
			}

		# Redirect user to other page & display success message
			flash_success( 'You have been registered successfully' );
			$this->_redirectCallback( $redirectTo, $user );
	}

	public function verifyEmail( $redirectTo ) //Need url query ?email={email}&code={code}
	{
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
			$registeredTime = new DateTime( $user['created'] );
			$now = new DateTime();
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
				flash_success( 'Your email has been verified' );
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
			flash_success( 'Your email has been verified successfully' );
			$this->_redirectCallback( $redirectTo, $user );
	}

	public function login( $redirectTo ) //need email & password input (remember input is optional)
	{
		# Make sure login form has been sent first
			if ( $_SERVER[ 'REQUEST_METHOD' ] != 'POST' ) return;

		# Make sure the account does really exists
			$user = $this->db->user[[ 'email'=>$_POST[ 'email' ] ]];
			if ( !$user ) {
				$this->_redirectCallback( $redirectTo, null, 'Your email does not exists in our system' ); return;
			}

		# Make sure email has been verified first (block fake account)
			if ( !$user['verified'] ) {
				$this->_redirectCallback( $redirectTo, $user, 'Unverified account. Please check your email for verification link.' ); return;
			}

		# Make sure the password is valid
			$password_verified = password_verify( $_POST['password'], $user['password'] );
			if ( !$password_verified ) {
				$this->_redirectCallback( $redirectTo, $user, 'Invalid password. Please try again.' ); return;
			}

		# Give the user permission to access member area (access card)
			$_SESSION[ 'auth' ] = [
									'id' => $user[ 'id' ],
									'username' => $user[ 'username' ],
									'role' => $user[ 'role' ]
									];

		# Save cookie token in user's browser
			if ( !empty($_POST['remember']) ) {
				$this->_saveToken( $user );
			}

		# Redirect user or execute callback
			$this->_redirectCallback( $redirectTo, $user );
	}

	public function forgotPassword( $redirectTo, $resetPasswordPage = '' ) //need email input
	{
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
				flash_success( 'A link to reset your password has been sent to your email.' );
				$this->_redirectCallback( $redirectTo, $user );
			}

		# Redirect user or execute callback
			$this->_redirectCallback( $redirectTo, $user );//----------------------------check this
	}

	public function resetPassword( $redirectTo ) //need 2 GET global variable 'email' & 'code' and 1 password input
	{
		# Make sure url query - email and code does exist
			if ( empty($_GET['email']) || empty($_GET['code']) ) {
				$this->_redirectCallback( $redirectTo, null, 'Invalid reset password link' ); return;
			}
			$email = $_GET[ 'email' ];
			$code = $_GET[ 'code' ];

		# Make sure the email format is valid
			$email = filter_var( $email, FILTER_VALIDATE_EMAIL );
			if ( !$email ) {
				$this->_redirectCallback( $redirectTo, null, 'Invalid email in reset password link' ); return;
			}
			// if (!checkdnsrr($domain, 'MX')) {
			// 	// domain is not valid
			// }

		# Make sure the email exists in our database
			$user = $this->db->user[[ 'email'=>$email ]];
			if ( !$user ) {
				$this->_redirectCallback( $redirectTo, null, 'The email does not exist in our database' ); return;
			}

		# Make sure the code is valid
			if ( $code != $user['code'] ) {
				$this->_redirectCallback( $redirectTo, $user, 'Invalid code in reset password link' ); return;
			}

		# 'Reset password' link is valid.--------------------------------------------------------------------------------------------------
		# Make sure reset password form has been sent
			if ( $_SERVER[ 'REQUEST_METHOD' ] != 'POST' ) {
				$this->_redirectCallback( $redirectTo, $user );
				return;
			}
			$password = $_POST[ 'password' ];

		# Then, reset value in 'password' column
			$password = password_hash( $password, PASSWORD_DEFAULT ); // Store only its hash for security
			$result = $user->update(compact( 'password' ));
			if ( !$result ) {
				$this->_redirectCallback( $redirectTo, $user, 'Failed to reset new password. Please try again.' ); return;
			}

		# Redirect user or execute callback
			flash_success( 'Your password was successfully changed' );
			$this->_redirectCallback( $redirectTo, $user );
	}

	public function logout( $redirectTo )
	{
		# Delete session
			unset( $_SESSION[ 'auth' ] );

		# Remove cookie from user's browser
			$this->_removeToken();
		
		# Redirect the user to other location and display message
			flash_success( 'You have successfully logged out!' );
			$this->_redirectCallback( $redirectTo );
	}

	public function isLoggedIn()
	{
		# Check whether the user's session exists
			if ( !empty($_SESSION['auth']) ) return true;

		# If not, then check browser's cookie
			$authToken = $this->_checkToken();
			if ( !$authToken ) return false;

		# If auth_token in browser's cookie is valid, then login user automatically
		# Give the user permission to access member area (access card)
			$_SESSION[ 'auth' ] = [
									'id' => $authToken->user[ 'id' ],
									'username' => $authToken->user[ 'username' ],
									'role' => $authToken->user[ 'role' ]
									];

		# Refresh token to improve security
			$this->_refreshToken();

		return true;
	}

	public function memberArea( $redirectTo, $allowedRole = null, $returnUserData = false )
	{
		# Make sure the user has logged in
			if ( !$this->isLoggedIn() ) {
				$this->_redirectCallback( $redirectTo, null, 'No access to member area!' ); return;// No access? Kick the user out!
				
			}

		# Make sure the user has permission to access page
			$user = $this->getUser();
			$userRole = $user[ 'role' ];
			if ( is_array($allowedRole) && !in_array($userRole,$allowedRole) ) {
				$this->_redirectCallback( $redirectTo, $user, 'No permission!' ); return; // No permission
			}

			if ( is_scalar($allowedRole) && $userRole != $allowedRole ) {
				$this->_redirectCallback( $redirectTo, $user, 'No permission!' ); return; // No permission
			}

		# Return user data
			if ( $returnUserData === true || $returnUserData === null ) {
				return $this->getUser();
			} elseif ( is_string($returnUserData) ) {
				return $this->getUser( $returnUserData );
			}
	}

	public function getUser( $column = null )
	{
		$auth = $_SESSION[ 'auth' ];
		$keys = array_keys( $auth );
		if ( in_array( $column, $keys ) ) { //id, username, role
			return $auth[ $column ];
		} elseif ( $column ) {
			return $this->db->user[ $auth['id'] ][ $column ]; //certain column
		} else {
			return $this->db->user[ $auth['id'] ];//'user' table
		}
	}

	// This can be used to change and add new session data
	// include id, username and role
	public function updateSession( $name, $value = null )
	{
		if ( is_array($name) ) {
			foreach ( $name as $key => $value ) {
				$_SESSION[ 'auth' ][ $key ] = $value;
			}
		} elseif ( is_string($name) ) {
			$_SESSION[ 'auth' ][ $name ] = $value;
		}
	}
}