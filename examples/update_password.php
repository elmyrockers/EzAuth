<?php
require_once 'config.php';
$user = $ezauth->memberArea();


list(, $flash, $csrfToken ) = $ezauth->updatePassword();

// dump( $user );

include 'forms/update_password_form.php';