<?php
App::import('Core', array('File', 'Folder', 'Sanitize'));

class AssetHelper extends AppHelper {
	var $helpers = array('Html');
	var $View = null;
	var $assets = array();

	//you can change this if you want to store the files in a different location.
	//this is relative to your webroot
	var $paths = array(
		'css' => 'ccss',
		'js'  => 'cjs'
	);

	//set the css compression level
	//options: default, low_compression, high_compression, highest_compression
	//default is no compression
	//I like high_compression because it still leaves the file readable.
	var $cssCompression = 'high_compression';

	//there is a *minimal* perfomance hit associated with looking up the filemtimes
	//if you clean out your cached dir (as set below) on builds then you don't need this.
	var $checkTs = false;

	//Class for localizing JS files if JS I18N plugin is installed
	//http://github.com/mcurry/js/tree/master
	var $Lang = false;

	var $viewScriptCount = 0;

	function __construct() {
		$this->View =& ClassRegistry::getObject('view');

		if (Configure::read('Asset.jsPath')) {
			$this->paths['js'] = Configure::read('Asset.jsPath');
		}

		if (Configure::read('Asset.cssPath')) {
			$this->paths['css'] = Configure::read('Asset.cssPath');
		}
		if (Configure::read('Asset.cssCompression')) {
			$this->cssCompression = Configure::read('Asset.cssCompression');
		}

		if (Configure::read('Asset.checkTs')) {
			$this->checkTs = Configure::read('Asset.checkTs');
		}

		if (App::import('Model', 'Js.JsLang')) {
			$this->Lang = ClassRegistry::init('Js.JsLang');
			$this->Lang->init();
		}
	}

	function afterRender() {
		if ($this->View) {
			$this->viewScriptCount = count($this->View->__scripts);
		}
	}

	function scripts_for_layout($types = array('css', 'js', 'codeblock')) {
		$types = (array)$types;

		if ($this->viewScriptCount) {
			$this->View->__scripts = array_merge(
				array_slice($this->View->__scripts, $this->viewScriptCount),
				array_slice($this->View->__scripts, 0, $this->viewScriptCount)
			);
			$this->viewScriptCount = false;
		}

		if (Configure::read('debug')) {
			return join("\n\t", $this->View->__scripts);
		}

		$this->_parse();

		$Helper =& $this->Html;
		if (array_key_exists('cdn', $this->View->loaded)) {
			$Helper =& $this->View->loaded['cdn'];
		}

		$scripts_for_layout = array();
		foreach ($this->assets as $group) {
			if (!in_array($group['type'], $types)) {
				continue;
			}
			extract($group, EXTR_OVERWRITE);
			if ($type == 'codeblock') {
				$content = Set::extract('/content', $assets);
				$scripts_for_layout = array_merge($scripts_for_layout, $content);
				continue;
			}
			$processed = $this->_process($type, $assets);
			if ($type == 'css') {
				$processed = str_replace('.css', '', $processed);
				$scripts_for_layout[] = $Helper->css('/' . $this->paths['css'] . '/' . $processed);
			} elseif ($type == 'js') {
				$scripts_for_layout[] = $Helper->script('/' . $this->paths['js'] . '/' . $processed);
			}
		}

		return implode("\n\t", $scripts_for_layout) . "\n\t";
	}

	function _parse() {
		$assets = array();
		$currentType = null;
		foreach ($this->View->__scripts as $i => $script) {
			if (preg_match('/(src|href)="(\/.*\.(js|css))"/', $script, $match)) {
				$type = $match[3];
				$asset = $this->_asset($type, $match[2]);

				if ($asset === false) {
					continue;
				}
			} else {
				$type = 'codeblock';
				$asset = array(
					'content' => $script
				);
			}
			if ($type !== $currentType && count($assets) > 0) {
				$this->assets[] = array(
					'type'   => $currentType,
					'assets' => $assets
				);
				$assets = array();
			}
			$currentType = $type;
			$assets[] = $asset;
		}
		if (!empty($assets)) {
			$this->assets[] = array(
				'type'   => $currentType,
				'assets' => $assets
			);
		}
		$this->View->__scripts = array();

		return true;
	}

	function _asset($type, $url) {
		$file = $this->_file($url, $type);

		if ($file === false) {
			return false;
		}

		return array(
			'url'     => $url,
			'file'    => $file
		);
	}

	function _file($url) {
		$file = trim(str_replace('/', DS, $url), DS);
		$wwwRoot = Configure::read('App.www_root');
		if (file_exists($wwwRoot . $file)) {
			return $wwwRoot . $file;
		}

		$parts = explode(DS, $file, 3);
		if ($parts[0] == 'theme') {
			$theme = $parts[1];
			$file = $parts[2];
			$viewPaths = App::path('views');

			foreach ($viewPaths as $viewPath) {
				$path = $viewPath . 'themed' . DS . $theme . DS  . 'webroot' . DS  . $file;

				if (file_exists($path)) {
					return $path;
				}
			}
			return false;
		}

		$plugin = $parts[0];
		$file = implode(DS, array_slice($parts, 1));
		$pluginPaths = App::path('plugins');
		foreach ($pluginPaths as $pluginPath) {
			$path = $pluginPath . $plugin . DS . 'webroot' . DS . $file;

			if (file_exists($path)) {
				return $path;
			}
		}

		if(strpos($url, '/lang') && $this->Lang) {
			$script = substr($url, 3);
			$script = $this->Lang->parseFile($this->Lang->normalize($script));
			if (is_file($this->Lang->paths['source'] . $script) && file_exists($this->Lang->paths['source'] . $script)) {
				return $this->Lang->paths['source'] . $script;
			}
		}

		return false;
	}

	function _compress($type, $file, $asset) {
		if ($type == 'css') {
			return $this->_compressCss($file, $asset);
		} elseif ($type == 'js') {
			return $this->_compressJs($file, $asset);
		}
		return file_get_contents($file);
	}

	function _compressCss($file, $asset) {
		static $tidy = false;
		if (!$tidy) {
			App::import('Vendor', 'Asset.csstidy', array('file' => 'class.csstidy.php'));
			$tidy = new csstidy();

			$tidy->set_cfg('preserve_css', false);
			$tidy->set_cfg('ie_fix_friendly', true);
			$tidy->set_cfg('optimise_shorthands', 0); //Maintain the order of ie hacks (properties)
			$tidy->set_cfg('discard_invalid_properties', false);

			$tidy->load_template($this->cssCompression);
		}
		$path = trim(str_replace(basename($asset['url']), '', $asset['url']), '/');

		$content = trim(file_get_contents($file));
		$content = preg_replace_callback('/url\(([^\)]+)\)/', array(&$this, '_cssResources'), $content);
		$content = str_replace('{path}', $path, $content);
		$tidy->parse($content);
		$content = $tidy->print->plain();

		return $content;
	}

	function _cssResources($matches) {
		var_dump($matches);
		if (array_key_exists('cdn', $this->View->loaded)) {
			$urlBase = $this->View->loaded['cdn']->url('/');
		} else {
			$urlBase = Router::url('/', true);
		}
		$url = $urlBase . '{path}' . '/' . trim($matches[1], '"\'');
		$replacement = 'url(' . $url . ')';
		return $replacement;
 	}

	function _compressJs($file, $asset) {
		if($this->Lang && strpos($file, $this->Lang->paths['source']) !== false) {
			$script = substr($asset['url'], 3);
			$content = $this->Lang->i18n($script);
		} else {
			$content = trim(file_get_contents($file));
		}

		if (!PHP5) {
			return $content;
		}
		App::import('Vendor', 'Asset.jsmin/jsmin');
		return trim(JSMin::minify($content));
	}

	function _process($type, $assets) {
		$folder = new Folder(Configure::read('App.www_root') . $this->paths[$type], true);

		$scripts = Set::extract('/file', $assets);
		$fileName = $this->_generateFileName($scripts) . '.' . $type;
		$path = Configure::read('App.www_root') . $this->paths[$type] . DS . $fileName;

		if (file_exists($path)) {
			if ($this->checkTs) {
				$lastModified = filemtime($path);
				foreach ($assets as $asset) {
					if (filemtime($asset['file']) > $lastModified) {
						break;
					}
				}
				if (empty($asset)) {
					return $fileName;
				}
			} else {
				return $fileName;
			}
		}

		$buffer = '';
		foreach ($assets as $asset) {
			$file = $asset['file'];
			$size = filesize($file);
			$compressed = $this->_compress($type, $file, $asset);

			$delta = 0;
			if ($size > 0) {
				$delta = (strlen($compressed) / $size) * 100;
			}
			$buffer .= sprintf("/* %s (%d%%) */\n", $asset['url'], $delta);
			$buffer .= $compressed . "\n\n";
		}

		$file = new File($path);
		$file->write(trim($buffer));
		$file->close();

		return $fileName;
	}

	function _generateFileName($names) {
		foreach ($names as &$name) {
			$name = md5(str_replace(array(APP, DS), array('', '/'), $name));
		}
		return md5(implode('', $names));
	}
}
?>