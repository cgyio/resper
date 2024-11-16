<?php
/**
 * cgyio/resper 核心类 
 * App 类
 */

namespace Cgy;

use Cgy\Resper;
use Cgy\resper\Responder;
use Cgy\util\Str;

class App extends Responder
{



    /**
     * static tools
     */

    /**
     * 判断是否存在 app
     * @param String $app app 名称
     * @return Mixed 找到则返回 app类全称，未找到则返回 false
     */
    public static function has($app)
    {
        $appcln = Str::beginUp($app) ? $app : ucfirst($app);
        $appcls = Resper::cls("App/$appcln");
        if (empty($appcls)) return false;
        return $appcls;
    }
}