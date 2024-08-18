<?php
require_once 'config.php';
list( $status, $flash, $csrfToken ) = $ezauth->login();




include 'login_form.php';