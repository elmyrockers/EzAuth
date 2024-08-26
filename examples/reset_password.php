<?php
require_once 'config.php';
list( $status, $flash, $csrfToken ) = $ezauth->resetPassword( 'login.php', $user );



include 'forms/reset_password_form.php';