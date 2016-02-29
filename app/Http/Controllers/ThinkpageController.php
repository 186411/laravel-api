<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Library\Api;
use App\Library\Weathers_V2;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ThinkpageController
 *
 * @author Administrator
 */
class ThinkpageController extends Controller {
    //put your code here
    
    
    public function day(){
       
       $day = new Weathers_V2();
       
       $json =  $day->weather_2345("http://tianqi.2345.com/api/partnerGetWeather.php","上海");
       Api::response($json);
        
    }
}
