<?php
/**
 * cgyio/resper Orm 数据库操作类
 * Model 数据表(数据模型)类 基类
 * 
 * 类   == 数据表 table
 * 实例 == 某条记录
 * 
 * 数据表方法 == static 静态方法
 * 数据记录方法 == 实例方法 
 * 
 */

namespace Cgy\orm;

use Cgy\Orm;
use Cgy\orm\Db;
use Cgy\orm\Curd;
use Cgy\orm\model\Record;
use Cgy\orm\model\Config;
use Cgy\orm\model\Exporter;
use Cgy\orm\model\ModelSet;
use Cgy\Resper;
use Cgy\Request;
use Cgy\Response;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Path;

class Model extends Record
{
    /**
     * 当前数据模型(表) 依赖项
     */
    //resper 实例
    public static $resper = null;
    //数据库实例
    public static $db = null;

    //此 数据表(模型) 类全称
    public static $cls = "";

    /**
     * 数据表 预设参数
     * 子类覆盖
     */
    /*public static $name = "";
    public static $table = "";  //数据表(模型)类 在数据库中的 表名称，通常是 model::$name 的全小写
    public static $title = "";
    public static $desc = "";
    public static $xpath = "";  // Appname/dbname/Tbname  -->  \Atto\Orm\Appname\model\dbname\Tbname
    //表结构
    public static $creation = [
        //...
    ];
    //字段 meta 数据
    public static $meta = [
        "fieldname" => ["产品编码", "此库存成品SKU的产品编码，在系统中唯一", 10],
    ];
    //特殊字段参数
    public static $special = [

    ];
    //关联表预设，medoo 方法的 join 参数形式
    public static $join = [

    ];
    //默认每次查询是否使用 join 表
    public static $useJoin = false;
    //每次查询必须包含的字段
    public static $includes = ["id","enable"];*/

    //预设参数解析对象 ModelConfiger 实例
    public static $config = null;

    /**
     * 数据表 方法
     * 均为 静态方法
     */

    /**
     * 依赖注入
     * @param Array $di 要注入 模型(表) 类的依赖对象，应包含：
     *  [
     *      "resper"    => 关联的 resper 响应者实例
     *      "db" => 此 模型(表) 所在的数据库实例
     *  ]
     * @return String 类全称
     */
    public static function dependency($di=[])
    {
        //注入 关联 Resper 实例
        $resper = $di["resper"] ?? null;
        if ($resper instanceof Resper) {
            static::$resper = $resper;
        }
        //依赖：此表所在数据库实例
        $db = $di["db"] ?? null;
        if (!empty($db) && $db instanceof Db) {
            static::$db = $db;
        }

        return static::$cls;
    }

    /**
     * 解析 数据表(模型) 预设参数
     * @return String 类全称
     */
    public static function parseConfig()
    {
        $cls = static::$cls;
        $cla = explode("\\", $cls);
        $cln = array_pop($cla);
        
        //从 Db 获取 config 内容
        $db = static::$db;
        $conf = $db->config->ctx("model/".lcfirst($cln));
        if (!empty($conf)) {
            $mdn = lcfirst($cln);
        } else {
            $conf = $db->config->ctx("model/".$cln);
            if (!empty($conf)) {
                $mdn = $cln;
            } else {
                return $cls;
            }
        }

        //使用 model\Config 解析表预设
        static::$config = new Config($cls, $conf, $mdn);
        return $cls;
    }

    /**
     * 获取模型名称，不包含namespace
     * @return String 数据模型名称，首字母大写
     */
    public static function mdn()
    {
        $clsn = static::class;
        return basename(str_replace("\\","/",$clsn));
    }

    /**
     * 判断数据模型是否已初始化
     * @return Bool
     */
    public static function inited()
    {
        if (!static::$db instanceof Db) return false;
        return static::$db->modelInited(static::mdn());
    }

    /**
     * 在数据模型类内部执行 md() 作为此数据模型 curd 链式调用起点
     * !! static::md() === static::$db->Mdn === Orm::Mdn()
     * 无论此数据模型是否已被初始化，执行 md() 将自动初始化
     * 用途：在数据模型类 内部 执行 curd 查询，例如
     *      static::md()->nojoin()->whereUid($uid)->get();
     * 
     * @return Db 与 Orm::Mdn() 和 static::$db->Mdn 返回同样的数据库实例，其内部指向当前数据模型，可继续链式调用 curd 方法
     */
    public static function md()
    {
        $mdn = static::mdn();
        //模型已被初始化，通过模型关联的数据库实例执行
        if (static::inited()===true) return static::$db->$mdn;
        //模型未被初始化，通过 Orm 方法执行
        return Orm::$mdn();
    }



    /**
     * 可通过链式调用的 数据模型表方法(静态方法)
     * 可以这样使用：
     *      Orm::Mdn()->where(...)->foo(...$args)->order(...)->select()
     *      static::md()->column("*")->foo(...$args)->limit(10)->select()
     * 这些方法可执行 自定义操作，修改当前 curd 操作实例的参数，并影响最终查询结果
     * !! 这些方法必须返回 数据模型类全称
     */

    /**
     * 根据 isXxxx 类型的字段值 查询记录
     * static::md()->specialColumns("generator", "~", "E99")->...->select()
     * @param String $isa 字段的特殊类型，如：unique 相当于查询所有 isUnique==true 的字段
     * @param Array $val 查询的字段值，可以在字段值之前加上 logic 符号
     * @return String 模型类全称
     */
    public static function specialColumns($isa, ...$val)
    {
        //确保数据模型已经初始化
        $mdo = static::md();
        //确保字段特殊类型 可用
        if (!Is::nemstr($isa)) return $mdo->mcls;
        //获取此特殊类型的字段列表
        $colkey = strtolower($isa)."Columns";
        $cols = $mdo->conf->$colkey;
        if (!Is::nemarr($cols) || !Is::indexed($cols)) return $mdo->mcls;
        //如果字段列表只有 1 个字段，直接调用 whereCol 方法
        if (count($cols)==1) {
            $m = "where".ucfirst($cols[0]);
            $mdo = $mdo->$m(...$val);
        } else {
            $mdo = $mdo->whereCols($cols, ...$val);
        }
        return $mdo->mcls;
    }
    //快捷查询 isUnique 字段
    public static function uniqueCols(...$val) {return static::specialColumns("unique", ...$val);}
    //快速查询 isId 字段
    public static function idCols(...$val) {return static::specialColumns("id", ...$val);}
    //快速查询 isGenerator 字段
    public static function generatorCols(...$val) {return static::specialColumns("generator", ...$val);}



    /**
     * 静态调用 数据表记录实例 构造方法
     * $model::create() 创建 数据表记录实例
     * @param Array $data 数据表记录内容，通常由 curd 操作返回
     * @return Model 一条数据记录实例
     */
    public static function create($data = [])
    {
        $rs = new static($data);
        return $rs;
    }

    /**
     * 创建一条新记录，但不写入数据库
     * @param Array $data 新记录初始数据
     * @return Model 实例
     */
    public static function new($data = [])
    {
        $idf = static::idf();
        if (isset($data[$idf])) unset($data[$idf]);
        $rs = static::create($data);
        $rs->isNew = true;
        return $rs;
    }


    /**
     * curd 操作
     */

    public static function __find(...$args)
    {
        $tb = static::$name;
        $db = static::$db;
        if (!$db instanceof Db) return static::$cls;
        $rs = $db->curdQuery("select");
        var_dump($rs);
        //create record set
        $rso  = [];
        foreach ($rs as $i => $rsi) {
            $rso[$i] = new static($rsi);
        }
        return $rso;
    }

    /**
     * 创建表
     * !! 子类必须实现 !!
     * @return Bool
     */
    public static function createTable()
    {
        //... 子类实现

        return true;
    }

    /**
     * 创建一个 数据表(模型) 实例
     * 用于以实例方式调用 类方法
     * @return Model 实例
     */
    public static function ___ins()
    {
        return new static([]);
    }

    /**
     * 包裹 curd 操作得到的 结果
     * 根据不同的 $rst 返回不同的数据：
     *      PDOStatement                    根据 $method 返回 Model 实例  or  ModelSet 记录集
     *      null,false,true,string,number   直接返回
     *      indexed array                   包裹成为 ModelSet 记录集
     *      associate array                 包裹成为 Model 实例
     * @param Mixed $rst 由 medoo 查询操作得到的结果
     * @param String $method 由 medoo 执行的查询方法，select / insert / ...
     * @param Curd $curd curd 操作实例
     * @return Mixed 
     */
    public static function wrap($rst, $method, &$curd)
    {
        $db = static::$db;  //数据库实例
        $mcls = static::$cls;   //数据表(模型) 类全称，== static::class
        if ($rst instanceof \PDOStatement) {
            //通常 insert/update/delete 方法返回 PDOStatement
            if ($method=="insert") {
                //返回 刚添加的 Model 实例
                //使用 medoo 实例的 id() 方法，返回最后 insert 的 id
                $id = $db->medoo("id");
                $idf = static::idf();
                //再次 curd 查询，查询完不销毁 curd 实例
                $rst = $curd->where([
                    $idf => $id
                ])->get(false);
                $curd->where = [];
                return $rst;
            } else if ($method=="update") {
                //返回 刚修改的 ModelSet 记录集
                //再次 curd 查询，使用当前的 curd->where 参数
                $rst = $curd->select(false);
                return $rst;
            } else if ($method=="delete") {
                //返回 删除的行数
                $rcs = $rst->rowCount();
                return $rcs;
            } else {
                return $rst;
            }
        } else if (is_array($rst)) {
            //返回的是 记录 / 记录集
            if (empty($rst)) {
                /*if ($method=="get") {
                    return static::create($rst);
                } else {
                    return new ModelSet($mcls, $rst);
                }*/
                return $rst;
            }
            if (Is::indexed($rst)) {
                //记录集 通常 select/rand 方法 返回记录集
                //包裹为 ModelSet 记录集对象
                return new ModelSet($mcls, $rst);
            } else if (Is::associate($rst)) {
                //单条记录 通常 get 方法 返回单条记录
                //包裹为 Model 实例
                return static::create($rst);
            }
        } else {
            return $rst;
        }
    }

    /**
     * 判断 表 是否包含字段 $column
     * @param String $column 可以是 column 或 table.column
     * @return Bool
     */
    public static function hasColumn($column)
    {
        $conf = static::$config;
        $fds = $conf->columns;
        $tbn = $conf->table;
        if (strpos($column, ".")===false) return in_array($column, $fds);
        $fa = explode(".",$column);
        return $tbn==$fa[0] && in_array($fa[1], $fds);
    }

    /**
     * 获取此表的自增字段
     * @return String 字段名
     */
    public static function aif()
    {
        $fdc = static::$config->column;
        $rtn = "id";
        foreach ($fdc as $fdn => $c) {
            if ($c["isId"]==true) {
                $rtn = $fdn;
                break;
            }
        }
        return $rtn;
    }
    //also can use idf()
    public static function idf() {return static::aif();}


    /**
     * Apis
     */

    /**
     * 执行任意 api 操作
     * @param String $api 方法名，不含末尾 "Api"
     * @param Array $args URI 参数
     * @return Mixed
     */
    public static function execApis($api, ...$args)
    {
        $apic = static::hasApi($api);
        $isModel = $apic["isModel"];
        $apin = $apic["name"];
        $fn = $apin."Api";

        //执行 数据模型(表) api 静态方法
        if ($isModel) {
            return static::$fn(...$args);
        }

        //执行 数据记录实例 api 实例方法
        $db = static::$db;
        $mdn = static::$config->name;
        //执行 curd->query() 使用 input 传入的参数 创建 ModelSet 实例
        $rs = $db->$mdn->query();
        if (!$rs instanceof ModelSet) return null;
        //查询并创建 ModelSet 记录集实例后，调用 实例 api 方法
        return $rs->$fn(...$args);
    }

    /**
     * 判断是否存在 api
     * @param String $api 方法名，不包含结尾的 "Api"
     * @return Mixed 存在则返回 $model::$config->api[$api] 参数内容，不存在则返回 false
     */
    public static function hasApi($api)
    {
        $conf = static::$config->api;
        if (isset($conf[$api])) return $conf[$api];
        $lapi = lcfirst($api);
        if (isset($conf[$lapi])) return $conf[$lapi];
        return false;
    }

    /**
     * 
     */

    /**
     * api
     * @role all
     * @desc 新建数据记录(C)
     * @param Array $args 更多 URI 参数
     * @return Model 实例
     */
    public static function createApi(...$args)
    {
        $pd = Request::input("json");
        $data = $pd["data"] ?? [];
        $rs = static::new($data);
        return $rs->context;
    }

    /**
     * api
     * @role all
     * @desc 编辑数据记录(U)
     * @param Array $args 更多 URI 参数
     * @return Model 实例
     */
    public static function updateApi(...$args)
    {
        
    }
    
    /**
     * api
     * @role all
     * @desc 查询数据记录(R)
     * @param Array $args 更多 URI 参数
     * @return Model 实例
     */
    public static function retrieveApi(...$args)
    {
        $post = array_pop($args);
        var_dump($post);
        exit;
        if (empty($post)) return [];
        $query = $post["query"] ?? [];
        
    }
    
    /**
     * api
     * @role all
     * @desc 删除数据记录(D)
     * @param Array $args 更多 URI 参数
     * @return Model 实例
     */
    public static function deleteApi(...$args)
    {
        
    }

    /**
     * api
     * @role all
     * @desc 获取数据表中全部记录
     * @param Array $args 
     * @return Array 记录集
     */
    public static function allApi(...$args)
    {
        $db = static::$db;
        $conf = static::$config;
        $mdn = $conf->name;
        $tbn = $conf->table;
        $rst = $db->medoo("select", $tbn);

    }



    /**
     * 通用方法
     */

    /**
     * 根据 orm 参数中的 models 项，解析得到当前数据库 model 文件路径
     * @param Array $conf 参数数组，包含 models 项内容
     * @return Array 解析得到的 model 模型文件路径参数 [path=>'模型类文件路径', clsp=>'模型类全称前缀', ...]
     */
    public static function initOrmConf($conf = [])
    {
        $ormc = [
            "path" => "",
            "clsp" => ""
        ];

        $models = $conf["models"] ?? DIR_MODEL;
        $mdps = Is::nemstr($models) ? explode(",", trim($models, ",")) : $models;
        if (!Is::nemarr($mdps) || !Is::indexed($mdps)) $mdps = explode(",", DIR_MODEL);
        $mdp = Path::exists($mdps, [
            "checkDir" => true,
            "all" => false
        ]);
        //var_dump($mdp);
        if (empty($mdp)) $mdp = Path::find("root/model", ["checkDir"=>true]);
        if (!empty($mdp)) {
            $ormc["path"] = Path::fix($mdp);
            //获取类全称前缀
            $ns = defined("NS") ? NS : "\\Cgy\\";
            $root = defined("ROOT_PATH") ? ROOT_PATH.DS : DS."data".DS;
            $clsp = $ns . str_replace($root, "", $mdp);
            $clsp = str_replace(DS,"\\", $clsp);
            $ormc["clsp"] = $clsp;

        } else {
            //指定的数据库文件路径不存在，报错
            trigger_error("resper::指定的数据模型路径不存在，DIRS = ".implode(", ",$mdps), E_USER_ERROR);
        }

        return $ormc;
    }



    /**
     * 获取 数据表(模型) 类全称
     * @param String $model 表名，不指定 则 返回当前 Model
     * @return Class 类全称 or null
     */
    public static function __cls($model="")
    {
        //当前 类全称
        $cls = static::class;
        if (substr($cls, 0,1)!="\\") $cls = "\\".$cls;
        if (!is_notempty_str($model)) {
            //不指定 model 返回当前 数据表(模型) 类全称
            return $cls;
        } else {
            //指定了 model
            if (strpos($model, "/")!==false) {
                $ma = explode("/", $model);
                if (count($ma)==2) {
                    //model == dbn/tbn 访问当前 DbApp 下的 其他数据表 类
                    $dbn = $ma[0];
                    $tbn = $ma[1];
                    $ncls = static::$db->db($dbn)->model(ucfirst($tbn));
                } else if (count($ma)==3) {
                    //model == appname/dbn/tbn  访问其他 DbApp 下的 数据表 类
                    $apn = $ma[0];
                    $dbn = $ma[1];
                    $tbn = $ma[2];
                    $appcls = cls("app/".ucfirst($apn));
                    if (class_exists($appcls)) {
                        $app = new $appcls();
                        $dbk = $dbn."Db";
                        $dbo = $app->$dbk;
                        if ($dbo instanceof Db) {
                            $ncls = $dbo->model(ucfirst($tbn));
                        } else {
                            return null;
                        }
                    } else {
                        return null;
                    }
                } else {
                    return null;
                }
            } else {
                //model == tbn
                $cla = explode("\\", $cls);
                array_pop($cla);
                $cla[] = ucfirst($model);
                $ncls = implode("\\", $cla);
            }
            if (class_exists($ncls)) return $ncls;
            return null;
        }
    }



    
    /**
     * 模型类 静态魔术方法
     */
    public static function __callStatic($key, $args)
    {
        if (static::$db instanceof Db) {
            //只有已初始化的 model 模型类，才能执行：

            
        }
    }

}