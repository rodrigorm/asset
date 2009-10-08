<?php
class CdnHelper extends Helper {
	var $scheme = 'http://';
	var $servers = 2;
	var $current = 0;
	var $host = 'static%d.example.com';
	var $sslHost = 'ssl.example.com';

	var $helpers = array('Html', 'Javascript');
	var $cache = array();

	function __construct() {
		$config = array_merge(array(
			'servers' => 2,
			'host' => 'static%d.example.com',
			'sslHost' => 'ssl.example.com'
		), (array)Configure::read('Cdn'));

		$this->servers = $config['servers'];
		$this->host    = $config['host'];
		$this->sslHost = $config['sslHost'];

		$this->isHttps = env('HTTPS') == 'on';
		if ($this->isHttps) {
			$this->scheme = 'https://';
			$this->host = $this->sslHost;
		}
	}

	function url($path) {
		if (strpos($path, '://')) {
			return $path;
		}
		return $this->generate($path);
	}

	function image($path, $options = array()) {
		if (strpos($path, '://')) {
			$url = $path;
		} else {
			$url = $this->url($this->webroot . $this->themeWeb . IMAGES_URL . $path);
		}
		return $this->Html->image($url, $options);
	}

	function javascript($url, $inline = true) {
		if (strpos($path, '://') == false) {
			$url = $this->url($url);
		}
		return $this->Javascript->link($url, $inline);
	}

	function css($path, $rel = null, $htmlAttributes = array(), $inline = true) {
		if (strpos($path, '://')) {
			$url = $path;
		} else {
			$url = $this->url($path);
		}
		if (strpos($url, '.css') === false) {
			$url .= '.css';
		}
		return $this->Html->css($url, $rel, $htmlAttributes, $inline);
	}

	function generate($url) {
		if (isset($this->cache[$url])) {
			return $this->cache[$url];
		}

		$this->current = ($this->current + 1) % $this->servers;
		$static = sprintf($this->host, $this->current + 1) . $url;
		$static = $this->scheme . str_replace('//', '/', $static);

		$this->cache[$url] = $static;

		return $static;
	}
}
?>