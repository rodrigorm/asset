<?php
App::import('Helper', array('Asset.Asset', 'Html'));
App::import('Core', array('Folder', 'View'));

App::import('Vendor', 'Asset.jsmin/jsmin');
App::import('Vendor', 'Asset.csstidy', array('file' => 'class.csstidy.php'));

class AssetTestCase extends CakeTestCase {
	function startCase() {
		$this->root = ROOT . DS . APP_DIR . DS . 'plugins' . DS . 'asset' . DS . 'tests' . DS . 'test_app' . DS;
		$this->webroot =  $this->root . 'webroot' . DS;
		$this->jsCache = $this->webroot . 'cjs' . DS;
		$this->cssCache = $this->webroot . 'ccss' . DS;

		Configure::write('App.www_root', $this->webroot);
		App::build(array(
			'views' => array(
				$this->root . 'views' . DS
			),
			'plugins' => array(
				$this->root . 'plugins' . DS
			)
		), true);

    	$this->Folder = new Folder();
	}

	function endCase() {
	    $this->Folder->delete($this->jsCache);
	    $this->Folder->delete($this->cssCache);
	}

	function startTest() {
		$controller = null;
	    new View($controller);

		$this->View =& ClassRegistry::getObject('view');
		$this->Asset =& new AssetHelper();
		$this->Asset->Html =& new HtmlHelper();
	}

	function testInstances() {
		$this->assertIsA($this->Asset, 'AssetHelper');
		$this->assertIsA($this->Asset->View, 'View');
	}

	function testAsset() {
		$result = $this->Asset->_asset('css', '/css/style1.css');

		$expected = array(
			'url'     => '/css/style1.css',
			'file'    => $this->webroot . 'css/style1.css'
		);
		$this->assertEqual($result, $expected);
	}

	function testFile() {
		$files = array(
			'/css/asset1.css'                         => $this->webroot . 'css' . DS . 'asset1.css',
			'/css/invalid.css'                        => '',
			'/theme/default/css/asset1.css'           => $this->root . 'views' . DS . 'themed' . DS . 'default' . DS . 'webroot' . DS . 'css' . DS . 'asset1.css',
			'/plugin_name/css/asset1.css'             => $this->root . 'plugins'. DS . 'plugin_name' . DS . 'webroot' . DS . 'css' . DS . 'asset1.css',
			'/js/open_source_with_js_and_css/style.css' => $this->webroot . 'js' . DS . 'open_source_with_js_and_css' . DS . 'style.css',
			'/js/open_source_with_js_and_css/lib.js'  => $this->webroot . 'js' . DS . 'open_source_with_js_and_css' . DS . 'lib.js'
		);

		foreach ($files as $url => $file) {
			$result = $this->Asset->_file($url);
			$this->assertEqual($result, $file);
		}
	}

	function testAfterRender() {
		$this->View->__scripts = array('script1', 'script2', 'script3');
		$this->Asset->afterRender();
		$this->assertEqual(3, $this->Asset->viewScriptCount);
	}

	function testGenerateFileName() {
		$files = array('script1', 'script2', 'script3');
		$this->Asset->md5FileName = false;
		$name = $this->Asset->_generateFileName($files);
		$this->assertEqual('d25c0791bfed224d0b12695dd05ae58b', $name);
	}

	function testGenerateFileNameMd5() {
		$this->Asset->md5FileName = true;
		$files = array('script1', 'script2', 'script3');
		$name = $this->Asset->_generateFileName($files);
		$this->assertEqual('d25c0791bfed224d0b12695dd05ae58b', $name);
	}

	function testProcessJsNew() {
		$assets = array(
			array(
				'url' => '/js/script1.js',
				'file' => $this->root . 'webroot' . DS . 'js' . DS . 'script1.js'
			),
			array(
				'url' => '/js/script2.js',
				'file' => $this->root . 'webroot' . DS . 'js' . DS . 'script2.js'
			),
			array(
				'url' => '/plugin_name/js/script3.js',
				'file' => $this->root . 'plugins' . DS . 'plugin_name' . DS . 'webroot' . DS . 'js' . DS . 'script3.js'
			)
		);

		$this->Asset->md5FileName = false;
		$fileName = $this->Asset->_process('js', $assets);

		$this->assertTrue(is_dir($this->jsCache), '%s: Failed to create folder ' . $this->jsCache);

		$expected = <<<END
/* /js/script1.js (91%) */
var str="I'm a string";alert(str);

/* /js/script2.js (73%) */
var sum=0;for(i=0;i<100;i++){sum+=i;}
alert(i);

/* /plugin_name/js/script3.js (86%) */
\$(function(){\$("#nav").show();});
END;
		$result = file_get_contents($this->jsCache . $fileName);

		$this->assertEqual($expected, $result);
	}

	function testProcessJsExistingNoChanges() {
		new Folder($this->jsCache);
		$file = new File($this->jsCache . 'script1js_script2js_script3js.js');
		$content = <<<END
/* /js/script1.js (91%) */
var str="I'm a string";alert(str);

/* /js/script2.js (73%) */
var sum=0;for(i=0;i<100;i++){sum+=i;}
alert(i);

/* /plugin_name/js/script3.js (86%) */
\$(function(){\$("#nav").show();});
END;
		$file->write($content);
		$expected = strtotime('-10 seconds');
		touch($file->path, $expected);

		$assets = array(
			array(
				'url' => '/js/script1.js',
				'file' => $this->root . 'webroot' . DS . 'js' . DS . 'script1.js'
			),
			array(
				'url' => '/js/script2.js',
				'file' => $this->root . 'webroot' . DS . 'js' . DS . 'script2.js'
			),
			array(
				'url' => '/plugin_name/js/script3.js',
				'file' => $this->root . 'plugins' . DS . 'plugin_name' . DS . 'webroot' . DS . 'js' . DS . 'script3.js'
			)
		);

		$this->Asset->md5FileName = false;
		$this->Asset->checkTs = true;
		$this->Asset->_process('js', $assets);
		$result = $file->lastChange();
		$this->assertEqual($expected, $result);
		$file->delete();
	}

	function testProcessJsExistingWithChanges() {
		new Folder($this->jsCache);
		$processed = '65a3c1916a3d63d20b7eebc418d6ad03.js';
		$file = new File($this->jsCache . $processed);
		$expected = <<<END
/* /js/script1.js (91%) */
var str="I'm a string";alert(str);

/* /js/script2.js (73%) */
var sum=0;for(i=0;i<100;i++){sum+=i;}
alert(i);

/* /plugin_name/js/script3.js (86%) */
\$(function(){\$("#nav").hide();});
END;
		$file->write($expected);
		$touched = touch($file->path, strtotime('-10 seconds'));
	    $this->assertTrue($touched, '%s: Touch failed. Check permissions on '. $file->path);

		$assets = array(
			array(
				'url' => '/js/script1.js',
				'file' => $this->root . 'webroot' . DS . 'js' . DS . 'script1.js'
			),
			array(
				'url' => '/js/script2.js',
				'file' => $this->root . 'webroot' . DS . 'js' . DS . 'script2.js'
			),
			array(
				'url' => '/plugin_name/js/script3.js',
				'file' => $this->root . 'plugins' . DS . 'plugin_name' . DS . 'webroot' . DS . 'js' . DS . 'script3.js'
			)
		);
		$touched = touch($assets[0]['file']);
	    $this->assertTrue($touched, '%s: Touch failed. Check permissions on '. $assets[0]['file']);

		$this->Asset->md5FileName = false;
		$this->Asset->checkTs = true;
		$this->Asset->_process('js', $assets);
		$result = file_get_contents($file->path);

		$this->assertNotEqual($expected, $result);
	}

	function testProcessCssNew() {
		$assets = array(
			array(
				'url' => '/css/style1.css',
				'file' => $this->root . 'webroot' . DS . 'css' . DS . 'style1.css'
			),
			array(
				'url' => '/css/style2.css',
				'file' => $this->root . 'webroot' . DS . 'css' . DS . 'style2.css'
			),
			array(
				'url' => '/plugin_name/css/style3.css',
				'file' => $this->root . 'plugins' . DS . 'plugin_name' . DS . 'webroot' . DS . 'css' . DS . 'style3.css'
			)
		);

		$fileName = $this->Asset->_process('css', $assets);

		$this->assertTrue(is_dir($this->cssCache), '%s: Failed to create folder '.$this->cssCache);

		$expected = <<<END
/* /css/style1.css (78%) */
*{margin:0;padding:0;}

/* /css/style2.css (89%) */
body{background:#003d4c;color:#fff;font-family:'lucida grande',verdana,helvetica,arial,sans-serif;font-size:90%;margin:0;}

/* /plugin_name/css/style3.css (72%) */
h1,h2,h3,h4{font-weight:400;}
END;
		$result = file_get_contents($this->cssCache . $fileName);

		$this->assertEqual($expected, $result);
	}

	function testParse() {
		$this->View->__scripts = array(
			'<link rel="stylesheet" type="text/css" href="/css/style1.css" />',
			'<link rel="stylesheet" type="text/css" href="/css/style2.css" />',
			'<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>',
			'<script type="text/javascript" src="/js/script1.js"></script>',
			'<script type="text/javascript" src="/js/script2.js"></script>',
			'<script type="text/javascript" src="/plugin_name/js/script3.js"></script>'
		);


		$this->Asset->_parse();
		$this->assertEqual($this->Asset->assets, array(
			array(
				'type' => 'css',
				'assets' => array(
					array(
						'url' => '/css/style1.css',
						'file' => $this->webroot . 'css' . DS . 'style1.css'
					),
					array(
						'url' => '/css/style2.css',
						'file' => $this->webroot . 'css' . DS . 'style2.css'
					)
				)
			),
			array(
				'type' => 'codeblock',
				'assets' => array(
					array(
						'content' => '<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>'
					)
				)
			),
			array(
				'type' => 'js',
				'assets' => array(
					array(
						'url' => '/js/script1.js',
						'file' => $this->webroot . 'js' . DS . 'script1.js'
					),
					array(
						'url' => '/js/script2.js',
						'file' => $this->webroot . 'js' . DS . 'script2.js'
					),
					array(
						'url' => '/plugin_name/js/script3.js',
						'file' => $this->root . 'plugins' . DS . 'plugin_name' . DS . 'webroot' . DS . 'js' . DS . 'script3.js'
					)
				)
			)
		));
	}

	function testScriptsForLayout() {
		$this->View->__scripts = array(
			'<link rel="stylesheet" type="text/css" href="/css/style1.css" />',
			'<link rel="stylesheet" type="text/css" href="/css/style2.css" />',
			'<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>',
			'<script type="text/javascript" src="/js/script1.js"></script>',
			'<script type="text/javascript" src="/js/script2.js"></script>',
			'<script type="text/javascript" src="/plugin_name/js/script3.js"></script>'
		);

		$result = $this->Asset->scripts_for_layout();
		$expected = implode("\n\t", array(
			'<link rel="stylesheet" type="text/css" href="/ccss/c347bd2c525b18cbbe2290fc69183450.css" />',
			'<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>',
			'<script type="text/javascript" src="/cjs/65a3c1916a3d63d20b7eebc418d6ad03.js"></script>'
		)) . "\n\t";

		$this->assertEqual($expected, $result);
	}

	function testScriptsForLayoutCssInJs() {
		$this->View->__scripts = array(
			'<link rel="stylesheet" type="text/css" href="/js/open_source_with_js_and_css/style.css" />',
			'<script type="text/javascript" src="/js/open_source_with_js_and_css/lib.js"></script>'
		);

		$result = $this->Asset->scripts_for_layout();
		$expected = implode("\n\t", array(
			'<link rel="stylesheet" type="text/css" href="/ccss/ae85cf9c04fe479e08e9de4de233990f.css" />',
			'<script type="text/javascript" src="/cjs/62eb0254d874005a0f2322630f87449e.js"></script>'
		)) . "\n\t";

		$this->assertEqual($expected, $result);
	}

	function testScriptsForLayoutJs() {
		$this->View->__scripts = array(
			'<link rel="stylesheet" type="text/css" href="/css/style1.css" />',
			'<link rel="stylesheet" type="text/css" href="/css/style2.css" />',
			'<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>',
			'<script type="text/javascript" src="/js/script1.js"></script>',
			'<script type="text/javascript" src="/js/script2.js"></script>',
			'<script type="text/javascript" src="/js/sublevel/sub.js"></script>',
			'<script type="text/javascript" src="/plugin_name/js/script3.js"></script>'
		);

		$result = $this->Asset->scripts_for_layout(array('js'));
		$expected = '<script type="text/javascript" src="/cjs/91aeb12f88e963bd155da029a993ea24.js"></script>' . "\n\t";

		$this->assertEqual($expected, $result);
	}

	function testScriptsForLayoutJsString() {
		$this->View->__scripts = array (
			'<link rel="stylesheet" type="text/css" href="/css/style1.css" />',
			'<link rel="stylesheet" type="text/css" href="/css/style2.css" />',
			'<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>',
			'<script type="text/javascript" src="/js/script1.js"></script>',
			'<script type="text/javascript" src="/js/script2.js"></script>',
			'<script type="text/javascript" src="/plugin_name/js/script3.js"></script>'
		);

		$this->Asset->md5FileName = false;
		$result = $this->Asset->scripts_for_layout('js');
		$expected = '<script type="text/javascript" src="/cjs/65a3c1916a3d63d20b7eebc418d6ad03.js"></script>' . "\n\t";

		$this->assertEqual($expected, $result);
	}

	function testScriptsForLayoutCss() {
		$this->View->__scripts = array(
			'<link rel="stylesheet" type="text/css" href="/css/style1.css" />',
			'<link rel="stylesheet" type="text/css" href="/css/style2.css" />',
			'<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>',
			'<script type="text/javascript" src="/js/script1.js"></script>',
			'<script type="text/javascript" src="/js/script2.js"></script>',
			'<script type="text/javascript" src="/asset/js/script3.js"></script>'
			);

		$result = $this->Asset->scripts_for_layout(array('css'));
		$expected = '<link rel="stylesheet" type="text/css" href="/ccss/c347bd2c525b18cbbe2290fc69183450.css" />' . "\n\t";

		$this->assertEqual($expected, $result);
	}

	function testScriptsForLayoutCssString() {
		$this->View->__scripts = array(
			'<link rel="stylesheet" type="text/css" href="/css/style1.css" />',
			'<link rel="stylesheet" type="text/css" href="/css/style2.css" />',
			'<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>',
			'<script type="text/javascript" src="/js/script1.js"></script>',
			'<script type="text/javascript" src="/js/script2.js"></script>',
			'<script type="text/javascript" src="/plugin_name/js/script3.js"></script>'
		);

		$result = $this->Asset->scripts_for_layout('css');
		$expected = '<link rel="stylesheet" type="text/css" href="/ccss/c347bd2c525b18cbbe2290fc69183450.css" />' . "\n\t";

		$this->assertEqual($expected, $result);
	}

	function testScriptsForLayoutSplit() {
		$this->View->__scripts = array(
			'<link rel="stylesheet" type="text/css" href="/css/style1.css" />',
			'<link rel="stylesheet" type="text/css" href="/css/style2.css" />',
			'<script type="text/javascript" src="/js/script1.js"></script>',
			'<script type="text/javascript" src="/js/script2.js"></script>',
			'<script type="text/javascript" src="/plugin_name/js/script3.js"></script>'
			);

		$result = $this->Asset->scripts_for_layout('css');
		$expected = '<link rel="stylesheet" type="text/css" href="/ccss/c347bd2c525b18cbbe2290fc69183450.css" />' . "\n\t";
		$this->assertEqual($expected, $result);

		$result = $this->Asset->scripts_for_layout('js');
		$expected = '<script type="text/javascript" src="/cjs/65a3c1916a3d63d20b7eebc418d6ad03.js"></script>' . "\n\t";
		$this->assertEqual($expected, $result);
	}

	function testWithCodeBlock() {
		$this->View->__scripts = array(
			'<script type="text/javascript" src="/js/script1.js"></script>',
			'<script type="text/javascript">//<![CDATA[alert("test");//]]></script>',
			'<script type="text/javascript" src="/js/script2.js"></script>'
		);
		$result = $this->Asset->scripts_for_layout();
		$expected = implode("\n\t", array(
			'<script type="text/javascript" src="/cjs/18c2bbe9cd88dc30673c25ad59466b42.js"></script>',
			'<script type="text/javascript">//<![CDATA[alert("test");//]]></script>',
			'<script type="text/javascript" src="/cjs/1cabd3b054d075609223060af5719907.js"></script>'
		)) . "\n\t";
		$this->assertEqual($expected, $result);
	}

	function testWithScriptsInLayout() {
		$this->View->__scripts = array(
			'<script type="text/javascript" src="/js/script1.js"></script>',
			'<script type="text/javascript" src="/js/layout.js"></script>'
		);
		$this->Asset->viewScriptCount = 1;
		$result = $this->Asset->scripts_for_layout();
		$expected = '<script type="text/javascript" src="/cjs/050a92f43d2d550e5c3fcbfd901a8b0c.js"></script>' . "\n\t";
		$this->assertEqual($expected, $result);

		$result = file_get_contents($this->jsCache . '050a92f43d2d550e5c3fcbfd901a8b0c.js');
		$expected = <<<END
/* /js/layout.js (0%) */


/* /js/script1.js (91%) */
var str="I'm a string";alert(str);
END;
		$this->assertEqual($expected, $result);
	}

/**
 * Test the Star Fix (formerly known as star_hack)
 * Star fix allows use of css properties like 
 * *position, *width which will restrict its being applied only to IE7. 
 *
 * @return void
 **/
	function testStarFix() {
		App::import('Vendor', 'Asset.csstidy', array('file' => 'class.csstidy.php'));
		$css = new csstidy();
		$css->set_cfg('preserve_css', false);
		$css->set_cfg('star_hack', true);
		$css->set_cfg('optimise_shorthands', 0);
		$css->set_cfg('discard_invalid_properties', false);

		$testCss = file_get_contents($this->webroot . 'css' . DS . 'star_fix.css');
		$css->parse($testCss);
		$result = $css->print->plain();

		$expected = <<<END
#star {
position:static;
*position:absolute;
_position:relative;
}
END;

		$this->assertEqual($expected, $result);
	}

/**
 * Test the Star Fix (formerly known as star_hack)
 * Star fix allows use of css properties like 
 * *position, *width which will restrict its being applied only to IE7. 
 *
 * @return void
 **/
	function testNoStarFix() {
		App::import('Vendor', 'Asset.csstidy', array('file' => 'class.csstidy.php'));
		$css = new csstidy();
		$css->set_cfg('preserve_css', false);
		$css->set_cfg('star_hack', false);
		$css->set_cfg('optimise_shorthands', 0);
		$css->set_cfg('discard_invalid_properties', false);

		$testCss = file_get_contents($this->webroot . 'css' . DS . 'star_fix.css');

		$css->parse($testCss);
		$result = $css->print->plain();

		$expected = <<<END
#star {
position:absolute;
_position:relative;
}
END;

		$this->assertEqual($expected, $result);
	}
}
?>