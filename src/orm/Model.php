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
use Cgy\orm\model\Record;
use Cgy\orm\model\Config;
use Cgy\orm\model\Exporter;
use Cgy\orm\model\ModelSet;
use Cgy\Request;
use Cgy\Response;

class Model extends Record
{
    /**
     * 当前数据模型(表) 依赖的 数据库实例
     */
    public static $db = null;

    //此 数据表(模型) 类全称
    public static $cls = "";

    /**
     * 数据表 预设参数
     * 子类覆盖
     */
    public static $name = "";
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
    public static $includes = ["id","enable"];

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
     *      "db" => 此 模型(表) 所在的数据库实例
     *  ]
     * @return String 类全称
     */
    public static function dependency($di=[])
    {
        //依赖：此表所在数据库实例
        $db = $di["db"] ?? null;
        if (!empty($db) && $db instanceof Db) {
            static::$db = $db;
        }

        return static::cls();
    }

    /**
     * 解析 数据表(模型) 预设参数
     * @return String 类全称
     */
    public static function parseConfig()
    {
        $cls = static::$cls;
        $cln = array_pop(explode("\\", $cls));
        
        //从 Db 获取 config 内容
        $db = static::$db;
        $conf = $db->config->ctx("model/".lcfirst($cln));

        //使用 model\Config 解析表预设
        static::$config = new Config($cls, $conf);
        return $cls;
    }

    /**
     * 静态调用 数据表记录实例 构造方法
     * $model::create() 创建 数据表记录实例
     * @param Array $data 数据表记录内容，通常由 curd 操作返回
     * @return Model 一条数据记录实例
     */
    public static function create($data=[])
    {
        $rs = new static($data);
        return $rs;
    }

    /**
     * 创建一条新记录，但不写入数据库
     * @param Array $data 记录初始值
     * @return Model 实例
     */
    public static function new($data=[])
    {
        if (!empty($data)) {
            $dft = static::$config->default;
            $data = arr_extend($dft, $data);
        }
        $rs = new static($data);
        $rs->isNew = true;
        return $rs;
    }


    /**
     * curd 操作
     */
    //r
    public static function __find(...$args)
    {
        $tb = static::$name;
        $db = static::$db;
        if (!$db instanceof Db) return static::cls();
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
            if (is_indexed($rst)) {
                //记录集 通常 select/rand 方法 返回记录集
                //包裹为 ModelSet 记录集对象
                return new ModelSet($mcls, $rst);
            } else if (is_associate($rst)) {
                //单条记录 通常 get 方法 返回单条记录
                //包裹为 Model 实例
                return static::create($rst);
            }
        } else {
            return $rst;
        }
    }

    /**
     * 判断 表 是否包含字段 $field
     * @param String $field 可以是 field 或 table.field
     * @return Bool
     */
    public static function hasField($field)
    {
        $conf = static::$config;
        $fds = $conf->fields;
        $tbn = $conf->table;
        if (strpos($field, ".")===false) return in_array($field, $fds);
        $fa = explode(".",$field);
        return $tbn==$fa[0] && in_array($fa[1], $fds);
    }

    /**
     * 获取此表的自增字段
     * @return String 字段名
     */
    public static function aif()
    {
        $fdc = static::$config->field;
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
     * 根据 api 方法名 获取 configer->api 参数数据
     * @param String $api 方法名，不包含结尾的 "Api"
     * @return Array  or  null
     */
    public static function getApiConf($api)
    {
        $conf = static::$config;
        if (!$conf instanceof Configer) return false;
        $apis = $conf->api;
        if (!is_notempty_arr($apis)) return false;
        $ao = null;
        foreach ($apis as $akey => $aconf) {
            if (substr($akey, -1*(strlen($api)+1))==="-".$api) {
                $ao = $aconf;
                break;
            }
        }
        return $ao;
    }

    /**
     * 判断是否存在 api
     * @param String $api 方法名，不包含结尾的 "Api"
     * @return Bool
     */
    public static function hasApi($api)
    {
        $aconf = static::getApiConf($api);
        if (!is_notempty_arr($aconf)) return false;
        //if ($aconf["isModel"]===true) return "static";
        return true;
    }

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
     * 获取 数据表(模型) 类全称
     * @param String $model 表名，不指定 则 返回当前 Model
     * @return Class 类全称 or null
     */
    public static function cls($model="")
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

}