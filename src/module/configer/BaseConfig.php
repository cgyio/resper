<?php
/**
 * resper 框架 各类型 resper 响应者类参数配置器 config 类的基类
 * 任意类型的 resper 响应者类，都存在相同的 部分配置参数，此基类旨在使用相同的方法，处理解析这些参数
 * 各类型的 resper 响应者类的 config 参数配置器类，都应继承此类
 */

namespace Cgy\module\configer;

use Cgy\Resper;
use Cgy\module\Configer;
use Cgy\orm\Driver;
use Cgy\orm\Model;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Is;
use Cgy\util\Path;

class BaseConfig extends Configer 
{
    /**
     * 定义各类型 resper 响应者 配置参数中的 相同的 部分
     * !! 子类不要覆盖
     */
    protected $commonInit = [
        //日志参数
        "log" => [
            //启用日志，默认启用
            "enable" => true,
            //日志文件路径
            "path" => "root/log",
            //日志文件后缀
            "ext" => ".log",
            //日志格式化，LineFormatter
            "format" => "%datetime% | %channel% | %level_name% | %message% | %context% | %level%\n",
        ],

        //数据库参数
        "orm" => [
            /**
             * !! 必须的
             */
            //是否启用 orm
            "enable" => false,
            //数据库类型
            "type" => "sqlite",
            
            /**
             * 数据库相关路径参数，数据库配置文件/模型文件 所在路径
             * 开发阶段 或 使用MySql数据库 数据库文件可能不存在，因此以数据库配置文件作为数据库存在的依据
             * 如果在 dirs 路径下存在 config/Foo.json 则表示存在数据库 Foo
             */
            "dirs" => "root/library/db",    //必须使用绝对路径
            "models" => "root/model",       //必须使用绝对路径

            /**
             * !! mysql 数据库 必须的设置
             * Medoo 库 连接 MySql 的参数
             */
            "mysql" => [
                "host" => "127.0.0.1",
                "port" => 3306,
                "database" => "",
                "username" => "",
                "password" => "",

                "charset" => "utf8mb4",                 //字符集
	            "collation" => "utf8mb4_general_ci",    //排序方式

                //更多参数 参考 Medoo 库
                //...
            ],

            /**
             * !! 可选的
             */
            //默认必须加载的数据库模型(表) 名称
            "required" => [
                //"usr"
            ],
        ],

        //权限控制参数
        "uac" => [
            /**
             * !! 启用 uac 控制，必须启用 orm
             * !! 必须的
             */
            "enable" => false,
            
            /**
             * 指定 usr/role 数据表名称
             * 如果 不同数据库下有同名数据表 表名称必须指定为 DbnTbn，否则指定为 Tbn
             * 可以通过 Orm::$tbn() 操作数据表
             */
            "model" => [
                "usr" => "Usr",
                "role" => "Role",
            ],

            /**
             * 指定 jwt 相关参数
             */
            "jwt" => [
                //过期时长
                "expire" => 8*60*60,
                //自定义 headers 字段名，默认 Authorization
                "header" => "Authorization",
                //自定义加密算法 alg，默认 HS256
                "alg" => "HS256",

                //密钥文件保存路径，绝对路径
                "secret" => "root/library/jwt",

                //可自定义请求来源解析类，默认 不指定，使用 Jwt 处理类默认方法
                "audience" => "",
                //如果未获取到可用的 请求来源，则使用此默认的请求来源
                "dftAudience" => "public",
            ],

            /**
             * 指定登陆页面路径，不指定则使用框架定义的 登陆页面
             */
            "login" => "root/page/login.php",
        ],

        //注册中间件
        "middleware" => [
            //入站
            "in" => [
                //中间件类全称，预定义一些通用的 中间件
                "\\Cgy\\middleware\\in\\Secure",    //用户输入数据过滤
                "\\Cgy\\middleware\\in\\AuthCheck", //用户权限验证
            ],
            //出站
            "out" => [
                //中间件类全称
            ],
        ],
    ];

    /**
     * 定义各类型 resper 响应者的 配置参数 数据格式
     * !! 子类必须覆盖
     * 要覆盖 commonInit 中的参数项目，也在此处定义，将与 commonInit 中数据合并
     */
    protected $init = [
        
    ];


    /**
     * 写入用户设置
     * !! 子类不要覆盖
     * @param Array $opt 用户设置
     * @return $this
     */
    public function setConf($opt = [])
    {
        //保存用户设置原始值
        $this->opt = Arr::extend($this->opt, $opt);

        //合并 commonInit 与 init 参数
        $commonInit = Arr::copy($this->commonInit);
        $this->init = Arr::extend($commonInit, $this->init);

        //合并 用户设置 与 默认参数，保存到 context
        $ctx = $this->context;
        if (empty($ctx)) $ctx = Arr::copy($this->init);
        $ctx = Arr::extend($ctx, $opt);

        //处理设置值，支持格式：String, IndexedArray, Numeric, Bool, null,
        $this->context = $this->fixConfVal($ctx);

        /**
         * 处理 任意类型 resper 响应者 配置参数的 相同部分
         */
        foreach ($commonInit as $confKey => $confVal) {
            $m = "init".ucfirst(strtolower($confKey))."Conf";
            if (method_exists($this, $m)) {
                $this->$m();
            }
        }

        /**
         * 各类型 resper 响应者 参数配置器类 在此处 执行其他处理方法
         */
        return $this->afterSetConf();
    }

    /**
     * 在 应用用户设置后 执行
     * !! 子类必须覆盖
     * @return $this
     */
    public function afterSetConf()
    {
        //各类型 resper 响应者参数配置器类 执行各自的处理方法
        //子类实现
        //...

        return $this;
    }



    /**
     * 定义通用配置参数项目处理方法
     */
    //处理 $this->init["orm"]
    protected function initOrmConf() 
    {
        $ctx = $this->context;
        $ormc = $ctx["orm"];
        //如果未指定 orm 参数，直接返回
        if (!Is::nemarr($ormc)) return $this;
        $enable = $ormc["enable"] ?? false;
        //如果未启用 orm，直接返回
        if (!is_bool($enable) || $enable!==true) return $this;

        //取得 dbtype
        $dbtp = $ormc["type"] ?? (defined("DB_TYPE") ? DB_TYPE : "sqlite");

        /**
         * 根据 dbtype 数据库类型，计算数据库参数
         */
        //var_dump("Driver::initOrmConf()");
        //var_dump($ormc);
        $driver = Driver::support($dbtp);
        if ($driver===false) {
            //不支持此类型数据库，直接返回
            return $this;
        }
        $ormc["type"] = $dbtp;
        //调用数据库模型的 initOrmConf() 方法，得到数据库参数 [path=>'数据库文件参数', dbns=>[可用数据库名称数组], ...]
        $ormc = Arr::extend($ormc, $driver::initOrmConf($ormc));
        //var_dump($ormc);

        /**
         * 处理预设的 model 模型文件路径，得到模型相关参数
         */
        $ormc["model"] = Model::initOrmConf($ormc);
        //var_dump($ormc["model"]);
        
        //将解析后的参数写入 $this->context
        $this->context["orm"] = $ormc;

        return $this;
    }
    //处理 $this->init["uac"]
    protected function initUacConf()
    {
        $ctx = $this->context;
        $ormc = $ctx["orm"];
        $ormEnable = $ormc["enable"] ?? false;
        $uacc = $ctx["uac"] ?? [];
        //如果未启用 orm ，则 uac 无法启用
        if (!Is::nemarr($ormc) || $ormEnable!==true) {
            //将 uac 的 enable 设为 false
            $uacc["enable"] = false;
            $this->context["uac"] = $uacc;
        }
        //uac 参数不需要其他预处理，在 Uac 实例化时，由 Uac 类自行处理
        return $this;
    }
    //处理 当前响应者的注册中间件 参数
    protected function initMiddlewareConf()
    {
        $ctx = $this->context;
        $mid = $ctx["middleware"] ?? [];
        $in = $mid["in"] ?? [];
        $out = $mid["out"] ?? [];
        if (!Is::nemarr($in) && !Is::nemarr($out)) return $this;
        if (Is::nemarr($in)) {
            $nin = [];
            foreach ($in as $i => $midi) {
                if (Is::nemstr($midi) && class_exists($midi)) {
                    $nin[] = $midi;
                }
            }
            $in = $nin;
        }
        if (Is::nemarr($out)) {
            $nout = [];
            foreach ($out as $i => $midi) {
                if (Is::nemstr($midi) && class_exists($midi)) {
                    $nout[] = $midi;
                }
            }
            $out = $nout;
        }
        //写入处理后的参数
        $this->context["middleware"] = [
            "in" => $in,
            "out" => $out
        ];

        return $this;
    }

}