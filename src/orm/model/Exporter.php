<?php
/**
 * 数据表(模型) 实例数据输出工具
 */

namespace Cgy\orm\model;

use Cgy\Orm;
use Cgy\orm\Db;
use Cgy\orm\Model;
use Cgy\orm\model\Config;
use Cgy\orm\model\ModelSet;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;

class Exporter 
{
    /**
     * 依赖
     */
    //关联的 数据库 实例
    public $db = null;
    //关联的 数据表(模型) 类 全称
    public $model = "";
    //关联的 数据表(模型) 类 $config
    public $conf = null;
    //要输出的 数据表(模型) 实例
    public $rs = null;

    /**
     * 构造
     * @param Model $rs model 实例
     * @return void
     */
    public function __construct($rs)
    {
        if (!$rs instanceof Model) return null;
        $this->rs = $rs;
        $this->model = $rs::$cls;
        $this->db = $this->model::$db;
        $this->conf = $this->model::$config;

        //对 $rs->context 进行初始处理
        $this->initContext();
    }

    /**
     * 在 exporter 创建时 对 $rs->context 执行处理
     * 对于特殊 type 的 字段值 进行 处理
     * @return Exporter $this
     */
    public function initContext()
    {
        //主表
        $fds = $this->conf->columns;
        $fdc = $this->conf->column;

        //对特殊格式字段执行 值处理
        /*$sfds = $this->conf->specialFields;
        foreach ($sfds as $tp => $sfdi) {
            $m = "init".ucfirst($tp)."FieldVal";
            if (method_exists($this, $m)) {
                $this->$m();
            }
        }*/
    }

    /**
     * 对 $rs->context 进行处理
     * 处理 time 类型 字段值
     * 增加一个 field_exp 字段 输出 format 日期/时间
     * @return Exporter $this
     */
    protected function initTimeFieldVal()
    {
        $spec = $this->conf->specialFields;
        $fds = $spec["time"] ?? [];
        foreach ($fds as $i => $fdn) {
            $fdc = $this->conf->column[$fdn]["time"];
            $format = $fdc["type"]=="datetime" ? "Y-m-d H:i:s" : "Y-m-d";
            $fv = $this->rs->context[$fdn];
            $str = $fv<=0 ? "" : date($format, $fv*1);
            $this->rs->context[$fdn."_exp"] = $str;
        }
        return $this;
    }

    /**
     * 对 $rs->context 进行处理
     * 处理 isMoney 类型 字段值
     * 增加一个 field_exp 字段 输出 ￥100.00 金额
     * @return Exporter $this
     */
    protected function initMoneyFieldVal()
    {
        $spec = $this->conf->specialFields;
        $fds = $spec["money"] ?? [];
        foreach ($fds as $i => $fdn) {
            $fv = $this->rs->context[$fdn];
            $str = "￥".(round($fv*100)/100);
            $str = strpos($str, ".")===false ? $str.".00" : $str."0000";
            $dotidx = strpos($str, ".");
            $str = substr($str, 0,$dotidx+3);
            $this->rs->context[$fdn."_exp"] = $str;
        }
        return $this;
    }



    /**
     * 输出记录实例的 字段值 数据
     */

    /**
     * 输出方法
     * 入口方法
     * @param String $key 字段名 / 关联表_字段名 / ...
     * @return Mixed
     */
    public function export($key="")
    {
        /**
         * 按输入格式 构建输出数据
         *  $rs->ctx(
         *      "column",
         *      "table",
         *      "getterFunc:alias",
         *      [
         *          "alias" => "foo", 
         *          "k"     => "foo_bar", 
         *          "kk"    => "table", 
         *          ...
         *      ]
         *  )  --> 
         *  [
         *      "column"    => $rs->context["column"],
         *      "table"     => $rs->joined["table"]->exporter->export(),
         *      "alias"     => $rs->getterFunc(),
         *      [
         *          "alias" => $rs->context["foo"],
         *          "k"     => $rs->joined["foo"]->context["bar"],
         *          "kk"    => $rs->joined["table"]->exporter->export(),
         *      ]
         *  ]
         */
        $args = func_get_args();
        $mapper = [];
        if (count($args)==1 && Is::nemarr($args[0])) {
            $mapper = $args[0];
        } else if (count($args)>1) {
            foreach ($args as $i => $arg) {
                if ($arg == "" || $arg == "_") {
                    $ak = $this->conf->table;
                    $mapper[$ak] = $arg;
                } else if (Is::nemstr($arg)) {
                    $aa = explode(":", $arg);
                    $ak = $aa[1] ?? $aa[0];
                    //$ak = trim($ak, "_");    // table_ --> table
                    $mapper[$ak] = $aa[0];
                } else if (Is::nemarr($arg) && isset($arg["alias"]) && Is::nemstr($arg["alias"])) {
                    $ak = $arg["alias"];
                    unset($arg["alias"]);
                    $mapper[$ak] = $arg;
                }
            }
        }
        if (!empty($mapper)) return $this->mapper($mapper);

        /**
         * $key == "" | "_"
         * $rs->ctx() == $rs->_ == $rs->context
         * 输出 主表数据
         */
        if (empty($key) || $key=="" || $key=="_") return $this->rs->context;

        /**
         * $key == "all"
         * $rs->all == $rs->exporter->expAll()
         * 输出 全部数据
         */
        if ($key == "all") return $this->expAll();

        /**
         * $key == column name
         * $rs->ctx("column") == $rs->context["column"]
         * $rs->ctx("getterFunc") == $rs->getterFunc == $rs->getterFunc()
         * 返回主表 字段值
         */
        if (isset($this->rs->context[$key])) return $this->rs->context[$key];
        $gfds = $this->conf->getters;
        if (in_array($key, $gfds)) {
            return $this->rs->$key();
        }

        /**
         * 返回关联表 实例 或 关联表数据
         */
        $jtbs = $this->rs->joined;

        /**
         * $key == PskuRs
         * $rs->ctx("PskuRs") == $rs->PskuRs == $rs->joined["Psku"]
         * 返回关联表 实例
         */
        if (strlen($key)>2 && substr($key, -2)=="Rs") {
            $jtbn = ucfirst(substr($key, 0, -2));
            if (isset($jtbs[$jtbn])) return $jtbs[$jtbn];
        }

        /**
         * $key == Psku | psku_foo | psku_foo_bar_...
         * $rs->ctx("Psku") == $rs->Psku == $rs->joined["Psku"]->exporter->export()
         * $rs->ctx("psku_foo") == $rs->psku_foo == $rs->joined["Psku"]->context["foo"]
         * $rs->ctx("psku_foo_bar") == $rs->psku_foo_bar == $rs->joined["Psku"]->exporter->export("foo_bar")
         * 返回关联表数据
         */
        if (isset($jtbs[ucfirst($key)])) {
            $jtbn = ucfirst($key);
            $jtb = $jtbs[$jtbn];
            return $jtb->exporter->export();
        } else if (strpos($key, "_")!==false) {
            $ka = explode("_", $key);
            if (isset($jtbs[ucfirst($ka[0])])) {
                $jtb = $jtbs[ucfirst(array_shift($ka))];
                $suk = implode("_", $ka);
                if ($suk=="") return $jtb->exporter->export();
                $rst = $jtb->exporter->export($suk);
                if (!is_null($rst)) return $rst;
            }
        }

        return null;
    }

    /**
     * 按指定格式 输出数据
     * 入口方法
     * @param Array $mapper 要输出的数据格式 
     *  [ 
     *      "键名" => "export 方法的 参数字符串", 
     *      "键名" => [ 
     *          "键名" => 可嵌套 $mapper, 
     *          ... 
     *      ] 
     *  ]
     * @return Array 输出数据
     */
    public function mapper($mapper=[])
    {
        if (empty($mapper)) return null;
        $rtn = [];
        foreach ($mapper as $k => $v) {
            if (Is::nemstr($v) || $v=="") {
                $rtn[$k] = $this->export($v);
            } else if (Is::nemarr($v) && Is::associate($v)) {
                $rtn[$k] = $this->mapper($v);
            }
        }
        if (empty($rtn)) return null;
        return $rtn;
    }

    /**
     * 输出 主表数据 + 所有 join 关联表数据
     * @return Array
     */
    public function expAll()
    {
        $rtn = [];
        $rtn = array_merge($rtn, $this->rs->context);
        //关联表
        $jtbs = $this->rs->joined;
        if (empty($jtbs)) return $rtn;
        foreach ($jtbs as $tbk => $jrs) {
            $jctx = $jrs->context;
            //$jctx = $jrs->exporter->expAll();     //只 返回 一层 关联表，关联表的关联表不返回，因为可能会出现 循环引用的问题
            foreach ($jctx as $jf => $jv) {
                $rtn[strtolower($tbk)."_".$jf] = $jv;
            }
        }
        return $rtn;
    }
}
