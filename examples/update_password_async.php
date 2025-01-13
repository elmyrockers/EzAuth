<?php
require_once 'config.php';
$user = $ezauth->memberArea();


list(, $flash, $csrfToken ) = $ezauth->updatePasswordAsync();

// dump( $user );

include 'forms/update_password_async_form.php';