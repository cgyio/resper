<?php
/**
 * cgyio/resper 工具类
 * $_SESSION 处理
 */

namespace Cgy\util;

use Cgy\Util;

class Session extends Util 
{

    //写入
    public static function set(
        $key = [],
        $val = ""
    ) {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $_SESSION[$k] = $v;
            }
        }else{
            $_SESSION[$key] = $val;
        }
    }

    //读取
    public static function get(
        $key, 
        $dft = null
    ) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $dft;
    }

    //删除
    public static function del(
        $key
    ) {
        if (isset($_SESSION[$key])) {
            $_SESSION[$key] = null;
            unset($_SESSION[$key]);
        }
    }

    //判断
    public static function has(
        $key
    ) {
        return isset($_SESSION[$key]) && $_SESSION[$key]!=null;
    }

}