<?php
/**
 * resper 框架 模型实例工具类 基类
 * 可以在模型实例中使用的工具类
 * 必须传入 当前模型实例(record) 作为依赖项
 */

namespace Cgy\orm\model;

use Cgy\orm\Model;

class Util 
{
    //关联的 当前模型实例
    public $record = null;

    /**
     * 构造
     */
    public function __construct($record)
    {
        if (!$record instanceof Model) return null;
        $this->record = $record;
        
        //初始化方法
        $this->initUtil();
    }

    /**
     * !! 子类必须覆盖
     * 工具类初始化方法
     * @return $this
     */
    protected function initUtil()
    {
        //子类实现

        return $this;
    }
}