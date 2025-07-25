<?php
/**
 * resper 框架 Orm 单例的参数处理类
 * 在 Orm 单例创建时，自动实例化此类，并关联到 Orm::$current->config 属性
 */

namespace Cgy\orm;

use Cgy\Resper;
use Cgy\Orm;
use Cgy\module\Configer;
use Cgy\orm\Driver;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Cls;
use Cgy\util\Path;
use Cgy\util\Conv;

class Config extends Configer 
{
    /**
     * 预设的设置参数
     * !! 覆盖父类
     */
    protected $init = [

    ];

    //用户设置 origin 数据
    protected $opt = [
        /*
        用户预设的 orm 参数，格式参考 module/configer/BaseConfig 类中定义
        在 构造函数中 由外部传入
        */
    ];

    //经过处理后的 运行时参数
    protected $context = [
        //Orm单例关联的所有可用的数据库 名称数组，全小写
        "dbns" => [],

        //预设参数中定义的 必须加载的数据库名称 数组
        "required" => [],

        /*
        # 按数据库名称 保存解析后的数据库参数
        "db_foo" => [
            "config" => "数据库配置文件的实际路径，可直接读取",
            "key" => "此数据库在 Orm::$DB[] 中的唯一key：DB_md5(数据库配置文件路径)",
            "name" => "数据库配置中的数据库名，可能与当前定义的不一致",
            "type" => "数据库类型：mysql|sqlite",
            "driver" => "数据库驱动类全称：Cgy\orm\driver\Mysql",
            "medoo" => [
                # 此数据库的 Medoo 连接参数
                "type" => "mysql",
                "database" => "db_foo",
                "host" => "",
                ...
            ],
            "prepare" => "对数据库配置参数进行预处理的类全称",
            "opt" => [
                # 预设参数中定义的 针对此数据库的参数，可覆盖数据库配置文件中的同名参数项的值
                "modelPath" => "可覆盖数据库配置文件中的，数据模型类的定义目录",
                ...
            ],
            "fixed" => [
                # 已经过处理的 合并了配置文件和预设参数的 数据库参数数组
                ...
            ],

            "models" => [
                # 此数据库中的数据表名称数组
            ],
            "model" => [
                # 初步处理得到数据模型的类全称，同一个数据库中的数据模型，可以使用不同的模型类定义
                "mdn" => "数据模型类全称",
                ...
            ],
        ],
        ...
        */
    ];

    /**
     * 默认配置文件后缀名，默认 .json
     * !! 覆盖父类
     * 可通过定义 DB_CONFEXT 形式的常量 来覆盖此参数
     */
    public static $confExt = ".json";

    //参数预设中用来替代 数据库名 的 字符串模板
    protected $dbnTpl = "%{DBN}%";

    //以来响应者实例
    public $resper = null;



    /**
     * 覆盖父类 构造函数
     * @param Array $opt 当前响应者的预设 orm 参数，格式参考 module/configer/BaseConfig 类中定义
     * @param Resper $resper 依赖的当前响应者实例
     * @return void
     */
    public function __construct($opt=[], $resper=null)
    {
        if (!isset($opt["dbs"]) || !Is::nemarr($opt["dbs"]) || !$resper instanceof Resper) return null;
        //缓存预设参数
        $this->init = Arr::extend($this->init, $opt);
        $this->opt = $opt;

        //关联 响应者实例
        $this->resper = $resper;

        //获取 Orm 参数 运行时缓存文件路径，[webroot]/runtime/[resper type]/[resper cls]/orm[.json]
        //$rcp = $resper->path("runtime/".strtolower($resper->cls), false);
        $this->runtimeCache = $resper->rtp."orm";

        //处理 预定义的数据库参数
        $this->setConf();
    }

    /**
     * 处理数据库参数，生成 $this->context
     * !! 子类可覆盖
     * @param Array $opt 用户设置
     * @return $this
     */
    public function setConf($opt = [])
    {
        //保存用户设置原始值
        $this->opt = Arr::extend($this->opt, $opt);

        //尝试读取 runtime 缓存
        $rc = $this->getRuntimeContext();
        if (Is::nemarr($rc)) {
            //存在缓存，应用到 context
            $this->context = $rc;
        } else {
            //不存在缓存，则初始化
            $opt = $this->opt;
            $dbs = $opt["dbs"] ?? [];
            $req = $opt["required"] ?? [];
            $dbns = [];
            $reqs = [];

            //依次处理预设参数中 定义的数据库
            foreach ($dbs as $dbn => $dbc) {
                //检查定义的数据库配置文件 是否存在，不存在则跳过
                $dbcf = $dbc["config"] ?? null;
                if (!Is::nemstr($dbcf)) continue;
                //解析数据库配置文件，获取数据库参数
                $dbnc = $this->parseDbConfigFile($dbcf, $dbc);
                //如果解析结果不正确，则跳过
                if (empty($dbnc) || !Is::nemarr($dbnc)) continue;
                //数据库名 格式确认
                $dbn = $dbnc["name"];
                //保存解析得到的数据库参数
                $this->context[$dbn] = $dbnc;
                $dbns[] = $dbn;
                if (in_array($dbn, $req)) $reqs[] = $dbn;
            }

            //保存到 context
            $this->context["dbns"] = $dbns;
            $this->context["required"] = $reqs;

            //尝试写入 runtime 缓存
            $this->cacheRuntimeContext();
        }

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
     * 读取数据库配置文件，处理得到数据库参数
     * @param String $conf 数据库配置文件路径，需要检查是否存在
     * @param Array $opt 在 orm 参数中定义的此数据库的参数，覆盖配置文件中的同名项目
     * @return Array|null 处理后的数据库参数，格式参考 $this->context 中的说明
     */
    protected function parseDbConfigFile($conf, $opt=[])
    {
        //如果配置文件不是有效路径，则返回 null
        if (!Is::nemstr($conf)) return null;
        //自动补全配置文件后缀名
        $conf = Configer::autoSuffix($conf, "db");
        //查找配置文件
        $file = Path::find($conf);
        //如果文件不存在，返回 null
        if (empty($file) || !file_exists($file)) return null;

        //读取配置文件
        $conf = file_get_contents($file);
        $conf = Conv::j2a($conf);

        //合并预设参数，如果有的话
        if (Is::nemarr($opt) && Is::associate($opt)) {
            //去除预设参数中的 config 项
            unset($opt["config"]);
            //使用预设参数 覆盖 配置文件中的同名项
            $conf = Arr::extend($conf, $opt);
        }

        //检查必须的数据库配置项目
        $path = $conf["path"] ?? null;              //数据库配置文件 绝对路径
        $dbn = $conf["name"] ?? null;               //数据库名称，应与预设参数中的数据库名称一致
        $type = $conf["type"] ?? DB_TYPE;           //数据库类型
        $mpath = $conf["modelPath"] ?? null;        //数据模型类定义的 绝对路径
        $required = $conf["modelRequired"] ?? [];   //必须加载的 数据模型数组
        /*var_dump($path);
        var_dump($dbn);
        var_dump($type);
        var_dump($mpath);
        var_dump(333);*/
        if (!Is::nemstr($path) || !Is::nemstr($dbn) ||  !Is::nemstr($type) || !Is::nemstr($mpath)) {
            //缺少配置项参数，返回 null
            return null;
        }
        //确保 数据库名 格式正确
        $dbn = Orm::snake($dbn);
        /**
         * !! 替换 路径参数中可能存在的 %{DBN}%
         * 例如 $path 可定义为 app/foo/library/db/config/%{DBN}%
         */
        $dbnTpl = $this->dbnTpl;
        $path = str_replace($dbnTpl, $dbn, $path);
        $mpath = str_replace($dbnTpl, $dbn, $mpath);
        //根据 $path 参数，获取 prepare 预处理类全称
        $prepareCls = $this->parsePrepareCls($path);
        //根据 $mpath 参数，获取此数据库包含的 数据模型类前缀
        $modelClsPre = $this->parseModelClsPre($mpath);
        /*var_dump($path);
        var_dump($mpath);
        var_dump($prepareCls);
        var_dump($modelClsPre);
        var_dump(444);*/
        //出现错误，返回 null
        if (!Is::nemstr($prepareCls) || !Is::nemstr($modelClsPre)) return null;

        //判断是否支持此类型数据库，不支持则返回 null
        $dclsn = Driver::support($type);
        if ($dclsn === false) return null;
        //获取针对当前数据库类型的 medoo 连接参数，未指定 或 缺少参数项 则返回 null
        $medoo = $conf[$type] ?? null;
        if (!Is::nemarr($medoo) || !isset($medoo["database"]) || !Is::nemstr($medoo["database"])) return null;
        /**
         * !! 替换 Medoo 参数的 database 项中可能存在的 %{DBN}%
         */
        $medoo["database"] = str_replace($dbnTpl, $dbn, $medoo["database"]);

        /**
         * 不同数据库类型 执行特殊处理
         */
        if ($type=="sqlite") {
            //sqlite 类型数据库 自动补全 database 路径的文件后缀名
            $medoo["database"] = $dclsn::autoSuffix($medoo["database"]);
        }

        //Medoo 参数增加 type 标记
        $medoo["type"] = $type;
        /*var_dump($dclsn);
        var_dump($medoo);
        var_dump(555);*/

        //初步处理数据模型参数，获取数据模型的类全称
        $mds = $conf["model"] ?? [];
        $models = [];
        $model = [];
        if (!Is::nemarr($mds)) return null;
        foreach ($mds as $mdn => $mdc) {
            //确保 数据表名 格式正确
            $mdn = Orm::snake($mdn);
            //数据表名 转为 模型类名，首字母大写
            $cln = Orm::camel($mdn, true);
            //获取可能存在的，自定义模型类路径
            $mdp = $mdc["path"] ?? "";
            if (!Is::nemstr($mdp)) {
                $mcls = $modelClsPre.$cln;
            } else {
                /**
                 * !! 替换 单独定义的模型类文件路径 项中可能存在的 %{DBN}%
                 */
                $mdp = str_replace($dbnTpl, $dbn, $mdp);
                $mcls = $this->parseModelClsPre($mdp);
                if (!Is::nemstr($mcls)) continue;
                $mcls .= $cln;
            }
            //var_dump($mdn);
            //var_dump($mcls);
            //确认数据模型类存在
            if (!class_exists($mcls)) continue;
            $models[] = $mdn;
            $model[$mdn] = $mcls;
        }
        /*var_dump($models);
        var_dump($model);
        var_dump(666);*/
        //如果没有可用的数据模型，返回 null
        if (!Is::nemarr($models) || !Is::nemarr($model)) return null;

        //一切正常，则使用 数据库配置文件路径 生成数据库唯一 key
        $dbkey = "DB_".md5(Path::fix($file));
        //将处理后的 参数写回 $conf
        $conf["name"] = $dbn;
        $conf["path"] = $path;
        $conf[$type] = $medoo;
        $conf["modelPath"] = $mpath;
        //从 $conf 去除 model 数据模型参数，这些参数将在 Model 初始化时自行处理
        unset($conf["model"]);
        
        //返回解析结果
        $rtn = [
            //数据库配置文件的实际路径，可直接读取
            "config" => $file,
            //数据库唯一key
            "key" => $dbkey,
            //数据库名
            "name" => $dbn,
            //数据库类型：mysql|sqlite
            "type" => $type,
            //数据库驱动类全称
            "driver" => $dclsn,
            //此数据库的 Medoo 连接参数
            "medoo" => $medoo,
            //此数据库使用的 prepare 预处理类
            "prepare" => $prepareCls,
            //预设参数中定义的 针对此数据库的参数，可覆盖数据库配置文件中的同名参数项的值
            "opt" => $opt,
            //已经过处理的 合并了配置文件和预设参数的 数据库参数数组
            "fixed" => $conf,
            //此数据库包含的可用的数据模型（定义了模型类）
            "models" => $models,
            //此数据库中可用的数据模型的类全称
            "model" => $model,
        ];
        //var_dump($rtn);var_dump(777);
        return $rtn;
    }

    /**
     * 取得数据库配置参数预处理类的类全称
     * 如果有自定义预处理类，应定义在数据库配置文件路径上一级的 prepare 路径下，例如：
     *      配置文件路径：          app/foo/library/db/config
     *      则预处理类应定义在：     app/foo/library/db/prepare 
     *      对应的类前缀：          NS\app\foo\db\prepare
     * 预处理类名应为： DbnPrepare
     * @param String $path 数据库配置文件路径，在配置文件的 path 项定义
     * @return String|null 返回类全称，如果配置文件路径不存在，返回 null
     */
    protected function parsePrepareCls($path)
    {
        if (!Is::nemstr($path)) return null;
        //自动补全配置文件后缀名
        $path = Configer::autoSuffix($path, "db");
        $pp = Path::find($path);
        if (empty($pp) || !file_exists($pp)) return null;
        //从数据库配置文件中解析得到 数据库名
        $conf = Conv::j2a(file_get_contents($pp));
        $dbn = $conf["name"] ?? null;
        //如果未定义 name 返回 null
        if (!Is::nemstr($dbn)) return null;
        //将 数据库名 转为 类名，首字母大写
        $cln = Orm::camel($dbn, true);

        //将路径 转为 类前缀
        $parr = explode("/", trim($path,"/"));
        $narr = [];
        for ($i=0;$i<count($parr)-2;$i++) {
            if (in_array($parr[$i], ["root","library"])) continue;
            if ($parr[$i]=="config") {
                $narr[] = "prepare";
            } else {
                $narr[] = $parr[$i];
            }
        }
        $narr[] = $cln."Prepare";
        $clsn = Cls::find(implode("/", $narr));
        if (!class_exists($clsn)) return Cls::find("orm/config/prepare");
        return $clsn;
    }

    /**
     * 将数据模型类文件路径，解析为类全称前缀
     * 例如：
     *      app/foo/model/dbn       --> NS\app\foo\model\dbn
     *      root/model/dbn          --> NS\model\dbn
     * @param String $path 数据模型类文件路径 
     * @return String|null 类全程前缀，如果路径不存在则返回 null
     */
    protected function parseModelClsPre($path)
    {
        if (!Is::nemstr($path)) return null;
        $pp = Path::find($path, ["checkDir"=>true]);
        if (empty($pp) || !is_dir($pp)) return null;

        //将路径 转为 类前缀
        $parr = explode("/", trim($path,"/"));
        $narr = [];
        for ($i=0;$i<count($parr);$i++) {
            if (in_array($parr[$i], ["root","library"])) continue;
            $narr[] = $parr[$i];
        }
        $clsn = Cls::pre(implode("/",$narr));
        return $clsn;
    }

}