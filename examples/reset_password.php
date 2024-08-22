<?php
require_once 'config.php';
list( $status, $flash, $csrfToken ) = $ezauth->resetPassword();




include 'forms/reset_password_form.php';