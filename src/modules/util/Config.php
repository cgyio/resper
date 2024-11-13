<?php
/**
 * cgyio/resper 框架 modules 模块设置类
 * modules/util 预设
 */

namespace Cgy\util;

use Cgy\Configer;

class Config extends Configer 
{
    /**
     * 预设的设置参数
     * !! 子类自定义
     */
    protected $init = [
        "utils" => "is,arr,str,path,conv"
    ];
}
