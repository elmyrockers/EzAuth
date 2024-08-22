<?php
require_once 'config.php';
list( $status, $flash, $csrfToken ) = $ezauth->recoverPassword();




include 'forms/recover_password_form.php';