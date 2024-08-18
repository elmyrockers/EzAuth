<?php
require_once 'config.php';
list( $status, $flash, $csrfToken ) = $ezauth->register();




include 'register_form.php';