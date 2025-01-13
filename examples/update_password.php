<?php
require_once 'config.php';
$user = $ezauth->memberArea();


list( $status, $flash, $csrfToken ) = $ezauth->updatePassword();

// dump( $status, $flash, $csrfToken );

include 'forms/update_password_form.php';