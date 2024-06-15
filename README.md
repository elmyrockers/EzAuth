
# EzAuth

Lightweight & easy authentication library with secure 'remember me' feature for PHP. With this, you just need to write a few lines of PHP code only in each pages.

## Usage/Examples

1. Install via composer:
	```sh
	composer require elmyrockers/ezauth
	```
2. Include composer's autoloader:
	```php
	<?php
	require_once 'vendor/autoload.php';
	```
3. Include this code to use EzAuth package:
	```php
	use elmyrockers\EzAuth;
	```
4. Create an array contain configuration for our authentication:
	```php
	$config = [];
	```
5. Create new EzAuth instance. Put the configuration as its parameter:
	```php
	$auth = new EzAuth( $config );
	```
6. Save current file as `config.php`. The whole of code for `config.php` should look like this one:
	```php
	// config.php
	<?php
	require_once 'vendor/autoload.php'; //Include composer's autoloader
	use elmyrockers\EzAuth; // Use EzAuth package

	$config = []; // Configuration
	$auth = new EzAuth( $config );
	```
7. Create `register.php` file and put this code:
	```php
	// register.php
	<?php
	require_once 'config.php'; // Include configuration file
	$auth->register();
	//--------------------------------------?>
	<!DOCTYPE html>
	<html>
	<head>
		<meta charset="utf-8">
		<title>EzAuth: Register</title>
		<!-- <link rel="stylesheet" type="text/css" href="bootstrap5"> -->
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	</head>
	<body>
		<div class="container">
			
		</div>
	</body>
	</html>	
	```
	```html


	```

5. Then you can call any EzAuth method:
	```php
	$auth->register();
	```

## Reference

>- __construct( array $config )
>- register( $callback = null )
>- verifyEmail( $callback = null )
>- login( $callback = null )
>- recoverPassword( $callback = null )
>- resetPassword( $callback = null )
>- memberArea( null\|string\|array $allowedRoles = null )
>- isLoggedIn()
>- logout()

[Simple Tutorial](https://elmyrockers.github.io/EzAuth)

## Authors

[@elmyrockers](https://www.github.com/elmyrockers)


## License

[MIT](https://choosealicense.com/licenses/mit/)