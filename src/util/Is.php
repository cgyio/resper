<?php
/**
 * cgyio/resper 工具类
 * Is 类型判断
 */

namespace Cgy\util;

use Cgy\Util;
use Cgy\util\Conv;

class Is extends Util 
{

    /**
     * 判断 $any 是否是 types 类型 (其中的某一个 / 全部)
     * @param Boolean $all 是否要全部符合
     * @param Mixed $any 要判断类型的数据
     * @param String $types Is类支持判断的 类型名称  or  系统方法 is_****()
     * @return Mixed true or false or 类型名
     * 仅在 $all==false 时 返回第一个符合的 类型名
     */
    protected static function chk($all, $any, ...$types)
    {
        $flag = $all;
        if (count($types)==1 && strpos($types[0], ",")!==false) {
            $types = explode(",", $types[0]);
        }
        $type = null;
        for ($i=0;$i<count($types);$i++) {
            $tpi = trim($types[$i]);
            $flagi = false;
            $m = strtolower($tpi);
            if (method_exists(self::class, $m)) {
                $flagi = self::$m($any);
            } else if (function_exists("is_".$m)) {
                $func = "is_".$m;
                $flagi = $func($any);
            } else {
                continue;
            }
            if ($flagi===true) $type = $tpi;
            if ($all) {
                $flag = $flag && $flagi;
            } else {
                $flag = $flag || $flagi;
            }
            if ($flag===!$all) {
                break;
            }
        }
        if ($all) return $flag;
        return $flag===true ? $type : $flag;
    }

    /**
     * 判断 $any 是否是 types 类型中的某一个
     * @param Mixed $any 要判断类型的数据
     * @param String $types Is类支持判断的 类型名称
     * @return Mixed false or 第一个符合的类型名
     */
    public static function any($any, ...$types)
    {
        return self::chk(false, $any, ...$types);
    }

    /**
     * 判断 $any 是否是 types 类型 全部符合
     * @param Mixed $any 要判断类型的数据
     * @param String $types Is类支持判断的 类型名称
     * @return Bool 
     */
    public static function all($any, ...$types)
    {
        return self::chk(true, $any, ...$types);
    }


    /**
     * 判断 array 类型
     */

    /**
     * 是否有序数组 [foo, bar, ... ]
     * @param Mixed $var 
     * @return Bool
     */
    public static function indexed($var = null)
    {
        if (!is_array($var)) return false;
        if (empty($var)) return true;
        return is_numeric(implode("", array_keys($var)));
    }

    /**
     * 是否关联数组 [ "foo"=>"bar", ... ]
     * @param Mixed $var 
     * @param Bool $allStr 是否要求所有 key 都不能是 numeric
     * @return Bool
     */
    public static function associate($var = null, $allStr = false)
    {
        if (!is_array($var)) return false;
        if (!$allStr) return !self::indexed($var);
        foreach ($var as $key => $value) {
            if (!is_string($key) || is_numeric($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 数组是否 一维数组
     * @param Mixed $var 
     * @return Bool
     */
    public static function onedimension($var = null)
    {
        if (!is_array($var)) return false;
        foreach ($var as $k => $v) {
            if (is_array($v) || is_object($v)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 是否 非空 array
     * @param Mixed $var 
     * @return Bool
     */
    public static function nemarr($var = null)
    {
        return is_array($var) && !empty($var);
    }


    /**
     * 判断 string 类型
     */
    
    /**
     * 是否 非空 string
     * @param Mixed $var 
     * @return Bool
     */
    public static function nemstr($var = null)
    {
        return is_string($var) && !empty($var);
    }

    /**
     * 大小写判断
     * @param Mixed $var 
     * @return Bool
     */
    public static function lower($var = "")
    {
        if (!self::nemstr($var)) return false;
        return strtolower($var) === $var;
    }
    public static function upper($var = "")
    {
        if (!self::nemstr($var)) return false;
        return strtoupper($var) === $var;
    }
    public static function lower_upper($var = "")
    {
        if (!self::nemstr($var)) return false;
        return !self::lower($var) && !self::upper($var);
    }

    /**
     * 是否 queryString
     * @param Mixed $var 
     * @return Bool
     */
    public static function query($var = null)
    {
        if (!self::nemstr($var)) return false;
        if (false === strpos($var, "&")) {
            if (false === strpos($var, "=")) {
                return false;
            } else {
                $sarr = explode("=", $var);
                return count($sarr) == 2;
            }
        } else {
            $sarr = explode("&", $var);
            $rst = true;
            for ($i=0; $i<count($sarr); $i++) {
                if (false === self::query($sarr[$i])){
                    $rst = false;
                    break;
                }
            }
            return $rst;
        }
    }

    /**
     * 是否特殊字符串 null,true,false
     * @param Mixed $var 
     * @return Bool
     */
    public static function ntf($var = null)
    {
        return is_string($var) && in_array(strtolower($var), ["null","true","false"]);
    }

    /**
     * 是否可以被 explode
     * @param Mixed $var 
     * @param String $split 指定分隔符
     * @return Mixed
     * 指定 $split 后 返回 Boolean
     * 不指定 $split 则 返回可用于 explode 的分隔符，没有分隔符的 返回 false
     */
    public static function explodable($var = null, $split = null)
    {
        if (!self::nemstr($var)) return false;
        $splits = ["/","\\",DS,",","|",";",".","&"];
        if (is_null($split)) {
            $scount = 0;
            $sp = null;
            foreach ($splits as $k => $v) {
                if (substr_count($var, $v) > $scount) {
                    $scount = substr_count($var, $v);
                    $sp = $v;
                    //return $v;
                }
            }
            if (!is_null($sp)) return $sp;
            return false;
        } else {
            return substr_count($var, $split) > 0;
        }
    }

    /**
     * 是否 合法 json 字符串
     * @param Mixed $var 
     * @return Bool
     */
    public static function json($var = null)
    {
        if (!self::nemstr($var)) {
            return false;
        } else {
            $jd = json_decode($var);
            if (is_null($jd)) return false;
            return json_last_error() == JSON_ERROR_NONE;
        }
    }

    /**
     * 是否 合法 xml 字符串
     * @param Mixed $var 
     * @return Bool
     */
    public static function xml($var = null)
    {
        if (!self::nemstr($var)) return false;
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($var, 'SimpleXMLElement', LIBXML_NOCDATA);
        return $xml !== false;
    }

    /**
     * 是否 合法的远程资源 url
     * @param Mixed $var 
     * @return Bool
     */
    public static function remote($file = null)
    {
        if (!self::nemstr($file)) return false;
        return false !== strpos($file, "://");
    }


    /**
     * 其他判断
     */

    /**
     * 带 类型 的 非空判断
     * @param Mixed $var
     * @param String $types Is 支持的 类型  or  系统函数 is_****()
     * @return Bool
     */
    public static function nem($var, ...$types)
    {
        if (empty($var)) return false;
        return self::any($var, ...$types);
    }

    /**
     * 判断两个变量是否相等
     * @param Mixed $a
     * @param Mixed $b
     * @return Bool
     */
    public static function eq($a, $b)
    {
        if (is_null($a)) return is_null($b);
        if (is_bool($a)) return $a ? $b==true : $b==false;
        if (is_numeric($a)) {
            if (!is_numeric($b)) return false;
            $a = (string)$a;
            $b = (string)$b;
            return bccomp($a, $b) == 0;
        }
        if (is_string($a)) {
            if (!is_string($b)) return false;
            if (self::ntf($a)) return self::ntf($b) ? strtolower($a)==strtolower($b) : false;
            if (self::json($a)) {
                if (!self::json($b)) return false;
                return self::eq(Conv::j2a($a), Conv::j2a($b));
            }
            return $a == $b;
        }
        if (is_array($a)) {
            if (!is_array($b)) return false;
            if (empty($a)) return empty($b);
            if (self::indexed($a)) {
                if (!self::indexed($b)) return false;
                if (count($a)!=count($b)) return false;
                return empty(array_diff($a, $b));
            }
            if (self::associate($a)) {
                if (!self::associate($b)) return false;
                $ksa = array_keys($a);
                $ksb = array_keys($b);
                if (!self::eq($ksa, $ksb)) return false;
                foreach ($ksa as $k) {
                    if (!self::eq($a[$k], $b[$k])) return false;
                }
                return true;
            }
            return $a == $b;
        }
        if (empty($a)) return empty($b);

        return $a === $b;
    }

    /**
     * 判断变量是否已定义
     * 当 var==null 时 isset(var) == false，但是 Is::set(var) == true
     * @param Mixed $var 
     * @return Bool
     */
    public static function set($var) 
    {
        return isset($var) || is_null($var);
    }

    /**
     * 判断 数组 中 是否包含 key
     * 部分情况下代替 isset($var[$key])
     * @param Mixed $var 
     * @return Bool
     */
    public static function def($var, $key)
    {
        if (!self::nemarr($var)) return false;
        return in_array($key, array_keys($var));
    }
}