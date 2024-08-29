<?php
require_once 'config.php';
// $csrfToken = $ezauth->csrfToken();
// $flash = $ezauth->flashMessage();
// $status = $ezauth->register();
list( $status, $flash, $csrfToken ) = $ezauth->register();

include 'forms/register_form.php';