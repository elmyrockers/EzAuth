<?php
require_once 'config.php';
list( $status, $flash, $csrfToken ) = $ezauth->resetPassword( 'login.php', $user, $hasSent );

if ( !$hasSent && $status === false ) {
	header( "Location: login.php" ); exit;
}

include 'forms/reset_password_form.php';