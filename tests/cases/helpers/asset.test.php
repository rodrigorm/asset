<?php
App::import('Helper', 'Asset.Asset');
App::import('Core', array('Folder', 'View'));

class AssetTestCase extends CakeTestCase {
	function startCase() {
	    $this->root = ROOT . DS . APP_DIR . DS . 'plugins' . DS . 'asset' . DS . 'tests' . DS . 'test_app' . DS;
	    $this->webroot =  $this->root . 'webroot' . DS;

		Configure::write('App.www_root', $this->webroot);
		App::build(array(
			'views' => array(
				$this->root . 'views' . DS
			),
			'plugins' => array(
				$this->root . 'plugins' . DS
			)
		), true);
	}

	function startTest() {
		$controller = null;
	    new View($controller);

		$this->View =& ClassRegistry::getObject('view');
		$this->Asset =& new AssetHelper();
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
			'/css/asset1.css'               => $this->webroot . 'css' . DS . 'asset1.css',
			'/css/invalid.css'              => '',
			'/theme/default/css/asset1.css' => $this->root . 'views' . DS . 'themed' . DS . 'default' . DS . 'webroot' . DS . 'css' . DS . 'asset1.css',
			'/plugin_name/css/asset1.css'   => $this->root . 'plugins'. DS . 'plugin_name' . DS . 'webroot' . DS . 'css' . DS . 'asset1.css'
		);

		foreach ($files as $url => $file) {
			$result = $this->Asset->_file($url);
			$this->assertEqual($result, $file);
		}
	}
}
?>