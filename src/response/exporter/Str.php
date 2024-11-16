<?php
/**
 * cgyio/resper Response 输出类
 * Error 错误输出类
 */

namespace Cgy\response\exporter;

use Cgy\response\Exporter;
use Cgy\Response;
use Cgy\util\Str as StrUtil;

class Str extends Exporter
{

    public $contentType = "text/plain; charset=utf-8";

    //准备输出的数据
    public function prepare()
    {
        $this->content = StrUtil::mk($this->data["data"]);
        return $this;
    }

}