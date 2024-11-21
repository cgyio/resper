<?php
/**
 * cgyio/resper Orm 数据库操作类
 * model/Record 某条记录实例方法
 * 
 * Model 类的实例方法 继承于此
 */

namespace Cgy\orm\model;

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
        Orm::eventRegist($this);

        //执行 可能存在的 initInsFooBar() 通常由 实现各种数据操作功能的 traits 引入
        $this->initInsQueue();
        //最后执行 initInsFinal() 方法，可由各 数据表(模型) 类自定义
        $this->initInsFinal();

        //触发 数据记录实例化 事件
        Orm::eventTrigger("model-insed", $this);
    }

    /**
     * 构造
     * 根据 curd 返回的数据 创建 $rs->context 以及 join 关联表实例
     * @param Array $data curd 操作返回的数据，可能包含关联表数据
     * @return Model $this
     */
    protected function initInsData($data=[])
    {
        //如果未传入 初始 data 则视为新建记录，初始 data = 默认值 default
        if (empty($data)) {
            $data = static::$configer->default;
            //标记为 新建(未保存)记录
            $this->isNew = true;
        }
        
        //从 data 中分离出 join 关联表返回的数据
        $jtbs = static::$configer->join["tables"] ?? [];
        $mdata = [];
        $jdata = [];
        if (empty($jtbs)) $mdata = $data;
        foreach ($jtbs as $i => $tbn) {
            $tbn = strtolower($tbn);
            $jdi = [];
            foreach ($data as $f => $v) {
                if (substr($f, 0, strlen($tbn)+1)==$tbn."_") {
                    $jdi[substr($f, strlen($tbn)+1)] = $v;
                    //unset($data[$f]);
                } else {
                    $mdata[$f] = $v;
                }
            }
            if (!empty($jdi)) {
                $jdata[$tbn] = $jdi;
            }
        }

        //当前主表数据写入 context
        $this->context = $mdata;
        //写入主表初始数据 origin
        $this->origin = $mdata;

        //创建 join 关联表 实例
        if (!empty($jdata)) {
            foreach ($jdata as $tbi => $tdi) {
                $tbk = ucfirst($tbi);
                $tcls = static::$db->$tbk->cls;
                $this->joined[$tbk] = $tcls::create($tdi);
            }
        }
        
        return $this;
    }

    /**
     * 依次执行 可能存在的 initInsFooBar()
     * 通常由 实现各种数据操作功能的 traits 引入
     * @return Model $this
     */
    protected function initInsQueue()
    {
        $model = static::$cls;
        //var_dump($model);
        $ms = cls_get_ms($model, function($mi) {
            if (substr($mi->name, 0, 7)==="initIns") {
                //必须是实例方法
                if ($mi->isStatic()) return false;
                $mk = substr($mi->name, 7);
                return !in_array(strtolower($mk), ["data","queue","final"]);
            }
            return false;
        }, "protected");
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
     * __get
     * @param String $key
     * @return Mixed
     */
    public function __get($key)
    {
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


        //要求此 数据表(模型) 类必须经过初始化
        if (!static::$db instanceof Dbo) return null;

        /**
         * $rs->Db / $rs->Main
         * 返回 $model::$db
         */
        if ($key=="Db" || $key==ucfirst(static::$db->name)) {
            return static::$db;
        }

        /**
         * $rs->Model / $rs->Tablename
         * 相当于 $db->Model
         */
        if ($key=="Model" || $key==static::$name) {
            $tbn = ucfirst(static::$name);
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
         * $rs->conf
         * 访问 $model::$configer
         */
        if ($key=="conf") {
            return static::$configer;
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
        $gfds = static::$configer->getterFields;
        if (in_array($method, $gfds)) {
            $getter = $method."Getter";
            if (method_exists($this, $getter)) {
                return $this->$getter();
            }
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
}