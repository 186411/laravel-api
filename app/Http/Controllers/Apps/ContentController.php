<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Library\Api;
use App\Models\Apps;
use Illuminate\Support\Facades\Input;
use Memcached;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ContentController
 *
 * @author Administrator
 */
class ContentController extends Controller {

    //put your code here

    protected $memcache = null;

    private function __init_memcache() {
        if ($this->_memcache) {
            return;
        }
        if (class_exists('Memcached')) {
            $server_config = config("app.debug") == true ? 'servers' : 'local';
            $mem_config_str = "memcache." . $server_config;
            $memcache_servers = config($mem_config_str);
            $this->_memcache = new Memcached ();
            $this->_memcache->setOption(Memcached::OPT_DISTRIBUTION, Memcached::DISTRIBUTION_CONSISTENT);
            $this->_memcache->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
            $this->_memcache->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
            $this->_memcache->setOption(Memcached::OPT_COMPRESSION, false);

            foreach ($memcache_servers as $server) {
                $this->_memcache->addServer($server ['host'], $server ['port']);
            }
        }
    }

    public function topics() {
        $id = Input::get("id");
        if ($id) {
            return $this->_topics_v2($id);
        } else {
            return $this->_topics_v1();
        }
    }

    private function _topics_v2($id = 0) {
        $id = intval($id);
        $page = intval(Input::get("page"));
        $page_size = intval(Input::get("page_size"));
        $top_id = Input::get("top_id");
        $top_id = isset($top_id) ? trim($top_id) : null;
        $left_id = Input::get("left_id");
        $left_id = isset($left_id) ? trim($left_id) : null;
        $page_max = 1000;
        $page = max(min($page, $page_max), 1);
        $page_size = max(min($page_size, 500), 10);
        $memkey = 'OP_TOPICS_V1_' . md5($id . '-' . $page . '-' . $page_size);
        $extra = array('category' => 'topics', 'topics_id' => $id);
        $this->__init_memcache();
        if ($this->memcache) {
            $arr = $this->memcache->get($memkey);
            if (!empty($arr)) {
                $result = json_decode($arr, 1);
                $next_url = $result['next'];
                Api::set_option('next', $next_url);
                Api::set_expire_time(Api::EXPIRE_HOUR * 2);
                return $result['list'];
            }
        }
        $gameModel = new \Game();
        $topic_list = $gameModell->get_topic_by_lid_v1($id, $page, $page_size);
        // print_r($result);
        $result = !empty($topic_list['list']) ? $topic_list['list'] : array();
        $page_count = count($topic_list['count']);
        $is_show_next_page = false;
        $all_count = $this->ci->game_model->get_topic_by_lid_count($id);
        if ($all_count > ($page * $page_size)) {
            $is_show_next_page = true;
        }
        if ($is_show_next_page) {
            $page++;
            $next_url = "apps/content/topics?id={$id}&page={$page}&page_size={$page_size}";
        } else {
            $next_url = '';
        }
        if ($this->memcache) {
            $this->memcache->set($memkey, json_encode(array('list' => $result, 'next' => $next_url)), Api::EXPIRE_HOUR * 2);
        }
        Api::set_option('next', $next_url);
        Api::set_expire_time(Api::EXPIRE_HOUR * 2);
        return $result;
    }

    private function _topics_v1() {
        $type = Input::get("type");
        $type = trim($type);
        switch ($type) {
            case 'soft':
                break;
            case 'game':
                break;
            case 'all':
            default:
                $type = '';
        }
        $page = intval(Input::get("page"));
        $page_size = intval(Input::get('page_size'));
        $page_max = 1000;
        $page = max(min($page, $page_max), 1);
        $page_size = max(min($page_size, 500), 10);
        $appmodel = new Apps();
        $category = 'list';
        $result = $appmodel->get_topic_list($type, $category, $page, $page_size);
        if (count($result) == $page_size) {
            $next_page_count = $appmodel->get_topic_next_page_count($type, $category, $page, $page_size);
        } else {
            $next_page_count = 0;
        }

        if ($result) {
            $sorted = array();
            $pending = array();
            $pending2 = array();
            foreach ($result as $row) {
                $row['note'] = number_format($row['rate_times'], 0, '.', ',') . '顶';
                if ($row['position'] == 2) {
                    $row['size'] = 6;
                    $row['ratio'] = 0.546153;
                    $row['image'] = $row['logo_url'];
                } else {
                    $banner_img = !empty($row['pad_banner']) ? $row['pad_banner'] : $row['banner_url'];
                    $row['size'] = 12;
                    $row['ratio'] = 0.26666666;
                    $row['image'] = $banner_img;
                }
                //$row ['size'] = $row ['position'] == 2 ? 6 : 12; // 一行定义为12格，半宽为6
                //$row ['ratio'] = $row ['size'] == 6 ? 0.546153 : 0.26666666; // 根据格子计算banner位比例
                $row['page'] = $row['type'] == 1 ? 'topic' : 'article';
                $row['interface'] = 'apps/content/topic?topic_id=' . $row['id'];
                $row['topic_id'] = $row['id'];
                //$row ['image'] = $row ['logo_url'];
                unset($row['logo_url']);
                unset($row['banner_url']);
                unset($row['pad_banner']);
                unset($row['type']);
                unset($row['id']);
                // unset($row['rate_times']);
                unset($row['position']);
                if (empty($pending)) {
                    if ($row['size'] == 6) {
                        $pending[] = $row;
                    } else {
                        $sorted[] = array(
                            $row,
                        );
                    }
                } else {
                    if ($row['size'] == 6) {
                        $pending[] = $row;
                        $sorted[] = $pending;
                        $pending = array();
                        if (!empty($pending2)) {
                            foreach ($pending2 as $row) {
                                $sorted[] = array(
                                    $row,
                                );
                            }
                            $pending2 = array();
                        }
                    } else {
                        $pending2[] = $row;
                    }
                }
            }
            if (!empty($pending2)) {
                foreach ($pending2 as $row) {
                    $sorted[] = array(
                        $row,
                    );
                }
            }
            if (!empty($pending)) {
                $sorted[] = array(
                    $pending[0],
                );
            }
            $result = $sorted;
        } else {
            $result = array();
        }

        Api::set_expire_time(Api::EXPIRE_HOUR * 2);
        $page++;
        if ($next_page_count > 0) {
            Api::set_option('next', "apps/content/topics?type={$type}&page={$page}");
        } else {
            Api::set_option('next', '');
        }
        return $result;
    }

}
