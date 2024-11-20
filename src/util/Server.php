<?php
/**
 * cgyio/resper 工具类
 * $_SERVER 工具
 */

namespace Cgy\util;

use Cgy\Util;
use Cgy\util\Is;
use Cgy\util\Str;

class Server extends Util 
{

    /**
     * 读取 $_SERVER
     * @param String $key
     * @param Mixed $dft
     * @return String  or  null
     */
    public static function get($key, $dft = null)
    {
        if (self::has($key)!==true) return $dft;
        $key = self::key($key);
        return $_SERVER[$key];
    }

    /**
     * 判断 $_SERVER 是否包含
     * @param String $key
     * @return Bool
     */
    public static function has($key)
    {
        $key = self::key($key);
        return isset($_SERVER[$key]);
    }

    /**
     * 按前缀获取 $_SERVER 数据
     * Server::pre("http") = [
     *      "Host" => "...",
     *      "Accept-Language" => "...",
     *      ...
     * ]
     * @param String $pre 前缀名 foo  -->  返回所有 $_SERVER["FOO_***"]
     * @return Array
     */
    public static function pre($pre = "")
    {
        if (!Is::nemstr($pre)) return [];
        $pre = strtoupper($pre);
        if ("_" !== substr($pre, -1)) $pre .= "_";
        $serv = $_SERVER;
        $arr = [];
        foreach ($serv as $k => $v) {
            if ($pre !== substr($k, 0, strlen($pre))) continue;
            $kk = strtolower(substr($k, strlen($pre)));
            $kk = ucwords(str_replace("_", " ", $kk));
            $kk = str_replace(" ", "-", $kk);
            $arr[$kk] = $v;
        }
        return $arr;
    }

    /**
     * $_SERVER 键名处理
     * foo-bar  -->  FOO_BAR
     * fooBar  -->  FOO_BAR
     * @param String $key
     * @return String 
     */
    public static function key($key)
    {
        $key = Str::snake($key, "_");
        $key = Str::replace("-", "_", $key);
        $key = strtoupper($key);
        return $key;
    }
    
}