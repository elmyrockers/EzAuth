<?php
namespace elmyrockers;

use elmyrockers\EzAuthRememberMeInterface;
use \RedBeanPHP\R as R;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;



class EzAuthRememberMe implements EzAuthRememberMeInterface
{
	private $config;
	public function initialize(array $config ):void
	{
		$this->config = $config;
	}

	public function generateToken(\RedBeanPHP\OODBBean $user ):bool
	{
		$secretKey = $this->config[ 'auth' ][ 'secret_key' ];
		$validIn7Days = strtotime( '+7 days' );

		# Generate token
			$plainToken = bin2hex( random_bytes(32) );
			$hashedToken = hash_hmac( 'sha256', $plainToken, $secretKey );

		# Save $hashedToken into database
			# Check existing data to determine whether it's insert or update
				$userID = "{$this->config[ 'database' ][ 'user_table' ]}_id";
				$rememberTable = $this->config[ 'database' ][ 'remember_table' ];
				$remember = R::findOne( $rememberTable, "{$userID}=?", [$user['id']] );
				if ( !$remember ) {
					$remember = R::dispense( $rememberTable );
				}

				$currentIsoDateTime = R::isoDateTime();
				$remember[ $userID ] = $user[ 'id' ];
				$remember[ 'token' ] = $hashedToken;
				$remember[ 'expires_at' ] = date( 'Y-m-d H:i:s', $validIn7Days );
				$remember[ 'created' ] = $currentIsoDateTime;
				$remember[ 'modified' ] = $currentIsoDateTime;

			# Save it
				try {
					R::store( $remember );
				} catch (\Exception $e) {
					return false;
				}
			
		# Store $plainToken into cookie
			# Generate JSON Web Token (JWT)
				$domain = $this->config[ 'auth' ][ 'domain' ];
				$payload = [
					'iss' => $domain,
					'aud' => $domain,
					'token' => $plainToken,
					$userID => $user[ 'id' ],
					'user_agent' => $_SERVER['HTTP_USER_AGENT'],
					'exp' => $validIn7Days // Expire in 7 days
				];
				$jwt = JWT::encode( $payload, $secretKey, 'HS256' );

			# Encrypt it
				$algorithm = 'aes-256-gcm';
				$iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length($algorithm) );
				$encryptedJwt = openssl_encrypt( $jwt, $algorithm, hex2bin($secretKey), OPENSSL_RAW_DATA, $iv, $tag );

				$encryptedJwtWithIv = base64_encode($iv.$encryptedJwt); // Combine both IV and encrypted JWT together
				$tag = base64_encode($tag);

			# Store it in cookie
				return setcookie( 'auth_token', "{$encryptedJwtWithIv}.{$tag}",[
					'expires' => $validIn7Days,
					'path' => '/',
					'samesite' => 'Strict',
					'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
					'httponly' => true
				]);
	}

	public function verifyToken():bool|\RedBeanPHP\OODBBean
	{
		$secretKey = $this->config[ 'auth' ][ 'secret_key' ];

		# Retrieve $plainToken from cookie
			# Retrieve encrypted JWT and IV from cookie
				$cookieValue = $_COOKIE[ 'auth_token' ] ?? null;
				if ( !$cookieValue ) return false;

				$cookieValue = explode( '.', $cookieValue );
				if ( count($cookieValue)<2 ) return false;

			# Decrypt it
				$algorithm = 'aes-256-gcm';

				$encryptedJwtWithIv = base64_decode( $cookieValue[0] ); //Extract the 'IV' and 'encryptedJWT'
				$tag = base64_decode( $cookieValue[1] ); //Extract the 'authentication tag'

				$ivLength = openssl_cipher_iv_length($algorithm);
				$iv = substr( $encryptedJwtWithIv, 0, $ivLength );
				$encryptedJwt = substr( $encryptedJwtWithIv, $ivLength );

				$jwt = openssl_decrypt( $encryptedJwt, $algorithm, hex2bin($secretKey), OPENSSL_RAW_DATA, $iv, $tag );
				if ( !$jwt ) return false;

			# Decode and verify JWT signature
				try {
					$payload = (array) JWT::decode( $jwt, new Key( $secretKey, 'HS256' ));
				} catch (\Exception $e) {
					return false;
				}

			# Validate issuer and audience
				$domain = $this->config[ 'auth' ][ 'domain' ];
				$invalidIssuer = $payload['iss'] !== $domain;
				$invalidAudience = $payload['aud'] !== $domain;
				if ( $invalidIssuer || $invalidAudience ) return false;
				
			# Validate user-agent
				if ( $payload['user_agent'] !== $_SERVER['HTTP_USER_AGENT'] ) return false;

		# Retrieve $hashedToken from database
			$userTable = $this->config[ 'database' ][ 'user_table' ];
			$userID = "{$userTable}_id";
			$rememberTable = $this->config[ 'database' ][ 'remember_table' ];
			$remember = R::findOne( $rememberTable, "{$userID}=?", [$payload[$userID]] );
			if ( !$remember ) return false;

		# Compare $plainToken and $hashedToken
			$cookieToken = hash_hmac( 'sha256', $payload['token'], $secretKey );
			$dbToken = $remember[ 'token' ];
			if ( !hash_equals($cookieToken,$dbToken) ) return false;

		# If success, get and return data about user details
			return $remember->$userTable;
	}

	public function removeToken():bool
	{
		# Get user data
			$user = $this->verifyToken();
			if ( !$user ) return false;

		# Remove $encryptedToken from cookie
			setcookie( 'auth_token', '', 1000 );

		# Remove $hashedToken from database
			$rememberTable = ucfirst($this->config[ 'database' ][ 'remember_table' ]);
			try {
				$remember = "own{$rememberTable}List";
				R::trashAll( $user->$remember );
			} catch (\Exception $e) {
				return false;
			}

		return true;
	}
}