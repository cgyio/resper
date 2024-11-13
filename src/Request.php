<?php
/**
 * cgyio/resper 核心类
 * Request 请求类
 */

namespace Cgy;

use Cgy\request\Url;
use Cgy\request\Header;
use Cgy\request\Ajax;
use Cgy\util\Server;
use Cgy\util\Session;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Conv;
use Cgy\traits\staticCurrent;

class Request
{
    //引入trait
    use staticCurrent;

    /**
     * current
     */
    public static $current = null;

    //Url 对象
    public $url = null;

    //Request Header 对象
    public $header = null;

    //Ajax 和 跨域请求处理 对象
    public $ajax = null;

    //uac
    public $uac = null;

    //app
    public $app = null;

    //request参数
    //public $headers = [];
    public $method = "";
    public $time = 0;
    public $https = false;
    //public $isAjax = false;
    //public $referer = "";

    //web 预定义参数
    public $lang = "zh-CN";
    public $pause = false;
    public $debug = false;

    //传入参数 对象
    public $gets = null;
    public $posts = null;
    public $files = null;
    public $inputs = null;

    //解析 request 后得到的 response.headers 初始值
    public $responseHeaders = [
        
    ];

    /**
     * 构造
     * @return void
     */
    public function __construct()
    {
        //创建 Url 处理对象
        $this->url = Url::current();

        //创建 Request Header 对象
        $this->header = new Header();
        //$this->headers = $this->header->context;
        $this->method = Server::get("Request-Method");
        $this->time = Server::get("Request-Time");
        $this->https = Is::nemstr(Server::get("Https"));    //$this->url->protocol == "https";

        //创建 Ajax 请求处理对象
        $this->ajax = new Ajax();
        //$this->isAjax = $this->ajax->true;
        //$this->referer = $this->ajax->referer;
        if (!empty($this->ajax->responseHeaders)) {
            $this->responseHeaders = array_merge($this->responseHeaders, $this->ajax->responseHeaders);
        }

        //web 参数
        //lang
        $this->lang = self::get("lang", EXPORT_LANG);
        //通过设置 WEB_PAUSE 暂停网站（src资源仍可以访问）
        $this->pause = WEB_PAUSE;
        //debug标记
        $this->debug = WEB_DEBUG;

        //处理传入数据
        $this->gets = new 
    }

    

    /**
     * 
     */
    public function ttt()
    {
        $url = $this->url;
        $u = $url->full;
        if ($u=="https://wx.cgy.design/qyspkj/scaninput/E9999?format=json") {
            header("Content-Type: application/json; charset=utf-8",);
            header("Access-Control-Allow-Origin: *");
            $s = [
                "foo" => "bar"
            ];
            echo a2j($s);
            exit;
        }
    }


    /**
     * static
     */

    //$_GET
    public static function get($key = [], $val = null)
    {
        if (is_array($key)) {
            if (empty($key)) return $_GET;
            $p = array();
            foreach ($key as $k => $v) {
                $p[$k] = self::get($k, $v);
            }
            return $p;
        }else{
            return isset($_GET[$key]) ? $_GET[$key] : $val;
        }
    }

    //$_POST
    public static function post($key = [], $val = null)
    {
        if (is_array($key)) {
            if (empty($key)) return $_POST;
            $p = array();
            foreach ($key as $k => $v) {
                $p[$k] = self::post($k,$v);
            }
            return $p;
        }else{
            return isset($_POST[$key]) ? $_POST[$key] : $val;
        }
    }

    //$_FILES
    public static function files($fieldname = [])
    {
        if (Is::nemstr($fieldname)) {
            if (!isset($_FILES[$fieldname])) return [];
            $fall = $_FILES[$fieldname];
            $fs = [];
            if (Is::indexed($fall["name"])) {
                $ks = array_keys($fall);
                $ci = count($fall["name"]);
                for ($i=0;$i<$ci;$i++) {
                    $fs[$i] = [];
                    foreach ($ks as $ki => $k) {
                        $fs[$i][$k] = $fall[$k][$i];
                    }
                }
            } else {
                $fs[] = $fall;
            }
            return $fs;
        }
        //if (Is::indexed($fieldname) && !empty($fieldname)) {
        if (Is::nemarr($fieldname)) {
            $fds = $fieldname;
            $fs = [];
            for ($i=0; $i<count($fds); $i++) {
                $fsi = self::files($fds[$i]);
                if (!empty($fsi)) {
                    $fs = array_merge($fs, $fsi);
                }
            }
            return $fs;
        }
        $fs = [];
        foreach ($_FILES as $fdn => $fdo) {
            $fsi = self::files($fdn);
            if (!empty($fsi)) {
                $fs = array_merge($fs, $fsi);
            }
        }
        return $fs;
    }

    //php://input，输入全部转为json，返回array
    public static function input($in = "json")
    {
        $input = file_get_contents("php://input");
        if (empty($input)) {
            $input = Session::get("_php_input_", null);
            if (is_null($input)) return null;
            Session::del("_php_input_");
        }
        $output = null;
        switch($in){
            case "json" :
                $output = Conv::j2a($input);
                break;
            case "xml" :
                $output = Conv::x2a($input);
                break;
            case "url" :
                $output = Conv::u2a($input);
                break;
            default :
                $output = Arr::mk($input);
                break;
        }
        return $output;
    }




    /**
     * static tools
     */
    
    /**
     * 获取 $_SERVER["FOO_****"] 内容
     * @param String $pre 前缀名 foo  -->  $_SERVER["FOO_***"]
     * @return Array
     */
    public static function getServPre($pre = "")
    {
        if (!Is::nemstr($pre)) return [];
        $pre = strtoupper($pre);
        if ("_" !== substr($pre, -1)) $pre .= "_";
        $serv = $_SERVER;
        $arr = [];
        foreach ($serv as $k => $v) {
            if ($pre !== substr($k, 0, strlen($pre))) continue;
            $kk = strtolower(substr($k, strlen($pre)));
            $kk = ucwords(str_replace("_", " ", $kk));
            $kk = str_replace(" ", "-", $kk);
            $arr[$kk] = $v;
        }
        return $arr;
    }

}