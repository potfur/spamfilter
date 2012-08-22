<?php
namespace lib;

/**
 * Request representation
 *
 * @package Moss HTTP
 * @author  Michal Wachowski <wachowski.michal@gmail.com>
 */
class Request {

	public $xhr = false;
	public $method;
	public $schema;
	public $domain;
	public $dir;
	public $baseName;
	public $clientIP;
	public $identifier;
	public $url;
	public $self;
	public $invalidRedirect = false;
	public $referer;
	public $cacheable = false;

	public $lang;
	public $controller;
	public $action;

	/** @var array */
	public $headers = array();

	/** @var array|string */
	public $query = array();

	/** @var array */
	public $post = array();

	/**
	 * Creates request instance
	 * Resolves request parameters
	 *
	 * @param null|string $identifier default controller identifier
	 */
	public function __construct($identifier = null) {
		$this->removeSlashes();

		$this->xhr = (bool) isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
		$this->method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'CLI';
		$this->schema = isset($_SERVER['SERVER_PROTOCOL']) ? strtoupper($_SERVER['SERVER_PROTOCOL']) : null;
		$this->domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;
		$this->dir = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : null;
		$this->referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;

		if(!in_array($this->dir[strlen($this->dir) - 1], array('/', '\\'))) {
			$this->dir = str_replace('\\', '/', dirname($this->dir));
		}

		if(isset($_SERVER['REQUEST_URI'])) {
			$this->url = $this->dir == '/' ? $_SERVER['REQUEST_URI'] : str_replace($this->dir, null, $_SERVER['REQUEST_URI']);
			$this->url = '/' . trim($this->url, '/');

			$this->hostCorrection();

			$this->baseName = strtolower(substr($this->schema, 0, strpos($this->schema, '/'))) . '://' . str_replace('//', '/', $this->domain . $this->dir . '/');
		}

		$this->clientIP = $this->getClientIP();

		$queryStart = strpos($this->url, '?');
		if($queryStart !== false) {
			$this->query = substr($this->url, $queryStart + 1);
			$this->url = substr($this->url, 0, $queryStart);
		}

		if(!empty($this->query)) {
			parse_str($this->query, $this->query);
		}

		$this->query = array_merge($this->query, $_GET);

		$this->resolveConsole();
		$this->resolveHeaders();

		$this->identifier = isset($this->query['controller']) ? str_replace(array('\\', '_'), ':', $this->query['controller']) : $identifier;
		unset($this->query['controller']);

		$this->post = $_POST;
	}

	protected function resolveConsole() {
		if(isset($GLOBALS['argc'], $GLOBALS['argv']) && $GLOBALS['argc'] > 1) {
			$this->url = $GLOBALS['argv'][1];

			for($i = 1; $i < $GLOBALS['argc']; $i++) {
				$arg = explode('=', $GLOBALS['argv'][$i]);
				$this->query[ltrim($arg[0], '--')] = isset($arg[1]) ? $arg[1] : null;
			}
		}
	}

	/**
	 * Retrieves all request parameters as associative array
	 *
	 * @return array
	 */
	public function retrieve() {
		return get_object_vars($this);
	}

	/**
	 * Returns true if request has header
	 *
	 * @param string $headerType
	 *
	 * @return bool
	 */
	public function hasHeader($headerType) {
		if(!isset($this->headers[$headerType])) {
			return false;
		}

		return true;
	}

	/**
	 * Returns header content
	 *
	 * @param string $headerType
	 *
	 * @return string
	 */
	public function getHeader($headerType) {
		if(!isset($this->headers[$headerType])) {
			return null;
		}

		return $this->headers[$headerType];
	}

	/**
	 * Returns all headers from request
	 *
	 * @return array
	 */
	public function getHeaders() {
		return $this->headers;
	}

	/**
 	 * Removes slashes from POST, GET and COOKIE
	 */
	protected function removeSlashes() {
		if(version_compare(phpversion(), '6.0.0-dev', '<') && get_magic_quotes_gpc()) {
			$_POST = array_map(array($this, 'removeSlashes'), $_POST);
			$_GET = array_map(array($this, 'removeSlashes'), $_GET);
			$_COOKIE = array_map(array($this, 'removeSlashes'), $_COOKIE);
		}
	}

	/**
	 * Removes shlashes from string
	 *
	 * @param array|string $value
	 *
	 * @return array|string
	 */
	protected function removeSlashed($value) {
		if(is_array($value)) {
			return array_map(array($this, 'removeSlashes'), $value);
		}
		else {
			return stripslashes($value);
		}
	}

	protected function resolveHeaders() {
		if(!function_exists('getallheaders')) {
			return;
		}

		foreach(getallheaders() as $type => $value) {
			$header = $type . ': ' . $value;

			if($type == 'Cookie') {
				continue;
			}

			if(array_search($header, $this->headers) !== false) {
				continue;
			}

			$this->headers[$type] = $value;
		}
	}

	/**
	 * For request outside /web/
	 * Some hosts do not allow include from outside domains directory
	 */
	protected function  hostCorrection() {
		if(!isset($_SERVER['REDIRECT_URL'])) {
			return;
		}

		$nodes = explode('/', trim($this->dir, '/'));
		$redirect = explode('/', trim($_SERVER['REDIRECT_URL'], '/'));

		$path = array();
		foreach($nodes as $node) {
			if(!in_array($node, $redirect)) {
				$path[] = $node;
			}
		}

		if(empty($path)) {
			return;
		}

		$this->invalidRedirect = implode('/', $path);
		$this->dir = substr($this->dir, 0, strpos($this->dir, $this->invalidRedirect));
		$this->url = (string) substr($this->url, strlen($this->dir) - 1);
	}

	/**
	 * Resolves request source IP
	 *
	 * @return null|string
	 */
	protected function getClientIP() {
		if(isset($_SERVER["REMOTE_ADDR"])) {
			return $_SERVER["REMOTE_ADDR"];
		}

		if(isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
			return $_SERVER["HTTP_X_FORWARDED_FOR"];
		}

		if(isset($_SERVER["HTTP_CLIENT_IP"])) {
			return $_SERVER["HTTP_CLIENT_IP"];
		}

		return null;
	}
}