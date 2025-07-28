<?php
/**
 * resper 框架 输出类型处理类
 * 输出类型：api 作为后端服务接口，输出数据到前端，通常输出 json 数据
 */

namespace Cgy\response\typer;

use Cgy\response\Typer;
use Cgy\response\Exporter;
use Cgy\Request;

class Api extends Typer 
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