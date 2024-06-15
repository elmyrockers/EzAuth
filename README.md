
# EzAuth

Lightweight & easy authentication library with secure 'remember me' feature for PHP. With this, you just need to write a few lines of PHP code only in each pages.

## Usage/Examples

1. Install via composer:
	```sh
	composer require elmyrockers/ezauth
	```
2. Include composer autoloader:
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