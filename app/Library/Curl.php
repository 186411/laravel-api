<?php 
namespace App\Library;

use Memcached;

/**
 * curl　继承类，设置变量较少，以后根据需要逐渐添加
 *
 *　2013-3-6
 */
class Curl {
	
	const EXPIRE_MINUTE = 60;
	const EXPIRE_HOUR = 3600;
	const EXPIRE_DAY = 86400;

	private static $_timeout = 3500;
	private static $_conect_timeout = 1000;
	private static $_ci = null;
	private static $_memcache = null;

	public static function __init_memcache() {
		if (self::$_memcache) {
			return;
		}
		if(class_exists('Memcached')){
			$server_config = config("app.debug") == true ? 'servers' : 'local';
			$mem_config_str = "memcache.".$server_config;
                        $memcache_servers = config($mem_config_str);
			self::$_memcache = new Memcached ();
			self::$_memcache->setOption(Memcached::OPT_DISTRIBUTION,Memcached::DISTRIBUTION_CONSISTENT); 
			self::$_memcache->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE,true);
			self::$_memcache->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
			self::$_memcache->setOption(Memcached::OPT_COMPRESSION, false);
			
			foreach ( $memcache_servers as $server ) {
				self::$_memcache->addServer ( $server ['host'], $server ['port'] );
				
			}
		}
	}

	/**
	 * curl get请求
	 * @param string $url  请求资源连接
	 * 
	 * return string
	 */
	public static function get($url) {
		self::__init_memcache();
		$memkey = 'CURL_GET_FAIL_'.md5($url);
		if (self::$_memcache) {
			$ret = self::$_memcache->get($memkey);
			if ($ret && intval($ret)+self::EXPIRE_HOUR > time()) {
				return array();
			}
		}
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_BINARYTRANSFER, 1);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, self::$_conect_timeout);
		curl_setopt($curl, CURLOPT_TIMEOUT_MS, self::$_timeout);
		$result = curl_exec($curl);
		if ($result === false) {
			if (self::$_memcache) {
				self::$_memcache->set($memkey, time(), self::EXPIRE_HOUR);
			}
			return array();
		} else {
			return $result;
		}
	}
	
	/**
	 * curl post请求 
	 * @param string $url	 	请求资源连接
	 * @param string $param  	请求参数
	 * 
	 * return string
	 */
	public static function post($url , $param) {
		self::__init_memcache();
		$param_string = gettype($param) !== 'string' ? json_encode($param) :  $param;
		$memkey = 'CURL_POST_FAIL_'.md5($url.'_'.$param_string);
		if (self::$_memcache) {
			$ret = self::$_memcache->get($memkey);
			if ($ret && intval($ret)+self::EXPIRE_HOUR > time()) {
				return array();
			}
		}
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_BINARYTRANSFER, 1);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, self::$_conect_timeout);
		curl_setopt($curl, CURLOPT_TIMEOUT_MS, self::$_timeout);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $param);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type:application/json"));
		curl_setopt($curl, CURLOPT_AUTOREFERER, 1); 
		$result = curl_exec($curl);
		if ($result === false) {
			if (self::$_memcache) {
				self::$_memcache->set($memkey, time(), self::EXPIRE_HOUR);
			}
			return array();
		} else {
			return $result;
		}
	}
	
	public static function get_timeout($timeout) {
		return self::$_timeout;
	}
	
	public static function get_connect_timeout($connect_timeout) {
		return self::$_connect_timeout;
	}
	
	public static function set_timeout($timeout) {
		self::$_timeout = $timeout;
	}
	
	public static function set_connect_timeout($connect_timeout) {
		self::$_connect_timeout = $connect_timeout;
	}
}