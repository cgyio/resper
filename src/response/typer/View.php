<?php
/**
 * resper 框架 输出类型处理类
 * 输出类型：view 输出视图 (html 页面)
 */

namespace Cgy\response\typer;

use Cgy\response\Typer;
use Cgy\response\Exporter;

class View extends Typer 
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