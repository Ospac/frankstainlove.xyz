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
		<link rel="stylesheet" href="/assets/css/photography.css">
		<link rel="icon" type="image/x-icon" href="/page/assets/image/favicon.ico">
	</head>
	<body>
		<header>
			<menu>
				<a href="/">...</a>
				/
				<a href="/photography/index.md">photography</a>
				/
				<a href="">
					<?php echo $title ?>
				</a>
			</menu>
		</header>
		<main class="photography-page">
			<?php echo $body ?>
		</main>
	</body>
</html>
