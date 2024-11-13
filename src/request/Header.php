<?php
/**
 * cgyio/resper 请求头处理类
 * Request Header 处理类
 */

namespace Cgy\request;

use Cgy\Header as Hdr;
use Cgy\Request;
use Cgy\util\Server;
use Cgy\util\Arr;

class Header extends Hdr 
{
    //$_SERVER 原始值
    public $origin = [];

    //其他 $_SERVER 参数
    //protected $keys = "request,path,content,server,remote,script,php";

    /**
     * 构造
     * 创建 Request Header 实例
     * @return void
     */
    public function __construct()
    {
        $this->origin = $_SERVER;
        $this->context = $this->getHeaders();

        //获取 其他 $_SERVER 参数
        /*$pres = explode(",", $this->keys);
        for ($i=0;$i<count($pres);$i++) {
            $pre = ucfirst($pres[$i]);
            $pv = Arr::copy(Server::pre($pre));
            $this->$pre = (object)$pv;
        }*/
    }

    /**
     * getHeaders 获取请求头
     * @return Array
     */
    public function getHeaders()
    {
        $hds = [];
        if (function_exists("apache_request_headers")) {
            //Apache环境下
            $hds = apache_request_headers();
        } else {
            $hds = Server::pre("http");
        }
        return $hds;
    }
}