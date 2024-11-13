<?php
/**
 * cgyio/resper 通用预设参数
 * 在 index.php 中，可通过 Resper::start([...]) 修改
 * 
 * 参数在初始化完成后，都被定义为 全局常量
 * 
 */

namespace Cgy\config;

use Cgy\Resper as Rp;
use Cgy\Configer;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Path;

class Resper extends Configer 
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
         * 特殊路径/文件夹
         * !! 尽量使用默认设置
         */
        "dir" => [
            //class 文件路径
            "lib"       => "modules,library, plugin",
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
            "formats"   => ",html,page,json,xml,str,dump",
            "format"    => "html",
            "lang"      => "zh-CN",     //输出语言
            "psr7"      => false,		//是否以Psr-7标准返回响应
        ],

        /**
         * 数据库预设
         * !! 尽量使用默认设置
         */
        "db" => [
            "base"      => "",              //db path for current website, cover PATH
            "type"      => "sqlite",        //default DB type, [mysql, sqlite, ...] 
            "route"     => "dbm",           //默认的 db 管理路由，各站点可根据需求 extend 此路由，并在此指定新路由 name（类名称）
            "formui"    => "Elementui",     //default frontend From UI-framework
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
            ],
            */
        ],
    ];

    //各 module 的 configer 实例
    public $module = null;

    //各 app 的 configer 实例
    public $app = null;

    /**
     * 在 应用用户设置后 执行
     * !! 子类可覆盖
     * @return $this
     */
    public function afterSetConf()
    {
        //用户设置应用完成后，执行 config 初始化动作

        //初始化 web 路径
        $this->initWebPath();
        //初始化 各设置项
        $this->initConf();

        return $this;
    }


    /**
     * 初始化
     * 初始化 web 路径  并定义为常量
     * @return $this
     */
    public function initWebPath()
    {
        $root = $this->ctx("web/root");
        $root = $root=="" ? PRE_PATH : PRE_PATH.DS.str_replace("/",DS,trim($root,"/"));
        $defs = [
            "root_path"     => $root,
            "app_path"      => $root . DS . "app",
            "route_path"    => $root . DS . "route",
            "asset_path"    => $root . DS . "asset",
            "src_path"      => $root . DS . "assets",
            "assets_path"   => $root . DS . "assets",
            "lib_path"      => $root . DS . "library",
            "db_path"       => $root . DS . "library" . DS ."db",
            "MODEL_path"    => $root . DS . "model",
            //"record_path"   => $root . DS . "record",
            //"opr_path"      => $root . DS . "operater",
            "page_path"     => $root . DS . "page",
            //"plugin_path"   => $root . DS . "plugin",
            "cache_path"    => $root . DS . "cache",
        ];
        Rp::def($defs);
        return $defs;
    }

    /**
     * 初始化
     * 处理 context 中的各项 设置内容
     * @return $this
     */
    public function initConf()
    {
        $ctx = $this->context;
        foreach ($ctx as $k => $v) {
            $m = "init".ucfirst(strtolower($k))."Conf";
            if (method_exists($this, $m)) {
                //定义了设置项处理方法
                $this->$m($v);
            } else {
                //未定义处理方法，则默认定义为 常量
                //以 item 名称作为常量前缀
                $k = strtolower($k);
                Rp::def($v, $k);
            }
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
        Rp::def($conf, "web");
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
            $cls = Rp::cls($mdn."/Config");
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
        //定义常量 无前缀
        Rp::def($this->ctx("module"), "");
        //各 module configer 实例缓存到 $this
        $this->module = (object)$mco;
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
            $cls = Rp::cls("App/".$apn."/Config");
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
            //保存 module configer 实例
            $aco[$apn] = $cfg;
        }
        @closedir($aph);
        //定义常量 前缀：APP_
        Rp::def($this->ctx("app"), "app");
        //各 app configer 实例缓存到 $this
        $this->app = (object)$aco;
        return $this;
    }


}