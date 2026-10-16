<!DOCTYPE html>
<?php
	$defaultTitle = 'lichen-markdown';
?>
<html>
	<head>
		<meta charset="UTF-8">
		<title><?php echo htmlspecialchars($title ?? $defaultTitle, ENT_QUOTES, 'UTF-8'); ?></title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="/assets/css/stylesheet.css">
	</head>
	<body>
		<header>
			<?php echo $header ?>
		</header>
		<main>
			<?php echo $body ?>
		</main>
		<footer>
			<?php echo $footer ?>
		</footer>
	</body>
</html>
