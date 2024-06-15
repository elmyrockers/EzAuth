<?php
require_once 'config.php';

$_SERVER[ 'REQUEST_METHOD' ] = 'POST';
$_POST[ 'username' ] = 'elmyrockers';
$_POST[ 'email' ] = 'elmyrockers@company.com';
$_POST[ 'password' ] = '12345';
$_POST[ 'confirm_password' ] = '12345';
$ezauth->register();
$flash = $ezauth->flashMessage();
echo $flash;
// $ezauth->verify_email( 'login.php' );
// dump( $ezauth );

// $secretKey = bin2hex( random_bytes( 32 ));
// dump( $secretKey );