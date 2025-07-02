<?php
/**
 * resper 框架 Resper config 类
 * 自定义 Resper 响应者类 应使用此类作为 参数配置器
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

class ResperConfig extends BaseConfig 
{
    /**
     * 定义统一的 Resper 类预设数据结构
     * !! 子类可以覆盖
     */
    protected $init = [
        
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