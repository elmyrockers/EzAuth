<!DOCTYPE html>
<html lang="en" >
	<head>
		<meta charset="UTF-8">
		<title>Update Password Form - EzAuth</title>
		<link rel="stylesheet" href="forms/style.css">
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-+0n0xVW2eSR5OomGNYDnhzAbDsOXxcvSN1TPprVMTNDbiYZCxYbOOl7+AMvyTG2x" crossorigin="anonymous">
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
	</head>
	<body>
		<div class="bg"></div>
		<main class="form-signin">
			<?=$flash ?>
			<h1 class="h3">Update Password</h1>
			<form method="post">
				<?=$csrfToken ?>
				<div class="form-floating">
					<input name="current_password" type="password" class="form-control mb-0" id="current_password" placeholder="Current Password" required>
					<label for="current_password">Current Password</label>
				</div>
				<div class="form-floating">
					<input name="new_password" type="password" class="form-control mb-0" id="new_password" placeholder="New Password" required>
					<label for="new_password">New Password</label>
				</div>
				<div class="form-floating mb-4">
					<input name="confirm_password" type="password" class="form-control" id="confirm_password" placeholder="Confirm Password" required>
					<label for="confirm_password">Confirm Password</label>
				</div>
				<button class="w-100 btn btn-lg" type="submit">Update</button>
			</form>
			<p class="copyright">&copy; 2025 EzAuth</p>
		</main>
	</body>
</html>