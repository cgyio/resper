<?php
/**
 * cgyio/resper Response 输出类
 * Page 输出类
 */

namespace Cgy\response\exporter;

use Cgy\Resper;
use Cgy\response\Exporter;
//use Cgy\Response;
//use Cgy\Request;
use Cgy\util\Is;

class Page extends Exporter
{
    public $contentType = "text/html; charset=utf-8";

    //准备输出的数据
    public function prepare()
    {
        $page = $this->data["data"];
        if (!Is::nemstr($page)) {
            var_dump($page);
            exit;
        }
        if (!file_exists($page)) {
            http_response_code(404);
            exit;
        }
        
        $_Request = Resper::$request;
        $_Resper = Resper::$resper;
        $_Response = $this->response;
        $_Params = [];

        $vars = get_object_vars($this->response);
        //$dps = Response::getDefaultParams();
        foreach ($vars as $k => $v) {
            if (!isset($_Params[$k])) {
                $_Params[$k] = $v;
                $$k = $v;
            }
        }

        //调用页面
        require($page);
        //从 输出缓冲区 中获取内容
        $this->content = ob_get_contents();
        //清空缓冲区
        ob_clean();

        return $this;
    }
    
}