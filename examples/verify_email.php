<?php
require_once 'config.php';


$ezauth->verify_email( 'login.php' );
echo $ezauth->flashMessage();