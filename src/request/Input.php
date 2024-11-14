<?php
/**
 * cgyio/resper 输入数据处理类
 * 处理 php://input 数据
 */

namespace Cgy\request;

use Cgy\util\Secure;
use Cgy\util\Session;
use Cgy\util\Is;
use Cgy\util\Conv;

class Input 
{
    //原始数据
    protected $origin = [];

    //处理后的数据
    public $context = [];

    /**
     * 构造
     * @param Array $data 待处理数据 php://input
     * @return void
     */
    public function __construct()
    {
        $input = file_get_contents("php://input");
        if (empty($input)) {
            $input = Session::get("_php_input_", null);
            //if (is_null($input)) return null;
            Session::del("_php_input_");
        }
        $this->origin = $input;

        //Secure 处理
        //...

        $this->context = $input;
    }

    /**
     * 按指定类型 转换 数据
     * @param String $type 默认 json
     * @return Array
     */
    protected function export($type = "json")
    {
        $input = $this->context;
        $output = [];
        switch($type){
            case "json" :
                $output = Conv::j2a($input);
                break;
            case "xml" :
                $output = Conv::x2a($input);
                break;
            case "url" :
                $output = Conv::u2a($input);
                break;
            case "arr" : 
                $output = Arr::mk($input);
            default :
                $output = $input;
                break;
        }
        return $output;
    }
    //public function json() { return $this->export("json");}
    //public function xml() { return $this->export("xml");}
    //public function url() { return $this->export("url");}

    /**
     * __get 调用 export 方法
     * $this->foo  -->  $this->export("foo")
     * @param String $key
     * @return Array
     */
    public function __get($key)
    {
        //if ($key=="raw") $key = "";
        $out = $this->export($key);
        return $out;
    }



}