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
				<a href="/">frankstainlove.xyz</a>
				/
				<a href="/photography/index.md">photography</a>
				/
				<div class="popover-container">
					<a href="/photography/threading-walking-this-and-that" aria-label="실꿰기, 이리저리 걷기">실꿰기, 이리저리 걷기</a>
					<div class="popover-image">
						<img src="/assets/image/threading-walking-this-and-that/threading-walking-this-and-that-1.webp" >
					</div>
				</div>
			</menu>
		</header>
		<main class="photography-page">
			<?php echo $body ?>
		</main>
		<footer>
			<?php echo $footer ?>
		</footer>
	</body>
</html>
