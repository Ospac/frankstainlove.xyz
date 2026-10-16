<?php
function exception_handler($e)
{
	http_response_code(500);
	echo $e->getMessage();
	exit(1);
}
set_exception_handler('exception_handler');

require_once "utils.php";

define("EDITABLE_EXTENSIONS", ["gmi", "md", "css", "html"]);

function normalize_edit_path()
{
	$path = $_SERVER['PATH_INFO'] ?? '/';
	$path = $path === '' ? '/' : $path;
	if (str_ends_with($path, '/')) {
		$path .= 'index.md';
	}

	return urldecode($path);
}

$_SERVER['PATH_INFO'] = normalize_edit_path();

?>
<?php
// File tree data and HTML rendering.
function get_filetree($basePath)
{
	$directory = new RecursiveDirectoryIterator($basePath);
	$filter = new RecursiveCallbackFilterIterator($directory, function ($current, $key, $iterator) {
		if ($current->getFilename()[0] === '.') { // skip hidden files and directories.
			return FALSE;
		} elseif (in_array($current->getBasename(), ['cms', 'layout', 'src', 'dist', 'update', '.git'])) {
			return FALSE;
		}
		return TRUE;
	});

	$iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);

	$fileTree = array();
	foreach ($iterator as $fileInfo) {
		$path = $fileInfo->isDir()
			? array($fileInfo->getFilename() => array())
			: array($fileInfo->getFilename());

		for ($depth = $iterator->getDepth() - 1; $depth >= 0; $depth--) {
			$path = array($iterator->getSubIterator($depth)->current()->getFilename() => $path);
		}
		$fileTree = array_merge_recursive($fileTree, $path);
	}
	$fileTree = array(basename(PROJECT_DIR) => $fileTree);
	return $fileTree;
} ?>
<?php function render_file($filename, $base)
{ ?>
	<?php $publicBase = preg_replace('#^/page/assets(?=/|$)#', '/assets', $base); ?>
	<li class="file">
		<?php $extension = strtolower(substr(strrchr($filename, '.'), 1)); ?>
		<?php if (in_array($extension, EDITABLE_EXTENSIONS, true)): ?>
			<a href="<?php echo "/cms/edit.php" . $base . $filename ?>" class="link--editable">
				<span class="name"><?php echo $filename ?></span>
			</a>
		<?php else: ?>
			<a href="<?php echo $publicBase . $filename ?>" target="_blank" class="link--unknown">
				<span class="name"><?php echo $filename ?></span>
			</a>
		<?php endif; ?>
		<input data-path="<?php echo $base . $filename ?>" class="edit-link hidden" style="width: 100%;"
			value="<?php echo $filename ?>" onkeydown="editInputKeydown(event, this.parentElement, this.dataset.path).then((newEl) => {
			if (newEl != null) {
				this.parentElement.replaceWith(newEl);
			}
			});"></input>
		<div class="actions">
			<button title="Insert Link" data-path="<?php echo $base . $filename ?>" onclick="
				const el = document.getElementById('content');
				let path = this.dataset.path;
				const [start, end] = [el.selectionStart, el.selectionEnd];
				if(path.endsWith('.md')) {
					path = path.substring(0, path.lastIndexOf('.md'));
					const filename = path.split('/').slice(-1);
					el.setRangeText(`\n[${filename}](${path})`, start, end, 'end');
				}
				else {
					const filename = path.split('/').slice(-1);
					el.setRangeText(`![${filename}](${path})`, start, end, 'end');
				}
				document.getElementById('panel_files').classList.toggle('hidden');
				document.getElementById('panel_editor').classList.toggle('hidden');
				el.focus();
				el.dispatchEvent(new Event('input'));
			">🔗</button>
			<button title="Rename" data-path="<?php echo $base . $filename ?>"
				onclick="toggleRename(this.parentElement.parentElement);">✎</button>
			<button title="Delete" data-path="<?php echo $base . $filename ?>"
				onclick="if(confirm('Are you sure you want to delete this file?')) { deleteFile(this.dataset.path).then(() => {this.parentElement.parentElement.remove(); }); }"
				style="color: red;">❌</button>
		</div>
	</li>
<?php } ?>
<?php function render_directory($filename, $base, $depth, $children)
{ ?>
	<?php $_key = $depth == 0 ? '' : $filename . "/"; ?>
	<li class="directory">
		<details data-path="<?php echo $base . $_key ?>" <?php echo $depth == 0 ? 'open' : '' ?>>
			<summary>
				<span class="name"><?php echo $filename ?></span>
				<input data-path="<?php echo $base . $filename ?>" class="edit-link hidden"
					style="flex-grow: 1; display:inline-block;" value="<?php echo $filename ?>"
					onkeydown="editInputKeydown(event, this.parentElement, this.dataset.path).then((fullTree) => { replaceDir(this.dataset.path, event.target.value, fullTree)});""></input>
				<div class=" actions">
				<button title="New Page" data-path="<?php echo $base . $_key ?>"
					onclick="newFile(this.dataset.path).then((el) => { this.parentElement.parentElement.parentElement.querySelector('ol').appendChild(el); });">+
					📄</button>
				<button title="New Folder" data-path="<?php echo $base . $_key ?>"
					onclick="newDir(this.dataset.path).then((el) => { this.parentElement.parentElement.parentElement.querySelector('ol').appendChild(el); });">+
					📁</button>
				<button title="Upload" data-path="<?php echo $base . $_key ?>"
					onclick="uploadContextPath = this.dataset.path; insertUpload = false; document.getElementById('upload').click();">Upload</button>
				<?php if ($depth != 0): ?>
					<button title="Rename" data-path="<?php echo $base . $filename ?>"
						onclick="toggleRename(this.parentElement.parentElement);">✎</button>
				<?php endif; ?>
				<?php if ($depth > 0): ?>
					<button title="Delete" data-path="<?php echo $base . $filename ?>"
						onclick="if(confirm('Are you sure you want to delete this directory?')) { deleteFile(this.dataset.path).then(() => {this.parentElement.parentElement.parentElement.remove(); }); }"
						style="color: red;">❌</button>
				<?php endif; ?>
				<?php if ($depth == 0): ?>
					<button title="Rebuild" data-path="<?php echo $base . $_key ?>"
						onclick="alert('Requesting to rebuild all pages.'); rebuildAll(this.dataset.path);">Rebuild</button>
					<button title="Export" data-path="<?php echo $base . $_key ?>"
						onclick="alert('A new .zip export will be created in assets/export.zip.'); createExport(this.dataset.path);">Export</button>
				<?php endif; ?>
				</div>
			</summary>
			<?php render_filetree($children, $base . $_key, $depth + 1) ?>
		</details>
	</li>
<?php } ?>
<?php function render_filetree($tree, $base, $depth)
{ ?>
	<?php ksort($tree); ?>
	<ol>
		<?php foreach ($tree as $key => $value): ?>
			<?php if (is_array($value)): ?>
				<?php render_directory($key, $base, $depth, $value); ?>
			<?php else: ?>
				<?php render_file($value, $base); ?>
			<?php endif; ?>
		<?php endforeach; ?>
	</ol>
<?php } ?>
<?php

// HTTP actions.

function handle_put_request()
{
	$src_path = $_SERVER['PATH_INFO'];
	$action = $_GET['action'] ?? null;
	if ($action === 'rename') {
		$newName = $_GET['newName'] ?? null;
		if (preg_match("/[\#\:\/\\\'\"\/\?]+/", $newName)) {
			http_response_code(400);
			echo "Invalid replacement filename: $newName.\n";
			exit(0);
		}
		$directoryUrl = dirname($src_path);
		if (substr($directoryUrl, -1) !== '/') {
			$directoryUrl .= '/';
		}
		$newUrl = $directoryUrl . $newName;
		$oldPath = PROJECT_DIR . $src_path;
		$directoryPath = dirname($oldPath);
		$newPath = $directoryPath . "/" . $newName;
		if (file_exists($oldPath)) {
			// Attempt to move the file or directory
			if (rename($oldPath, $newPath)) {
				// return html render of new file
				$base = dirname($newUrl);
				if (substr($base, -1, 1) !== "/") {
					$base .= "/";
				}
				save_dist($newUrl);
				http_response_code(200);
				header('Content-Type: text/html');
				if (is_dir($newPath)) {
					$dir = dirname($newUrl);
					// we re-render the whole filetree
					// and then let the front-end just grab the part they want to replace
					$fileTree = get_filetree(PROJECT_DIR);
					render_filetree($fileTree, "/", 0);
				} else {
					render_file(basename($newUrl), $base);
				}
				exit(0);
			} else {
				error_log("Failed to move the file.", 4);
			}
		} else {
			echo "Source file does not exist: $src_path.\n";
		}
		http_response_code(500);
		exit(0); //If `exit(1)` is called, the response is always blank!  Therefore, to get the correct error messages to display, use `exit(0)`.
	}
	// if no action query param was supplied, then process the default action 
	else {
		$_dest = fopen(PROJECT_DIR . $src_path, "w");
		if (!$_dest) {
			http_response_code(500);
			header('Content-Type: text/plain');
			echo 'Could not write file. Check file permissions.';
			exit(0); // If you want the response to display, this has to be an `exit(0)`!
		}
		$_src = fopen("php://input", "r");
		$written = stream_copy_to_stream($_src, $_dest);
		fclose($_dest);
		// if it was a modification of header or footer then we need to rebuild the whole site to dist
		if ($src_path == "/page/header.md" || $src_path == "/page/footer.md") {
			save_all_to_dist(PROJECT_DIR);
		}
		// otherwise just save the changed file to dist
		else {
			save_dist($src_path);
		}
		http_response_code(204);
		exit(0);
	}
}

function handle_post_request()
{

	define("RESIZABLE_IMAGE_EXTENSIONS", ["png", "avif", "bmp", "jpg", "jpeg", "webp"]);

	$public_path = null;
	$newFileUrl = $_SERVER['PATH_INFO'];
	$new_basename = basename($_SERVER['PATH_INFO']);
	if (preg_match("/[\#\:\/\\\'\"\/\?]+/", $new_basename)) {
		http_response_code(400);
		echo "Invalid replacement filename: $new_basename.\n";
		exit(0);
	}
	$extension = strtolower(pathinfo($_SERVER['PATH_INFO'], PATHINFO_EXTENSION));
	$destinationDirectory = dirname(PROJECT_DIR . $_SERVER['PATH_INFO']);
	if (!is_dir($destinationDirectory) && !mkdir($destinationDirectory, 0755, true)) {
		http_response_code(500);
		header('Content-Type: text/plain');
		echo 'Could not create the upload directory.';
		exit(0);
	}
	if (getenv('IMG_RESIZE_ENABLE') && in_array($extension, RESIZABLE_IMAGE_EXTENSIONS, true)) {
		$max_width = 800;
		$maxWidth = getenv('IMG_RESIZE_MAX_WIDTH') ?: getenv('IMG__RESIZE_MAX_WIDTH');
		if ($maxWidth !== false && $maxWidth !== '') {
			$max_width = intval($maxWidth);
		}
		$dest_format = getenv('IMG_RESIZE_FORMAT') ?: "source";
		if ($dest_format == "source") {
			$dest_format = $extension;
		}
		$img = imagecreatefromstring(file_get_contents("php://input"));

		if (imagesx($img) > $max_width) {
			$img = imagescale($img, $max_width);
		}

		// replace extension with new format
		$public_path = dirname($_SERVER['PATH_INFO']) . "/" . basename($new_basename, "." . $extension) . "." . $dest_format;

		$_dest = fopen(PROJECT_DIR . $public_path, "x");
		if (!$_dest) {
			http_response_code(400);
			header('Content-Type: text/plain');
			echo 'File already exists in this directory. Delete it to overwrite.';
			exit(0); //If you want the response to carry back to the client, this has to be an `exit(0)`!
		}
		switch ($dest_format) {
			case "png":
				imagepng($img, $_dest, intval(getenv('IMG_RESIZE_PNG_COMPRESSION') ?: -1));
				break;
			case "jpg":
			case "jpeg":
				imagejpeg($img, $_dest, intval(getenv('IMG_RESIZE_JPEG_QUALITY') ?: -1));
				break;
			case "webp":
				imagewebp($img, $_dest, intval(getenv('IMG_RESIZE_WEBP_QUALITY') ?: -1));
				break;
			case "avif":
				imageavif($img, $_dest, intval(getenv('IMG_RESIZE_AVIF_QUALITY') ?: -1), intval(getenv('IMG_RESIZE_AVIF_SPEED') ?: -1));
				break;
			case "bmp":
				imagewbmp($img, $_dest);
				break;
			default:
				http_response_code(400);
				header('Content-Type: text/plain');
				echo "Unknown image format: " . $dest_format;
				exit(0); //If you want the above error to display properly, this has to be `exit(0)`.
		}
		fclose($_dest);
	} else {
		$public_path = dirname($_SERVER['PATH_INFO']) . "/" . $new_basename;
		$_dest = fopen(PROJECT_DIR . $public_path, "x");
		if (!$_dest) {
			http_response_code(400);
			header('Content-Type: text/plain');
			echo 'File already exists in this directory. Delete it to overwrite.';
			exit(0); //If you want the above error to display, this has to be `exit(0)`
		}
		$_src = fopen("php://input", "r");
		stream_copy_to_stream($_src, $_dest);
		fclose($_dest);
		fclose($_src);
	}

	$base = dirname($public_path);
	if (substr($base, -1, 1) !== "/") {
		$base .= "/";
	}
	save_dist($newFileUrl);
	http_response_code(200);
	header('Content-Type: text/html');
	render_file(basename($public_path), $base);
	exit(0);
}

function handle_delete_request()
{
	if ($_SERVER['PATH_INFO'] === "" || $_SERVER['PATH_INFO'] === "." || $_SERVER['PATH_INFO'] === "/") {
		http_response_code(400);
		exit(1);
	}
	$path = $_SERVER['PATH_INFO'];
	if (is_dir(PROJECT_DIR . $path)) {
		delTree(PROJECT_DIR . $path);
		delTree(DIST_DIR . $path);
	} else {
		unlink(PROJECT_DIR . $path);
		if (str_ends_with($path, '.md')) {
			// if it's a .md file, remove the corresponding .html file from DIST_DIR
			$htmlPath = preg_replace('/\.md$/', '.html', $path);
			unlink(DIST_DIR . $htmlPath);
		} else {
			// otherwise, remove the same path from DIST_DIR
			unlink(DIST_DIR . $path);
		}
	}
	http_response_code(204);
	exit(0);
}

function handle_patch_request()
{
	mkdir(PROJECT_DIR . $_SERVER['PATH_INFO']);
	http_response_code(200);
	header('Content-Type: text/html');
	$dir = dirname($_SERVER['PATH_INFO']);
	$dir = rtrim($dir, "/") . "/"; # render_directory expects dir to end with a trailing slash  
	render_directory(basename($_SERVER['PATH_INFO']), $dir, count(explode("/", $dir)), array());
	exit(0);
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'PUT') {
	handle_put_request();
} elseif ($requestMethod === 'POST') {
	handle_post_request();
} elseif ($requestMethod === 'DELETE') {
	handle_delete_request();
} elseif ($requestMethod === 'PATCH') {
	handle_patch_request();
}
?>
<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8">
	<title>Lichen</title>
	<style>
		* {
			box-sizing: border-box;
		}

		body {
			height: 100vh;
			margin: 0;
			padding: 0;
			font-family: sans-serif;
		}

		.container {
			height: 100%;
			display: flex;
			flex-direction: row;
		}

		.panel {
			display: flex;
			flex-direction: column;
			border-right: 1px solid gray;
			min-width: 60ch;
			position: relative;
		}

		.panel .controls {
			border-top: 1px solid gray;
			padding: 0.5rem;
			display: flex;
			gap: 0.5rem;
			flex-direction: row;
		}

		textarea {
			min-width: 100%;
			max-width: 100%;
			resize: horizontal;
			margin: 0;
			padding: 1rem;
			outline: none;
			border: none;
			font-size: 1rem;
			font-family: monospace;
		}

		.syntax-editor {
			position: relative;
			flex-grow: 1;
			min-height: 0;
			overflow: hidden;
			background: white;
		}

		.syntax-editor pre,
		.syntax-editor textarea {
			position: absolute;
			inset: 0;
			width: 100%;
			height: 100%;
			margin: 0;
			padding: 1rem;
			border: 0;
			font-family: monospace;
			font-size: 1rem;
			font-weight: normal;
			font-style: normal;
			line-height: 1.2;
			letter-spacing: normal;
			tab-size: 4;
			white-space: pre-wrap;
			word-wrap: break-word;
			overflow: auto;
		}

		.syntax-editor pre code {
			font: inherit;
			line-height: inherit;
			letter-spacing: inherit;
			tab-size: inherit;
		}
		.syntax-editor code {
			background: none;
		}
		.syntax-editor pre {
			pointer-events: none;
			color: #202124;
		}

		.syntax-editor textarea {
			z-index: 1;
			resize: none;
			color: transparent;
			background: transparent;
			caret-color: #202124;
		}


		.syntax-heading { color: #7c3aed; font-weight: bold; }
		.syntax-strong { color: #2563eb; font-weight: bold; }
		.syntax-code { color: #047857; }
		.syntax-link { color: #0369a1; }
		.syntax-list { color: #b45309; }
		.syntax-quote { color: #6b7280; font-style: italic; }
		.syntax-html-tag { color: #dc2626; }

		iframe {
			flex-grow: 1;
			border: none;
			outline: none;
			height: 100%;
		}

		#save {
			background: #77d763;
			color: white;
			text-shadow: 0 1px 0 #2fa62f;
			border-radius: 3px;
			outline: none;
			border: 1px solid #2fa62f;
		}

		#save:disabled {
			opacity: 0.5;
		}

		#panel_files {
			width: 60ch;
		}

		#help {
			font-size: 0.8rem;
			font-family: monospace;
			border-top: 1px solid gray;
			display: flex;
			flex-direction: row;
			flex-wrap: wrap;
			gap: 1ch 2ch;
			padding: 1ch;
		}

		#files {
			overflow-y: auto;
		}

		#files ol {
			list-style: none;
			border-left: 1px solid lightgray;
			padding-left: 1rem;
			font-family: monospace;
			user-select: none;
		}

		#files ol:first-child {
			border: none;
			padding-right: 1rem;
		}

		#files a,
		#files summary>span {
			flex-grow: 1;
			display: inline-block;
			padding: 0.25rem 0.5rem;
			padding-left: 0;

			overflow: hidden;
			white-space: nowrap;
			text-overflow: ellipsis;
		}

		#files details {
			width: 100%;
		}

		#files li.file:hover,
		#files li.directory summary:hover {
			background: #f0f0f0;
		}

		#files a {
			display: list-item;
			text-decoration: none;
			color: inherit;
		}

		#files li {
			border-bottom: 1px solid white;
		}

		#files li.file,
		#files li.directory summary {
			display: flex;
			flex-direction: row;

		}

		#files li .actions {
			display: flex;
			visibility: hidden;
		}

		#files li.file:hover .actions,
		#files li.directory summary:hover .actions {
			visibility: visible;
		}

		#files li button {
			background: transparent;
			border: none;
			outline: none;
			padding: 0.25rem 0.50rem;
			cursor: pointer;
			border-left: 1px solid white;
		}

		#files summary {
			cursor: pointer;
		}

		#files summary::marker {
			display: none;
		}

		#files details>summary::before {
			content: '📁 ';
			padding: 0.25rem 0.50rem 0.25rem 0;
		}

		#files details[open]>summary::before {
			content: '📂 ';
		}

		.link--editable::before {
			content: '📄 ';
		}

		.link--unknown::before {
			content: '📦 ';
		}

		.panel .overlay {
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: #00000080;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 1rem;
			gap: 0.5rem;
			color: white;
		}

		@keyframes throb {

			0%,
			100% {
				opacity: 0;
			}

			50% {
				opacity: 1;
			}
		}

		.throb {
			animation: throb 1s infinite;
		}

		code {
			background: lightgray;
			padding: 2px;
			border-radius: 2px;
		}

		.hidden {
			display: none !important;
		}
	</style>
	<script>
		'use strict';

		let REQ_PATH = '<?php echo $_SERVER['PATH_INFO']; ?>';

		function debounce(callback, delay) {
			let id = null;
			return (...args) => {
				window.clearTimeout(id);
				id = window.setTimeout(() => {
					callback.apply(null, args);
				}, delay);
			};
		}

		let contentModified = false;
		let scrollX = 0;
		let scrollY = 0;

		function escapeEditorHtml(value) {
			return value
				.replaceAll('&', '&amp;')
				.replaceAll('<', '&lt;')
				.replaceAll('>', '&gt;')
				.replaceAll('"', '&quot;');
		}

		function highlightMarkdownLine(line) {
			const escaped = escapeEditorHtml(line);
			if (/^\s*#{1,6}\s/.test(line)) {
				return `<span class="syntax-heading">${escaped}</span>`;
			}
			if (/^\s*>/.test(line)) {
				return `<span class="syntax-quote">${escaped}</span>`;
			}

			const tokenPattern = /(\*\*[^*]+\*\*|__[^_]+__|`[^`]+`|!?\[[^\]]+\]\([^\)]+\))/g;
			let result = '';
			let lastIndex = 0;
			for (const match of line.matchAll(tokenPattern)) {
				result += escapeEditorHtml(line.slice(lastIndex, match.index));
				const token = escapeEditorHtml(match[0]);
				const className = match[0].startsWith('`')
					? 'syntax-code'
					: match[0].startsWith('[') || match[0].startsWith('![')
						? 'syntax-link'
						: 'syntax-strong';
				result += `<span class="${className}">${token}</span>`;
				lastIndex = match.index + match[0].length;
			}
			result += escapeEditorHtml(line.slice(lastIndex));

			if (/^\s*(?:[-*+]\s|\d+\.\s)/.test(line)) {
				return `<span class="syntax-list">${result}</span>`;
			}
			return result;
		}

		function highlightMarkdown(source) {
			return source.split('\n').map(highlightMarkdownLine).join('\n');
		}

		function highlightHtml(source) {
			const tagPattern = /<!--[\s\S]*?-->|<\/?[A-Za-z][^>]*>/g;
			let result = '';
			let lastIndex = 0;
			for (const match of source.matchAll(tagPattern)) {
				result += escapeEditorHtml(source.slice(lastIndex, match.index));
				result += `<span class="syntax-html-tag">${escapeEditorHtml(match[0])}</span>`;
				lastIndex = match.index + match[0].length;
			}
			return result + escapeEditorHtml(source.slice(lastIndex));
		}

		function updateSyntaxHighlight() {
			const input = document.getElementById('content');
			const code = document.querySelector('#highlight code');
			if (!input || !code) return;

			const isHtml = REQ_PATH.toLowerCase().endsWith('.html');
			code.innerHTML = isHtml
				? highlightHtml(input.value)
				: highlightMarkdown(input.value);
		}

		function currentDocumentImagePath() {
			const path = REQ_PATH.replace(/^\/+|\/+$/g, '');
			const parts = path.split('/');
			const fileName = parts[parts.length - 1] || 'index.md';
			const slug = fileName.replace(/\.[^.]+$/, '');
			return `/page/assets/image/${slug}/`;
		}

		function syncEditorScroll() {
			const input = document.getElementById('content');
			const highlight = document.getElementById('highlight');
			if (!input || !highlight) return;
			highlight.scrollTop = input.scrollTop;
			highlight.scrollLeft = input.scrollLeft;
		}

		function previewPagePath(path) {
			path = path.replace(/^\/page(?=\/|$)/, '');
			if (path === '' || path.endsWith('/')) {
				return `${path}index.html` || '/index.html';
			}
			return path.replace(/\.md$/i, '.html');
		}

		function setPreviewDocument(html, pagePath = REQ_PATH) {
			const preview = document.getElementById('preview');
			const basePath = previewPagePath(pagePath);
			const baseHref = new URL(basePath, window.location.origin).href;
			const baseTag = `<base href="${baseHref}">`;
			const documentWithBase = /<head\b[^>]*>/i.test(html)
				? html.replace(/<head\b[^>]*>/i, `$&${baseTag}`)
				: `${baseTag}${html}`;

			preview.srcdoc = documentWithBase;
		}

		async function applyContent() {
			const input = document.getElementById('content');
			const preview = document.getElementById('preview');
			const save = document.getElementById('save');


			save.disabled = !contentModified;
			if (contentModified) {
				save.innerText = 'Save';
			}
			try {
				scrollY = preview.contentWindow.scrollY;
				scrollX = preview.contentWindow.scrollX;
			} catch (e) {
				// cross-origin content doesn't allow scroll access
				// (user may have clicked a link)
			}

			// CSS is previewed against the latest rendered page instead of rendered itself.
			if (REQ_PATH.toLowerCase().endsWith('.css')) {
				const renderablePreview = localStorage.getItem('renderablePreview');
				if (renderablePreview) {
					const preview = document.getElementById('preview');
					preview.addEventListener('load', () => reloadStylesheet(REQ_PATH), { once: true });
					setPreviewDocument(renderablePreview, localStorage.getItem('renderablePreviewPath') || '/');
				}
				return;
			}

			let contentType = "text/markdown";

			const res = await fetch('/cms/render.php' + REQ_PATH, {
				method: 'POST',
				headers: {
					'Content-Type': contentType,
				},
				body: input.value,
			});
			if (res.status != 200) {
				alert('There was an error rendering the page.');
				const body = await res.text();
				throw new Error(body);
			}

			preview.addEventListener('load', function () {
				preview.contentWindow.scrollTo({
					top: scrollY,
					left: scrollX,
					behavior: 'instant'
				});
			}, {
				once: true
			});
			let _preview = await res.text();
			localStorage.setItem('renderablePreview', _preview);
			localStorage.setItem('renderablePreviewPath', REQ_PATH);
			setPreviewDocument(_preview);
		}

		window.addEventListener('DOMContentLoaded', () => {
			applyContent()
		}, {
			once: true
		});
		window.addEventListener('DOMContentLoaded', function () {
			const onContentInput = debounce(() => {
				if (REQ_PATH.includes(".css")) {
					const preview = document.getElementById('preview');
					const previewDocument = preview.contentDocument;
					if (previewDocument) {
						let liveStyle = previewDocument.getElementById('live-preview-style');
						if (!liveStyle) {
							liveStyle = previewDocument.createElement('style');
							liveStyle.id = 'live-preview-style';
							previewDocument.head.appendChild(liveStyle);
						}
						liveStyle.textContent = document.getElementById('content').value;
					}
					reloadStylesheet(REQ_PATH);
					// enable save button
					document.getElementById('save').disabled = false;
				} else {
					applyContent();
				}
			}, 1000);
			const input = document.getElementById('content');
			input.focus();
			updateSyntaxHighlight();
			input.addEventListener('scroll', syncEditorScroll);
			input.addEventListener('input', onContentInput);
			input.addEventListener('input', function () {
				contentModified = true;
				updateSyntaxHighlight();
				syncEditorScroll();
			});

			window.addEventListener('beforeunload', function (event) {
				// NOTE: it seems like the page only sometimes manages to call this fetch function
				// before it closes, but sometimes it does, and this is just a fallback precaution in any event
				fetch('/cms/build.php', {
					method: 'POST',
				});
				if (!contentModified) return;
				event.preventDefault();
				return 'Unsaved changes will be lost.';
			});

			const preview = document.getElementById('preview');
			preview.addEventListener('load', function (event) {
				// TODO: handle navigation
				// console.log(event, event.target.contentWindow.location);
			});
		}, {
			once: true
		});


		// --- API --- //
		async function saveContent() {
			const input = document.getElementById('content');
			const button = document.getElementById('save');
			let contentType = "text/gemini";
			button.disabled = true;
			button.innerText = 'Saving...';

			if (REQ_PATH.indexOf(".md") !== -1) {
				contentType = "text/markdown";
			}

			const res = await fetch('/cms/edit.php' + REQ_PATH, {
				method: 'PUT',
				headers: {
					'Content-Type': contentType,
				},
				body: input.value,
			});
			if (res.status != 204) {
				button.disabled = false;
				button.innerText = 'Save';
				const body = await res.text();
				alert('There was an error that prevented the file from being saved.');
				throw new Error(body);
				return;
			}

			button.disabled = true;
			button.innerText = 'Saved';
			contentModified = false;
		}

		async function deleteFile(path) {
			const res = await fetch('/cms/edit.php' + path, {
				method: 'DELETE',
			});
			if (res.status != 204) {
				const body = await res.text();
				alert('There was an error deleting the file.');
				throw new Error(body);
			}
		}

		async function rebuildAll(path) {
			const res = await fetch('/cms/build.php', {
				method: 'POST',
			});
			if (res.status != 200) {
				const body = await res.text();
				alert('There was an error rebuilding the build directory.');
				throw new Error(body);
			}
			else {
				alert('Successfully rebuilt the build directory, everything is in sync.');
			}
		}

		async function createExport(path) {
			const res = await fetch('/cms/export.php', {
				method: 'POST',
			});
			const body = await res.text();
			if (res.status != 200) {
				alert('There was an error creating the export.');
				throw new Error(body);
			}
			else {
				console.log("++ result: " + body + "\n");
				alert('Successfully created an export in assets/export.zip. After clicking OK, the page will automatically reload, and then in the files navigator you can click on export.zip to download it.');
				location.reload();
			}
		}

		async function editInputKeydown(event, el, filePath) {
			if (event.key === 'Enter') {
				const inputValue = event.target.value; // Get the value from the input field
				if (/[\#\/\\\:\'\"\%\?]+/.test(inputValue)) {
					alert("Filename includes invalid characters!\nFilename cannot include any of the following: #\\/:'\"?%");
					return;
				}
				return sendRenameRequest(filePath, inputValue);
			}
			else if (event.key === 'Escape') {
				toggleRename(el);
			}
		}

		async function replaceDir(oldPath, newName, fullTree) {
			if (fullTree != null) {
				if (!oldPath.endsWith('/')) {
					oldPath += '/';
				};
				let newUrlPath = oldPath.replace(/(.*\/)[^\/]*\//, `$1${newName}/`);
				let newNode = fullTree.querySelector(`details[data-path='` + newUrlPath + `']`);
				let oldNode = document.querySelector(`details[data-path='` + oldPath + `']`);
				oldNode.replaceWith(newNode);
			}
		}

		async function sendRenameRequest(oldPath, newName) {
			const url = '/cms/edit.php' + oldPath + "?action=rename&newName=" + encodeURIComponent(newName);
			console.log("++ sending: " + url);
			const res = await fetch(url, {
				method: 'PUT'
			});
			if (res.status != 200) {
				const body = await res.text();
				alert('There was an error renaming the file.');
				throw new Error(body);
			}
			else {
				const newSrc = await res.text();
				const newNode = new DOMParser().parseFromString(newSrc, 'text/html').body.firstElementChild;
				return newNode;
			}
		}

		async function toggleRename(el) {
			var editInput = el.querySelector('.edit-link');
			editInput.classList.toggle('hidden');
			el.querySelector('.name').classList.toggle('hidden');
			editInput.focus();
		}

		async function newFile(path) {
			let filename = prompt('New page filename:');
			if (!filename) return;
			if (/[\#\/\\\:\'\"\%\?]+/.test(filename)) {
				alert("Filename includes invalid characters!\nFilename cannot include any of the following: #\\/:'\"?%");
				return;
			}
			const res = await fetch('/cms/edit.php' + path + filename, {
				method: 'POST',
			});
			if (res.status != 200) {
				const body = await res.text();
				alert('There was an error creating the file.');
				throw new Error(body);
			}
			const newSrc = await res.text();
			const newNode = new DOMParser().parseFromString(newSrc, 'text/html').body.firstElementChild;
			window.location = '/cms/edit.php' + path + filename;
			return newNode;
		}

		async function newDir(path) {
			let filename = prompt('New folder name:');
			if (!filename) return;
			if (/[\#\/\\\:\'\"\%\?]+/.test(filename)) {
				alert("Filename includes invalid characters!\nFilename cannot include any of the following: #\\/:'\"?%");
				return;
			}
			const res = await fetch('/cms/edit.php' + path + filename, {
				method: 'PATCH',
			});
			if (res.status != 200) {
				const body = await res.text();
				alert('There was an error creating the folder.');
				throw new Error(body);
			}
			const newSrc = await res.text();
			const newNode = new DOMParser().parseFromString(newSrc, 'text/html').body.firstElementChild;
			return newNode;
		}

		let uploadContextPath;
		let insertUpload = false;
		async function handleUpload(event) {
			const overlay = document.getElementById('upload-overlay');
			overlay.classList.remove('hidden');
			const file = event.target.files[0];
			let fileName = file.name.replaceAll(" ", "_");
			if (/[\#\/\\\:\'\"\%\?]+/.test(fileName)) {
				alert("Filename includes invalid characters!\nFilename cannot include any of the following: #\\/:'\"?%");
				return;
			}
			const isImage = file.type.startsWith('image/') ||
				/\.(avif|bmp|gif|jpe?g|png|svg|webp)$/i.test(fileName);
			const uploadPath = isImage ? currentDocumentImagePath() : uploadContextPath;
			const publicUploadPath = uploadPath.replace(/^\/page(?=\/|$)/, '');
			try {
				const res = await fetch('/cms/edit.php' + uploadPath + fileName, {
					method: 'POST',
					body: file,
				});

				if (res.status != 200) {
					const body = await res.text();
					alert('There was an error uploading the file.');
					throw new Error(body);
				}
				const newSrc = await res.text();
				const newNode = new DOMParser().parseFromString(newSrc, 'text/html').body.firstElementChild;
				const uploadDirectory = document.querySelector(`details[data-path="${uploadPath}"] > ol`);
				if (uploadDirectory) uploadDirectory.appendChild(newNode).scrollIntoView();
				if (insertUpload) {
					const filePath = publicUploadPath + fileName;
					document.getElementById('panel_files').classList.add('hidden');
					document.getElementById('panel_editor').classList.remove('hidden');
					const el = document.getElementById('content');
					const [start, end] = [el.selectionStart, el.selectionEnd];
					el.setRangeText(`![${fileName}](${filePath})`, start, end, 'end');
					el.focus();
					el.dispatchEvent(new Event('input'));
				}
			} catch (e) {

			} finally {
				overlay.classList.add('hidden');
			}
		}

		function reloadStylesheet(filePath) {
			const preview = document.getElementById('preview');
			const previewDocument = preview.contentDocument;
			if (!previewDocument) return;

			const targetPath = new URL(filePath, window.location.origin).pathname;
			const link = [...previewDocument.querySelectorAll('link[rel="stylesheet"]')]
				.find((stylesheet) => new URL(stylesheet.href).pathname === targetPath);
			if (link) {
				const stylesheetUrl = new URL(link.href);
				stylesheetUrl.searchParams.set('v', Date.now());
				const newHref = stylesheetUrl.href;
				link.href = newHref;
				console.log(`Stylesheet reloaded: ${newHref}`);
			} else {
				console.warn(`Stylesheet not found: ${filePath}`);
			}
		}
	</script>
</head>

<body>
	<div class="container">
		<div class="panel" id="panel_editor">
			<div class="syntax-editor">
				<pre id="highlight" aria-hidden="true"><code></code></pre>
				<textarea id="content" autocomplete="off" spellcheck="false"><?php
				if (file_exists(PROJECT_DIR . $_SERVER['PATH_INFO'])) {
					echo file_get_contents(PROJECT_DIR . $_SERVER['PATH_INFO']);
				}
				?></textarea>
			</div>
			<div id="help" class="hidden">

				<?php if (substr_count($_SERVER['PATH_INFO'], '.gmi') > 0): ?>

					<code># heading<br>## subhead<br>### sub-subhead</code>
					<code>* bulleted<br>* list<br>* items</code>
					<code>=> https://abc.xyz external link<br>=> /page.gmi internal link<br>=> /image.jpg image alt</code>
					<code>```<br>preformatted</br>```</code><br>
					<div>
						<code>> blockquote</code>
						<div>&nbsp;</div>
						<a href="https://gemini.circumlunar.space/docs/cheatsheet.gmi" target="_blank">Cheatsheet</a>
					</div>

				<?php else: ?>

					<code># heading<br>## subhead<br>### sub-subhead</code>
					<code>* bulleted<br>* list<br>* items</code>
					<code>[external link](https://abc.xyz)<br>[internal link](/page.md)<br>![image alt](/assets/image.jpg)</code>
					<code>`preformatted`<br>> blockquote</code>
					<code>&lt;span markdown="1"&gt;<br/>**Markdown** inside<br/> *HTML*&lt;/span&gt;</code>
					<a href="/cms/edit.php/page/cheatsheet.md" target="_blank">Markdown cheatsheet</a>

				<?php endif; ?>

			</div>

			<div class="controls">
				<button id="toggle-files"
					onclick="document.getElementById('panel_files').classList.toggle('hidden'); document.getElementById('panel_editor').classList.toggle('hidden');">Files</button>
				<button id="toggle-cheatsheet" data-path="/page/assets/"
					onclick="uploadContextPath = this.dataset.path; insertUpload = true; document.getElementById('upload').click();">Upload</button>
				<button id="toggle-cheatsheet"
					onclick="document.getElementById('help').classList.toggle('hidden');">Help</button>
				<span style="flex-grow: 1;"></span>
				<button id="save" disabled onclick="saveContent();">Save</button>
			</div>
		</div>

		<div class="panel hidden" id="panel_files">
			<nav id="files" style="flex-grow: 1;">
				<?php // top-level dir
				$fileTree = get_filetree(PROJECT_DIR);
				?>
				<?php render_filetree($fileTree, "/", 0) ?>
			</nav>

			<div class="controls">
				<button id="toggle-editor"
					onclick="document.getElementById('panel_files').classList.toggle('hidden'); document.getElementById('panel_editor').classList.toggle('hidden');">Editor</button>
				<span style="flex-grow: 1;"></span>
			</div>

			<div class="overlay hidden" id="upload-overlay">
				<p>Uploading<span class="throb">…</span></p>
			</div>
		</div>

		<iframe id="preview"></iframe>
	</div>
	<input type="file" id="upload" class="hidden" onchange="handleUpload(event);">
</body>

</html>
