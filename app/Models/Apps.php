<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Apps
 *
 * @author Administrator
 */
class Apps extends Model {

    const IMAGE_URL_PREFIX = 'http://static.lewaos.com/apps/image/';
    
    private $connect ;
    
     protected $table = 'adm_topics';
    
     
    public function get_topic_list($type, $category = 'list', $page = 1, $size = 12) {
        $query = Apps::on("op")->select('id','name','banner_url','logo_url','pad_banner','type','rate_times','position')
                ->where('deleted', '0')
                ->where('valid_on','<', date('Y-m-d H:i:00'))
                ->where('valid_on','>', '2013-01-01 0:0:0')
                ->whereIn('visible', array(0, 1))
                ->orderBy('priority', 'desc')
                ->orderBy('valid_on', 'desc')
                ->skip(($page - 1) * $size)
                ->take($size);
        
        if ($category == 'list') {
            Apps::on("op")->where('position','<>', 1);
        } elseif ($category == 'banner') {
           Apps::on("op")->where('position', 1);
        }
        if ($type) {
            Apps::on("op")->where("kind = '$type' or kind = 'both'");
        }
        $queryResult = Apps::on("op")->get();
        $result = array();
        if (!empty($queryResult)&& $queryResult->count()>0) {
            $result = array();
            foreach ($queryResult->toArray() as $row) {
                $row['logo_url'] = $this->_get_image_url($row['logo_url']);
                $row['banner_url'] = $this->_get_image_url($row['banner_url']);
                $row['pad_banner'] = empty($row['pad_banner']) ? '' : $this->_get_image_url($row['pad_banner']);
                $result[] = $row;
            }
        }
        return $result;
    }

    public function get_topic_next_page_count($type, $category = 'list', $page = 1, $size = 12) {
        DB::table('adm_topics')->select('id')
                ->where('deleted', '0')
                ->where('valid_on < ', date('Y-m-d H:i:00'))
                ->where('valid_on > ', '2013-01-01 0:0:0')
                ->where_in('visible', '0,1')
                ->limit($size, $page * $size);
        if ($category == 'list') {
            DB::table('adm_topics')->where('position <>', 1);
        } elseif ($category == 'banner') {
            DB::table('adm_topics')->where('position', 1);
        }
        $query = $this->db->get();

        if ($query && $query->num_rows()) {
            $count = $query->num_rows();
        } else {
            $count = 0;
        }
        return $count;
    }

    private function _get_image_url($file) {
        return self::IMAGE_URL_PREFIX . $file;
    }

}
