<?php
/**
 * cgyio/resper 模块类
 * Module 模块基类
 */

namespace Cgy;

use Cgy\Resper;
use Cgy\resper\Responder;
use Cgy\util\Str;

class Module extends Responder
{


    /**
     * static tools
     */

    /**
     * 判断是否存在 模块
     * @param String $md 模块名称
     * @return Mixed 找到则返回 模块类全称，否则返回 false
     */
    public static function has($md)
    {
        $mdcln = Str::beginUp($md) ? $md : ucfirst($md);
        $mdf = MODULE_PATH.DS.$mdcln.EXT;
        $mdcls = Resper::cls($mdcln);
        if (empty($mdcls)) return false;
        if (!file_exists($mdf)) return false;
        return $mdcls;
    }
    
}