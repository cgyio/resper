<?php
/**
 * cgyio/resper 响应头处理类
 * Response Header 处理类
 */

namespace Cgy\response;

use Cgy\Header as Hdr;
use Cgy\Resper;
use Cgy\Request;
use Cgy\Response;
use Cgy\util\Arr;

class Header extends Hdr 
{
    //headers 参数数组
    public $context = [
        //默认值
        "Content-Type" => "text/html; charset=utf-8",
        "Access-Control-Allow-Origin" => "*",
        "Access-Control-Allow-Headers" => "*",
        "Access-Control-Allow-Methods" => "POST,GET,OPTIONS",
        //"Access-Control-Allow-Credentials" => "true",
        "User-Agent" => "Cgy/Response",
        "X-Framework" => "cgyio/resper",
    ];

    /**
     * 构造
     * @return void
     */
    public function __construct()
    {
        //合并 Request 实例创建的 responseHeaders 数组
        $hds = Request::current()->responseHeaders;
        $this->context = Arr::extend($this->context, $hds);
        
    }

    /**
     * 设定 headers 参数数组
     * @param Mixed $key 键名 或 [ associate ]
     * @param Mixed $val 参数内容
     * @return $this
     */
    public function set($key = [], $val = null)
    {
        if (is_array($key) && !empty($key)) {
            $this->context = Arr::extend($this->context, $key);
        } else if (is_string($key)/* && isset($this->context[$key])*/) {
            $this->context[$key] = $val;
        }
        return $this;
    }


}