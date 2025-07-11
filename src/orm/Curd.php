<?php
/**
 * cgyio/resper 数据库操作类
 * CURD 操作类
 * 每次 curd 操作，将生成一个 Curd 实例
 * 操作结束后，此实例将释放
 */

namespace Cgy\orm;

use Cgy\Orm;
use Cgy\orm\Db;
use Cgy\orm\Model;
use Cgy\orm\model\ModelSet;
use Cgy\orm\curd\JoinParser;
use Cgy\orm\curd\ColumnParser;
use Cgy\orm\curd\WhereParser;
use Cgy\orm\curd\Queryer;
use Cgy\util\Is;
use Cgy\util\Str;
use Cgy\util\Arr;
use Medoo\Medoo;

class Curd 
{
    //关联的数据库实例 Db
    public $db = null;

    //关联的 模型(数据表) 类全称
    public $model = "";
    //$model::$config
    public $conf = null;

    //curd 操作针对的 table 表名称
    public $table = "";

    //debug 标记，用于输出 SQL
    protected $debug = false;

    /**
     * 使用 curd 参数处理工具类
     */
    public $joinParser = null;
    public $columnParser = null;
    public $whereParser = null;
    //符合查询条件处理类
    public $queryer = null;

    /**
     * 事务操作 transaction
     * 一次事务操作可以包含多个 curd 实例的 多次操作参数
     * 因此一次 transaction 的操作列表，需要保存在 静态参数中
     * 此静态参数 在每次 commit 后 重置
     */
    public static $transaction = [
        /*
        [
            "curd" => 缓存本次 curd 实例 副本,
            "db" => 缓存 本次 curd 依赖的 db 实例,
            "model" => 缓存 本次 curd 依赖的 model 类，
            "method" => "要执行的 medoo 方法，如：insert/update/delete/select 等",
            "params" => "medoo 方法的参数，经过本次 curd 操作 *Parser 处理过的参数，可直接用于 medoo 方法",
            "callback" => 预定义的 回调函数，对这个 curd 操作的结果进行处理
        ],
        ...
        */
    ];


    //支持的 medoo 方法
    protected $medooMethods = [
        "select", "insert", "update", "delete", 
        "replace", "get", "has", "rand", 
        "count", "max", "min", "avg", "sum"
    ];

    /**
     * 构造 curd 操作实例
     * @param Db $db 数据库实例
     * @param String $model 要执行 curd 的 数据表(模型) 类全称
     */
    public function __construct($db, $model)
    {
        if (!$db instanceof Db || !class_exists($model)) return null;
        $mdn = $model::$config->name;
        if ($db->hasModel($mdn)===false) return null;
        $this->db = $db;
        $this->model = $model;
        $this->table = $mdn;
        $this->conf = $model::$config;
        
        //使用 curd 参数处理工具，初始化/编辑 curd 参数
        $this->joinParser = new JoinParser($this);
        $this->columnParser = new ColumnParser($this);
        $this->whereParser = new WhereParser($this);
        $this->queryer = new Queryer($this);
    }

    /**
     * curd 操作实例 是否 ready
     * 已经有 必要参数 table field
     * @return Bool
     */
    public function ready()
    {
        $db = $this->db;
        $model = $this->model;
        $table = $this->table;
        return 
            $db instanceof Db &&
            class_exists($model) &&
            $table!="" && 
            $model == $db->hasModel($table);
    }

    /**
     * 销毁当前 curd 实例
     * @return Null
     */
    public function unset()
    {
        $this->db->curdUnset();
        return null;
    }

    /**
     * 构造 medoo 查询参数
     * join 关联表查询参数
     * 符合 medoo join 参数形式
     * 调用 $this->joinParser->setParam() 方法
     * 
     * @param Mixed
     *      Bool        开启/关闭 join table
     *      String, ... like: '[>]table' 从 $model::$join 参数中 挑选 相应参数
     *      Array       重新指定 join 参数
     * @return Curd $this
     */
    public function join(...$args)
    {
        $jp = $this->joinParser;
        if ($jp instanceof JoinParser) {
            $jp->setParam(...$args);
        }
        return $this;
    }
    public function nojoin() {return $this->join(false);}

    /**
     * 构造 medoo 查询参数
     * 指定要返回值的 字段名 or 字段名数组 
     * 符合 medoo column 参数形式
     * 调用 $this->columnParser->setParam() 方法
     * 
     * @param Mixed
     *      "*"
     *      "field name","table.field",...
     *      [ "*", "table.*", "fieldname [JSON]", "tablename.fieldname [Int]", ... ]
     *      [ "table.*", "map" => [ "fieldname [JSON]", "tablename.fieldname [Int]", ... ] ]
     * @return Curd $this
     */
    public function column(...$args)
    {
        $cp = $this->columnParser;
        if ($cp instanceof ColumnParser) {
            $cp->setParam(...$args);
        }

        return $this;
    }

    /**
     * 构造 medoo 查询参数
     * 直接编辑 where 参数 
     * @param Array $where 与 medoo where 参数格式一致
     * @return Curd $this
     */
    public function where($where=[])
    {
        $wp = $this->whereParser;
        if ($wp instanceof WhereParser) {
            $wp->setParam($where);
        }

        return $this;
    }

    /**
     * 构造 medoo 查询参数
     * 解析 复合查询条件 
     * @param Array $extra 传入的复合查询参数
     * @param Bool $mixin 是否 合并 php://input 数据，默认 true
     * @return Curd $this
     */
    public function query($extra = [], $mixin = true)
    {
        //通过 curd\Queryer 解析并创建 查询参数
        $this->queryer->apply($extra, $mixin);
        //var_dump($this->parseArguments());
        //return $this->parseArguments();
        return $this;
    }

    /**
     * 在执行查询前，生成最终需要的 medoo 查询参数
     * 在查询时，可根据 method 组装成 medoo 方法的 参数 args[]
     * @return Array [ "table"=>"", "join"=>[], "field"=>[], "where"=>[] ]
     */
    public function parseArguments()
    {
        $args = [];
        $args["table"] = $this->table;
        $args["join"] = $this->joinParser->getParam();      //$this->parseJoin();
        $args["column"] = $this->columnParser->getParam();   //$this->parseField();
        $args["where"] = $this->whereParser->getParam();
        return $args;
    }

    /**
     * 根据 medoo 方法，处理当前的 curd 参数，得到最终用于 medoo 方法的实际参数
     * @param String $method 要执行的 medoo 方法
     * @param Array $args 某些 medoo 方法需要额外的参数，例如：update 需要传入记录数据
     * @return Array $params 例如：[ tbn, columns, where, ... ]
     * 可以直接用于 medoo 方法 $this->db->medoo($method, ...$params)
     */
    public function getMedooMethodParams($method="select", &$args)
    {
        //判断 medoo 方法是否支持
        if ($this->hasMedooMethod($method)!==true) return [];

        //准备查询参数
        $ag = $this->parseArguments();
        //join
        $join = $ag["join"] ?? [];
        $jp = $this->joinParser;
        $canJoin = $jp->use!==false && $jp->available==true;    //$this->useJoin!==false && !empty($join);
        //column
        $column = $ag["column"] ?? [];
        //where
        $where = $ag["where"] ?? [];
        //准备 medoo 方法参数
        $ps = [];
        $ps[] = $ag["table"];
        switch ($method) {
            case "select":
            case "get":
            case "rand":
            case "count":
            case "max":
            case "min":
            case "avg":
            case "sum":
                if ($canJoin) $ps[] = $join;
                $ps[] = $column;
                if (!empty($where)) $ps[] = $where;
                break;
            case "insert":
            case "update":
                if (Is::nemarr($args) && Is::nemarr($args[0])) {
                    $ps[] = array_shift($args);
                } else {
                    return null;
                }
                if ($method=="update" && !empty($where)) $ps[] = $where;
                break;
            case "delete":
                if (!empty($where)) {
                    $ps[] = $where;
                } else {
                    return null;
                }
                break;
            case "replace":
                $ps[] = $column;
                if (!empty($where)) $ps[] = $where;
                break;
            case "has":
                if ($canJoin) $ps[] = $join;
                if (!empty($where)) $ps[] = $where;
                break;
        }

        return $ps;
    }


    
    /**
     * 执行 medoo 查询
     * 使用 __call 方法
     * @param String $method medoo 查询方法
     * @param Array $args 输入参数
     */
    public function __call($method, $args)
    {
        $model = $this->model;

        /**
         * 执行 where 方法，构造 where 参数
         * 返回 curd 实例自身
         */
        if ($this->hasWhereMethod($method)===true) {

            /**
             * 调用 whereParser->method()
             * $curd->limit()->order()->...
             */
            $wp = $this->whereParser;
            if ($wp instanceof WhereParser && method_exists($wp, $method)) {
                $wp->$method(...$args);
                return $this;
            }

            /**
             * $curd->whereFooBar("~", "bar")  -->  $curd->where([ "foo_bar[~]"=>"bar" ])
             * $curd->orderFooBar() -->  $curd->order("foo_bar")
             * $curd->orderFooBar("ASC") -->  $curd->order([ "foo_bar"=>"ASC" ])
             * 执行 curd->where()/order()
             */
            if (strlen($method)>5 && in_array(substr($method, 0,5), ["where","order"])) {
                //whereFooBar --> 字段名：foo_bar
                $fdn = Str::snake(substr($method, 5), "_");
                if ($model::hasColumn($fdn)) {
                    if (substr($method, 0,5)=="where" && count($args)>0) {
                        $this->whereCol($fdn, ...$args);
                        return $this;
                    } else if (substr($method, 0,5)=="order") {
                        $this->orderCol($fdn, ...$args);
                        return $this;
                    }
                }

                return $this;
            }

        }

        /**
         * 执行 medoo 方法，完成 curd 操作，返回查询结果
         * $curd->...->select()
         * 查询结果如果是 记录/记录集 则 自动包裹为 Model/ModelSet 实例
         */
        //$ms = $this->medooMethods;  //explode(",", "select,insert,update,delete,replace,get,has,rand,count,max,min,avg,sum");
        //if (in_array($method, $ms)) {
        if ($this->hasMedooMethod($method)===true) {
            //调用 medoo 查询方法
            if (!$this->ready()) return null;
            //根据需要调用的 medoo 方法，取得方法参数
            $ps = $this->getMedooMethodParams($method, $args);

            //debug 输出 SQL
            if ($this->debug==true) {
                $this->db->medoo("debug")->$method(...$ps);
                //从缓冲区读取 sql
                $sql = ob_get_contents();
                //清空缓冲区
                ob_clean();
                return [
                    "args" => $ag,
                    //"argsQueue" => $ps,
                    "SQL" => $sql
                ];
            }

            //执行 medoo 方法
            $rst = $this->db->medoo($method, ...$ps);
            //var_dump($rst);

            //包裹 查询结果
            $rst = $this->model::wrap($rst, $method, $this);

            //销毁当前 curd 操作
            $unset = true;
            if (Is::nemarr($args) && is_bool($args[0])) {
                $unset = array_shift($args);
            }
            if ($unset) $this->unset();
            
            return $rst;
        }

        return null;
    }

    /**
     * 通过 Db->Model->method 调用 curd 操作时
     * 判断 给定的 method 是否是支持的 medooMethod
     * @param String $key method
     * @return Bool
     */
    public function hasMedooMethod($key)
    {
        return in_array($key, $this->medooMethods);
    }

    /**
     * 通过 Db->Model->method 调用 whereParser 方法时
     * 判断 给定的 method 是否支持
     * @param String $key method
     * @return Bool
     */
    public function hasWhereMethod($key)
    {
        $wp = $this->whereParser;
        if ($wp instanceof WhereParser && method_exists($wp, $key)) {
            return true;
        }
        //whereFooBar() / orderFooBar()
        if (strlen($key)>5 && in_array(substr($key, 0,5), ["where","order"])) {
            return true;
        }
        return false;
    }

    /**
     * debug 输出 SQL
     * $curd->debug()->select() 输出 根据当前查询参数 得到的 SQL
     * @param Bool $debug 默认 true
     * @return Curd $this
     */
    public function debug($debug=true)
    {
        $this->debug = $debug;
        return $this;
    }



    /**
     * 事务操作，调用方法：
     *      $db->Mdn_a->nojoin()->column("*")->where("...")->trans("update", [$data...]);
     *      $db->Mdn_b->join(true)->where("...")->trans("delete");
     *      $db->Mdn_c->whereEnable(1)->trans("delete");
     *      ...
     *      $db->Mdn_[a|b|c]->commit(function($rs 每个 curd 操作的 结果) {...});
     * 
     * 每次调用 trans 方法后，当前的 curd 实例将被 reset，这样下一次 curd 操作可以指向不同的 数据模型(表)
     * 每次调用 commit 方法后，Curd::$transaction 数组将被重置清空，因此确认执行事务必须通过 commit 方法
     * 如果要手动开始执行事务，则应在执行完毕后，手动清空 Curd::$transaction 数组，否则，下一次执行事务操作将发生严重错误
     * 
     * !! 事务操作只能在同一个数据库下执行，不同的数据库，意味着不同的 medoo 实例，无法使用 action 方法
     */

    /**
     * 将某个 curd 操作放入 transaction 队列
     * @param String $method 本次 curd 操作最终要执行的 medoo 方法
     * @param Array $args 某些 medoo 方法需要额外参数，例如：update
     *              最后一个参数可以是预定义的 回调函数，用于在执行阶段对 curd 操作结果进行处理
     * @return Bool 标记加入队列操作是否成功
     */
    public function trans($method, ...$args)
    {
        //先尝试提取 可能存在的 回调函数
        $callback = null;
        if (!empty($args) && is_callable($args[count($args)-1])) {
            $callback = array_pop($args);
        }

        //加入 transaction 队列的 curd 操作必须针对同一个数据库
        $trans = Curd::$transaction;
        if (!Is::nemarr($trans) || !Is::indexed($trans)) {
            Curd::$transaction = [];
            $trans = [];
        }
        if (!empty($trans)) {
            //如果队列已有等待执行的 curd 操作，比较依赖的 db 实例是否一致 dbkey 相等
            $db0 = $trans[0]["db"] ?? null;
            $dbi = $this->db;
            if (!$db0 instanceof Db || !$dbi instanceof Db) return false;
            if ($db0->key !== $dbi->key) return false;
        }

        //取得当前 curd 操作参数
        $params = $this->getMedooMethodParams($method, $args);

        if (Is::nemarr($params) && Is::indexed($params)) {
            //push 到 Curd::$transaction 数组
            Curd::$transaction[] = [
                //缓存此刻的 curd 实例，以及 curd 依赖的 db实例/model类
                "curd" => $this,
                "db" => $this->db,
                "model" => $this->model,
                "method" => $method,
                "params" => $params,
                "callback" => $callback
            ];

            //释放当前 curd 实例，$this = null
            $this->unset();

            //加入队列成功
            return true;
        }

        //加入队列失败
        return false;
    }

    /**
     * 开始按顺序执行 transaction 队列
     * @param Callable $callback 可以对每个 curd 操作的结果，执行回调函数
     * @return Array 返回统一的事务执行结果
     *  [
     *      "result" => [] | null,
     *      "error" => null 错误实例,
     *      "transaction" => []
     *  ]
     */
    public function commit($callback=null)
    {
        //返回值
        $rtn = [
            "result" => null,
            "error" => null,
            "transaction" => []
        ];

        //获取执行队列
        $trans = Curd::$transaction;
        if (!Is::nemarr($trans) || !Is::indexed($trans)) {
            //队列不合法，或队列为空，不执行，返回
            Curd::$transaction = [];
            return $rtn;
        }

        //获取执行事务队列操作的 db 实例
        $db = $trans[0]["db"];
        if (!$db instanceof Db) {
            //队列中的操作 缺少依赖的 数据库实例，报错
            trigger_error("orm/base::事务队列操作参数不合法", E_USER_ERROR);
            return $rtn;
        }

        //使用 medoo 库的 action 方法，执行 transaction 队列
        $result = [];
        $error = null;
        $db->medoo("action", function($medoo) use ($trans, $db, &$result, &$error) {
            try {
                for ($i=0;$i<count($trans);$i++) {
                    //提取队列中的 执行参数
                    $tri = $trans[$i];
                    $ci = $tri["curd"] ?? null;
                    $dbi = $tri["db"] ?? null;
                    $mdi = $tri["model"] ?? null;
                    $mi = $tri["method"] ?? null;
                    $pi = $tri["params"] ?? [];
                    $cb = $tri["callback"] ?? null;
        
                    /**
                     * 检查 队列中的 操作是否在同一个数据库下
                     */
                    if (
                        !$dbi instanceof Db ||
                        $db->key !== $dbi->key
                    ) {
                        //存在跨库的 curd 操作，返回 false 执行回滚
                        $result = null;
                        return false;
                    }
        
                    //执行队列中的 curd 操作
                    // 1 执行 medoo 方法
                    $rs = $medoo->$mi(...$pi);
                    // 2 通过缓存的 Model 类执行 wrap 方法对 medoo 方法返回数据进行包裹
                    $rs = $mdi::wrap($rs, $mi, $ci);
                    if (is_callable($cb)) {
                        //如果队列操作中定义了回调方法，此处执行
                        $rs = $cb($rs);
                    } else if (is_callable($callback)) {
                        //如果 commit 方法定义了回调函数，此处执行
                        $rs = $callback($rs);
                    }
        
                    //保存处理结果
                    $result[] = $rs;
                }

                //重置 transaction 队列
                Curd::$transaction = [];

            } catch (\Exception $e) {
                //发生异常
                //缓存 error 
                $error = $e;
                //直接返回 false 通知 medoo 库执行 rollback
                return false;
            }
        });

        if (!empty($error)) {
            //有错误信息
            //准备返回值
            $rtn["error"] = $error;
            $rtn["transaction"] = array_merge($trans);
            //重置 transaction 队列
            Curd::$transaction = [];
            //报错
            trigger_error("orm/base::执行事务出错，".$error->getMessage(), E_USER_ERROR);
            return $rtn;
        }

        //返回执行结果
        $rtn["result"] = $result;
        return $rtn;

    }


    
}