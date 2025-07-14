<?php
/**
 * cgyio/resper 通用预设参数
 * 在 index.php 中，可通过 Resper::start([...]) 修改
 * 
 * 可以自动配置 App/Module/自定义Resper 类型的 响应者类的参数
 * 处理后的参数保存在 $config->context[ app/appName | module/moduleName | 自定义Resper名称 ] 
 * 各类型参数配置器实例保存在 $config->[ app | module | resper ]->[name]
 * 
 * 其他全局参数在初始化完成后，都被定义为 全局常量
 */

namespace Cgy;

use Cgy\Resper;
use Cgy\module\Configer;
use Cgy\module\configer\ResperConfig;
use Cgy\Event;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Path;
use Cgy\util\Cls;

class Config extends Configer 
{
    /**
     * 预设的设置参数
     * !! 子类自定义
     * !! Resper::start([ ... ]) 参数格式应与此处一致
     */
    protected $init = [

        /**
         * 框架启动时 php 初始设置参数
         * !! 这些参数不要变
         */
        "php" => [
            "error_reporting" => -1,
            "timezone" => "Asia/Shanghai",
            "ini_set" => [
                "display_errors" => "1"
            ],
        ],
        
        /**
         * 特殊路径/文件夹
         * !! 尽量使用默认设置
         */
        "dir" => [
            //class 文件路径
            "lib"       => "library,plugin",
            //sqlite 数据库保存路径
            "db"        => "db,library/db",
            //数据表(模型) 类
            "model"     => "model,library/model,library/db,library/db/model",
            //assets 路径
            "asset"     => "assets,assets/library,asset,asset/library,src,src/library,public,page",
            //文件上传路径
            "upload"    => "uploads",
        ],

        /**
         * response export 参数
         * !! 尽量使用默认设置
         */
        "export" => [
            "formats"   => "pause,html,page,json,xml,str,dump",
            "format"    => "html",
            "ajax"      => "json",
            "lang"      => "zh-CN",     //输出语言
            "psr7"      => false,		//是否允许以Psr-7标准返回响应
            "errpage"   => "",          //错误页面名称
            "pausepage" => "",          //WEB_PAUSE 显示的页面名称
        ],

        /**
         * 数据库预设
         * !! 尽量使用默认设置
         */
        "db" => [
            //"base"      => "library/db",    //db path for current website, cover PATH
            "type"      => "sqlite",        //default DB type, [mysql, sqlite, ...] 
            "route"     => "dbm",           //默认的 db 管理路由，各站点可根据需求 extend 此路由，并在此指定新路由 name（类名称）
            "formui"    => "Elementui",     //default frontend From UI-framework
        ],

        

        /**
         * web 参数
         * !! 用户可在启动时 修改这些参数
         */
        "web" => [
            //当前网站根目录，从 PRE_PATH 起始，不指定则以 PRE_PATH 作为网站根目录
            "root" => "",
            //当前网站 key, 应该是网站根目录 文件夹名
            "key" => "",

            //网站参数
            "protocol"      => "https",
            "domain"        => "cgydev.work",
            "ip"            => "124.223.97.67",
            "ajaxallowed"   => ",cgydev.work",

            //开关
            //是否显示debug信息
            "debug" => false,	
            //暂停网站
            "pause" => false,	
            //日志开关
            "log" => false,

            //其他 web 参数

            //阿里云参数
            "ali" => [
                //安装ssl证书，首次需要开启此验证，通过后即可关闭
                "sslcheck" => false
            ],

        ],


        
        /**
         * 各 module 自定义参数
         * !! 在 Resper::start([ ... ]) 写入自定义参数
         */
        "module" => [
            /*
            "module name" => [
                修改各 \Cgy\module\Config 类的 init 参数
            ],
            */
        ],



        /**
         * 各 app 自定义参数
         * !! 在 Resper::start([ ... ]) 写入自定义参数
         */
        "app" => [
            /*
            "app name" => [
                修改各 \Cgy\App\appname\Config 类的 init 参数
                
                "name" => "乾耀PMS",

                //App 数据库参数
                "orm" => [
                    "enable" => true,
                    "type" => "sqlite",
                    "dirs" => "../foo/bar/libary/db",   //可以不在当前响应者路径下
                    "models" => "../../model/foo",      //可以不再当前响应者路径下
                    "required" => [
                        "usr",...
                    ],
                ],
            ],
            */
        ],

        /**
         * 可手动定义 额外的 response 相应参数
         * !! 在 Resper::start([ ... ]) 写入自定义参数
         */
        "response" => [

        ],

        /**
         * 可手动定义 自定义 resper 响应者类
         * !! 在 Resper::start([ ... ]) 写入自定义参数
         * 这些类通常保存在 webroot/library 路径下
         * 可以为这些类定义对应的 config 类，类文件应为 webroot/library/[custom resper name]/Config.php
         */
        /*
        "Foo" => [
            "config" => "\\Cgy\\foo\\Config",  自定义响应者的 config 类全称
            或者直接在此处定义参数，形式与 module/configer/BaseConfig 类要求一致
            ...
        ],
        */

    ];

    /**
     * 固定的 常量
     */
    protected $defines = [
        "version" => "0.0.1",       //版本升级时修改
        "ds" => DIRECTORY_SEPARATOR,
        "ns" => "Cgy\\",    //"\\Cgy\\",
        "ext" => ".php",
    ];

    //各 resper 的 configer 实例
    public $resper = null;

    //各 module 的 configer 实例
    public $module = null;

    //各 app 的 configer 实例
    public $app = null;

    /**
     * 在 应用用户设置后 执行
     * !! 覆盖父类
     * @return $this
     */
    public function afterSetConf()
    {
        //用户设置应用完成后，执行 config 初始化动作

        //定义 固定常量 / 系统路径
        $this->initStatic();
        //初始化 web 路径
        $this->initWebPath();
        //定义 特殊路径 DIR_***
        $this->initSpecialPath();

        return $this;
    }
    
    /**
     * 定义固定常量 / 系统路径
     * @return $this
     */
    protected function initStatic()
    {
        //定义 固定常量
        self::def($this->defines);
        //定义 系统路径常量
        $pre = Path::fix(__DIR__.DS."..".DS."..".DS."..".DS."..".DS."..");
        $vdp = $pre.DS."vendor";
        $cgp = $vdp.DS."cgyio";
        $rep = $cgp.DS."resper".DS."src";
        $mdp = $rep.DS."module";
        $path = [
            "pre_path" => $pre,
            "vendor_path" => $vdp,
            "cgy_path" => $cgp,
            "resper_path" => $rep,
            "module_path" => $mdp
        ];
        self::def($path);
        //路径常量合并到 $defines
        $this->defines = Arr::extend($this->defines, $path);
        return $this;
    }

    /**
     * 初始化
     * 初始化 web 路径  并定义为常量
     * @return $this
     */
    protected function initWebPath()
    {
        $root = $this->ctx("web/root");
        $root = $root=="" ? PRE_PATH : PRE_PATH.DS.str_replace("/",DS,trim($root,"/"));
        $defs = [
            "root_path"     => $root,
            "app_path"      => $root . DS . "app",
            //"route_path"    => $root . DS . "route",
            //"asset_path"    => $root . DS . "asset",
            "src_path"      => $root . DS . "assets",
            "assets_path"   => $root . DS . "assets",
            "lib_path"      => $root . DS . "library",
            //"db_path"       => $root . DS . str_replace("/", DS, trim($this->ctx("db/base"), "/")),   //"library" . DS ."db",
            //"db_path"       => $root . DS . "library" . DS ."db",
            //"model_path"    => $root . DS . "model",
            //"record_path"   => $root . DS . "record",
            //"opr_path"      => $root . DS . "operater",
            "page_path"     => $root . DS . "page",
            //"plugin_path"   => $root . DS . "plugin",
            "cache_path"    => $root . DS . "cache",
        ];
        self::def($defs);
        return $defs;
    }

    /**
     * 初始化
     * 定义 特殊路径 DIR_***
     * @return $this
     */
    protected function initSpecialPath()
    {
        $dirs = $this->ctx("dir");
        self::def($dirs, "dir");
        return $this;
    }

    /**
     * 初始化
     * 处理 context 中的各项 设置内容
     * @return $this
     */
    public function initConf()
    {
        $ctx = $this->context;
        //自定义的 Resper 响应者类列表
        $respers = [];
        foreach ($ctx as $k => $v) {
            //dir 已定义
            if ($k=="dir") continue;
            $m = "init".ucfirst(strtolower($k))."Conf";
            if (method_exists($this, $m)) {
                //定义了设置项处理方法
                $this->$m($v);
            } else if (Resper::has($k)!==false) {
                /**
                 * 针对自定义 resper 响应者
                 * 建立 Resper configer 实例
                 * 如果参数中定义了 config 类，直接使用
                 * 否则 使用 ResperConfig 类 
                 */
                $ccls = $v["config"] ?? null;
                if (Is::nemstr($ccls) && class_exists($ccls)) {
                    //参数定义了 config 类，实例化
                    unset($v["config"]);
                    $cfg = new $ccls($v);
                } else {
                    //未定义 config，直接使用 ResperConfig 类
                    $cfg = new ResperConfig($v);
                }
                //将 处理完的参数 写入 context
                $ctx = $cfg->ctx();
                $this->context = Arr::extend($this->context, [
                    $k => $ctx
                ]);
                //保存 ResperConfig 实例
                $respers[$k] = $cfg;
            } else {
                //未定义处理方法，则默认定义为 常量
                //以 item 名称作为常量前缀
                $k = strtolower($k);
                self::def($v, $k);
            }
        }
        if (Is::nemarr($respers)) {
            //所有自定义 resper 响应者类的 configer 实例，保存到 $this->resper
            $this->resper = (object)$respers;
        }
        return $this;
    }
    // initPhpConf 执行初始 php 设置
    protected function initPhpConf($conf = [])
    {
        if (isset($conf["error_reporting"])) {
            //0/-1 = 关闭/开启
            @error_reporting($conf["error_reporting"]);
            //unset($conf["error_reporting"]);
        }
        if (isset($conf["timezone"])) {
            //时区
            @date_default_timezone_set($conf["timezone"]);
            //unset($conf["timezone"]);
        }
        if (isset($conf["ini_set"])) {
            //ini_set
            $ist = $conf["ini_set"];
            if (Is::nemarr($ist)) {
                foreach ($ist as $k => $v) {
                    ini_set($k, $v);
                }
            }
            //unset($conf["ini_set"]);
        }
        return $this;
    }
    // initWebConf 初始化 web 参数
    protected function initWebConf($conf = [])
    {
        if ($conf["key"]=="") {
            $conf["key"] = strtolower(array_slice(explode("/",$conf["root"]), -1)[0]);
        }
        //定义常量
        self::def($conf, "web");
        return $this;
    }
    // initModuleConf 初始化 各 module config
    protected function initModuleConf($conf = [])
    {
        $mco = [];
        $mds = MODULE_PATH;
        $mdh = @opendir($mds);
        while (($md = @readdir($mdh)) !== false) {
            if ($md=="." || $md=="..") continue;
            $mdp = $mds.DS.$md;
            if (!is_dir($mdp)) continue;
            //模块名 必须小写
            $mdn = strtolower($md);
            //获取 module/Config 设置类
            $cls = Cls::find("module/".$mdn."/Config");
            if (empty($cls)) continue;
            //读取用户设置项
            $opt = $this->ctx("module/".$mdn);
            if (!Is::nemarr($opt)) {
                $opt = [];
            }
            //建立 module configer 实例
            $cfg = new $cls($opt);
            //将 module 参数 写入 context
            $this->context = Arr::extend($this->context, [
                "module" => [
                    $mdn => $cfg->ctx()
                ]
            ]);
            //保存 module configer 实例
            $mco[$mdn] = $cfg;
        }
        @closedir($mdh);
        //各 module configer 实例缓存到 $this
        $this->module = (object)$mco;

        /**
         * 等待 Resper::$resper 响应类确定后，再定义 MODULE_*** 常量
         */
        //定义常量 无前缀
        //self::def($this->ctx("module"), "");
        //订阅一次性事件
        /*Event::addHandlerOnce("responder-created", $this, function($responder, $rtype) {
            var_dump("Event resper-created triggered !!");
            var_dump($this);
            var_dump($responder);
            var_dump($rtype);
        });*/
        
        return $this;
    }
    // initAppConf 初始化 各 app config
    protected function initAppConf($conf = [])
    {
        $aco = [];
        $apd = APP_PATH;
        $aph = @opendir($apd);
        while (($app = @readdir($aph)) !== false) {
            if ($app=="." || $app=="..") continue;
            $appd = $apd.DS.$app;
            if (!is_dir($appd)) continue;
            //app 名称 必须小写
            $apn = strtolower($app);
            if (!file_exists($appd.DS.ucfirst($apn).EXT)) continue;
            //获取 App/apn/Config 设置类
            $cls = Cls::find("App/".$apn."/Config");
            if (empty($cls)) continue;
            //读取用户设置项
            $opt = $this->ctx("app/".$apn);
            if (!Is::nemarr($opt)) {
                $opt = [];
            }
            //建立 app configer 实例
            $cfg = new $cls($opt);
            //将 app 参数 写入 context
            $this->context = Arr::extend($this->context, [
                "app" => [
                    $apn => $cfg->ctx()
                ]
            ]);
            //var_dump($cfg->ctx());
            //保存 module configer 实例
            $aco[$apn] = $cfg;
        }
        @closedir($aph);
        //各 app configer 实例缓存到 $this
        $this->app = (object)$aco;
        
        //定义常量 前缀：APP_
        //self::def($this->ctx("app"), "app");

        return $this;
    }


    /**
     * 运行时 修改 Resper::$config->context
     * @param Array $opt
     * @return $this
     */
    public function runtimeSet($opt = [])
    {
        $this->context = Arr::extend($this->context, $opt);
        return $this;
    }


}