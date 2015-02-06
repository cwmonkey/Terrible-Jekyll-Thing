<?php

if ( file_exists('config.local.php') ) include('config.local.php');
if ( !isset($dir) ) $dir = '../cwmonkey.github.io';
if ( !isset($npmdir) ) $npmdir = 'C:\Users\Administrator\AppData\Roaming\npm';

$min = ( isset($_GET['min']) ) ? true : false;

$r = @$_SERVER['REDIRECT_URL'];

if ( is_dir($dir . $r) ) {
	$file = $dir . $r . '/index.html';
} elseif ( is_file($dir . $r) ) {
	$file = $dir . $r;
}

if ( preg_match('/(jpg)|(png)|(ico)/', $file) ) {
	if ( preg_match('/\.ico$/', $file) ) header('Content-type: image/x-icon');
	if ( preg_match('/\.png$/', $file) ) header('Content-type: image/png');
	if ( preg_match('/\.jpg$/', $file) ) header('Content-type: image/jpeg');
	readfile($file);
	exit;
}

if ( preg_match('/\.css$/', $file) ) header('Content-type: text/css');
if ( preg_match('/\.js$/', $file) ) header('Content-type: text/javascript');

$vars = array(
	'site.domain' => $_SERVER['SERVER_NAME'],
);

$Directory = new RecursiveDirectoryIterator($dir);
$Iterator = new RecursiveIteratorIterator($Directory);
$Regex = new RegexIterator($Iterator, '/^.+\.js$/i', RecursiveRegexIterator::GET_MATCH);

$js_files = array();
foreach ( $Regex as $k => $v ) {
	if ( !isset($js_files[basename($k)]) ) {
		$js_files[basename($k)] = $k;
	}
}

jinclude($dir, $file, $vars, $js_files);

function jinclude($dir, $file, $vars, $files) {
	global $npmdir;
	global $min;

	if ( ($ss = strstr($file, '{{site.stylesheets}}')) || ($js = strstr($file, '{{site.javascripts}}')) ) {
		if ( $ss ) {
			$min = '/css/stylesheets-minify.css';
		} else {
			$min = '/js/javascripts-minify.js';
		}

		$file = str_replace('{{site.stylesheets}}', 'stylesheets.html', $file);
		$file = str_replace('{{site.javascripts}}', 'javascripts.html', $file);
		$text = file_get_contents($file);
		preg_match_all('/((href)|(src))="([^"]+)/', $text, $matches);
		$output = '';

		$youngest = 0;
		foreach ( $matches[4] as $sheet ) {
			$output .= file_get_contents($dir . $sheet);
			$old = filemtime($dir . $sheet);
			$youngest = ( $old > $youngest ) ? $old : $youngest;
		}

		if ( $ss ) {
			$min_src = realpath($dir) . $min;
			if ( $youngest > filemtime($min_src) ) {
				/*$output = Minify_CSS_Compressor::process($output);
				$fp = fopen($dir . $min, 'w+');
				//$output = $this->GetCompressedJs($output);
				fwrite($fp, $output);
				fclose($fp);*/
				$src = dirname(__FILE__) . '/temp.css';
				file_put_contents($src, $output);
				$cleancss = $npmdir . '/cleancss -o "' . $min_src . '" "' . $src . '"';
				exec($cleancss);
			}
		} else {
			$min_src = realpath($dir) . $min;
			if ( $youngest > filemtime($min_src) ) {
				//$output = JSMin::minify($output);
				$src = dirname(__FILE__) . '/temp.js';
				file_put_contents($src, $output);
				$uglifyjs = $npmdir . '/uglifyjs "' . $src . '" -o "' . $min_src . '" -c -m --comments "/^!/"';
				//var_dump( exec($uglifyjs) );
				//$output = JSMin::minify($output);
			}
		}
	} elseif ( !$min && strstr($file, '.min.js') ) {
		//$min = $file;
		$min_src = realpath(dirname($file)) . '/' . basename($file);
		$file = basename(str_replace('.min.js', '.js', $file));
		$file = $files[$file];
		$output = file_get_contents($file);
		$output = str_replace('{{protocol}}', '', $output);
		$src = dirname(__FILE__) . '/temp.js';
		file_put_contents($src, $output);
		//$min_src = str_replace('.js', '.min.js', realpath($file));
		$uglifyjs = $npmdir . '/uglifyjs "' . $src . '" -o "' . $min_src . '" -c -m --comments "/^!/"';
		exec($uglifyjs);
		//$output = JSMin::minify($output);
		//$fp = fopen($min, 'w+');
		//fwrite($fp, $output);
		//fclose($fp);
		$text = file_get_contents($file);
	} else {
		$text = file_get_contents($file);
	}

	$layout = null;
	if ( preg_match_all('/^---[\s\S]+layout: ([a-zA-Z\-_]+)/', $text, $matches) ) {
		$layout = $matches[1][0];
	}

	if ( preg_match_all('/^---[\s\S]+title: ([a-zA-Z0-9\-_\ \.]+)/', $text, $matches) ) {
		$vars['page.title'] = $matches[1][0];
	}

	$text = preg_replace('/^---([\s\S]+)---/', '', $text);
	$text = str_replace('{% include ', '<? jinclude($dir, $dir . "/_includes/', $text);
	$text = str_replace(' %}', '", $vars, $files) ?>', $text);

	foreach ( $vars as $key => $val ) {
		$text = str_replace('{{ ' . $key . ' }}', $val, $text);
	}

	$text = str_replace('{{protocol}}', 'http:', $text);

	if ( $layout ) {
		ob_start();
		eval('?>' . $text);
		$text = ob_get_clean();
		$vars['content'] = $text;
		jinclude($dir, $dir . '/_layouts/' . $layout . '.html', $vars, $files);
	} else {
		eval('?>' . $text);
	}
}

function cssinclude($dir, $file) {

}