<?php
/**
 * cgyio/resper 工具类
 * Cls 类操作工具
 */

namespace Cgy\util;

use Cgy\Util;
use Cgy\util\Is;
use Cgy\util\Str;
use Cgy\util\Arr;

class Cls extends Util 
{

    /**
     * 获取 类全称
     * foo/bar  -->  NS\foo\Bar
     * @param String $path      full class name
     * @param String $ns        namespace 前缀 默认使用常量 NS
     * @return Class            not found return null
     */
    public static function find($path = "", $ns = null)
    {
        if (!Is::nemstr($path) && !Is::nemarr($path)) return null;
        $ns = !Is::nemstr($ns) ? (defined("NS") ? NS : "\\Cgy\\") : strtoupper($ns);
        $ps = Is::nemstr($path) ? explode(",", $path) : $path;
        $cl = null;
        for ($i=0; $i<count($ps); $i++) {
            //先判断一下
            if (class_exists($ps[$i])) {
                $cl = $ps[$i];
                break;
            }

            $pi = trim($ps[$i], "/");
            $pia = explode("/", $pi);
            $pin = $pia[count($pia)-1];
            if (!Str::beginUp($pin)) {
                $pia[count($pia)-1] = ucfirst($pin);
            }
            $cls = $ns . implode("\\", $pia);
            //var_dump($cls);
            if (class_exists($cls)) {
                $cl = $cls;
                break;
            }
        }
        return $cl;
    }

    /**
     * 生成 类全称前缀
     * foo/bar  -->  NS\foo\bar\
     * @param String $path
     * @return String
     */
    public static function pre($path = "")
    {
        $path = trim($path, "/");
        return NS . str_replace("/","\\", $path) . "\\";
    }

    /**
     * 获取不包含 namespace 前缀的 类名称
     * NS\foo\bar  -->  bar
     * @param Object $obj 类实例
     * @return String
     */
    public static function name($obj)
    {
        try {
            $cls = get_class($obj);
            $carr = explode("\\", $cls);
            return array_pop($carr);
        } catch(Exception $e) {
            return null;
        }
    }
    
    /**
     * 取得 ReflectionClass
     * @param String $cls 类全称
     * @return ReflectionClass instance
     */
    public static function ref($cls)
    {
        if (!is_string($cls)) {
            if (is_object($cls)) {
                $cls = get_class($cls);
            } else {
                return null;
            }
        }
        if (!class_exists($cls)) return null;
        return new \ReflectionClass($cls);
    }
    
    /**
     * method/property filter 简写
     * ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC 简写为 'static,is_public'
     * @param String $filter 简写后的 filter
     * @param String $type 区分 ReflectionMethod / ReflectionProperty / ReflectionClassConstant ... 默认 ReflectionMethod
     * @return Int 完整的 filter
     */
    public static function filter($filter=null, $type="method")
    {
        if (is_null($filter) || $filter=="") return null;
        $fs = explode(",", $filter);
        $fs = array_map(function($i) {
            $j = strtolower(trim($i));
            if (substr($j, 0,3)!="is_") $j = "is_".$j;
            return strtoupper($j);
        }, $fs);
        $ff = array_shift($fs);
        $fp = "Reflection".ucfirst($type);
        $filter = constant($fp."::$ff");
        if (empty($fs)) return $filter;
        for ($i=0;$i<count($fs);$i++) {
            $fi = $fs[$i];
            $filter = $filter | constant($fp."::$fi");
        }
        return $filter;
    }
    
    /**
     * 获取 类 中的所有(符合条件) method 
     * 返回 ReflectionMethod 实例数组
     * @param String $cls 类全称
     * @param String $filter 过滤方法，默认 null
     *      可选： 'static / public / protected / private / abstract / final'
     *      多个条件之间以 , 连接：'static,public'
     * @param Closure $condition 条件判断函数，参数为 ReflectionMethod 实例，返回 Bool
     * @return Array [ ReflectionMethod Instance, ... ]
     */
    public static function methods($cls, $filter=null, $condition=null)
    {
        $ref = self::ref($cls);
        $filter = self::filter($filter);
        $ms = $ref->getMethods($filter);
        if (is_callable($condition)) {
            $ns = array_filter($ms, $condition);
            return $ns;
        }
        return $ms;
    }

    /**
     * 获取 类 中的所有(符合条件) method 
     * 返回 方法名称 数组
     * @param String $cls 类全称
     * @param String $filter 过滤方法，默认 null
     *      可选： 'static / public / protected / private / abstract / final'
     *      多个条件之间以 , 连接：'static,public'
     * @param Closure $condition 条件判断函数，参数为 ReflectionMethod 实例，返回 Bool
     * @return Array [ method name, ... ]
     */
    public static function methodNames($cls, $filter=null, $condition=null)
    {
        $ms = self::methods($cls, $filter, $condition);
        $ns = array_map(function($i) {
            return $i->name;
        }, $ms);
        return $ns;
    }
    
    /**
     * 检查 类 中 是否包含方法
     * @param String $cls 类全称
     * @param String $method 要检查的方法名
     * @param String $filter 过滤方法，默认 null
     * @param Closure $condition 条件判断函数，参数为 ReflectionMethod 实例，返回 Bool
     * @return Bool
     */
    public static function hasMethod($cls, $method, $filter=null, $condition=null)
    {
        $ms = self::methodNames($cls, $filter, $condition);
        return in_array($method, $ms);
    }
    
    /**
     * 获取 类 中的所有 property 
     * 返回 ReflectionProperty 实例数组
     * @param String $cls 类全称
     * @param String $filter 过滤方法，默认 null
     *      可选： 'static / public / protected / private / abstract / final'
     *      多个条件之间以 , 连接：'static,public'
     * @param Closure $condition 条件判断函数，参数为 ReflectionProperty 实例，返回 Bool
     * @return Array [ ReflectionProperty Instance, ... ]
     */
    public static function properties($cls, $filter=null, $condition=null)
    {
        $ref = self::ref($cls);
        $filter = self::filter($filter, "property");
        $ps = $ref->getProperties($filter);
        if (is_callable($condition)) {
            $ns = array_filter($ms, $condition);
            return $ns;
        }
        return $ps;
    }

    /**
     * 获取 类 中的所有 property 
     * 返回 属性名 数组
     * @param String $cls 类全称
     * @param String $filter 过滤方法，默认 null
     *      可选： 'static / public / protected / private / abstract / final'
     *      多个条件之间以 , 连接：'static,public'
     * @param Closure $condition 条件判断函数，参数为 ReflectionProperty 实例，返回 Bool
     * @return Array [ property name, property name, ... ]
     */
    public static function propertyNames($cls, $filter=null, $condition=null)
    {
        $ps = self::properties($cls, $filter, $condition);
        $ns = array_map(function($i) {
            return $i->name;
        }, $ps);
        return $ns;
    }
    
    /**
     * 检查 类 中 是否包含属性
     * @param String $cls 类全称
     * @param String $property 要检查的属性名
     * @param String $filter 过滤方法，默认 null
     * @param Closure $condition 条件判断函数，参数为 ReflectionMethod 实例，返回 Bool
     * @return Bool
     */
    public static function hasProperty($cls, $property, $filter=null, $condition=null)
    {
        $ps = self::propertyNames($cls, $filter, $condition);
        //var_dump($ps);
        return in_array($property, $ps);
    }
    
}