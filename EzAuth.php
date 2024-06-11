<?php
namespace elmyrockers;

require_once 'rb.php';


/**
 * 
 */
class EzAuth
{

	public function __construct( $config )
	{
		extract( $config ); // $database, $email, $message, $auth
	}
}

$database = [
	'dsn' => 'mysql:host=localhost;dbname=ezauth',
	'username' => 'root',
	'password' => ''
];
$auth = new EzAuth(compact( 'database' ));