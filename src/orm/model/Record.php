<?php
/**
 * cgyio/resper Orm 数据库操作类
 * model/Record 某条记录实例方法
 * 
 * Model 类的实例方法 继承于此
 */

namespace Cgy\orm\model;

use Cgy\Orm;
use Cgy\orm\Db;
use Cgy\orm\model\Exporter;
use Cgy\util\Is;
use Cgy\util\Str;
use Cgy\util\Arr;
use Cgy\util\Num;
use Cgy\util\Cls;

class Record 
{
    /**
     * 数据表(模型) 实例参数
     */
    //数据表记录条目内容，不含 关联表 记录内容
    public $context = [];
    //当记录修改时，此处保存初始数据
    public $origin = [];
    //join 关联表 记录实例
    public $joined = [
        //"Tablename" => model instance,
        //...
    ];
    //是否新建 记录
    public $isNew = false;

    /**
     * 依赖
     */
    //数据记录实例输出工具
    public $exporter = null;

    //依赖：字段值转换对象 FieldConvertor 实例
    public $convertor = null;



    /**
     * 数据表(模型) 实例方法
     * 针对 一条记录
     */
    
    /**
     * 构造
     * 使用 $model::create() 方法创建 数据表记录实例
     * @return Model instance
     */
    public function __construct($data=[])
    {
        //解析 $data
        $this->initInsData($data);

        //建立输出工具实例
        $this->exporter = new Exporter($this);
        
        //创建事件订阅，订阅者为此 数据表记录实例
        //Orm::eventRegist($this);

        //执行 可能存在的 initInsFooBar() 通常由 实现各种数据操作功能的 traits 引入
        $this->initInsQueue();
        //最后执行 initInsFinal() 方法，可由各 数据表(模型) 类自定义
        $this->initInsFinal();

        //触发 数据记录实例化 事件
        //Orm::eventTrigger("model-insed", $this);
    }

    /**
     * 构造
     * 根据 curd 返回的数据 创建 $rs->context 以及 join 关联表实例
     * @param Array $data curd 操作返回的数据，可能包含关联表数据
     * @return Model $this
     */
    protected function initInsData($data=[])
    {
        //如果未传入 初始 data 或传入的 data 不包含 idf 字段值 则视为新建记录，初始 data = 默认值 default
        $idf = static::idf();
        if (empty($data) || !isset($data[$idf])) {
            //标记为 新建(未保存)记录
            $this->isNew = true;
            $dft = static::$config->default;
            $data = Arr::extend($dft, $data);
            //if (empty($data)) $data = static::$config->default;
        }
        
        //从 data 中分离出 join 关联表返回的数据
        $jtbs = static::$config->join["tables"] ?? [];
        //var_dump($jtbs);
        $mdata = [];
        $jdata = [];
        if (empty($jtbs)) $mdata = $data;
        $cdata = Arr::copy($data);
        foreach ($jtbs as $i => $tbn) {
            $tbn = strtolower($tbn);
            $jdi = [];
            foreach ($cdata as $f => $v) {
                if (substr($f, 0, strlen($tbn)+1)==$tbn."_") {
                    $jdi[substr($f, strlen($tbn)+1)] = $v;
                    unset($cdata[$f]);
                }
            }
            if (!empty($jdi)) {
                $jdata[$tbn] = $jdi;
            }
        }

        //当前主表数据写入 context
        $this->context = $cdata;
        //写入主表初始数据 origin
        $this->origin = $cdata;

        //创建 join 关联表 实例
        if (!empty($jdata)) {
            foreach ($jdata as $tbi => $tdi) {
                $tbk = ucfirst($tbi);
                $tcls = static::$db->model($tbi);
                $this->joined[$tbk] = $tcls::create($tdi);
            }
        }
        
        return $this;
    }

    /**
     * 构造
     * 依次执行 可能存在的 initInsFooBar()
     * 通常由 实现各种数据操作功能的 traits 引入
     * @return Model $this
     */
    protected function initInsQueue()
    {
        $model = static::$cls;
        //var_dump($model);
        $ms = Cls::methods($model, "protected", function($mi) {
            if (substr($mi->name, 0, 7)==="initIns") {
                //必须是实例方法
                if ($mi->isStatic()) return false;
                $mk = substr($mi->name, 7);
                return !in_array(strtolower($mk), ["data","queue","final"]);
            }
            return false;
        });
        if (empty($ms)) return $this;
        foreach ($ms as $n => $mi) {
            $fn = $mi->name;
            //执行这些方法
            $this->$fn();
        }
        return $this;
    }

    /**
     * 构造
     * 在 数据记录实例构造操作最后 执行此方法
     * !! 子类覆盖 !!
     * @return Model $this
     */
    protected function initInsFinal()
    {
        //... 子类实现
        return $this;
    }

    /**
     * 记录写入数据
     * @return Model $this
     */
    public function save()
    {
        $diff = $this->diff();
        if (empty($diff)) return $this;
        
    }

    /**
     * 判断哪些字段值被修改了
     * @return Array $data 被修改的字段值，用于写入数据库
     */
    protected function diff()
    {
        $idf = static::idf();
        $ctx = $this->context;

        //新建记录的 直接返回 context
        if ($this->isNew) {
            if (isset($ctx[$idf])) {
                unset($ctx[$idf]);
            }
            return $ctx;
        }

        $conf = static::$config;
        $cols = $conf->columns;
        $origin = $this->origin;
        $diff = [];
        foreach ($cols as $col) {
            if ($col==$idf) continue;
            $ctxi = !isset($ctx[$col]) ? null : $ctx[$col];
            $orgi = !isset($origin[$col]) ? null : $origin[$col];
            if (!Is::eq($ctxi, $orgi)) {
                $diff[$col] = $ctxi;
            }
        }
        return $diff;
    }
    

    /**
     * __get
     * @param String $key
     * @return Mixed
     */
    public function __get($key)
    {

        //要求此 数据表(模型) 类必须经过初始化
        if (!static::$db instanceof Db) return null;

        /**
         * 通过 $rs->exporter->export($key) 方法，返回数据记录 字段值/关联表字段值
         * 
         * $rs->fieldname       --> $rs->context["fieldname"]
         * $rs->getterFunc      --> $rs->getterFuncGetter()
         * $rs->_               --> $rs->exporter->export()
         * $rs->Table           --> $rs->joined["Table"] 关联表实例
         * $rs->table_          --> $rs->joined["Table"]->exporter->export()
         * $rs->table_foo_bar   --> $rs->joined["Table"]->foo_bar
         */
        $exper = $this->exporter;
        if ($exper instanceof Exporter) {
            $rst = $exper->export($key);
            if (!is_null($rst)) return $rst;
        }

        /**
         * $rs->Resper / $rs->resper
         * 返回 $model::$resper
         */
        if (strtolower($key) == "resper") return static::$resper;

        /**
         * $rs->Db / $rs->db / $rs->Main
         * 返回 $model::$db
         */
        if (strtolower($key) == "db" || strtolower($key) == strtolower(static::$db->name)) {
            return static::$db;
        }

        /**
         * $rs->Model / $rs->model / $rs->Tablename
         * 相当于 $db->Model
         */
        if (strtolower($key) == "model" || strtolower($key) == strtolower(static::$config->name)) {
            $tbn = ucfirst(static::$config->name);
            return static::$db->$tbn;
        }

        /**
         * $rs->Othermodel
         * 访问 当前 数据库下 其他 数据表(模型) 类
         * 相当于 $db->Other
         */
        if (static::$db->hasModel($key)) {
            return static::$db->$key;
        }

        /**
         * $rs->conf / $rs->Conf
         * 访问 $model::$configer
         */
        if (strtolower($key) == "conf") {
            return static::$config;
        }

        /**
         * $rs->curd 
         * 相当于 $db->Model->curd
         * $db->currentModel 指向当前 数据表，返回 $db->curd 实例
         */
        if (strtolower($key) == "curd") {
            return $this->Model->curd;
        }

        return null;
    }

    /**
     * __call
     * @param String $method
     * @param Array $args
     * @return Mixed
     */
    public function __call($method, $args)
    {
        /**
         * $rs->getterFunc()
         * 调用 数据表(模型) 实例 getter 方法
         */
        $conf = static::$config;
        $gfds = $conf->getters;
        if (in_array($method, $gfds)) {
            //手动定义的 getter
            $getter = $method."Getter";
            if (method_exists($this, $getter)) {
                return $this->$getter();
            }

            /**
             * 自动定义的 autoGetters 包含：
             *  1   根据字段类型 自动生成的 getter 的 计算字段值
             *      如：isTime 字段 会生成 ***Str 计算字段
             * 
             *  2   通过 trait 为 数据模型增加功能时 可能自动添加了一些 getter
             *      如：model\traits\Package 为 price 字段增加了 pricePkg 计算字段
             */
            $gc = $conf->$method;
            if (!empty($gc)) {
                $getter = $gc->method;
                if (method_exists($this, $getter)) {
                    return $this->$getter($gc);
                }
            }
        }

        /**
         * $rs->curdFunc()
         * $rs->where(...)->order(...)->select()  -->  $db->Foobar->where(...)->...
         */
        $curd = $this->curd;
        if (
            method_exists($curd, $method) ||
            $curd->hasWhereMethod($method) === true ||
            $curd->hasMedooMethod($method) === true
        
        ) {
            $db = static::$db;
            $mdn = ucfirst(static::$config->name);
            return $db->$mdn->$method(...$args);
        }


        return null;
    }

    /**
     * 输出 字段值
     * 调用 $exporter->export() 方法 输出 字段内容
     * @param String $args 查看 export 方法参数
     * @return Array [ field=>val, field=>val ]
     */
    public function ctx(...$args)
    {
        return $this->exporter->export(...$args);
    }

    /**
     * 输出全部字段
     * 调用 $exporter->expAll() 
     * @return Array 全部字段，包含 join 表
     */
    public function all()
    {
        return $this->exporter->expAll();
    }



    /**
     * ***AutoGetters 方法
     */

    /**
     * timeStrAutoGetters 时间戳输出字符串
     * @param Object $gc auto getter 参数
     * @return String
     */
    protected function timeStrAutoGetters($gc)
    {
        $origin = $gc->origin;
        //读取原字段值 时间戳
        $data = $this->$origin;
        //读取原字段 参数
        $colc = $this->conf->$origin;
        //确认 isTime
        if ($colc->isTime!=true) return $data;

        $tc = $colc->time;
        $ttp = $tc["type"];
        //判断是否 时间区间
        $range = substr($ttp, -6) == "-range";
        $ttp = str_replace("-range","",$ttp);
        $fo = $ttp=="datetime" ? "Y-m-d H:i:s" : "Y-m-d";
        if ($range) {
            if (!Is::indexed($data)) return [];
            return array_map(function($i) use ($fo) {
                if (!is_numeric($i) || !is_int($i*1)) return "";
                return date($fo, $i*1);
            }, $data);
        } else {
            if (!is_numeric($data) || !is_int($data*1)) return "";
            return date($fo, $data*1);
        }
    }

    /**
     * moneyStrAutoGetters 金额输出为字符串
     * 3.1415926  -->  ￥3.1416
     * @param Object $gc auto getter 参数
     * @return String
     */
    protected function moneyStrAutoGetters($gc)
    {
        $origin = $gc->origin;
        //读取原字段值 时间戳
        $data = $this->$origin;
        //读取原字段 参数
        $colc = $this->conf->$origin;
        //确认 isMoney
        if ($colc->isMoney!=true) return $data;
        //金额 转为 字符串
        $mc = $colc->money;
        $prec = $mc["precision"];
        $data = Num::roundPad($data, $prec);
        return $mc["icon"].$data;
    }
}