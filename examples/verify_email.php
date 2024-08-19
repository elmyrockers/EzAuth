<?php
require_once 'config.php';
list( $status, $flash ) = $ezauth->verify_email( 'login.php' );

echo $flash;