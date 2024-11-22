<?php
/**
 * cgyio/resper 数据库操作
 * Curd 操作类 column 参数处理
 */

namespace Cgy\orm\curd;

use Cgy\Orm;
use Cgy\orm\Db;
use Cgy\orm\Model;
use Cgy\orm\Curd;
use Cgy\orm\curd\Parser;
use Cgy\util\Is;
use Cgy\util\Arr;

class ColumnParser extends Parser 
{

    //解析得到的 column 参数
    public $column = [];

    /**
     * 初始化 curd 参数
     * !! 子类必须实现 !!
     * @return Parser $this
     */
    public function initParam()
    {
        $jp = $this->curd->joinParser;
        if ($jp->use==true) {
            $this->setJoinTableColumns();
        } else {
            $this->setParam("*");
        }
        return $this;
    }

    /**
     * 设置 curd 参数
     * !! 子类必须实现 !!
     * 构造 medoo 查询参数
     * 指定要返回值的 字段名 or 字段名数组 
     * 
     * medoo return data mapping 可构造返回的记录数据格式
     *      字段名数组，自动添加 输出格式 数据表(模型) 预定义的 字段类型：
     *          $column = [ "fieldname [JSON]", "tablename.fieldname [Int]", ... ]
     *          $column = func_get_args()
     * 
     * @param Mixed $param 与 medoo column 参数格式一致
     * @return Parser $this
     */
    public function setParam($param=null)
    {
        $args = func_get_args();
        if (empty($args)) {
            $column = ["*"];
        } else if (count($args)==1) {
            $column = is_array($args[0]) ? $args[0] : $args;
        } else {
            $column = [];
            for ($i=0;$i<count($args);$i++) {
                $argi = $args[$i];
                if (is_string($argi)) {
                    $column[] = $argi;
                } else if (is_array($argi)) {
                    $column = array_merge($column, $argi);
                } else {
                    continue;
                }
            }
        }
        if (Is::nemarr($column)) {
            $column = $this->setColumnTypeArr($column);
        } else {
            return $this;
        }
        $this->column = $column;
        return $this;
    }

    /**
     * 重置 curd 参数 到初始状态
     * !! 子类必须实现 !!
     * @return Parser $this
     */
    public function resetParam()
    {
        $this->column = [];
        return $this;
    }

    /**
     * 执行 curd 操作前 返回处理后的 curd column 参数
     * 每次查询都必须包含以下字段：
     *      $conf->idColumns id 字段
     *      $conf->generatorColumns 系统创建的自增 id
     *      $conf->includes 数组中指定的 字段
     * 
     * !! 子类必须实现 !!
     * @return Mixed curd 操作 medoo 参数，应符合 medoo 参数要求
     */
    public function getParam()
    {
        $column = $this->column;
        if (!is_array($column)) $column = [$column];
        
        $includes = $this->conf->includes;
        $idfs = $this->conf->idColumns;
        $gfs = $this->conf->generatorColumns;
        if (!is_array($includes)) $includes = [];
        if (Is::nemarr($idfs)) $includes = array_merge($includes, $idfs);
        if (Is::nemarr($gfs)) $includes = array_merge($includes, $gfs);

        $incs = $this->setColumnTypeArr($includes);
        foreach ($incs as $i => $fi) {
            if (!in_array($fi, $column)) {
                $column[] = $fi;
            }
        }
        return $column;
    }

    /**
     * 将关联表所有字段 添加到 column 参数
     * [ "join table name" => [ "*" ] ]
     * @return Parser $this
     */
    public function setJoinTableColumns()
    {
        $jp = $this->curd->joinParser;
        $cols = ["*"];
        if ($jp instanceof Parser) {
            $tbs = $jp->getJoinTables();
            if (!empty($tbs)) {
                foreach ($tbs as $i => $tbn) {
                    //$cols[$tbn] = [$tbn.".*"];
                    $cols[] = $tbn.".*";
                }
            }
        }
        $this->setParam($cols);
        return $this;
    }

    /**
     * 为查询字段名数组 中的 字段名 增加 [字段类型]
     * @param String $fdn 字段名  or  表名.字段名
     * @return String 表名.字段名 [类型]
     */
    protected function setColumnType($fdn)
    {
        if ($fdn=="*") return $this->setColumnTypeAll();
        if (substr($fdn, -2)==".*") {
            // table.*
            $mdn = ucfirst(str_replace(".*","",$fdn));
            return $this->setColumnTypeAll($mdn);
        }
        $db = $this->curd->db;
        $model = $this->curd->model;
        $cfg = $model::$config;
        $fds = $cfg->columns;
        $fdc = $cfg->column;
        $useJoin = $this->curd->joinParser->use;
        if (strpos($fdn, ".")===false) {
            //字段名  -->  表名.字段名 [类型]
            if (in_array($fdn, $fds)) {
                //读取预设的 字段类型
                $type = $fdc[$fdn]["type"]["php"] ?? "String";
                //if ($useJoin) $fdn = $model::$table.".".$fdn." (".$model::$table."_".$fdn.")";
                if ($useJoin) $fdn = $cfg->table.".".$fdn;
                if ($type!="String") {
                    return $fdn." [".$type."]";
                }
            }
        } else {
            //表名.字段名  -->  表名.字段名 [类型]
            $fda = explode(".", $fdn);
            $tbn = $fda[0];
            $nfdn = $fda[1];
            $nmodel = $db->model(ucfirst($tbn));
            $ncfg = $nmodel::$config;
            if (!empty($nmodel)) {
                $nfds = $ncfg->columns;
                $nfdc = $ncfg->column;
                if (in_array($nfdn, $nfds)) {
                    //读取预设的 字段类型
                    $ntype = $nfdc[$nfdn]["type"]["php"] ?? "String";
                    $fdn = $fdn." (".str_replace(".","_",$fdn).")";
                    if ($ntype!="String") {
                        return $fdn." [".$ntype."]";
                    }
                }
            }
        }
        return $fdn;
    }

    /**
     * 以递归方式处理输入的 查询字段名数组
     * @param Array $column 与 medoo column 参数格式一致
     * @return Array 返回处理后的数组
     */
    protected function setColumnTypeArr($column=[])
    {
        if (!Is::nemarr($column)) return $column;
        $fixed = [];
        foreach ($column as $k => $v) {
            if (Is::nemarr($v)) {
                $fixed[$k] = $this->setColumnTypeArr($v);
            } else if (Is::nemstr($v)) {
                $v = $this->setColumnType($v);
                if (!is_array($v)) $v = [ $v ];
                $fixed = array_merge($fixed, $v);
            } else {
                $fixed = array_merge($fixed, [ $v ]);
            }
        }
        return $fixed;
    }

    /**
     * 将 * 转换为 columns []
     * @param String $model 指定要查询的 fields 的 数据表(模型) 类，不指定则为当前 $this->curd->model
     * @return Array [ 表名.字段名 [类型], ... ]
     */
    protected function setColumnTypeAll($model=null)
    {
        $cmodel = empty($model);
        $model = empty($model) ? $this->curd->model : $this->curd->db->model($model);
        if (empty($model)) return [];
        $cfg = $model::$config;
        //$useJoin = $this->curd->joinParser->use;
        $fds = $cfg->columns;
        //if ($useJoin) {
        if (!$cmodel) {
            $fds = array_map(function ($i) use ($cfg) {
                return $cfg->table.".".$i;
            }, $fds);
        }
        return $this->setColumnTypeArr($fds);
    }

}