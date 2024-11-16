<?php
/**
 * cgyio/resper Response 输出类
 * Error 错误输出类
 */

namespace Cgy\response\exporter;

use Cgy\response\Exporter;
use Cgy\Response;

class Error extends Exporter
{

    public $contentType = "application/json; charset=utf-8";

    //准备输出的数据
    public function prepare()
    {
        $this->_prepare();
        $err = array_pop($this->data["errors"]);
        $this->data["error"] = true;
        foreach ($err as $k => $v) {
            $this->data[$k] = $v;
        }
        $this->data["data"] = $err["title"]." ".$err["msg"]." in ".$err["file"]." at line ".$err["line"];
    }

    //准备要输出的字符串
    protected function parse()
    {
        //$this->content = a2j($this->data);
    }

    //改写export方法
    public function export()
    {
        //$format = Response::exportFormat();
        //$exp = Response::exporterClass($format);
        //$exporter = new $exp($this->response);
        $response = $this->response;
        $exporter = Exporter::create($response);
        $exporter->prepare();
        $exporter->data = $this->data;
        $exporter->export();
        exit;
    }

}