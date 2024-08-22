<?php
require_once 'config.php';
list( $status, $flash ) = $ezauth->verifyEmail( 'login.php' );

echo $flash;