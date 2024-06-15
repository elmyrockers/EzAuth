
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
7. Create `register_form.php` file, and add this HTML form:
	```html
	<!-- register_form.php -->
	<form method="post">
		<?=$flash ?>
		<?=$csrfToken ?>
		
		<h1 class="my-3">Register Form</h1>
		<div class="mb-3 row">
			<label for="username" class="col-sm-3 col-form-label">Username:</label>
			<div class="col-sm-9">
				<input type="text" id="username" class="form-control" value="">
			</div>
		</div>
		<div class="mb-3 row">
			<label for="email" class="col-sm-3 col-form-label">Email:</label>
			<div class="col-sm-9">
				<input type="email" id="email" class="form-control" value="">
			</div>
		</div>
		<div class="mb-3 row">
			<label for="password" class="col-sm-3 col-form-label">Password:</label>
			<div class="col-sm-9">
				<input type="password" id="password" class="form-control">
			</div>
		</div>
		<div class="mb-3 row">
			<label for="confirm-password" class="col-sm-3 col-form-label">Confirm Password:</label>
			<div class="col-sm-9">
				<input type="password" id="confirm-password" class="form-control">
			</div>
		</div>
		<div class="mt-3 mb-3 row">
			<div class="col">
				<button type="submit" class="btn btn-primary float-end">Register</button>
			</div>
		</div>
	</form>
	```
7. Create `register.php` file and add this code. Make sure you include `register_form.php` at the bottom:
	```php
	// register.php
	<?php
	require_once 'config.php'; // Include configuration file
	$auth->register();
	$flash = $auth->flashMessage();
	$csrfToken = $auth->csrfToken();

	include 'register_form.php'; 
	```
	Then, you can try to submit this register form, then view its result on your localhost. Good luck!


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