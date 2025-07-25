<?php
/**
 * cgyio/resper 单个数据库 参数 解析器
 * 在 数据库实例化之后，立即处理 orm/Config 处理得到的 单个数据库的参数
 * 
 * 如果定义了 prepare 与处理参数，会对数据库参数进行预处理，如：
 *      继承通用数据模型配置参数，
 *      添加通用的数据字段(列)，
 *      自动为数据表添加索引 等
 */

namespace Cgy\orm\config;

use Cgy\Orm;
use Cgy\orm\Db;
use Cgy\orm\config\Prepare;
use Cgy\module\Configer;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Cls;
use Cgy\util\Conv;

class DbConfig extends Configer
{
    //关联的 数据库实例
    public $db = null;

    /**
     * 构造
     * @param Array $opt 数据库设置文件参数，格式参考：orm/Config::$context 属性
     *      [
     *          config      => "数据库配置文件真实路径",
     *          key         => "数据库实例唯一 key",
     *          name        => "数据库名称",
     *          type        => "sqlite", 
     *          driver      => "数据库驱动类全称",
     *          medoo       => [
     *              # Medoo 连接参数
     *              database    => "sqlite类型时，为数据库文件路径",
     *              ...
     *          ],
     *          prepare     => "数据库配置参数 预处理类全称",
     *          opt         => [
     *              # 预设参数中定义的，覆盖配置文件中同名项的 设置值
     *              ...
     *          ],
     *          fixed       => [
     *              # 经过合并的 数据库配置参数，不含 model 设置参数
     *              ...
     *          ],
     *          models      => [ 可用数据模型(表)名称数组 ],
     *          model       => [
     *              # 可用数据模型类全称
     *              mdn     => "数据模型类全称",
     *              ...
     *          ]
     *      ]
     * @return void
     */
    public function __construct($opt = [])
    {
        $conf = $opt["config"] ?? null;
        $dbkey = $opt["key"] ?? null;
        $fixed = $opt["fixed"] ?? [];
        $prepare = $opt["prepare"] ?? null;
        if (
            !Is::nemstr($conf) || !file_exists($conf) ||
            !Is::nemstr($dbkey) ||
            !Is::nemarr($fixed) ||
            !Is::nemstr($prepare) || !class_exists($prepare)
        ) {
            return null;
        }

        //读取 json 内容到 $this->init
        $json = Conv::j2a(file_get_contents($conf));
        //用 已处理过的 fixed 数据库参数 替换 json 中数据
        $this->init = $fixed;
        $this->init["model"] = $json["model"] ?? [];

        //缓存 $opt
        $this->opt = $opt;

        //关联到 数据库实例
        $this->db = Orm::$DB[$dbkey];

        //指定 runtime 缓存文件路径
        $this->runtimeCache = $this->db->path("runtime/%{name}%", false);
        //尝试读取缓存数据
        $ctx = $this->getRuntimeContext();
        if (Is::nemarr($ctx)) {
            //使用缓存数据
            $this->context = $ctx;
        } else {
            //开始处理 数据库配置参数

            //记录数据库参数 runtime 缓存文件路径
            $this->context["runtime"] = $this->runtimeCache;
            
            //解析 json 文件，保存到 $this->context
            $this->setConf();

            //数据库初始化时的 参数前置处理，处理模型参数，结果将合并到 $this->context 
            $dbname = $this->context["name"] ?? null;
            if (!Is::nemstr($dbname)) {
                $pp = new Prepare($this->context);
            } else {
                /**
                 * 调用 orm/Config 类解析得到的 prepare 预处理类
                 * 对数据库参数执行预处理，结果合并到 $this->context
                 */
                $pp = new $prepare($this->context);
            }
            $ctx = $pp->parse();
            $this->context = $ctx;

            //解析 Db 类实例中的 proxy 方法
            $this->parseProxyMethods();
            
            //将处理得到的参数数据，缓存到 runtimeCache
            $this->cacheRuntimeContext();
        }

        //执行最终参数处理
        $this->afterSetConf();
    }

    /**
     * 解析设置文件 [dbpath]/config/[dbname].json
     * !! 覆盖父类
     * @param Array $opt 用户设置
     * @return $this
     */
    public function setConf($opt = [])
    {
        $init = $this->init;
        $mds = $init["model"] ?? [];

        //解析 除 model参数 外的 一般设置
        foreach ($init as $k => $v) {
            if ($k == "model") continue;
            $this->context[$k] = $v;
        }

        //当前数据库 runtime 运行时缓存文件路径，作为 数据模型缓存文件的路径前缀
        $runtime = $this->context["runtime"];
        //如果数据库缓存文件路径带有文件后缀，则去除
        if (strpos($runtime, ".")!==false) {
            $runtime = implode(".", array_slice(explode(".", $runtime), 0, -1));
        }

        //将解析得到的 数据模型类全称/runtime路径，写入 $this->context["model"][mdn]["clsn"]
        $opt = $this->opt;
        $mcls = $opt["model"] ?? [];
        $omod = array_merge($mds);  //$this->context["model"];
        $confExt = defined("DB_CONFEXT") ? constant("DB_CONFEXT") : static::$confExt;
        foreach ($mcls as $mdn => $mclsn) {
            if (isset($omod[$mdn])) {
                //将解析得到的 数据模型类全称 写入 model 参数
                $omod[$mdn]["clsn"] = $mclsn;
                //删除原参数中定义的 path 项
                unset($omod[$mdn]["path"]);
                //将 model 的 runtime 运行时缓存文件路径 写入 model 参数
                $omod[$mdn]["runtime"] = $runtime.DS.$mdn.$confExt;
            }
        }
        //写回 context
        $this->context["model"] = $omod;
        $this->context["models"] = $opt["models"];

        return $this;
    }

    /**
     * 在 应用用户设置后 执行
     * !! 覆盖父类
     * @return $this
     */
    public function afterSetConf()
    {
        

        return $this;
    }

    /**
     * 解析 Db 类实例中的 proxy 方法，提取 方法信息，缓存到 $db->config->context["proxyMethods"] 中
     * @return $this
     */
    protected function parseProxyMethods()
    {
        $opt = $this->opt;
        //数据库名称 全小写，下划线_
        $dbn = Orm::snake($opt["name"]);
        //数据库标题
        $dbt = $this->context["title"];
        //操作标识 前缀
        $oprpre = "db/".$dbn;
        //操作说明 前缀
        $oprtit = $dbt;
        //从类中 查找指定的 方法
        $prms = Cls::specific(
            Db::class,
            "public,&!static",
            "proxy",
            null,
            function($mi, $conf) use ($oprpre, $oprtit) {
                //处理方法信息数组
                $conf = Cls::parseMethodInfoWithUac($mi, $conf, $oprpre, $oprtit);
                return $conf;
            }
        );
        //找到的方法，写入 context
        $this->context["proxyMethods"] = $prms;

        return $this;
    }

}