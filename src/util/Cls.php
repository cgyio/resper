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
        $ns = !Is::nemstr($ns) ? (defined("NS") ? NS : "Cgy\\") : $ns;
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
            $pin = array_pop($pia);
            //类名称 统一转为 驼峰，首字母大写 格式
            $pin= Str::camel($pin, true);
            $pia[] = $pin;
            //拼接 类全称
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
     * @param Object|String $obj 类实例 或 类全称
     * @return String
     */
    public static function name($obj)
    {
        if (Is::nemstr($obj)) {
            $oarr = explode("//", $obj);
            return array_pop($oarr);
        }
        if (is_object($obj)) {
            try {
                $cls = get_class($obj);
                $carr = explode("\\", $cls);
                return array_pop($carr);
            } catch(Exception $e) {
                return null;
            }
        }
        return null;
    }
    
    /**
     * 取得 ReflectionClass
     * @param String|Object $cls 类全称 或 类实例
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
     * !! 注意：筛选条件是 或 关系
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
     * 处理 filter 条件中的 &*** 条件，这些条件采用 与关系，如：public,&!static,&final
     * !! 此方法必须在 self::filter() 方法之前执行，此方法将会返回 filter 方法的第一个参数
     * @param String $filter method/property filter 筛选条件字符串
     * @return Array 返回处理结果，例如：public,&!static,&final 处理后的结果为：
     *  [
     *      "filter" => "public",   //用于 self::filter() 方法的第一个参数
     *      "fn" => function($mi) { //返回一个筛选方法，对通过 filter 条件筛选得到的 method/property 数组进行 再次筛选
     *          return $mi->isStatic()!==true && $mi->isFinal()===true;
     *      }
     *  ]
     */
    public static function filterAnd($filter=null)
    {
        //空值
        $rtn = [
            "filter" => null,
            "fn" => null
        ];
        //判断参数合法
        if (!Is::nemstr($filter)) return $rtn;
        //提取 或关系|与关系 筛选条件
        $farr = explode(",", $filter);
        $or = [];
        $and = [];
        foreach ($farr as $i => $v) {
            if (substr($v, 0,1)=="&") {
                $and[] = substr($v, 1);
            } else {
                $or[] = $v;
            }
        }
        //或关系的筛选条件，转为 self::filter() 方法的参数
        if (Is::nemarr($or)) $rtn["filter"] = implode(",",$or);
        //与关系的筛选条件，转为生成一个筛选函数
        if (Is::nemarr($and)) {
            $rtn["fn"] = function($mi) use ($and) {
                $flag = true;
                foreach ($and as $i => $v) {
                    $rev = false;
                    if (substr($v, 0,1)==="!") {
                        //针对 !static 这种形式
                        $rev = true;
                        $v = substr($v, 1);
                    }
                    $ism = "is".ucfirst(strtolower($v));
                    if (!method_exists($mi, $ism)) continue;
                    $flag = $flag && ($mi->$ism()===!$rev);
                }
                return $flag;
            };
        }
        //返回处理结果
        return $rtn;
    }
    
    /**
     * 获取 类 中的所有(符合条件) method 
     * 返回 ReflectionMethod 实例数组
     * @param String|Object $cls 类全称 或 类实例
     * @param String $filter 过滤方法，默认 null，形式例如：public,&!static,&final
     * @param Closure $condition 条件判断函数，参数为 ReflectionMethod 实例，返回 Bool
     * @return Array [ ReflectionMethod Instance, ... ]
     */
    public static function methods($cls, $filter=null, $condition=null)
    {
        //取得反射类
        $ref = self::ref($cls);
        //先处理 与关系 筛选条件
        $ftr = self::filterAnd($filter);
        //再处理 或关系 筛选条件
        $filter = self::filter($ftr["filter"]);
        //开始筛选
        //先用 或关系 筛选条件 筛选 method
        $ms = $ref->getMethods($filter);
        //再用 与关系 筛选条件 对选中的 method 数组进行再次筛选
        if (is_callable($ftr["fn"])) {
            //如果有筛选方法
            $ms = array_filter($ms, $ftr["fn"]);
        }
        //最后再用 用户自定义的 筛选方法 对结果进行筛选
        if (is_callable($condition)) {
            $ns = array_filter($ms, $condition);
            return $ns;
        }
        return $ms;
    }

    /**
     * 获取 类 中的所有(符合条件) method 
     * 返回 方法名称 数组
     * @param String|Object $cls 类全称 或 类实例
     * @param String $filter 过滤方法，默认 null，形式例如：public,&!static,&final
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
     * @param String|Object $cls 类全称 或 类实例
     * @param String $method 要检查的方法名
     * @param String $filter 过滤方法，默认 null，形式例如：public,&!static,&final
     * @param Closure $condition 条件判断函数，参数为 ReflectionMethod 实例，返回 Bool
     * @return Bool
     */
    public static function hasMethod($cls, $method, $filter=null, $condition=null)
    {
        $ms = self::methodNames($cls, $filter, $condition);
        //方法名格式 驼峰，首字母小写
        $method = Str::camel($method, false);
        return in_array($method, $ms);
    }
    
    /**
     * 获取 类 中的所有 property 
     * 返回 ReflectionProperty 实例数组
     * @param String|Object $cls 类全称 或 类实例
     * @param String $filter 过滤方法，默认 null，形式例如：public,&!static,&final
     * @param Closure $condition 条件判断函数，参数为 ReflectionProperty 实例，返回 Bool
     * @return Array [ ReflectionProperty Instance, ... ]
     */
    public static function properties($cls, $filter=null, $condition=null)
    {
        //取得反射类
        $ref = self::ref($cls);
        //先处理 与关系 筛选条件
        $ftr = self::filterAnd($filter);
        //再处理 或关系 筛选条件
        $filter = self::filter($ftr["filter"], "property");
        //开始筛选
        //先用 或关系 筛选条件 筛选 property
        $ps = $ref->getProperties($filter);
        //再用 与关系 筛选条件 对选中的 property 数组进行再次筛选
        if (is_callable($ftr["fn"])) {
            //如果有筛选方法
            $ps = array_filter($ps, $ftr["fn"]);
        }
        //最后再用 用户自定义的 筛选方法 对结果进行筛选
        if (is_callable($condition)) {
            $ns = array_filter($ps, $condition);
            return $ns;
        }
        return $ps;
    }

    /**
     * 获取 类 中的所有 property 
     * 返回 属性名 数组
     * @param String|Object $cls 类全称 或 类实例
     * @param String $filter 过滤方法，默认 null，形式例如：public,&!static,&final
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
     * @param String|Object $cls 类全称 或 类实例
     * @param String $property 要检查的属性名
     * @param String $filter 过滤方法，默认 null，形式例如：public,&!static,&final
     * @param Closure $condition 条件判断函数，参数为 ReflectionMethod 实例，返回 Bool
     * @return Bool
     */
    public static function hasProperty($cls, $property, $filter=null, $condition=null)
    {
        $ps = self::propertyNames($cls, $filter, $condition);
        //var_dump($ps);
        //属性名格式 驼峰，首字母小写
        $property = Str::camel($property, false);
        return in_array($property, $ps);
    }

    /**
     * 查找类中的特殊方法，区分方法的类型依据的是 在注释中存在指定的 字符，如： api|getter|resper|...
     * 提取出这些方法，读取方法注释，获取方法信息，最终返回 [ method=> [信息], ... ]
     * @param String|Object $cls 类全称 或 类实例
     * @param String $filter 过滤方法，默认 null，形式例如：public,&!static,&final
     * @param String $key 方法类型，api|getter|resper|... 表示方法注释中必须包含 * api|* getter|* resper|...
     * @param Closure $condition 额外的条件判断函数，参数为 ReflectionMethod 实例，返回 Bool
     * @param Closure $process 额外的信息处理函数，
     *                  !! 参数为 ReflectionMethod 实例 和 已经解析出的方法信息数组，返回 新的方法信息数组
     * @return Array 以方法名(全小写，下划线_)为键，方法信息为值的 数组
     *  [
     *      "foo_bar" => [
     *          "name" => "fooBar",
     *          "title" => "方法标题",
     *          "desc" => "方法说明",
     *          "auth" => true,
     *          ...
     *      ],
     *      ...
     *  ]
     */
    public static function specific($cls, $filter=null, $key="api", $condition=null, $process=null)
    {
        //传入的 类全称 或 类实例 统一为 类全称
        $clsn = is_object($cls) ? get_class($cls) : (Is::nemstr($cls) ? $cls : null);
        if (is_null($clsn)) return [];

        //key 转为 首字母大写形式
        $ukey = ucfirst(strtolower($key));
        //是否 必须包含对应后缀，api|getter|proxy 类型的方法，方法名中必须包含 Api|Getter|Proxy 后缀
        $suffix = in_array(strtolower($key), ["api", "getter", "proxy"]);
        //获取 $cls 类中 符合条件的 特殊方法
        $ms = self::methods($cls, $filter, function($mi) use ($key, $ukey, $suffix, $condition) {
            if ($suffix) {
                //必须包含后缀
                if (substr($mi->name, strlen($key)*-1)!==$ukey) return false;
            }
            //获取方法注释
            $doc = $mi->getDocComment();
            //方法必须包含 注释 * $key
            if (strpos($doc, "* ".$key)===false && strpos($doc, "* ".$ukey)===false) return false;
            //使用自定义的 额外筛选条件
            if (is_callable($condition)) return $condition($mi);
            //默认
            return true;
        });
        //读取 符合条件的 特殊方法 的信息，从注释中提取信息
        $info = [];
        if (!empty($ms)) {
            foreach ($ms as $i => $mi) {
                $doc = $mi->getDocComment();
                $conf = self::parseComment($doc);
                $name = $conf["name"] ?? "";
                if (!Is::nemstr($name)) {
                    $name = $mi->name;
                    //去除方法名后缀
                    if ($suffix) $name = str_replace($ukey,"",$name);
                    $conf["name"] = $name;
                }
                $mk = Str::snake($name, "_");   //驼峰形式 方法名 转为 全小写，下划线_ 形式的 键名
                //键名保存到 方法信息中
                $conf["name"] = $mk;
                //保存完整的方法名
                $conf["method"] = $mi->name;
                //保存完整的 类全称
                $conf["class"] = $clsn;
                //使用自定义处理方法 处理方法信息数组
                if (is_callable($process)) {
                    $conf = $process($mi, $conf);
                }
                //保存到 info 中
                $info[$conf["name"]] = $conf;
            }
        }
        //返回获取到的 方法信息数组
        return $info;
    }

    /**
     * 块状注释 解析，形式为：
     * 
     * api
     * @name apiName
     * @title 方法标题
     * @desc 方法说明
     * @auth false|true 不启用|启用 权限控制
     * @foo ...
     * ...
     * 
     * 将注释中的方法信息，解析为 []
     * 
     * @param String $comment 注释字符串
     * @param Bool $full 是否在返回的信息数组中包含 __str, __desc 等带有 __ 前缀的项目，默认 false
     * @return Array 包含所有信息的 关联数组
     */
    public static function parseComment($comment=null, $full=false)
    {
        if (!Is::nemstr($comment) || strpos($comment, "/*")===false || strpos($comment, "*/")===false) {
            //不是有效的 块状注释 字符串
            return [];
        }
        //统一换行符
        $comment = str_replace(["\r\n", "\r", "\n"],"\n",$comment);
        //按行解析
        $rows = explode("\n", $comment);
        //结果
        $info = [
            //不带 * @ 前缀的 注释行字符串
            "__str" => [],
            //对带 @ 前缀的项目的 说明文字，形式为： * @foo value 这里是说明文字
            "__desc" => [
                //"foo" => "这里是说明文字"
            ],
            //方法信息必须包含这些项
            "name" => "",
            "title" => "",
            "desc" => "",
            "auth" => true,
        ];

        for ($i=0;$i<count($rows);$i++) {
            $row = $rows[$i];
            //去除头尾空格
            $row = trim($row);
            //去除 头尾
            if (substr($row, 0, 2)=="/*" || substr($row, -2)=="*/" || substr($row, 0,1)!="*") continue;
            //将多个空格 替换为 单个空格
            $row = preg_replace("/\s+/"," ",$row);
            //不带 * @ 前缀的
            if (substr($row, 0, 3)!=="* @") {
                $info["__str"][] = trim(substr($row, 1));
                continue;
            }
            //带 * @ 前缀的 信息项目
            $ra = explode("@", $row);
            if (!Is::nemstr($ra[1])) continue;
            //单个空格 分割
            $ra = explode(" ", $ra[1]);
            $ik = $ra[0];
            //@param, @return 项目不处理
            if (in_array(strtolower($ik), ["param","return"])) continue;
            //获取信息项目值
            $iv = count($ra)<2 ? null : $ra[1];
            //null,true,false 字符串转为对应值
            if (Is::ntf($iv)) {
                eval("\$iv = ".$iv.";");
            }
            $info[$ik] = $iv;
            //如果信息项目带有说明文字
            if (count($ra)>2) {
                $info["__desc"][$ik] = implode(" ", array_slice($ra, 2));
            }
        }
        //返回解析结果
        if ($full!==true) {
            foreach ($info as $k => $v) {
                if (substr($k, 0,2)==="__") unset($info[$k]);
            }
        }
        return $info;
    }

    /**
     * 对 通过解析注释内容 得到的 方法信息，进一步处理 uac 权限控制相关信息
     * 在最终生成的 方法信息数组中，添加权限控制相关的 信息项目
     * @param ReflectionMethod $mi 反射的方法实例
     * @param Array $conf 通过解析注释内容得到的 方法信息数组
     * @param String $oprpre 操作标识前缀
     * @param String $oprtit 操作标识的说明内容前缀
     * @return Array 最终生成的 方法信息数组
     */
    public static function parseMethodInfoWithUac($mi, $conf=[], $oprpre=null, $oprtit=null)
    {
        //方法的信息数组中 必须包含这些项目
        $dftc = [
            "name" => "",       //方法 key 全小写，下划线_
            "method" => "",     //实际方法名 驼峰形式 首字母小写
            "auth" => true,     //是否启用 uac 权限控制，默认开启
            "role" => "all",    //可手动定义 拥有权限的 用户角色，逗号隔开，默认 all
            "oprn" => "",       //此 可用响应方法 的 操作标识，用于权限控制
            "title" => "",      //可用响应方法 中文名
            "desc" => "",       //可用响应方法 功能说明
        ];

        //uac 相关
        $auth = $conf["auth"] ?? true;
        $conf["auth"] = $auth;
        if ($auth===true) {
            //启用 uac 控制
            $role = $conf["role"] ?? "all";
            //指定了允许访问的 用户角色
            if ($role!="all") $role = Arr::mk($role);
            $conf["role"] = $role;
        }
        
        //生成 操作标识
        $conf["oprn"] = $oprpre.":".$conf["name"];
        //修改 方法说明
        $desc = $conf["desc"] ?? "";
        if (!Is::nemstr($desc)) $desc = $conf["title"] ?? "";
        if (!Is::nemstr($desc)) $desc = Str::camel($conf["name"],false)."方法";
        $conf["desc"] = $oprtit."：".$desc;

        //返回处理后的 方法信息数组
        $conf = Arr::extend($dftc, $conf);
        return $conf;
    }
    
}