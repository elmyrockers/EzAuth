
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
5. Create new EzAuth object instance. Put the configuration `$config` as its parameter:
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
7. Create `register_form.php` file, and put this HTML code:
	```html
	<!DOCTYPE html>
	<html>
		<head>
			<meta charset="utf-8">
			<title>EzAuth: Register</title>
			<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
		</head>
		<body>
			<div class="container">
				<div class="row">
					<div class="offset-2 col-8 mt-5">
						<div class="alert alert-danger">There is an error occurred!</div>
						<?=$flash ?>
						<h1 class="my-3">Register Form</h1>
						<form method="post">
							<?=$csrfToken ?>
							<div class="mb-3 row">
								<label for="username" class="col-sm-3 col-form-label">Username:</label>
								<div class="col-sm-9">
									<input type="text" name="username" id="username" class="form-control" value="">
								</div>
							</div>
							<div class="mb-3 row">
								<label for="email" class="col-sm-3 col-form-label">Email:</label>
								<div class="col-sm-9">
									<input type="email" name="email" id="email" class="form-control" value="">
								</div>
							</div>
							<div class="mb-3 row">
								<label for="password" class="col-sm-3 col-form-label">Password:</label>
								<div class="col-sm-9">
									<input type="password" name="password" id="password" class="form-control">
								</div>
							</div>
							<div class="mb-3 row">
								<label for="confirm-password" class="col-sm-3 col-form-label">Confirm Password:</label>
								<div class="col-sm-9">
									<input type="password" name="confirm_password" id="confirm-password" class="form-control">
								</div>
							</div>
							<div class="mt-3 mb-3 row">
								<div class="col">
									<button type="submit" class="btn btn-primary float-end">Register</button>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>
		</body>
	</html>
	```
7. Create `register.php` file and put this code:
	```php
	// register.php
	<?php
	require_once 'config.php'; // Include configuration file
	$auth->register();
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