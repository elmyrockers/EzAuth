
# EzAuth

Lightweight & easy authentication library with secure 'remember me' feature for PHP. With this, you just need to write a few lines of PHP code only in each pages.

## Usage/Examples

#### 1. Installation:
Install the EzAuth package using Composer:
```sh
composer require elmyrockers/ezauth
```
#### 2.  Include Composer's Autoloader:
Include Composer's autoloader script in your project:
```php
<?php
require_once 'vendor/autoload.php';
```
#### 3. Using EzAuth Package:
Import the `EzAuth` class from the `elmyrockers\EzAuth` namespace:
```php
use elmyrockers\EzAuth;
```
#### 4. Configuration:
Create an array contain configuration for our authentication:
```php
$config = [];
```
#### 5. EzAuth Object:
Instantiate a new `EzAuth` object, passing the configuration array as an argument.
```php
$auth = new EzAuth( $config );
```
#### 6. bootstrap.php:
Save the code as `bootstrap.php`. The complete code for this file should look like this:
```php
// bootstrap.php
<?php
require_once 'vendor/autoload.php'; //Include composer's autoloader
use elmyrockers\EzAuth; // Use EzAuth package

$config = []; // Configuration
$auth = new EzAuth( $config );
```
#### 7. Register Form:
Create a file named `register_form.php` containing the following HTML form. This form includes Bootstrap 5 for styling (you can replace it with your preferred framework).
```html
<!-- register_form.php -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
#### 8. register.php:
Create a file named `register.php` with the following code. It includes `bootstrap.php` and retrieves flash messages and the CSRF token from the `EzAuth` object before displaying the registration form.
```php
// register.php
<?php
require_once 'bootstrap.php'; // Include bootstrap file

list(,$flash, $csrfToken ) = $auth->register();  // Extract flash message and CSRF token

include 'register_form.php';
```
	
You can now access `register.php` in your web browser to see the registration form and submit registration data. Good luck!


## Reference

>#### Configurations:
>- Database
>- Email
>- Message
>- Auth

>#### Methods:
>- __construct( array $config )
>- $csrfToken = csrfToken()
>- $flash = flashMessage()
>- list( $status, $flash, $csrfToken ) = register( $callback = null )
>- list( $status, $flash ) = verifyEmail( $callback = null )
>- list( $status, $flash, $csrfToken ) = login( $callback = null )
>- list( $status, $flash, $csrfToken ) = recoverPassword( $callback = null )
>- list( $status, $flash, $csrfToken ) = resetPassword( $callback = null )
>- $user = memberArea( null\|string\|array $allowedRoles = null )
>- $user = isLoggedIn()
>- redirectLoggedInUser()
>- logout()

[Simple Tutorial](https://elmyrockers.github.io/EzAuth)

## Authors

[@elmyrockers](https://www.github.com/elmyrockers)


## License

[MIT](https://choosealicense.com/licenses/mit/)