<?php 
	$title = $title ?? "frankstainlove.xyz"
?>
<!DOCTYPE html>
<html lang="ko">
	<head>
		<meta charset="UTF-8">
		<title><?php echo $title ?></title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="/assets/css/stylesheet.css">
		<link rel="icon" type="image/x-icon" href="/page/assets/image/favicon.ico">
	</head>
	<body>
		<header>
			<?php echo $header ?>
		</header>
		<main class="broider-leaves">
			<?php echo $body ?>
		</main>
		<footer>
			<?php echo $footer ?>
		</footer>
	</body>
</html>
