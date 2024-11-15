<?php
/**
 * cgyio/resper Response 输出类
 * Pause 暂停输出类
 * WEB_PAUSE == true 时 调用此类
 */

namespace Cgy\response\exporter;

use Cgy\response\Exporter;

class Pause extends Exporter 
{
    public $contentType = "text/plain; charset=utf-8";

    //准备输出的数据
    public function prepare()
    {
        $this->content = "Website Paused!";
        return $this;
    }
}