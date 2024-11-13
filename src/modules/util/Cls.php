<?php
/**
 * cgyio/resper 工具类
 * Cls 类操作工具
 */

namespace Cgy\util;

use Cgy\Util;

class Cls extends Util 
{
    
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
     * @return Bool
     */
    public static function hasMethod($cls, $method, $filter=null)
    {
        $ms = self::methodNames($cls, $filter);
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
     * @return Bool
     */
    public static function hasProperty($cls, $property, $filter=null)
    {
        $ps = self::propertyNames($cls, $filter);
        return in_array($property, $ps);
    }
    
}