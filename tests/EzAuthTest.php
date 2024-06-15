<?php
use PHPUnit\Framework\TestCase;
use elmyrockers\EzAuth;

class EzAuthTest extends TestCase
{
	const ROLE_USER = 0;
	const ROLE_ADMIN = 1;
	const ROLE_SUPERADMIN = 2;

	private static $auth;

	public static function setUpBeforeClass(): void
	{
		$database = [
			'dsn' => 'mysql:host=localhost;dbname=ezauth',
			'username' => 'root',
			'password' => ''
		];
		$email = [
			'smtp_settings' => [
				'debug' => true,
				'host' => 'amazonaws.com',
				'username' => 'username',
				'password' => 'password',
				'port' => '587',
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
		$message = [
				// 'language' => [ 'en', '/optional/path/to/language/directory/' ],
				'template' => [
					'success' => '<div class="alert alert-success">{$message}</div>',
					'error' => '<div class="alert alert-danger">{$message}</div>'
				]
		];
		$auth = [
				'id_fields' => [ 'email' ], // username / email / phone - follow table columns (its value must be unique)
				'allowed_fields' => [ 'username','email', 'password', 'confirm_password' ],

				'logout_redirect' => '/auth/login/',
				'member_area' => [
					self::ROLE_USER => '/member/user/',
					self::ROLE_ADMIN => '/member/admin/',
					self::ROLE_SUPERADMIN => '/member/superadmin/'
				],
				'verify_email' => '',  // Specify page for email verification
				'reset_password' => '' // Specify page for reset password
		];
		self::$auth = new EzAuth(compact( 'database', 'email', 'message', 'auth' ));
	}

	public function testRegister()
	{
		$auth = self::$auth;

		$auth->register();
		// dump( $auth );
		$this->assertTrue( true );
		
		$this->markTestIncomplete( 'This test has not been implemented yet.' );
	}

	public function testVerifyEmail()
	{
		$auth = self::$auth;

		$auth->verify_email();
		// dump( $auth );
		$this->assertTrue( true );
		
		$this->markTestIncomplete( 'This test has not been implemented yet.' );
	}
}
