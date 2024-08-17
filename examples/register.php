<?php
require_once 'config.php';

// $_SERVER[ 'REQUEST_METHOD' ] = 'POST';
// $_POST[ 'username' ] = 'elmyrockers';
// $_POST[ 'email' ] = 'elmyrockers@company.com';
// $_POST[ 'password' ] = '12345';
// $_POST[ 'confirm_password' ] = '12345';
$ezauth->register();
echo $ezauth->flashMessage();







include 'register_form.php';