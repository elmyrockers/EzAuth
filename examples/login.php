<?php
require_once 'config.php';
list( $status, $flash, $csrfToken ) = $ezauth->login();




include 'forms/login_form.php';