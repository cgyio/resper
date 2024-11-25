<?php
/**
 * cgyio/resper 核心类
 * Orm 数据库操作类
 * 
 * Orm 数据库操作绑定到每一个 Resper 类，跟随 Resper 类同时实例化
 * 以这些方式，访问并操作数据库：
 *      $resper->orm->Dbn 访问数据库，执行操作
 *      $resper->orm->Dbn->Model 访问数据表类
 *      $resper->orm->Dbn->Model->where()->select() 执行 CURD
 * 
 * 一次会话，只实例化一个 Orm 实例，因此：
 *      Orm::Dbn() == $resper->orm->Dbn
 * 
 */

namespace Cgy;

use Cgy\Resper;
use Cgy\Event;
use Cgy\orm\Db;
use Cgy\orm\Model;
use Cgy\Request;
use Cgy\util\Is;
use Cgy\util\Str;
use Cgy\util\Cls;

use Cgy\traits\staticCurrent;

class Orm 
{
    //引入trait
    use staticCurrent;

    /**
     * current
     * 缓存已实例化的 Orm 类
     */
    public static $current = null;

    /**
     * 缓存已实例化的 Db 数据库类 
     */
    public static $DB = [
        /*
        db_key => Db 实例
        */
    ];

    //依赖 Resper 实例
    public $resper = null;

    //Orm 初始化参数
    public $config = [];

    /**
     * 记录 Orm 是否已经初始化
     */
    public static $inited = false;
    //Orm 服务初始化时，必须要加载的 Db 数据库列表
    public static $initRequiredDbs = [
        //"Uac"
    ];

    /**
     * 构造
     * @param Resper $resper 传入关联的 Resper 实例
     * @return void
     */
    public function __construct($resper)
    {
        //依赖 Resper 实例
        $this->resper = $resper;
        $conf = $resper->conf;

        //Orm 参数
        $ormc = $conf["orm"] ?? [];
        if (empty($ormc)) return null;
        $this->config = $ormc;

        //数据库驱动类
        $driver = $this->driver;
        //创建 Medoo 连接参数
        $this->config["medoo"] = $driver::initMedooParams($this->config);
        
        //如果定义了 required 参数
        $reqs = $this->config["required"] ?? [];
        if (Is::nemarr($reqs) && Is::indexed($reqs)) {
            //创建 所有 required Db 数据库实例
            for ($i=0;$i<count($reqs);$i++) {
                $this->db($reqs[$i]);
            }
        }

        /**
         * 触发 orm-created 事件
         */
        Event::trigger("orm-created", $this);

    }

    /**
     * __get
     * @param String $key
     * @return Mixed
     */
    public function __get($key)
    {
        $conf = $this->config;

        /**
         * $this->driver 获取 db driver 类全称
         */
        if ($key == "driver") {
            $dbtp = $conf["type"];
            return self::driver($dbtp);
        }

        /**
         * $this->Dbn 获取/创建 Db 数据库实例
         */
        $dbns = $conf["dbns"] ?? [];
        if (in_array($key, $dbns)) return $this->db($key);
        if (Str::beginUp($key)) {
            //取消首字母大写
            $lkey = lcfirst($key);
            if (in_array($lkey, $dbns)) return $this->db($lkey);
        }

        /**
         * 
         */


        return null;
    }

    /**
     * 连接数据库，创建 Db 实例
     * @param String $dbn 数据库名称
     * @return Db 实例
     */
    public function db($dbn)
    {
        $conf = $this->config;
        $medoo = $conf["medoo"] ?? [];
        if (empty($medoo) || !isset($medoo[$dbn])) return null;
        $opt = $medoo[$dbn];
        $driver = $this->driver;
        $dbkey = $driver::dbkey($opt);
        if (isset(self::$DB[$dbkey])) return self::$DB[$dbkey];
        $db = $driver::connect($opt);
        //依赖注入
        $db->dependency([
            "resper" => $this->resper,
            "orm" => $this
        ]);
        return $db;
    }

    /**
     * 判断 数据库是否存在
     * @param String $dbn 数据库名称
     * @return Mixed 存在则返回正确的数据库名，不存在返回 false
     */
    public function hasDb($dbn)
    {
        $dbns = $this->config["dbns"] ?? [];
        if (in_array($dbn, $dbns)) return $dbn;
        $ldbn = lcfirst($dbn);
        if (in_array($ldbn, $dbns)) return $ldbn;
        return false;
    }



    /**
     * 替代 resper 实例，响应数据库操作请求
     * 发送到 任意 resper 的 db/*** 请求，将被转发到此
     * @param Array $args 请求的 URI 
     * @return Mixed
     */
    public function response(...$args)
    {
        if (empty($args)) trigger_error("orm::无法执行数据库操作，缺少必要参数", E_USER_ERROR);

        //检查 URI 参数是否包含数据库名
        if (false !== ($dbn = $this->hasDb($args[0]))) {
            $db = $this->db($dbn);
            array_shift($args);     //dbn
            //检查 URI 参数是否包含数据表名
            if (!empty($args) && false !== $db->hasModel($args[0])) {
                $model = $db->model($args[0]);
                array_shift($args); //mdn
            } else {
                $model = null;
            }
        } else {
            $db = null;
            $model = null;
        }

        //指定了 数据库或数据表 但是未指定 操作方法，则返回 数据库/数据表 的运行时参数
        if (empty($args) && (!is_null($db) || !is_null($model))) {
            if (is_null($model)) {
                $dcf = $db->config->ctx();
                unset($dcf["model"]);
                return $dcf;
            }
            return $model::$config->context;
        }

        //执行 数据库/数据表(模型) 的 API 方法
        $api = array_shift($args);
        if (is_null($model)) return $db->execApis($api, ...$args);
        return $model::execApis($api, ...$args);
        
    }



    /**
     * static tools
     */

    /**
     * 判断 指定的 resper 类路径下，是否存在 数据库/数据表
     * 通过在
     */

    /**
     * 获取 Db Driver
     * @param String $type 数据库类型
     * @return String driver 类全称
     */
    public static function driver($type)
    {
        return Cls::find("orm/driver/".ucfirst(strtolower($type)));
    }

    /**
     * Orm 初始化
     * 当实例化任意 Db 时，将执行此操作
     * @param String $callby 此初始化动作是在哪个 Db 中被调用
     * @param Array $opt Orm 服务初始化参数
     * @return Orm
     */
    public static function Init($callby, $opt=[])
    {
        if (self::$inited==false) {
            $reqs = self::$initRequiredDbs;
        } else {
            $reqs = [];
        }

        //按顺序，实例化 Orm 服务必须的 Db
        $confreqs = $opt["requiredApps"] ?? [];
        $reqs = array_merge($reqs, $confreqs);
        if (!empty($reqs)) {
            $reqs = array_merge(array_flip(array_flip($reqs)));
            if (in_array($callby, $reqs)) array_splice($reqs, array_search($callby, $reqs), 1);
            if (!empty($reqs)) {
                for ($i=0;$i<count($reqs);$i++) {
                    $dbn = ucfirst($reqs[$i]);
                    Orm::$dbn();
                }
            }
        }

        self::$inited = true;
        return self::class;
    }

    /**
     * __callStatic
     * @param String $key
     * @param Mixed $args
     */
    public static function __callStatic($key, $args)
    {
        /**
         * Orm::Db()
         * 返回 Db 实例
         */
        if (Orm::hasApp($key)) {
            //已实例化的，直接返回
            if (Orm::appInsed($key)) return Orm::$APP[$key];
            //实例化 DbApp
            $cls = cls("app/$key");
            if (!class_exists($cls)) return null;
            $app = new $cls();
            //缓存
            Orm::cacheApp($app);
            //返回 DbApp 实例
            return $app;
        }

        return null;
    }

    /**
     * 获取 app 路径下所有 可用的 DbApp name
     * @return Array [ DbAppName, ... ]
     */
    public static function apps()
    {
        $appcls = Orm::cls("DbApp");
        $dir = APP_PATH;
        $dh = @opendir($dir);
        $apps = [];
        while(($app = readdir($dh))!==false) {
            if ($app=="." || $app=="..") continue;
            $dp = $dir.DS.$app;
            if (!is_dir($dp)) continue;
            if (!file_exists($dp.DS.ucfirst($app).EXT)) continue;
            $cls = cls("app/".ucfirst($app));
            if (!class_exists($cls)) continue;
            if (!is_subclass_of($cls, $appcls)) continue;
            $apps[] = ucfirst($app);
        }
        return $apps;
    }

    /**
     * 判断是否存在 给出的 DbApp
     * @param String $app DbApp name like: Uac
     * @return Bool
     */
    public static function hasApp($app)
    {
        $apps = Orm::apps();
        return in_array(ucfirst($app), $apps);
    }

    /**
     * 判断给出的 DbApp 是否已经实例化
     * @param String $app DbApp name like: Uac
     * @return Bool
     */
    public static function appInsed($app)
    {
        return isset(Orm::$APP[ucfirst($app)]);
    }

    /**
     * 缓存 DbApp 实例
     * @param DbApp $app 实例
     * @return Orm self
     */
    public static function cacheApp($app)
    {
        if (!$app instanceof DbApp) return self::class;
        $appname = $app->name;
        Orm::$APP[$appname] = $app;
        return self::class;
    }

    /**
     * 创建 DbApp 路径以及文件
     * @param String $app name
     * @param Array $opt 更多创建参数
     * @return Orm self
     */
    public static function createAppFile($app, $opt=[])
    {
        //即使存在 app 路径，也需要检查是否存在下级必要路径，因此不检查 app 主路径是否存在
        //if (self::hasApp($app)) return self::class;
        $dbs = $opt["dbOptions"] ?? [];
        $dbns = array_keys($dbs);
        $app = strtolower($app);
        //创建主文件夹
        $approot = APP_PATH.DS.$app;
        if (!is_dir($approot)) @mkdir(APP_PATH.DS.$app, 0777);
        //创建必要目录
        $ds = [
            "assets","db","library","model","page",
            "db".DS."sqlite",
            "db".DS."config"
        ];
        for ($i=0;$i<count($dbns);$i++) {
            $ds[] = "model".DS.strtolower($dbns[$i]);
            $ds[] = "db".DS."config".DS.strtolower($dbns[$i]);
        }
        foreach ($ds as $i => $di) {
            $diri = $approot.DS.$di;
            if (!is_dir($diri)) @mkdir($diri, 0777);
        }
        //创建 app 主文件
        $mf = $approot.DS.ucfirst($app).EXT;
        if (!file_exists($mf)) {
            $tmpd = [
                "app" => [
                    "name" => ucfirst($app),
                    "intr" => ""
                ],
                "dbOptions" => arr_extend([
                    "main" => [
                        "type" => "sqlite",
                        "database" => "main.db"
                    ]
                ], $dbs),
            ];
            var_export($tmpd["dbOptions"]);
            //从缓冲区读取 sql
            $dbos = ob_get_contents();
            //清空缓冲区
            ob_clean();
            $dbos = str_replace("array (","[", $dbos);
            $dbos = str_replace(")","]", $dbos);
            $dbos = str_replace("'","\"", $dbos);
            $tmpd["dbos"] = $dbos;
            $tmp = file_get_contents(path_find("root/library/temp/dbapp.tmp"));
            $tmp = str_tpl($tmp, $tmpd);
            $fh = @fopen($mf, "w");
            @fwrite($fh, $tmp);
            @fclose($fh);
        }
        return self::class;
    }

    /**
     * 创建 DbApp 路径下 Model 路径以及文件
     * @param String $app name like: Uac
     * @param String $dbn db name like: main
     * @param Array $conf model 参数 数据表参数 json 内容
     * @return Orm self
     */
    public static function createModelFile($app, $dbn, $conf=[])
    {
        if (empty($conf)) return self::class;
        $mdn = $conf["name"] ?? null;
        if (empty($mdn)) return self::class;
        $mdp = APP_PATH.DS.strtolower($app).DS."model".DS.strtolower($dbn);
        if (!is_dir($mdp)) @mkdir($mdp, 0777);
        $mdf = $mdp.DS.ucfirst($mdn).EXT;
        if (file_exists($mdf)) return self::class;
        $tmp = file_get_contents(path_find("root/library/temp/model.tmp"));
        $tmp = str_tpl($tmp, [
            "appName" => ucfirst($app),
            "dbName" => strtolower($dbn),
            "modelName" => ucfirst($mdn),
            "table" => [
                "name" => $conf["table"],
                "title" => $conf["title"],
                "desc" => $conf["desc"],
            ]
        ]);
        $fh = @fopen($mdf, "w");
        @fwrite($fh, $tmp);
        @fclose($fh);
        return self::class;
    }


    
    /**
     * 获取 attoorm 类
     * @param String $clspath like: foo/Bar  --> \Atto\Orm\foo\Bar
     * @return Class 类全称
     */
    public static function cls($clspath)
    {
        return self::NS.str_replace("/", "\\", trim($clspath, "/"));
    }
}