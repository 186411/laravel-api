<?php
namespace App\Library;

use App\Library\Curl;
use Memcached;
/**
 * Created by PhpStorm.
 * User: frank
 * Date: 14-8-21
 * Time: 上午10:45
 */

class Weathers_V2{

    private $_memcache = null;

    public function __construct() {
        $this->__init_memcache();
    }

    /**
     * memcache 初始化
     */
    public function __init_memcache() {

        if ($this->_memcache) {
            return;
        }

        if(class_exists('Memcached')) {
            $server_config = config("app.debug") == true ? 'servers' : 'local';
            $mem_config_str = "memcache.".$server_config;
            $memcache_servers = config($mem_config_str);
            $this->_memcache = new Memcached ();
			$this->_memcache->setOption(Memcached::OPT_DISTRIBUTION,Memcached::DISTRIBUTION_CONSISTENT); 
			$this->_memcache->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE,true);
			$this->_memcache->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
			$this->_memcache->setOption(Memcached::OPT_COMPRESSION, false);
           
            foreach ( $memcache_servers as $server ) {
                $this->_memcache->addServer ( $server ['host'], $server ['port'] );
               
            }
        }
    }

    /**
     * 搜索城市
     * @param $url
     * @return array
     */
    public function search($url) {

        if (empty( $url )) return false;

        return $this->curl_get($url);
    }

    /**
     * 根据城市code获取该城市当天天气（中国天气网）
     * @param $city_name
     * @param $city_code
     * @param $url
     * @return array|bool
     */
    public function weather_day( $city_info, $url) {

        if (empty( $url ) || !isset( $city_info['city_code'] )) return false;

        $url = $url . $city_info['city_code'] . '.html';
        return $this->curl_get($url);
    }
    
    
    /**
     * api 2345获取天气情况
     *
     * @param unknown $url
     * @param unknown $city_name
     * @param string $ctype
     * @param string $partner
     * @return boolean Ambigous NULL, mixed>
     */
    public function weather_2345($url, $city_name, $ctype = '', $partner = 'lewa') {
        if (empty ( $url ))
            return false;
        $nowtime = time ();
        $token=md5('lewa'.$nowtime.'kf1eOP93w');
        if (! empty ( $ctype )) {
            $param = array (
                'city_name' => $city_name,
                'partner' => $partner,
                'ctype' => $ctype,
                'time' => $nowtime,
                'token' =>$token
            );
        } else {
            $param = array (
                'city_name' => $city_name,
                'partner' => $partner,
                'time' => $nowtime,
                'token' => $token
            );
        }
    
        $url = $url . "?" . http_build_query ( $param );
        return $this->curl_get ( $url );
    }
    

    /**
     * 根据城市code获取该城市天气趋势（中国天气网）
     * @param $city_name
     * @param $city_code
     * @param $url
     * @return array|bool
     */
    public function weather_trends($city_name, $city_code, $url) {

        if (empty( $url ) || empty( $city_code )) return false;

		$url = $url . $city_code . '.html';
        return $this->curl_get($url);
    }

    /**
     * 根据city code获取该城市的当时空气质量，当天天气，将来7天天气 (thinkpage数据源)
     * @param $url
     * @param $city_code
     * @param $passwd
     * @param string $language
     * @return array|bool|mixed
     */
    public function thinkpage( $city_code, $url, $passwd, $language = 'zh-chs') {

        if (empty( $url ) || empty( $city_code ) || empty( $passwd )) return false;

        $memcache_key = $city_code . '_' . $passwd;
        $mem_data = $this->_memcache->get($memcache_key);
        $data = array();
        if ($mem_data) {

			$data = json_decode( $mem_data, true);
        } else {
            $url = $url .'city='. $city_code . '&language='. $language .'&unit=c&aqi=city&key='.$passwd;
            $data = $this->curl_get($url);
            if ($data) {
                if ((4 < date('H')) && (date('H') <= 8)) {
                    $expire_time = Api::EXPIRE_HOUR * 2;
                } elseif ((8 < date('H')) && (date('H') <= 18)) {
                    $expire_time = Api::EXPIRE_HOUR * 3;
                } elseif ((18 < date('H')) && (date('H') <= 21)) {
                    $expire_time = Api::EXPIRE_HOUR * 2;
                } elseif ((21 < date('H')) && (date('H') <= 23)) {
                    $expire_time = Api::EXPIRE_HOUR * 1;
                } else {
                    $expire_time = Api::EXPIRE_HOUR * 3;
                }
                $data = json_encode($data);
                $this->_memcache->set($memcache_key, $data, $expire_time);
                $data = json_decode($data, true);
            }
        }
        return $data;
    }

    /**
     * 根据city code获取该城市的当时空气质量，当天天气 (thinkpage数据源)
     * @param array $city_info
     * @param string $url
     * @param string $passwd
     * @param string $language
     * @return array
     */
    public function thinkpage_day( $city_info, $url, $passwd, $language = 'china') {
        $rs = array();
        if (!isset($city_info['city_code'])) return false;

        $data = $this->thinkpage( $city_info['city_code'], $url, $passwd);
		if (!empty($data) && 'OK' == $data['status']) {
            $now = isset($data['weather'][0]['now'])?$data['weather'][0]['now']:array();
            $today = isset($data['weather'][0]['today'])?$data['weather'][0]['today']:array();
            if (!empty($now) && !empty($today)) {
                $city_name   = isset($data['weather'][0]['city_name']) ? $data['weather'][0]['city_name'] : $city_info['city_name'];
                $city_id     = isset($city_info['city_code']) ? $city_info['city_code'] :$data['weather'][0]['city_id'];
                $last_update = isset($data['weather'][0]['last_update'])?$data['weather'][0]['last_update']:date('Y-m-d H:i:s');
                $last_time   = strtotime($last_update);
                $last_update = date('Y-m-d H:i:s', $last_time);
                $update_day  = date('n/d/Y', $last_time);
                $update_time = date('H:i', $last_time);
                $level       = '';
                switch($language){
                    case 'china':
                        $level.='级';
                        break;
                    case 'fanti':
                        $level.='級';
                        break;
                    default:
                        $level.='km/h';
                        break;
                }

                $rs = array(
                    'weatherinfo' => array(
                        'city'       => $city_name,
                        'cityid'     => $city_id,
                        'temp'       => $now['temperature'],//温度
                        'WD'         => $now['wind_direction'] . '风',//风向
                        'WS'         => $now['wind_scale'].$level,//风力
                        'SD'         => $now['humidity'] . '%',//湿度
                        'WSE'        => $now['wind_scale'].$level,//风力
                        'time'       => $update_time,//更新时间
                        'wind_speed' => $now['wind_speed'],
                        'day'        => $update_day,//更新日期
                        'Pressure'   => $now['pressure'], //气压  单位：百帕hPa
                        'Rising'     => $now['pressure_rising'], //气压变化  0或steady为稳定，1或rising为升高，2或falling为降低。
                        'Sunrise'    => $today['sunrise'], //日出时间
                        'Sunset'     => $today['sunset'], //日落时间
                        'Visibility' => $now['visibility'],
                        'weather1'   => str_replace('/', '转', $now['text']),
                        'location_type' => 1, //1)百度,2)高德，3)乐蛙

						'PM25'      => $now['air_quality']['city']['pm25'],
                        'PM_24h'    => $now['air_quality']['city']['pm25'],
                        'PM25Text'  => $now['air_quality']['city']['quality'],
                        'aqi'       => $now['air_quality']['city']['aqi'],
                        'co'        => $now['air_quality']['city']['co'],
                        'co_24h'    => $now['air_quality']['city']['co'],
                        'no2'       => $now['air_quality']['city']['no2'],
                        'no2_24h'   => $now['air_quality']['city']['no2'],
                        'o3'        => $now['air_quality']['city']['o3'],
                        'o3_24h'    => $now['air_quality']['city']['o3'],
                        'o3_8h'     => $now['air_quality']['city']['o3'],
                        'o3_8h_24h' => $now['air_quality']['city']['o3'],
                        'pm10'      => $now['air_quality']['city']['pm10'],
                        'pm10_24h'  => $now['air_quality']['city']['pm10'],
                        'so2'       => $now['air_quality']['city']['so2'],
                        'so2_24h'   => $now['air_quality']['city']['so2'],
                        'time_point'=> $last_update
                    )
                );

                if ( isset($city_info['city_pinyin']) ) {
                    $pm_suggest = $this->pm_proposal($city_info['city_pinyin']);
                    if (!empty($pm_suggest)) {
                        $rs['weatherinfo']['jiankang'] = $pm_suggest['health'];
                        $rs['weatherinfo']['jianyi'] = $pm_suggest['suggest'];
                    }
                }
            }
        }
        return $rs;
    }

    /**
     * 根据city code获取该城市未来几天天气趋势 (thinkpage数据源)
     * @param $city_name
     * @param $city_code
     * @param $url
     * @param $passwd
     * @return array
     */
    public function thinkpage_trends( $city_info, $url, $passwd, $language = 'china') {

        $rs = array();
        if (!isset($city_info['city_code'])) return false;

        $data = $this->thinkpage( $city_info['city_code'], $url, $passwd);
		if (!empty($data)) {
            $future = $today = isset($data['weather'][0]['future'])?$data['weather'][0]['future']:array();
            if (!empty($future)) {

                $city_name   = isset($data['weather'][0]['city_name'])?$data['weather'][0]['city_name']:$city_info['city_name'];
                $city_id     = isset($city_info['city_code']) ? $city_info['city_code'] :$data['weather'][0]['city_id'];
                $last_update = isset($data['weather'][0]['last_update'])?$data['weather'][0]['last_update']:date('Y-m-d H:i:s');
                $last_update = date('Y-m-d H:i:s',strtotime($last_update));
                $update_day  = date('n/d/Y',strtotime($last_update));

                $rs = array(
					'weatherinfo' => array(
						'day'         => $update_day,
						'city'        => $city_name,
						'cityid'      => $city_id,
						'week'        => $future[0]['day'],
						'temp1'       => $future[0]['high'] . '℃~' . $future[0]['low'] . '℃',
						'temp2'       => $future[1]['high'] . '℃~' . $future[1]['low'] . '℃',
						'temp3'       => $future[2]['high'] . '℃~' . $future[2]['low'] . '℃',
						'temp4'       => $future[3]['high'] . '℃~' . $future[3]['low'] . '℃',
						'temp5'       => $future[4]['high'] . '℃~' . $future[4]['low'] . '℃',
						'temp6'       => $future[5]['high'] . '℃~' . $future[5]['low'] . '℃',
						'weather1'    => str_replace('/', '转', $future[0]['text']),
						'weather1_cn' => str_replace('/', '转', $future[0]['text']),
						'weather2'    => str_replace('/', '转', $future[1]['text']),
						'weather2_cn' => str_replace('/', '转', $future[1]['text']),
						'weather3'    => str_replace('/', '转', $future[2]['text']),
						'weather3_cn' => str_replace('/', '转', $future[2]['text']),
						'weather4'    => str_replace('/', '转', $future[3]['text']),
						'weather4_cn' => str_replace('/', '转', $future[3]['text']),
						'weather5'    => str_replace('/', '转', $future[4]['text']),
						'weather5_cn' => str_replace('/', '转', $future[4]['text']),
						'weather6'    => str_replace('/', '转', $future[5]['text']),
						'weather6_cn' => str_replace('/', '转', $future[5]['text']),
					)
                );

                //字段审核
                foreach ($rs['weatherinfo'] as $key => $value) {
                    if (in_array($key, array('weather1', 'weather2', 'weather3', 'weather4', 'weather5', 'weather6', 'weather1_cn', 'weather2_cn', 'weather3_cn', 'weather4_cn', 'weather5_cn', 'weather6_cn'))) {
                        if ($value == "T-Showers") {
                            $rs['weatherinfo'][$key] = '雷阵雨';
                        }
                        if ($value == "Mostly多云加风") {
                            $rs['weatherinfo'][$key] = '多云有风';
                        }
                        if ($value == "小雨阵雨") {
                            $rs['weatherinfo'][$key] = '小雨转阵雨';
                        }
                        if ($value == "小雨阵雨") {
                            $rs['weatherinfo'][$key] = '小雨转阵雨';
                        }
                        if ($value == "雨阵雨") {
                            $rs['weatherinfo'][$key] = '雨转阵雨';
                        }
                    }

                    if (in_array($key, array('temp1', 'temp2', 'temp3', 'temp4', 'temp5', 'temp6'))) {
                        $temp_array = explode('℃', $value);
                        if (($temp_array[0] == '') || ($temp_array[0] == '-') || ($temp_array[1] == '~')) {
                            $rs = array();
                        }
                    }
                }
            }
        }
        return $rs;
    }

    /**
     * 获取城市空气质量详情
     * @param $city_name
     * @param $url
     * @param $passwd
     * @return array
     */
    public function aqi( $city_info, $url, $passwd, $language = 'china'){

        $rs = array();
        if (!isset($city_info['city_code'])) return false;
        $data = $this->thinkpage( $city_info['city_code'], $url, $passwd);

        if (!empty($data)) {

            $now = isset($data['weather'][0]['now']) ? $data['weather'][0]['now'] : null;
            if (!empty($now)) {
                $aqi_city = $now['air_quality']['city'];
                $rs = array(
					//pm25数据
					'PM25'      => $aqi_city['pm25'],
					'PM_24h'    => $aqi_city['pm25'],
					'PM25Text'  => $aqi_city['quality'],
					'aqi'       => $aqi_city['aqi'],
					'co'        => $aqi_city['co'],
					'co_24h'    => $aqi_city['co'],
					'no2'       => $aqi_city['no2'],
					'no2_24h'   => $aqi_city['no2'],
					'o3'        => $aqi_city['o3'],
					'o3_24h'    => $aqi_city['o3'],
					'o3_8h'     => $aqi_city['o3'],
					'o3_8h_24h' => $aqi_city['o3'],
					'pm10'      => $aqi_city['pm10'],
					'pm10_24h'  => $aqi_city['pm10'],
					'so2'       => $aqi_city['so2'],
					'so2_24h'   => $aqi_city['so2'],
					'time_point'=> date('Y-m-d H:i:s',strtotime($aqi_city['last_update']))
                );

                if (isset($city_info['city_pinyin'])) {
                    $pm_suggest = $this->pm_proposal($city_info['city_pinyin']);
                    if (!empty($pm_suggest)) {
                        $rs['jiankang'] = $pm_suggest['health'];
                        $rs['jianyi'] = $pm_suggest['suggest'];
                    }
                }
            }
        }

        return $rs;
    }

    /**
     * 历史上的今天接口
     * @param string $url
     */
    public function history_day($url) {
        $data = array();
        $result = curl::get($url);
        if (!empty($result)) {
            $result_array = json_decode($result, true);
            if ($result_array['error'] == 0) {
                $rand_memorabilia = rand(0, count($result_array['memorabilia']) - 1);
                $rand_brith = rand(0, count($result_array['brith']) - 1);
                $rand_death = rand(0, count($result_array['death']) - 1);
                $rand_festival = rand(0, count($result_array['festival']) - 1);
                $data[0] = $result_array['memorabilia'][$rand_memorabilia];
                $data[1] = $result_array['brith'][$rand_brith];
                $data[2] = $result_array['death'][$rand_death];
                $data[3] = $result_array['festival'][$rand_festival];
            }
        }
        return $data;
    }

    /**
     * 灾害预警
     * @param string $url
     * <a href="/sjyj/0014003/201312081404545111.htm" target="_blank">xxxxxx</a>
     * <td width="139">&nbsp;2013年12月09日07时</td>
     * return array $warnings
     */
    public function warning($url) {

        $data = Curl::get($url);
        $data = iconv('EUC-CN', 'UTF-8', $data);
        preg_match_all('/<a href="\/sjyj\/[0-9\/]+\.htm" target="_blank">.*市(.*)预警<\/a>/', $data, $warning_data);
        preg_match_all('/<td width="139">&nbsp;' . date('Y') . '年' . date('m') . '月' . date('d') . '日([0-9]+)时<\/td>/', $data, $warning_time);
		$i = 0;
        $warnings = array();
        foreach($warning_time[1] as $key => $value) {
            if (strpos($warning_data[1][$key], '发布') !== false) {
                $warnings[$i]['datetime'] = date('Y-m-d') . ' ' . $value. ':00:00';
                $warnings[$i]['warning'] = mb_substr($warning_data[1][$key], 2);
                $i ++;
            }
        }
        return $warnings;
    }

    /**
     * 获取pm建议
     * @param unknown_type $city_pinyin
     */
    private function pm_proposal($city_pinyin) {

        $data = array();
        $url = 'http://pm25.in/' . $city_pinyin;
        $result = curl::get($url);

        if (!empty($result)) {
            preg_match_all('/对健康影响情况：\s*(.*)/', $result, $suggest_1);
            preg_match_all('/建议采取的措施：\s*(.*)/', $result, $suggest_2);
            $data['health'] =	!empty($suggest_1[1][0]) ? $suggest_1[1][0] : '';
            $data['suggest'] = !empty($suggest_2[1][0]) ? $suggest_2[1][0] : '';
        }

        return $data;
    }

    /**
     * 根据风速判断等级
     * @param $speed
     * @param string $language
     * @return int
     */
    public function deal_wind( $speed, $language = 'china'){

        $real_speed = $speed * 1000 /3600;

        $real_level = 0;

        if ( 0 < $real_speed && $real_speed < 0.3) {
            $real_level = 0;
        } elseif (0.3 <= $real_speed && $real_speed < 1.6) {
            $real_level = 1;
        } elseif (1.6 <= $real_speed && $real_speed < 3.4) {
            $real_level = 2;
        } elseif (3.4 <= $real_speed && $real_speed < 5.5) {
            $real_level = 3;
        } elseif (5.5 <= $real_speed && $real_speed < 8.0) {
            $real_level = 4;
        } elseif (8.0 <= $real_speed && $real_speed < 10.8) {
            $real_level = 5;
        } elseif (10.8 <= $real_speed && $real_speed < 13.9) {
            $real_level = 6;
        } elseif (13.9 <= $real_speed && $real_speed < 17.2) {
            $real_level = 7;
        } elseif (17.2 <= $real_speed && $real_speed < 20.8) {
            $real_level = 7;
        } elseif (20.8 <= $real_speed && $real_speed < 24.5) {
            $real_level = 8;
        } elseif (24.5 <= $real_speed && $real_speed < 28.4) {
            $real_level = 9;
        } elseif (28.4 <= $real_speed) {
            $real_level = 11;
        }

        switch($language){
            case 'china':
                $real_level.='级';
                break;
            case 'fanti':
                $real_level.='級';
                break;
            default:
                $real_level.='km/h';
                break;

        }

        return $real_level;
    }

    /**
     * curl get 方式调用
     * @param $url
     * @return array
     */
    public function curl_get($url) {
        
        $result = Curl::get($url);
        if (!empty($result)) {
            $result = json_decode($result, true);
            if (is_array($result) && !empty($result)) {
				return $result;
            }
        }
        return null;
    }

}