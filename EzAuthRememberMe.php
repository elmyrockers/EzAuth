<?php
namespace elmyrockers;

use elmyrockers\EzAuthRememberMeInterface;
use \RedBeanPHP\R as R;
use Firebase\JWT\JWT;



class EzAuthRememberMe implements EzAuthRememberMeInterface
{
	private $config;
	public function __construct( $config )
	{
		$this->config = $config;
	}

	public function generateToken( $user )
	{
		$secretKey = $this->config[ 'auth' ][ 'secret_key' ];
		$validIn7Days = strtotime( '+7 days' );

		# Generate token
			$plainToken = bin2hex( random_bytes(32) );
			$hashedToken = hash_hmac( 'sha256', $plainToken, $secretKey );

		# Save $hashedToken into database
			$rememberTable = $this->config[ 'database' ][ 'remember_table' ];
			$remember = R::dispense( $rememberTable );

			$currentIsoDateTime = R::isoDateTime();
			$remember[ 'user_id' ] = $user[ 'id' ];
			$remember[ 'token' ] = $hashedToken;
			$remember[ 'expires_at' ] = date( 'Y-m-d H:i:s', $validIn7Days );
			$remember[ 'created' ] = $currentIsoDateTime;
			$remember[ 'modified' ] = $currentIsoDateTime;
			try {
				R::store( $remember );
			} catch (\Exception $e) {
				return false;
			}
			
		# Store $plainToken into cookie
			# Generate JSON Web Token (JWT)
				$payload = [
					'token' => $plainToken,
					'user_id' => $user[ 'id' ],
					'user_agent' => $_SERVER['HTTP_USER_AGENT'],
					'exp' => $validIn7Days // Expire in 7 days
				];
				$jwt = JWT::encode( $payload, $secretKey, 'HS256' );

			# Encrypt it
				$algorithm = 'aes-256-cbc';
				$iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length($algorithm) );
				$encryptedJwt = openssl_encrypt( $jwt, $algorithm, hex2bin($secretKey), OPENSSL_RAW_DATA, $iv );

				$encryptedJwtWithIv = base64_encode($iv.$encryptedJwt); // Combine both IV and encrypted JWT together

			# Store it in cookie
				return setcookie( 'auth_token', $encryptedJwtWithIv,[
					'expires' => $validIn7Days,
					'path' => '/',
					'samesite' => 'Strict',
					'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
					'httponly' => true
				]);
	}

	public function verifyToken()
	{
		
	}
}