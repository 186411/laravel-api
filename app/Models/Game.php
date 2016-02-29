<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Game
 *
 * @author Administrator
 */
class Game extends Model {

    private function __construct() {
        DB::connection('op')->enableQueryLog();
        ;
    }

    //put your code here
    public function get_topic_by_lid_v1($tid = 0, $page = 1, $page_size = 20) {
        $result = array();
        $topic_list = $this->get_topic_by_lid($tid, $page, $page_size);
        $topic_count = count($topic_list);
        foreach ($topic_list as $k => $topic) {
            $temp = array();
            $result_count = count($result);
            // print_r($topic);
            $temp['name'] = $topic->name;
            $temp['rate_times'] = $topic->rate_times;
            $temp['note'] = number_format($topic->rate_times, 0, '.', ',') . '顶';
            $temp['size'] = $topic->position == 2 ? 6 : 12;
            $temp['ratio'] = $topic->position == 2 ? 0.546153 : 0.26666666;
            $temp['image'] = $topic->position == 2 ? $this->_get_image_url($topic->logo_url) : $this->_get_image_url($topic->banner_url);
            $temp['page'] = $topic->type == 1 ? 'topic' : 'article';
            $temp['interface'] = 'apps/content/topic?topic_id=' . $topic->id;
            $temp['topic_id'] = $topic->id;
            if (($temp['size'] == 12) || ($k == 0 || (isset($result[$result_count - 1]) && ($result[$result_count - 1][0]['size'] == 12 || count($result[$result_count - 1]) > 1)))) {
                $result[] = array($temp);
            } elseif (!empty($result[$result_count - 1])) {
                array_push($result[$result_count - 1], $temp);
            }
        }
        return array(
            'list' => $result,
            'count' => $topic_count
        );
    }

    public function get_topic_by_lid($tid, $page, $page_size) {
        $start = ($page - 1) * $page_size;
        $result = DB::table("adm_topic_listing")
                ->join('adm_topics', 'adm_topic_listing.topic_id = adm_topics.id')
                ->select('adm_topics.id,name,banner_url,logo_url,type,rate_times,position,adm_topics.description')
                ->where('adm_topic_listing.list_id', $tid)
                ->where('position !=', 1)
                ->where_in('visible', array(0, 2))
                ->where('adm_topics.valid_on < ', date('Y-m-d H:i:00'))
                ->where('adm_topics.valid_on > ', '2013-01-01 0:0:0')
                ->limit($page_size, $start)
                ->order_by('displayorder', 'desc')
                ->get();
        return $result;
    }

}
