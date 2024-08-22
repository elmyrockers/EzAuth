<!DOCTYPE html>
<html lang="en" >
	<head>
		<meta charset="UTF-8">
		<title>Recover Password Form - EzAuth</title>
		<link rel="stylesheet" href="forms/style.css">
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-+0n0xVW2eSR5OomGNYDnhzAbDsOXxcvSN1TPprVMTNDbiYZCxYbOOl7+AMvyTG2x" crossorigin="anonymous">
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
	</head>
	<body>
		<div class="bg"></div>
		<main class="form-signin">
			<?=$flash ?>
			<h1 class="h3">Recover Password</h1>
			<form method="post">
				<div class="form-floating mb-4">
					<input name="email" type="email" class="form-control" id="floatingInput" placeholder="Email Address" required value="test@gmail.com">
					<label for="floatingInput">Email Address</label>
				</div>
				<button class="w-100 btn btn-lg" type="submit">Send Link</button>
			</form>
			<p class="copyright">&copy; 2024 EzAuth</p>
		</main>
	</body>
</html>