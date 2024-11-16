<?php
/**
 * cgyio/resper Response 输出类
 * Dump debug 模式下 直接输出 var_dump
 */

namespace Cgy\response\exporter;

use Cgy\response\Exporter;
use Cgy\Response;

class Dump extends Exporter
{
    //准备输出的数据
    public function prepare()
    {
        return $this;
    }

    //改写 parent->export() 方法
    public function export()
    {
        print("<pre>".print_r($this->data, true)."</pre>");
        exit;
    }
}