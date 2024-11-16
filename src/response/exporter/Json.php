<?php
/**
 * cgyio/resper Response 输出类
 * Json 输出类
 */

namespace Cgy\response\exporter;

use Cgy\response\Exporter;
use Cgy\Response;
use Cgy\util\Conv;

class Json extends Exporter
{

    public $contentType = "application/json; charset=utf-8";

    //准备输出的数据
    public function prepare()
    {
        if ($this->response->exportOnlyData) {
            $this->content = Conv::a2j($this->data["data"]);
        } else {
            $this->content = Conv::a2j($this->data);
        }
    }

}