<?php
/**
 * resper 框架 runtime 缓存文件 读取/写入
 * 为所有引用的 类 增加 runtime 缓存文件的 读取/写入 功能
 * 
 * 必须有属性：
 *      public $runtimeCache = 缓存文件的绝对路径，可省略后缀名 [.json]     
 *      protected $context = [ 经过解析得到的，需要缓存的 数据... ]
 */

namespace Cgy\module\configer\traits;

use Cgy\module\Configer;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Conv;
use Cgy\util\Path;

trait runtimeCache 
{
    /**
     * !! 必须有如下定义的属性
     */
    //context 需要缓存的数据
    //protected $context = [];
    //runtime 缓存文件路径
    public $runtimeCache = "";

    /**
     * 必须的 静态属性
     */
    //缓存数据中的 时间项
    protected $rcTimeKey = "__CACHE_TIME__";
    //缓存数据被读取到 context 中后，添加的 缓存使用标记
    protected $rcSignKey = "__USE_CACHE__";
    //缓存数据的 过期时间 1h
    protected $rcExpired = 60*60;
    //默认的 缓存文件后缀名
    protected $rcExt = ".json";

    
    /**
     * 从缓存中读取数据
     * runtime 运行时缓存数据 == $this->context 
     * @param String $path 缓存文件路径，不指定则使用 $this->runtimeCache 默认 null
     * @return Array|null
     */
    public function getRuntimeContext($path=null)
    {
        //如果未启用 WEB_CACHE 直接返回 null
        if (!defined("WEB_CACHE") || WEB_CACHE !== true) return null;

        //缓存文件路径
        $cf = Is::nemstr($path) ? $path : $this->runtimeCache;
        //未定义缓存路径，返回 null
        if (!Is::nemstr($cf)) return null;
        //自动补全缓存文件后缀名
        $cf = $this->cacheSuffix($cf);
        //缓存文件不存在，返回空数据
        if (!file_exists($cf)) return [];
        //读取缓存数据
        $conf = Conv::j2a(file_get_contents($cf));
        //检查是否过期
        $tk = $this->rcTimeKey;
        $sk = $this->rcSignKey;
        $exp = $this->rcExpired;
        $ct = $conf[$tk] ?? null;
        $ct = is_null($ct) ? 0 : strtotime($ct);
        if ($ct<=0 || time()-$ct>$exp) return [];     //缓存过期，不读取
        //清除缓存中的 时间戳
        unset($conf[$tk]);
        //增加标记
        $conf[$sk] = true;
        //返回数据
        return $conf;
    }

    /**
     * 将当前解析得到的参数数据，缓存到 runtime 运行时参数缓存文件中
     * @param Array $conf 参数数据，不指定则使用 $this->context，默认 null
     * @param String $path 缓存文件路径，不指定则使用 $this->runtimeCache，默认 null
     * @return Bool
     */
    public function cacheRuntimeContext($conf=null, $path=null)
    {
        //如果未启用 WEB_CACHE 直接返回 true
        //!! 不论是否开启 WEB_CACHE 一律写入 cache 文件
        //if (!defined("WEB_CACHE") || WEB_CACHE !== true) return true;
        
        //缓存文件路径
        $cf = Is::nemstr($path) ? $path : $this->runtimeCache;
        //未定义缓存路径，返回 null
        if (!Is::nemstr($cf)) return false;
        //自动补全缓存文件后缀名
        $cf = $this->cacheSuffix($cf);

        //处理要写入缓存的数据
        $conf = Is::nemarr($conf) ? $conf : $this->context;
        //如果要缓存的数据为空，返回 false
        if (!Is::nemarr($conf)) return false;
        $conf = Arr::copy($conf);
        //增加时间戳
        $conf[$this->rcTimeKey] = date("Y-m-d h:i:s",time());
        //删除标记
        unset($conf[$this->rcSignKey]);
        //转为字符串
        $cnt = Conv::a2j($conf);

        //写入缓存文件，如果不存在则自动创建（文件|多级路径）
        return Path::mkfile($cf, $cnt);
    }

    /**
     * 自动补全配置文件后缀名
     * @param String $path 配置文件路径
     * @return String 补全后的文件路径
     */
    protected function cacheSuffix($path)
    {
        if (!Is::nemstr($path)) return $path;
        //获取默认后缀名
        $cnst = "CONFEXT_CACHE";
        $ext = defined($cnst) ? constant($cnst) : $this->rcExt;
        //自动补全
        if (strpos($path, ".")===false || substr($path, strlen($ext)*-1)!==$ext) return $path.$ext;
        //已包含后缀名，直接返回
        return $path;
    }
}