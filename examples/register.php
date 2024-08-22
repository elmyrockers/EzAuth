<?php
require_once 'config.php';
list( $status, $flash, $csrfToken ) = $ezauth->register();




include 'forms/register_form.php';