<?php
/**
 * cgyio/resper Orm 数据库操作类
 * Db 数据库类 
 * 此类可直接操作数据库
 * 一次会话，一个数据库只实例化一次
 * 
 * 创建 Db 实例：
 *      !! 应通过 Orm 实例创建 Db 实例
 *      $db = $resper->orm->db($dbn) 或者 $resper->orm->Dbn
 * 手动依赖注入：
 *      !! 通过 Orm 实例创建的 Db 实例，已自动注入依赖项
 *      $db->dependency([
 *          "resper"    => $resper,         依赖 Resper 响应者实例
 *          "orm"       => $resper->orm     依赖关联的 Orm 实例
 *      ])
 * 初始化一个 curd 操作，链式调用：
 *      $db->Model->join(false)->field("*")->where([...])->limit([0,20])->order(["foo"=>"DESC"])->select();
 * 获取数据表(模型)类：
 *      $model = $db->Model;
 *      $conf = $model::$configer;
 *      $name = $model::$name;
 * 获取数据表(模型)实例，以 实例方式 调用 类方法/类属性
 *      $table = $db->ModelTb;
 *      $conf = $table->tbConfiger;
 *      $name = $table->tbName;
 */

namespace Cgy\orm;

use Cgy\Orm;
use Cgy\orm\Config;
//use Cgy\orm\Table;
use Cgy\orm\Model;
use Cgy\orm\Curd;
use Cgy\Resper;
use Cgy\Request;
use Cgy\Response;
use Cgy\Event;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;
use Medoo\Medoo;

class Db
{
    //缓存已初始化的 数据表(模型) 类全称
    public $initedModels = [];

    //缓存已实例化的 数据表(模型)
    public $MDS = [/*
        "table name" => Table instance
    */];

    /**
     * Db config
     */
    public $type = "";      //db type
    public $connectOptions = [];    //缓存的 medoo 连接参数
    public $name = "";
    public $key = "";       //md5(path_fix($db->filepath))
    public $pathinfo = [];  //sqlite db file pathinfo
    
    //数据库设置类 处理 数据库设置文件 json
    public $config = null;

    //数据库 driver 类
    public $driver = "";    //数据库类型驱动类
    
    //medoo 实例
    protected $_medoo = null;

    /**
     * 数据库实例 内部指针，指向当前操作的 model 类全称
     * 后指定的 覆盖 之前指定的
     */
    protected $currentModel = "";

    /**
     * Curd 操作类实例
     * 在每次 Curd 完成后 销毁
     */
    public $curd = null;

    //当前会话的 Resper 响应者实例
    public $resper = null;
    //此数据库实例 挂载到的 Orm 实例，即：$orm->Dbn == $this
    public $orm = null;

    /**
     * 构造 数据库实例
     * @param Array $opt Medoo实例创建参数
     */
    public function __construct($opt = [])
    {
        $this->connectOptions = $opt;
        //Medoo 连接数据库
        $this->_medoo = new Medoo($opt);
        //触发 db-created 事件
        Event::trigger("db-created", $this);
    }

    /**
     * 依赖注入
     * @param Array $di 要注入 数据库实例 的依赖对象，应包含：
     *  [
     *      "resper"    => 关联的 resper 响应者实例
     *      "orm"       => 此 数据库实例 所属 Orm 实例
     *  ]
     * @return Db $this
     */
    public function dependency($di=[])
    {
        //注入 关联 Resper 实例
        $resper = $di["resper"] ?? null;
        if ($resper instanceof Resper) {
            $this->resper = $resper;
        }
        //注入 关联 Orm 实例
        $orm = $di["orm"] ?? null;
        if ($orm instanceof Orm) {
            $this->orm = $orm;
        }

        return $this;
    }

    /**
     * 数据库路径下 查找文件
     * @param String $path 可使用字符串模板 %{name}% 代表 数据库 name
     * @param Bool $exists 是否判断文件存在
     * @return String  找到返回路径，未找到返回 null
     */
    public function path($path = "", $exists = false)
    {
        $dir = $this->pathinfo["dirname"];
        if ($path=="") return $dir;
        $path = Str::tpl($path, [
            "name" => $this->name
        ]);
        $dp = $dir.DS.str_replace("/", DS, trim($path,"/"));
        if ($exists) {
            if (file_exists($dp) || is_dir($dp)) return $dp;
            return null;
        }
        return $dp;
    }

    /**
     * 输出 db 数据库信息
     * @param String $xpath 访问数据库信息
     * @return Array
     */
    public function info($xpath="")
    {
        $ks = explode(",", "type,connectOptions,name,key,pathinfo,driver,orm");
        $info = [];
        foreach ($ks as $i => $k) {
            $info[$k] = $this->$k;
        }
        if ($xpath=="") return $info;
        return Arr::find($info, $xpath);
    }

    /**
     * 获取当前数据库所属 Orm 实例下 其他数据库实例
     * 调用 $orm->Dbn
     * @param String $dbn 在 $orm->config["dbns"] 中包含的 数据库名
     * @return Db 数据库实例  未找到则返回 null
     */
    public function db($dbn)
    {
        $db = $this->orm->$dbn;
        return $db;
    }

    /**
     * 获取当前数据库中 数据表(模型)类 全称
     * 并对此 数据表(模型) 类 做预处理，注入依赖 等
     * @param String $model 表(模型)名称 如：Usr
     * @return String 类全称
     */
    public function model($model)
    {
        $mcls = $this->hasModel($model);
        if ($mcls===false || !class_exists($mcls)) return null;
        if ($this->modelInited($model)!==true) {
            //类全称
            $mcls::$cls = $mcls;
            //创建事件订阅，订阅者为此 数据表(模型)类
            //Orm::eventRegist($mcls);
            //依赖注入
            $mcls::dependency([
                //将当前 数据库实例 注入 数据表(模型) 类
                "db" => $this
            ]);
            //解析表预设参数
            $mcls::parseConfig();
            //缓存 mcls
            $this->initedModels[] = $mcls;
            //触发 数据表(模型) 类初始化事件
            Event::trigger("model-inited", $mcls);
        }
        return $mcls;
    }

    /**
     * 获取所有定义的 数据表(模型) 类
     * @param Bool $init 是否初始化这些类 默认 false
     * @return Array [ 类全称, ... ]
     */
    public function models($init=false)
    {
        $mds = $this->config->models;
        if ($init) {
            $mclss = array_map(function ($i) {
                return $this->model($i);
            }, $mds);
        } else {
            $mclss = array_map(function ($i) {
                return $this->hasModel($i);
            }, $mds);
        }
        $mclss = array_merge(array_filter($mclss, function ($i) {
            return !empty($i) && class_exists($i);
        }));
        return $mclss;
    }

    /**
     * 获取所有定义的 数据表(模型) 类名称
     * @param Bool $init 是否初始化这些类 默认 false
     * @return Array [ 类名称, ... ]
     */
    public function modelNames($init=false)
    {
        $mclss = $this->models($init);
        if (empty($mclss)) return [];
        $mns = array_map(function ($i) {
            $ia = explode("\\", $i);
            return array_pop($ia);
        }, $mclss);
        return $mns;
    }

    /**
     * 判断 数据表(模型) 是否存在
     * @param String $model 表(模型)名称 如：Usr
     * @return Mixed 不存在返回 false 存在则返回 数据表(模型) 类全称
     */
    public function hasModel($model)
    {
        $mds = $this->config->models;
        if (in_array($model, $mds)) {
            $mdn = $model;
        } else {
            $lmdn = lcfirst($model);
            if (in_array($lmdn, $mds)) {
                $mdn = $lmdn;
            } else {
                return false;
            }
        }
        //在当前 Resper 实例所在路径下 查找 model 类
        if (!$this->resper instanceof Resper) return false;
        $dbn = $this->name;
        $mdcls = $this->resper->cls("model/".$dbn."/".ucfirst($mdn));
        if (empty($mdcls)) return false;
        return $mdcls;
    }

    /**
     * 判断 数据表(模型) 类是否已经初始化
     * @param String $model 表(模型)名称 如：Usr
     * @return Bool
     */
    public function modelInited($model)
    {
        $mcls = $this->hasModel($model);
        if (!class_exists($mcls)) return false;
        $inited = $this->initedModels;
        return in_array($mcls, $inited) && !empty($mcls::$db) && $mcls::$db instanceof Db;
    }

    /**
     * __get 方法
     * @param String $key
     * @return Mixed
     */
    public function __get($key)
    {
        //var_dump($key);
        /**
         * $db->Model 
         * 将数据库实例内部指针 currentModel 指向 当前的 model 类
         * 同时 初始化一个 针对此 model 的 curd 操作，准备执行 curd 操作
         */
        if ($this->hasModel($key)!==false) {
            $mcls = $this->model($key);
            //指针指向 model 类全称
            $this->currentModel = $mcls;
            //准备 curd 操作
            if ($this->curdInited()!=true || $this->curd->model::$name!=$key) {
                //仅当 curd 操作未初始化，或 当前 curd 操作为针对 此 数据表(模型) 类 时，重新初始化 curd
                $this->curdInit($key);
            }
            //返回 $db 自身，准备接收下一步操作指令
            return $this;
        }

        /**
         * 如果内部指针 currentModel 不为空
         */
        if ($this->currentModel!="") {
            $model = $this->currentModel;

            /**
             * $db->Model->property 
             * 访问 数据表(模型) 类属性 静态属性
             */
            if (cls_hasp($model, $key, 'static,public')) {
                return $model::$$key;
            }

            /**
             * 如果 curd 操作已被初始化为 针对 此 model
             */
            //if ($this->curdInited && $this->curd->model == $model) {
            //    $curd = $this->curd;
                /**
                 * $db->Model->curdProperty
                 * 访问 curd 操作实例的 属性
                 * !! 不推荐，推荐：$db->Model->curd->property
                 */
            //    if (property_exists($curd, $key)) {
            //        return $curd->$key;
            //    }
            //}

        }

        /**
         * $db->api***
         * 返回所有已初始化的 数据表(模型) api 数据
         */
        if (substr($key, 0,3)==="api") {
            $api = [];
            $mclss = $this->models(true);     //初始化所有 数据表(模型) 类
            foreach ($mclss as $i => $mcls){
                if (empty($mcls::$configer)) continue;
                $mapi = $mcls::$configer->api;
                if (empty($mapi)) continue;
                $api = array_merge($api, $mapi);
            }
            //$db->api 返回所有已初始化的 数据表(模型) api 数据 数组
            if ($key=="api") return $api;
            //$db->apis 返回 api 名称数组
            if ($key=="apis") return empty($api) ? [] : array_keys($api);
        }

        return null;
    }

    /**
     * __call medoo method
     */
    public function __call($key, $args)
    {
        /**
         * 如果内部指针 currentModel 不为空
         */
        if ($this->currentModel!="") {
            $model = $this->currentModel;
            /**
             * 如果 curd 操作已被初始化为 针对 此 model
             * 优先执行 curd 操作
             */
            if ($this->curdInited() && $this->curd->model == $model) {
                $curd = $this->curd;
                /**
                 * $db->Model->where() 
                 * 执行 curd 操作
                 * 返回 curd 操作实例  or  操作结果
                 */
                if (method_exists($curd, $key) || $curd->hasWhereMethod($key) || $curd->hasMedooMethod($key)) {
                    $rst = $this->curd->$key(...$args);
                    if ($rst instanceof Curd) return $this;
                    return $rst;
                }
            }

            /**
             * $db->Model->func()
             * 调用 数据表(模型) 类方法 静态方法
             */
            if (cls_hasm($model, $key, "static,public")) {
                $rst = $model::$key(...$args);
                if ($rst == $model) {
                    //如果方法返回 数据表(模型) 类，则返回 $db 自身，等待下一步操作
                    return $this;
                } else {
                    return $rst;
                }
            }
        }
        
        return null;
    }


    /**
     * table 操作
     */
    
    /**
     * 实例化数据表
     * @param String $tbn table name
     * @return Table instance  or  null
     */
    public function __table($tbn)
    {
        if ($this->tableInsed($tbn)) return $this->TABLES[$tbn];
        $tbcls = $this->tableCls($tbn);
        if (empty($tbcls)) return null;
        $tbo = new $tbcls($this);
        $this->TABLES[$tbn] = $tbo;
        return $tbo;
    }

    /**
     * 判断数据表是否已实例化
     * @param String $tbn table name
     * @return Bool
     */
    public function __tableInsed($tbn)
    {
        if (!isset($this->TABLES[$tbn])) return false;
        $tbo = $this->TABLES[$tbn];
        return $tbo instanceof Table;
    }

    /**
     * 获取数据表 类全称
     * @param String $tbn table name
     * @return String table class name
     */
    public function __tableCls($tbn)
    {
        $dbn = $this->name;
        $app = $this->app->name;
        $cls = Orm::cls("$app/table/$dbn/".ucfirst($tbn));
        if (class_exists($cls)) return $cls;
        return null;
    }




    /**
     * medoo 操作
     */

    //创建 medoo 实例
    /*protected function medooConnect($opt=[])
    {
        $opt = arr_extend($this->connectOptions, $opt);
        $this->_medoo = new Medoo($opt);
        return $this;
    }*/

    /**
     * get medoo instance  or  call medoo methods
     * @param String $method
     * @param Array $params
     * @return Mixed
     */
    public function medoo($method = null, ...$params)
    {
        if (is_null($this->_medoo)) $this->medooConnect();
        if (!is_notempty_str($method)) return $this->_medoo;
        if (method_exists($this->_medoo, $method)) return $this->_medoo->$method(...$params);
        return null;
    }

    /**
     * 创建表
     * @param String $tbname 表名称
     * @param Array $creation 表结构参数
     * @return Bool
     */
    public function medooCreateTable($tbname, $creation=[])
    {
        if (!isset($creation["id"])) {
            //自动增加 id 字段，自增主键
            $creation["id"] = [
                "INT", "NOT NULL", "AUTO_INCREMENT", "PRIMARY KEY"
            ];
        }
        if (!isset($creation["enable"])) {
            //自动增加 enable 生效字段，默认 1
            $creation["enable"] = [
                "INT", "NOT NULL", "DEFAULT 1"
            ];
        }
        var_dump($creation);
        return $this->_medoo->debug()->create($tbname, $creation);
    }



    /**
     * CURD
     */

    /**
     * 初始化一个 curd 操作
     * @param String $tbn 表(模型) 名称
     * @return Db $this
     */
    public function curdInit($model)
    {
        $model = $this->model($model);
        //var_dump($model);
        if (!empty($model)) {
            $this->curd = new Curd($this, $model);
            //var_dump($this->curd);
        }
        return $this;
    }

    /**
     * 销毁当前 curd 操作实例
     * @return Db $this
     */
    public function curdUnset()
    {
        if ($this->curdInited()==true) {
            $this->curd = null;
        }
        return $this;
    }

    /**
     * 执行 curd 操作
     * @param String $method medoo method
     * @param Bool $initCurd 是否重新初始化 curd，默认 true
     * @return Mixed
     */
    public function __curdQuery($method, $initCurd=true)
    {
        if (!$this->curdInited()) return false;
        $table = $this->curd["table"];
        $field = $this->curd["field"];

        $rst = $this->medoo($method, $table, $field);
        if ($initCurd) $this->curdInit();
        
        return $rst;
    }

    /**
     * 判断 curd 是否已被 inited
     * @return Bool
     */
    public function curdInited()
    {
        return !empty($this->curd) && $this->curd instanceof Curd && $this->curd->db->key == $this->key;
    }



    /**
     * 入口 Action 功能
     * 通过 https://[host]/[appname]/[dbname]/... 调用的 数据库 action
     */
    /**
     * [url]/create
     * 创建数据库
     */
    /**
     * [url]/update
     * 更新数据库结构
     * 
     * @param Array $args URI
     * @return Mixed
     */
    public function updateAction()
    {
        return "update db ".$this->app->name."/".$this->name;
    }

    



    /**
     * static
     */

    /**
     * 创建数据库实例
     * @param Array $opt 数据库连接参数
     * @return Dbo 实例
     */
    /*public static function connect($opt=[])
    {
        $driver = self::getDriver($opt);
        //var_dump($driver);
        if (!empty($driver) && class_exists($driver)) {
            return $driver::connect($opt);
        }
        return null;
    }*/

    /**
     * 创建数据库
     * @param Array $opt 数据库创建参数：
     *  [
     *      type => sqlite / mysql
     *      其他参数 由 driver 决定其结构
     *  ]
     * @return Bool
     */
    /*public static function create($opt=[])
    {
        $driver = self::getDriver($opt);
        if (!empty($driver) && class_exists($driver)) {
            return $driver::create($opt);
        }
        return false;
    }*/



    /**
     * static tools
     */

    //根据连接参数 获取 driver 类
    /*public static function getDriver($opt=[])
    {
        $type = $opt["type"] ?? "sqlite";
        $driver = Orm::cls("driver/".ucfirst($type));
        if (class_exists($driver)) return $driver;
        return null;
    }*/

    
}