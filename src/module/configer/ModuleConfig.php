<?php
/**
 * resper 框架 Module config 基类
 * 任意 Module 类的 config 类继承自此类
 */

namespace Cgy\module\configer;

use Cgy\Resper;
use Cgy\module\configer\BaseConfig;
use Cgy\orm\Driver;
use Cgy\orm\Model;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Is;
use Cgy\util\Path;

class ModuleConfig extends BaseConfig 
{
    /**
     * 定义统一的 Module 类预设数据结构
     * !! 子类可以覆盖
     */
    protected $init = [
        "name" => "App应用类名称",
    ];

    /**
     * 在 应用用户设置后 执行
     * !! 子类可覆盖
     * @return $this
     */
    public function afterSetConf()
    {

        return $this;
    }

}