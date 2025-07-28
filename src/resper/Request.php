<?php
/**
 * cgyio/resper 核心类
 * Request 请求类
 */

namespace Cgy;

//use Cgy\Resper;
use Cgy\Response;
use Cgy\App;
use Cgy\Event;
use Cgy\request\Url;
use Cgy\request\Header;
use Cgy\request\Ajax;
use Cgy\request\Gets;
use Cgy\request\Posts;
use Cgy\request\Input;
use Cgy\request\Files;
use Cgy\request\Seeker;
use Cgy\util\Server;
//use Cgy\util\Session;
use Cgy\util\Is;
//use Cgy\util\Arr;
//use Cgy\util\Conv;

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

    //解析 响应者 (相当于 路由实例)
    //public $seeker = null;

    //请求用户
    //public $usr = null;

    //app
    //public $app = null;

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

        //处理传入数据
        $this->gets = new Gets($_GET);
        $this->posts = new Posts($_POST);
        $this->inputs = new Input();
        $this->files = new Files();

        //web 参数
        //lang
        $this->lang = $this->gets->lang(EXPORT_LANG);
        //通过设置 WEB_PAUSE 暂停网站（src资源仍可以访问）
        $this->pause = WEB_PAUSE;
        //debug标记
        $this->debug = WEB_DEBUG;

        //触发 request-created 事件
        Event::trigger("request-created", $this);

    }

    /**
     * 静态方法
     */
    //$_GET
    public static function get($key, $dft)
    {
        $gets = empty(Request::$current) ? new Gets($_GET) : Request::$current->gets;
        return $gets->$key($dft);
    }
    //$_POST
    public static function post($key, $dft)
    {
        $posts = empty(Request::$current) ? new Posts($_POST) : Request::$current->posts;
        return $posts->$key($dft);
    }
    //php://input
    public static function input($key="json")
    {
        $inps = empty(Request::$current) ? new Input() : Request::$current->inputs;
        return $inps->$key;
    }

    /**
     * 获取当前请求的 来源信息
     * 为一些需要区分请求来源的场景，提供数据
     * !! Request::$current 必须已创建
     * @return Array | null
     */
    public static function audience()
    {
        $req = self::$current;
        if (!$req instanceof Request) return null;
        $aud = [
            "referer" => $_SERVER["HTTP_REFERER"] ?? "",
            "origin" => $_SERVER["HTTP_ORIGIN"] ?? "",
            "ip" => $_SERVER["REMOTE_ADDR"] ?? "",
            "audience" => "",
            "protocol" => "",   //https or http
        ];
        $audience = "public";   //默认的 来源
        if (Is::nemstr($aud["referer"])) {
            $audience = $aud["referer"];
        } else {
            if (Is::nemstr($aud["origin"])) {
                $audience = $aud["origin"];
            }
        }
        if ($audience==="public") {
            $aud["protocol"] = "http";
        } else {
            $aud["protocol"] = strpos(strtolower($audience), "https://")!==false ? "https" : "http";
            $audience = explode("/", explode("://", $audience)[1])[0];
        }
        $aud["audience"] = $audience;

        return $aud;
    }

}