<?php
require_once 'config.php';


// $ezauth->register();
// $flash = $ezauth->flashMessage();
$ezauth->verify_email( 'login.php' );
// dump( $ezauth );

// $secretKey = bin2hex( random_bytes( 32 ));
// dump( $secretKey );