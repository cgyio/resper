<?php
/**
 * resper 框架 输出类型处理类
 * 输出类型：src 输出 resource 资源类型数据，如：image/video/audio/stream/js/css/vue/ ...
 */

namespace Cgy\response\typer;

use Cgy\response\Typer;
use Cgy\response\Exporter;

class Src extends Typer 
{
    
    /**
     * 初始化处理
     * !! 子类必须覆盖
     * @return $this
     */
    public function initType()
    {
        
    }
}