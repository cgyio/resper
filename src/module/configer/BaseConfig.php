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
use Cgy\uac\Operation;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Is;
use Cgy\util\Path;
use Cgy\util\Cls;

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

            /**
             * 指定要使用的数据库配置
             * 可以指定多个 不同位置/不同类型 的数据库
             * !! 数据库配置文件的结构，参考 orm/temp/db_foo.json
             */
            "dbs" => [
                /* 
                !! 键名必须与配置文件中的实际数据库名称一致
                "db_foo" => [
                    !! 必须指定数据库配置文件的绝对路径，包括文件名，可以不要后缀（配置文件后缀，在 Driver 基类中定义）
                    "config" => "root/library/db/db_foo[.json]",

                    !! 此处指定的其他参数，将覆盖 数据库配置文件中 同名项目的值
                    "type" => "mysql",
                    "mysql" => [
                        ...
                    ],
                    "modelPath" => "root/model/db_foo", 数据模型类定义位置 绝对路径
                    "modelRequired" => [
                        ...
                    ],
                ],
                ... 可定义多个数据库
                */
            ],

            /**
             * 可定义哪些数据库是必须的
             * 在 Orm 实例创建之后，这些数据库也必须立即初始化
             */
            "required" => [
                //"db_foo",
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

            /**
             * 权限操作列表 生成/管理 类参数
             */
            "operation" => [
                //自定义 操作列表处理类，不指定使用默认的 \Cgy\uac\Operation
                "handler" => "Cgy\\uac\\Operation",
                /**
                 * 可以指定 特殊的 操作列表，用于权限控制
                 */
                "operates" => [
                    /*
                    "sys:finance/report" => "系统财务权限：查看财务报表"
                    */
                ],
            ],
        ],

        //注册中间件
        "middleware" => [
            //入站
            "in" => [
                //中间件类全称，预定义一些通用的 中间件
                "Cgy\\middleware\\in\\IpFilter",    //ip 入站过滤
                "Cgy\\middleware\\in\\Secure",      //用户输入数据过滤
            ],
            //出站
            "out" => [
                //中间件类全称
            ],

            //与中间件相关的 预设参数
            /**
             * ip地址过滤参数
             */
            "Cgy\\middleware\\in\\IpFilter" => [
                //ip 过滤类型 false|black|white
                "filter" => 'black',
                //黑名单配置文件路径，绝对路径
                "black" => "root/library/ip/black.json",
                //白名单配置文件路径，绝对路径
                "white" => "root/library/ip/white.json",
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
         * 解析 任意类型 resper 响应者类中定义的 可用于响应请求的 resper 响应方法
         */
        $this->parseResperMethods();

        /**
         * 针对 app|module 类型的响应者，查找其路径内部 可能定义的 Resper 响应者类
         */
        $this->parseSubResperMethods();

        /**
         * 各类型 resper 响应者 参数配置器类 在此处 执行其他处理方法
         */
        return $this->afterSetConf();
    }

    /**
     * 解析 任意类型 resper 响应者类中 定义的 resper 响应方法 | api 方法 必须在注释中包含 * resper | * api 的 public 方法
     * 解析得到的此响应者类 可用的 响应方法 信息数组 保存到 $this->context["respers"]
     * !! 子类可覆盖此方法
     * @param String $rcls 响应者类全称，不指定则使用当前响应者，默认 null
     * @return $this
     */
    protected function parseResperMethods($rcls=null)
    {
        //获取此 config 实例对应的 响应者 类全称 NS\app\FooBar
        if (!Is::nemstr($rcls)) $rcls = $this->getResperCls();
        //对应响应者的 类名 FooBar
        $rn = Cls::name($rcls);
        //根据响应者类全称，获取路径信息
        $pi = $rcls::pathinfo();
        if (!Is::nemarr($pi)) return $this;

        //响应者类型
        $rtype = $pi["rtype"];
        //根据 类全称 解析得到 操作标识 前缀
        $oprpre = $pi["oprn"];  //Operation::getResperOperatePrefix($rcls);
        //获取 响应者类 定义的 intr 属性的值
        $rcps = Cls::ref($rcls)->getDefaultProperties();
        $rintr = $rcps["intr"] ?? Str::camel($rn, true);
        $rintr = (
            $rtype=="App" ? "[应用]" : (
                $rtype=="Module" ? "[模块]" : "[自定义]"
            )
        ).$rintr;

        //在 响应者类中 查找 public,&!static 方法，且 注释中带有 * resper 标记的方法
        $rms = Cls::specific(
            $rcls,
            "public,&!static",
            "resper",
            null,
            function($mi, $conf) use ($oprpre, $rintr) {
                //为这些方法添加 uac 相关信息
                $conf = Cls::parseMethodInfoWithUac($mi, $conf, $oprpre, $rintr);
                return $conf;
            }
        );

        //在 响应者类中 查找 public,&!static 方法，且 注释中带有 * api 标记 以及方法名带有 -Api后缀 的方法
        $apis = Cls::specific(
            $rcls,
            "public,&!static",
            "api",
            null,
            function($mi, $conf) use ($oprpre, $rintr) {
                //为这些方法添加 uac 相关信息
                $conf = Cls::parseMethodInfoWithUac($mi, $conf, "api/".$oprpre, $rintr);
                return $conf;
            }
        );

        //写入 $this->context 中
        if (!isset($this->context["respers"])) $this->context["respers"] = [];
        if (!isset($this->context["apis"])) $this->context["apis"] = [];
        $this->context["respers"] = Arr::extend($this->context["respers"], $rms);
        $this->context["apis"] = Arr::extend($this->context["apis"], $apis);

        return $this;
    }

    /**
     * 针对 app|module 类型的响应者，查找其路径内部 可能定义的 Resper 响应者类
     * 读取这些内部 响应者类，解析取得 respers|apis 方法信息，保存到 context
     * !! 子类可覆盖
     * @return $this
     */
    protected function parseSubResperMethods()
    {
        //获取此 config 实例对应的 响应者 类全称 NS\app\FooBar
        $rcls = $this->getResperCls();
        //对应响应者的 类名 FooBar
        $rn = Cls::name($rcls);
        //根据响应者类全称，获取路径信息
        $pi = $rcls::pathinfo();
        if (!Is::nemarr($pi)) return $this;

        //响应者类型
        $rtype = $pi["rtype"];
        //操作标识前缀
        $oprn = $pi["oprn"];
        
        if (in_array($rtype, ["App", "Module"])) {
            //响应者 内部自定义 resper 类保存路径
            $rp = $pi["path"];
            if ($rtype=="App") $rp .= "/library";
            //实际路径
            $d = Path::find($rp, ["checkDir"=>true]);
            if (empty($d) || !is_dir($d)) return $this;
            //在路径下查找 resper 类
            $this->recursiveParseSubResperMethods($d, $oprn);
        }

        return $this;
    }

    /**
     * 递归查找指定路径下的 resper 类，并解析类中定义的 respers|apis 方法信息，保存到 context 
     * @param String $dir 要查找的目录
     * @param String $oprn 类名路径
     * @return $this
     */
    protected function recursiveParseSubResperMethods($dir, $oprn)
    {
        //在路径下查找 resper 类
        $dh = @opendir($dir);
        while (($fn = readdir($dh))!==false) {
            if ($fn=="." || $fn=="..") continue;
            if (is_dir($dir.DS.$fn)) {
                $this->recursiveParseSubResperMethods($dir.DS.$fn, $oprn."/".$fn);
            };
            if (substr($fn, strlen(EXT)*-1)!=EXT) continue;
            //构建类全称
            $sclsn = str_replace(EXT,"",$fn);
            $subcls = $oprn."/".$sclsn;
            $subcls = Cls::find($subcls);
            //确认找到的类 是 Resper 类
            if (empty($subcls) || !class_exists($subcls) || !is_subclass_of($subcls, Resper::class)) continue;
            //解析此类中的 respers|apis 方法信息
            $this->parseResperMethods($subcls);
        }
        return $this;
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
        //如果未指定 orm 参数
        if (!Is::nemarr($ormc)) {
            //填充默认关闭的 orm 参数，然后返回
            $this->context["orm"] = [
                "enable" => false
            ];
            return $this;
        }
        $enable = $ormc["enable"] ?? false;
        $dbs = $ormc["dbs"] ?? [];
        //如果未启用 orm，或 未指定要使用的数据库
        if (!is_bool($enable) || $enable!==true || !Is::nemarr($dbs) || !Is::associate($dbs)) {
            //关闭 orm 清空 dbs 预设
            $this->context["orm"] = [
                "enable" => false,
                "dbs" => []
            ];
        }

        //orm 参数不需要其他预处理，在 Orm 实例化时，由 Orm 类自行处理
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
            $this->context["uac"] = [
                "enable" => false
            ];
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
        unset($mid["in"]);
        unset($mid["out"]);
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
        $this->context["middleware"] = Arr::extend($mid, [
            "in" => $in,
            "out" => $out
        ]);

        //取得所有定义的 中间件 类全称 列表
        $all = array_merge([], $in, $out);
        //循环调用 这些中间件的 initConf 预设参数初始化处理方法
        foreach ($all as $mclsn) {
            //如果未定义 此中间件的参数，跳过
            if (!isset($this->context["middleware"][$mclsn])) continue;
            //调用中间件自定义的 预设参数初始化处理方法
            $mc = $mclsn::initConf($this->context["middleware"][$mclsn]);
            //处理后的参数 写回 context
            if (Is::nemarr($mc)) {
                $this->context["middleware"][$mclsn] = $mc;
            }
        }

        return $this;
    }



    /**
     * 获取此 config 实例对应的 响应者类全称
     * 对于 Resper 类型的 自定义响应者类，直接有属性 $this->rcls 储存了对应 响应者类全称，
     * 其他类的 响应者类的类全称，需要通过解析当前 config 类全称 来获取
     * @return String|null 对应 响应者类全称
     */
    protected function getResperCls()
    {
        if (isset($this->rcls)) {
            $rcls = $this->rcls;
            if (Is::nemstr($rcls) && class_exists($rcls)) return $rcls;
            return null;
        }

        //根据当前 FooConfig 类全称，解析得到对应的 响应者类全称
        $clsn = get_class($this);
        $clsa = explode("\\", $clsn);
        /**
         * 去除当前类名，响应者类名 和 对应的 config 类名的 对应关系为：
         *  resper:     NS\Foo          --> NS\foo\Config
         *  module:     NS\module\Foo   --> NS\module\foo\Config
         *  app:        NS\app\FooBar   --> NS\app\foo_bar\Config
         */
        /*$cclsn = */array_pop($clsa);
        //响应者路径名 foo_bar 形式
        $rn = array_pop($clsa);
        //转为响应者类名  foo_bar  -->  FooBar
        $clsa[] = Str::camel($rn, true);
        //连接得到 对应响应者类全称
        $rcls = implode("\\", $clsa);
        //如果此类不存在，直接返回
        if (!class_exists($rcls)) return null;
        return $rcls;
    }

}