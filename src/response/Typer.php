<?php
/**
 * resper 框架 输出类型处理类
 * 抽象基类
 */

namespace Cgy\response;

use Cgy\response\Exporter;
use Cgy\util\Is;
use Cgy\util\Str;
use Cgy\util\Arr;
use Cgy\util\Path;

abstract class Typer 
{
    /**
     * 依赖
     */
    //Exporter 实例
    public $exporter = null;

    /**
     * 构造
     * @param Exporter $exporter
     * @return void
     */
    public function __construct($exporter)
    {
        $this->exporter = $exporter;
    }

    /**
     * 初始化处理
     * !! 子类必须覆盖
     * @return $this
     */
    abstract public function initType();
}