<?php
/**
 * cgyio/resper 框架设置工具类
 */

namespace Cgy\module;

use Cgy\util\Is;
use Cgy\util\Str;
use Cgy\util\Arr;
use Cgy\util\Path;
use Cgy\util\Conv;

use Cgy\module\configer\traits\runtimeCache;

class Configer 
{
    /**
     * 使用 runtime 缓存相关功能
     */
    use runtimeCache;

    /**
     * 预设的设置参数
     * !! 子类自定义
     */
    protected $init = [];

    //用户设置 origin 数据
    protected $opt = [];

    //经过处理后的 运行时参数
    protected $context = [];

    //已定义的常量
    public static $cnsts = [];

    /**
     * runtimeCache trait 要求的属性
     * 已在 trait 中定义
     */
    //public $runtimeCache = "";
    //protected $rcTimeKey = "__CACHE_TIME__";
    //protected $rcSignKey = "__USE_CACHE__";
    //protected $rcExpired = 60*60;    //缓存更新的时间间隔，1h

    /**
     * 默认配置文件后缀名，默认 .json
     * !! 子类可覆盖
     * 可通过定义 XX_CONFEXT 形式的常量 来覆盖此参数
     */
    public static $confExt = ".json";
    /**
     * 支持的 配置文件后缀名
     * !! 子类不要覆盖
     */
    public static $confExts = [
        ".json", ".xml", ".yaml", ".yml",
    ];

    /**
     * 构造
     * @param Array $opt 输入的设置参数
     * @return void
     */
    public function __construct($opt = [])
    {
        //应用用户设置
        $this->setConf($opt);
    }

    /**
     * 写入用户设置
     * !! 子类可覆盖
     * @param Array $opt 用户设置
     * @return $this
     */
    public function setConf($opt = [])
    {
        //保存用户设置原始值
        $this->opt = Arr::extend($this->opt, $opt);

        //合并 用户设置 与 默认参数，保存到 context
        $ctx = $this->context;
        if (empty($ctx)) $ctx = Arr::copy($this->init);
        $ctx = Arr::extend($ctx, $opt);

        //处理设置值，支持格式：String, IndexedArray, Numeric, Bool, null,
        $this->context = $this->fixConfVal($ctx);

        //执行后续
        return $this->afterSetConf();
    }

    /**
     * 在 应用用户设置后 执行
     * !! 子类可覆盖
     * @return $this
     */
    public function afterSetConf()
    {
        //子类可自定义方法
        //...

        return $this;
    }

    /**
     * __get 访问 context
     * @param String $key
     * @return Mixed
     */
    public function __get($key)
    {
        /**
         * $this->ctx  --> $this->context
         */
        if ($key=="ctx") return $this->context;

        /**
         * $this->field  -->  $this->context[field]
         */
        if (isset($this->context[$key])) {
            $ctx = $this->context[$key];
            //if (Is::associate($ctx)) return (object)$ctx;
            return $ctx;
        }

        /**
         * $this->_field  -->  $this->init[field]
         */
        if (substr($key, 0, 1)==="_" && isset($this->init[substr($key, 1)])) {
            $k = substr($key, 1);
            return $this->init[$k];
        }

        /**
         * $this->origin  -->  $this->opt
         * 访问传入的用户自定义参数
         * 即 此类的构造函数的参数
         * 保存在 $this->opt 中
         */
        if ($key == "origin") {
            if (!is_null($this->opt)) return $this->opt;
        }

        /**
         * runtime 运行时缓存相关
         */
        if ($key=="cached") return $this->context[$this->rcSignKey] ?? false;
        if ($key=="cachetime") return $this->context[$this->rcTimeKey] ?? 0;

        return null;
    }

    /**
     * 外部访问 context
     * @param String $key context 字段 或 字段 path： 
     *      foo | foo/bar  -->  context["foo"] | context["foo"]["bar"]
     * @param Mixed $data 可以指定新值，覆盖旧的设置值，默认 __empty__ 标识未指定
     * @return Mixed
     */
    public function ctx($key = "", $data="__empty__")
    {
        //确认是否指定了需要覆盖的新设置值
        $nconf = $data!=="__empty__";
        //原设置数组
        $conf = $this->context;

        if (!Is::nemstr($key)) {
            //指定的设置项路径不是非空字符串
            if ($key=="") {
                //设置项路径为 空字符串
                if ($nconf) {
                    //覆盖原设置值
                    $this->context = Arr::find($conf, $key, $data);
                }
                //返回完整的设置值
                return $this->context;
            }
            return null;
        }

        if (isset($conf[$key])) {
            //直接指定了某个设置项 键名
            if ($nconf) {
                //覆盖原设置值
                $this->context[$key] = Arr::extend($conf[$key], $data);
                return $this->context;
            }
            return $conf[$key];
        }

        if ($nconf) {
            //覆盖原设置值
            $this->context = Arr::find($conf, $key, $data);
            //返回完整设置值
            return $this->context;
        }

        //使用 Arr::find 方法，查找目标设置值，未找到返回 null
        return Arr::find($conf, $key);
    }

    /**
     * 处理设置值
     * 设置值支持格式：String, IndexedArray, Numeric, Bool, null
     * @param Mixed $val 要处理的设置值
     * @return Mixed 处理后的设置值，不支持的格式 返回 null
     */
    public function fixConfVal($val = null)
    {
        if (Is::associate($val)) {
            $vn = [];
            foreach ($val as $k => $v) {
                $vn[$k] = $this->fixConfVal($v);
            }
            return $vn;
        }

        if (Is::ntf($val)) {
            //"null true false"
            eval("\$val = ".$val.";");
        } else if (is_numeric($val)) {
            $val = $val*1;
        } else if (Is::nemstr($val)) {
            if ("," == substr($val, 0,1) || false !== strpos($val, ",")) {
                //首字符为 , 或 包含字符 , 表示是一个 array
                $val = trim(trim($val), ",");
                $val = preg_replace("/\s+,/", ",", $val);
                $val = preg_replace("/,\s+/", ",", $val);
                $val = explode(",", $val);
                $val = array_map(function($i) {
                    return trim($i);
                }, $val);
            }
        } else if ($val=="" || is_bool($val) || Is::indexed($val)) {
            //$val = $val;
        } else {
            $val = null;
        }

        return $val;
    }

    /**
     * 外部调用 $this->context 赋值
     * @param Array $conf 要写入的 设置内容
     * @param Bool $cover 是否覆盖原数据，默认 false 使用 extend 方法合并
     * @return Bool
     */
    public function setCtx($conf=[], $cover=false)
    {
        if (!Is::nemarr($conf)) return false;
        $ctx = $this->context;
        if ($cover) {
            $this->context = $conf;
        } else {
            $nctx = Arr::extend($ctx, $conf);
            $this->context = $nctx;
        }
        return true;
    }

    /**
     * 从缓存中读取数据
     * runtime 运行时缓存数据 == $this->context 
     * @param String $path 缓存文件路径，不指定则使用 $this->runtimeCache 默认 null 
     * @return Array|null
     */
    /*public function getRuntimeContext($path=null)
    {
        //缓存文件路径
        $path = Is::nemstr($path) ? $path : $this->runtimeCache;
        return static::getRuntimeContextByPath($path);
    }*/

    /**
     * 将当前解析得到的参数数据，缓存到 runtime 运行时参数缓存文件中
     * @param Array $conf 参数数据，不指定则使用 $this->context，默认 null
     * @param String $path 缓存文件路径，不指定则使用 $this->runtimeCache，默认 null
     * @return Bool
     */
    /*public function cacheRuntimeContext($conf=null, $path=null)
    {
        //缓存文件路径
        $path = Is::nemstr($path) ? $path : $this->runtimeCache;
        //处理要写入缓存的数据
        $conf = Is::nemarr($conf) ? $conf : $this->context;
        return static::cacheRuntimeContextByPath($conf, $path);

    }*/



    /**
     * static tools
     */

    /**
     * 定义常量
     * @param Array $defs
     * @param String $pre 常量前缀
     * @return Array
     */
    public static function def($defs = [], $pre="")
    {
        $pre = ($pre=="" || !Is::nemstr($pre)) ? "" : strtoupper($pre)."_";
        foreach ($defs as $k => $v) {
            $k = $pre.strtoupper($k);
            $ln = count(explode("_",$k));
            if (Is::nemarr($v) && Is::associate($v)) {
                self::def($v, $k);
            } else {
                if (!defined($k)) {
                    self::$cnsts[] = $k;
                    define($k, $v);
                }
            }
        }
        return $defs;
    }

    /**
     * 自动补全配置文件后缀名
     * @param String $path 配置文件路径
     * @param String $for 此配置文件的用途，db|app|cache... 相应的需要定义 CONFEXT_[DB|APP|CACHE...] 形式的常量，来覆盖默认后缀名
     * @return String 补全后的文件路径
     */
    protected static function autoSuffix($path, $for="db")
    {
        if (!Is::nemstr($path)) return $path;
        //获取默认后缀名
        $cnst = "CONFEXT_".strtoupper($for);
        $ext = defined($cnst) ? constant($cnst) : static::$confExt;
        //支持的配置文件后缀名
        $exts = defined("CONFEXT_SUPPORT") ? Arr::mk(CONFEXT_SUPPORT) : static::$confExts;
        //自动补全
        if (strpos($path, ".")===false) return $path.$ext;
        //获取当前路径的后缀名
        $pi = pathinfo($path);
        $cext = ".".$pi["extension"];
        //如果已有 被支持的后缀名，不补全，直接返回
        if (in_array(strtolower($cext), $exts)) return $path;
        //否则 补全并返回
        return $path.$ext;
    }
    
}