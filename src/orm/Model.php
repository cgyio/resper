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
        //模型类全称
        $cls = static::class;
        //数据模型名称 首字母大写
        $mdn = static::mdn();
        //转为 数据表名称 
        $tbn = static::tbn();
        
        //从 Db 获取 config 内容
        $db = static::$db;
        $conf = $db->config->ctx("model/$tbn");
        //未获取到 model 预设参数，直接返回
        if (empty($conf)) return $cls;
        //静态检查 数据模型 $cls 是否已有缓存的 config 实例
        $cfger = Config::hasConfig($cls);
        if ($cfger===false) {
            //没有缓存的 config 实例
            //使用 model\Config 解析表预设
            $cfger = new Config($cls, $conf, $mdn);
        }
        //关联 config 实例到此类
        static::$config = $cfger;

        //返回 类全称
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
     * 获取当前数据模型 对应的数据表名，全小写，下划线_连接
     * @return String 数据表名
     */
    public static function tbn()
    {
        $mdn = static::mdn();
        return Orm::snake($mdn);
    }

    /**
     * 获取当前模型的 权限操作标记的 前缀  db/[dbname]/[modelname]
     * 此方法将自动初始化 当前数据模型
     * @return String 
     */
    public static function oprpre()
    {
        $db = static::md();
        $dbn = strtolower($db->name);
        $mdn = strtolower(static::mdn());
        return "db/$dbn/$mdn";
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
     * @return Record 一条数据记录实例
     */
    public static function create($data = [])
    {
        $rs = new static($data);
        return $rs;
    }

    /**
     * 创建一条新记录，但不写入数据库
     * @param Array $data 新记录初始数据
     * @return Record 实例
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
     * C/U/R/D 实际方法，管理模型数据的入口方法
     * 响应前端的 数据库操作的 请求
     */

    /**
     * C 新建记录，实际写入数据库
     * @param Record|Array $data 可以传入 记录实例 或 记录数据，不传入数据，则尝试从 input 中读取
     * @return Array 数据记录实例的 ctx() 结果
     */
    public static function C($data=null)
    {
        //未传入数据，则从 input 中读取
        $data = empty($data) ? Request::$current->inputs->json : $data;

        if (!$data instanceof Record) {
            if (!Is::nemarr($data)) $data = [];
            $data = static::new($data);
            return $data;
        }
        
        if ($data instanceof Record) {
            if ($data->isNew!==true) {
                //传入的记录实例 不是新建记录，报错
                trigger_error("orm/base::不是新增的记录无法添加到数据库中 [表=".static::mdn()."]", E_USER_ERROR);
            }
            //直接调用 记录实例的 save 方法
            $data->save();
            //返回新建记录的数据，所有字段+计算字段，不包含关联表数据
            return $data->ctx();
        }
    }

    /**
     * 根据ID获取 单条记录
     * @return Record|null
     */
    public static function find($id)
    {
        return static::md()->whereId($id)->get();
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
     * Proxy 代理响应方法
     * 通过 url 访问 数据模型的方法，将被发送到这些方法中
     * 例如：
     * https://[host]/[resper]/db/[dbn]/[tbn]/foo_bar  -->  static::fooBarProxy
     * https://[host]/[resper]/db/[dbn]/[tbn]/api/foo_bar  -->  static::fooBarApi 或记录实例方法 $record->fooBarApi
     * 
     * 数据模型固有的 操作方法：在 uac/Operation::$dftModelOprs 中定义
     *      C   create      --> createProxy
     *      U   update      --> updateProxy
     *      R   retrieve    --> retrieveProxy
     *      D   delete      --> deleteProxy
     *          toggle      --> toggleProxy
     * 
     * 操作权限验证 在对应的 OrmProxyer 类中执行，此处仅定义操作逻辑
     */

    /**
     * proxy 代理响应方法
     * @name create
     * @title 新建记录
     * 
     * @param Array $args URI 参数序列
     * @return Record 新增的记录实例
     */
    public static function createProxy(...$args)
    {

    }

    /**
     * proxy 代理响应方法
     * @name update
     * @title 修改记录
     * 
     * @param Array $args URI 参数序列
     * @return Record 修改后的记录实例
     */
    public static function updateProxy(...$args)
    {
        
    }

    /**
     * proxy 代理响应方法
     * @name retrieve
     * @title 查询记录
     * 
     * @param Array $args URI 参数序列
     * @return ModelSet 符合条件的数据记录集
     */
    public static function retrieveProxy(...$args)
    {
        
    }

    /**
     * proxy 代理响应方法
     * @name delete
     * @title 删除记录
     * 
     * @param Array $args URI 参数序列
     * @return Int 删除的记录行数
     */
    public static function deleteProxy(...$args)
    {
        
    }

    /**
     * proxy 代理响应方法
     * @name toggle
     * @title 切换记录生效/失效
     * 
     * @param Array $args URI 参数序列
     * @return Record|ModelSet 编辑后的记录(集)实例
     */
    public static function toggleProxy(...$args)
    {
        var_dump("toggle enable data");
    }



    /**
     * 通用方法
     */



    
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