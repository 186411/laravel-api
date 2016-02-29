<?php 
namespace App\Library;


class Api {
	const DEFAULT_CODE = self::CODE_SUCCEED;

	const CODE_SUCCEED = 0;
	const CODE_UNKNOW = 1;
	const CODE_NOT_FOUND = 2;
	const CODE_QUERY_FAIL = 3;
	const CODE_PARAMETER_ERROR = 4;
	const CODE_SECRET_ERROR = 5;
	const CODE_TOKEN_ERROR = 6;
	const CODE_EXCEPTION = 7;
	const CODE_MAX_CLIENTS = 8;
	const USER_UN_VERIFIED=9;
	const CODE_CUSTOM = 100;

	const STATUS_CODE_OK = 200;
	const STATUS_CODE_CREATED = 201;
	const STATUS_CODE_NO_CONTENT = 204;
	const STATUS_CODE_INVALID_REQUEST = 400;
	const STATUS_CODE_FORBIDDEN = 403;
	const STATUS_CODE_NOT_FOUND = 404;
	const STATUS_CODE_INTERNAL_ERROR = 500;

	const EXPIRE_NOW = 0;
	const EXPIRE_MINUTE = 60;
	const EXPIRE_HOUR = 3600;
	const EXPIRE_DAY = 86400;
	
	private static $_expire_time = 0;
	private static $_result = NULL;
	private static $_code = self::CODE_SUCCEED;
	private static $_status_code = self::STATUS_CODE_OK;
	private static $_message = NULL;
	private static $_options = array();
	
	protected static $_message_text = array(
		self::CODE_SUCCEED => 'OK',
		self::CODE_UNKNOW => 'Unknow',
		self::CODE_NOT_FOUND => 'Not found',
		self::CODE_QUERY_FAIL => 'Database query failed',
		self::CODE_PARAMETER_ERROR => 'Parameter error',
		self::CODE_SECRET_ERROR => 'Secret error ',
		self::CODE_TOKEN_ERROR => 'Token error',
		self::CODE_EXCEPTION => 'Some exception occurs',
		self::CODE_MAX_CLIENTS => 'Clients SET MAX',
	    self::USER_UN_VERIFIED => 'USER IS NOT VERIFIED',		
	);
	
	/**
	 * @return the $_status_code
	 */
	public static function get_status_code() {
		return Api::$_status_code;
	}

	/**
	 * @param string $_status_code
	 */
	public static function set_status_code($_status_code) {
		Api::$_status_code = $_status_code;
	}

	/**
	 * @return the $_result
	 */
	public static function get_result() {
		return Api::$_result;
	}

	/**
	 * @return the $_code
	 */
	public static function get_code() {
		return Api::$_code;
	}

	/**
	 * @return the $_message
	 */
	public static function get_message() {
		return Api::$_message;
	}

	/**
	 * @param mixed $_result
	 */
	public static function set_result($_result) {
		Api::$_result = $_result;
	}

	/**
	 * @param string $_code
	 */
	public static function set_code($_code) {
		Api::$_code = $_code;
	}

	/**
	 * @param string $_message
	 */
	public static function set_message($_message) {
		Api::$_message = $_message;
	}

	public static function response($result = NULL) {
		self::_set_expire_headers();
		$result = is_null($result) ? self::$_result : $result;

		if (class_exists('SimpleXMLElement') && ($result instanceof SimpleXMLElement)) {
			self::xml($result, self::$_code, self::$_message, self::$_options);
		} else {
			self::json($result, self::$_code, self::$_message, self::$_options);
		}
	}

	public static function xml($result, $code = self::DEFAULT_CODE, $message = null, $options = null) {
		header('Content-Type: text/xml; charset=utf-8');

		if (class_exists('SimpleXMLElement') && ($result instanceof SimpleXMLElement)) {
			$response = $result->asXML();
		} else {
			$response = strval($result);
		}
		exit($response);
	}

	public static function json($result, $code = self::DEFAULT_CODE, $message = null, $options = null) {
		if (is_null($message)) {
			$message = self::get_message_text($code);
		}

		$result = array('code' => $code, 'message' => $message, 'result' => $result);
		if (is_array($options)) {
			$result = array_merge($options, $result);
		}
		header('Content-Type: application/x-javascript; charset=utf-8');
		exit(json_encode($result));
	}

	public static function set_option($key, $value) {
		self::$_options[$key] = $value;
	}
	
	public static function get_option($key) {
		return self::$_options[$key];
	}

	public static function set_options($options) {
		if (!is_array($options)) {
			return false;
		}
		self::$_options = $options;
		return true;
	}

	public static function get_options() {
		return self::$_options;
	}
	
	private static function _set_expire_headers() {
		if (self::$_expire_time) {
			header('Cache-Control: max-age=' . self::$_expire_time);
			header('Expires: ' . gmdate('D, j M Y H:i:s T', time() + self::$_expire_time));
		} else {
			header("Cache-Control: no-cache, must-revalidate");
			header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
		}	
	}
	
	/**
	 * 设置HTTP HEADER里的缓存时间
	 * 
	 * @param int $seconds 过期时间的秒数，0为不缓存。 如果该值大于当前的Unix时间戳，则为实际过期时间
	 */
	public static function set_expire_time($seconds) {
		$now = time();
		if ($seconds > $now) {
			$seconds -= $now;
		}
		$old = self::$_expire_time;
		self::$_expire_time = intval($seconds);

		return $old;
	}
	
	/**
	 * 根据状态代码返回错误信息
	 * 
	 * @param intval $code
	 * @return string
	 */
	public static function get_message_text($code) {
		if (!array_key_exists($code, self::$_message_text)) {
			$code = self::CODE_UNKNOW;
		}
		return self::$_message_text[$code];
	}
}