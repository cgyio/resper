<?php
/**
 * cgyio/resper 数据库操作
 * Curd 操作类 where 参数处理
 */

namespace Cgy\orm\curd;

use Cgy\Orm;
use Cgy\orm\Db;
use Cgy\orm\Model;
use Cgy\orm\Curd;
use Cgy\orm\curd\Parser;
use Cgy\orm\curd\JoinParser;
use Cgy\util\Is;
use Cgy\util\Arr;

class WhereParser extends Parser 
{
    //解析得到的 where 参数
    public $where = [];


    /**
     * 初始化 curd 参数
     * !! 子类必须实现 !!
     * @return Parser $this
     */
    public function initParam()
    {
        return $this;
    }

    /**
     * 设置 curd 参数
     * !! 子类必须实现 !!
     * 构造 medoo 查询 where 参数
     * @param Mixed $param 要设置的 curd 参数
     * @return Parser $this
     */
    public function setParam($param=null)
    {
        if (!Is::nemarr($param)) return $this;
        $ow = $this->where;
        $this->where = Arr::extend($ow, $param);
        return $this;
    }

    /**
     * 重置 curd 参数 到初始状态
     * !! 子类必须实现 !!
     * @return Parser $this
     */
    public function resetParam()
    {
        $this->where = [];
        return $this;
    }

    /**
     * 执行 curd 操作前 返回处理后的 curd 参数
     * !! 子类必须实现 !!
     * @return Mixed curd 操作 medoo 参数，应符合 medoo 参数要求
     */
    public function getParam()
    {
        $where = $this->where;
        if (empty($where)) return [];
        $where = $this->withTbnPre($where);
        return $where;
    }



    /**
     * 构造 where 参数 方法
     */

    /**
     * 构造 where 参数
     * whereCol("field name", "~", ["foo","bar"])  -->  where([ "field name[~]"=>["foo","bar"] ]) 
     * @param String $key 列名称
     * @param Array $args 列参数
     * @return Parser $this
     */
    public function whereCol($key, ...$args)
    {
        if (!$this->model::hasColumn($key) || empty($args)) return $this;
        $where = [];
        if (count($args) == 1) {
            $where[$key] = $args[0];
        } else {
            $where[$key."[".$args[0]."]"] = $args[1];
        }
        return $this->setParam($where);
    }

    /**
     * 构造 where 参数
     * 同时设置多个字段的 查询值
     * 参数形式：
     *      whereCols( [col1,col2], "~", val) === whereCols("OR", [col1,col2], "~", val)
     *      whereCols("AND", [col1,col2], val) === whereCol1(val)->whereCol2(val)
     */
    public function whereCols(...$args)
    {
        if (empty($args)) return $this;
        $where = [];
        $aor = Is::nemstr($args[0]) ? array_shift($args) : "OR";
        if (!in_array($args[0], ["AND", "OR"])) $aor = "OR";
        $cols = Is::nemarr($args[0]) ? array_shift($args) : [];
        if (!Is::indexed($cols) || empty($cols)) return $this;
        foreach ($cols as $i => $coln) {
            if ($aor=="AND") {
                $this->whereCol($coln, ...$args);
            } else {
                if (count($args) == 1) {
                    $where[$coln] = $args[0];
                } else {
                    $where[$coln."[".$args[0]."]"] = $args[1];
                }
            }
        }

        if ($aor=="AND") return $this;
        return $this->setParam([
            "OR #where cols" => $where
        ]);
    }

    /**
     * 构造 where 参数
     * 筛选 某些字段
     * [
     *      "column" => [
     *          "logic" => "=/>/</>=/<=/<>/></! 等于/大于/小于/不小于/不大于/之内/之外/不等于",
     *          "value" => ""/[]
     *      ]
     * ]
     * @param Array $filter 筛选参数
     * @return Parser $this
     */
    public function filter($filter)
    {
        $fs = [];
        foreach ($filter as $coln => $fc) {
            $flgc = $fc["logic"] ?? "=";
            $fv = $fc["value"] ?? null;
            if (is_null($fv) || $fv=="" || (Is::indexed($fv) && count($fv)<=0)) continue;
            if (in_array($flgc, ["=","=="])) {
                $ck = $coln;
            } else {
                $ck = $coln."[".$flgc."]";
            }
            $fs[$ck] = $fv;
        }
        if (empty($fs)) return $this;
        return $this->setParam($fs);
    }

    /**
     * 构造 where 参数
     * 关键字搜索
     * search("sk,sk,...")
     * @param String $sk 关键字，可有多个，逗号隔开
     * @return Parser $this
     */
    public function search($sk)
    {
        if (!Is::nemstr($sk)) return false;
        $ska = explode(",", trim(str_replace("，",",",$sk), ","));
        //可以搜索的 字段
        $scols = $this->conf->searchColumns;
        if (empty($scols)) return false;
        //有关联表的 字段
        $jcols = $this->conf->joinColumns;
        $or = [];
        for ($i=0;$i<count($scols);$i++) {
            $col = $scols[$i];
            if (in_array($col, $jcols)) {
                //如果要搜索的字段是 关联表字段，则 获取此关联表的 searchable 字段
                $jtbn = array_keys($this->conf->join["column"][$col])[0];
                $jtbo = $this->curd->db->model($jtbn);
                $jtbc = $jtbo::$config;
                $jtbscols = $jtbc->searchColumns;
                foreach ($jtbscols as $jtbcol) {
                    $or[$jtbn.".".$jtbcol."[~]"] = $ska;
                }

            } else {
                $or[$col."[~]"] = $ska;
            }
        }
        return $this->setParam([
            "OR #search keywords" => $or
        ]);
    }

    /**
     * 构造 where 参数
     * limit 参数 
     * @param Array $limit 与 medoo limit 参数格式一致
     * @return Parser $this
     */
    public function limit($limit=[])
    {
        if (
            (is_numeric($limit) && $limit>0) ||
            (Is::nemarr($limit) && Is::indexed($limit))
        ) {
            $this->where["LIMIT"] = $limit;
        }
        return $this;
    }

    /**
     * 构造 where 参数
     * 分页加载
     * @param Int $ipage 要加载的页码，>=1
     * @param Int $pagesize 每页记录数，默认 100
     */
    public function page($ipage=1, $pagesize=100)
    {
        $ipage = $ipage<1 ? 1 : $ipage;
        if ($ipage==1) {
            return $this->limit($pagesize);
        }
        $ps = ($ipage-1)*$pagesize;
        return $this->limit([$ps, $pagesize]);
    }

    /**
     * 构造 where 参数
     * order 参数 
     * @param Array $order 与 medoo order 参数格式一致
     * @return Parser $this
     */
    public function order($order=[])
    {
        if (
            Is::nemstr($order) ||
            (Is::nemarr($order) && Is::associate($order))
        ) {
            if (Is::nemarr($order)) {
                foreach ($order as $i => $v) {
                    if (is_numeric($i)) continue;
                    if (Is::nemstr($v)) {
                        if (in_array(strtoupper($v), ["DESC","ASC"])) {
                            $order[$i] = strtoupper($v);
                        }
                    }
                }
            }
            $this->where["ORDER"] = $order;
        }
        return $this;
    }

    /**
     * 构造 where 参数
     * orderCol("col name", "DESC")  -->  order([ "tbn.col"=>"DESC" ])
     * @param String $key 列名称
     * @param Array $args 列参数
     * @return Parser $this
     */
    public function orderCol($key, ...$args)
    {
        if (!$this->model::hasColumn($key)) return $this;
        $order = [];
        if (empty($args)) {
            $order = $key;
        } else {
            if (in_array(strtoupper($args[0]), ["DESC", "ASC"])) {
                $order[$key] = strtoupper($args[0]);
            } else {
                $order = $key;
            }
        }
        return $this->order($order);
    }

    /**
     * 构造 where 参数
     * match 参数 全文搜索
     * @param Array $match 与 medoo match 参数格式一致
     * @return Parser $this
     */
    public function match($match=[])
    {
        if (!empty($match)) {
            $this->where["MATCH"] = $match;
        }
        return $this;
    }



    /**
     * tools
     */

    /**
     * 处理 where 参数 array 
     * 递归处理
     * 
     * 如果 joinParser->use==true && joinParser->available==true 则：
     *      所有字段名前加上 table.
     * 如：
     *      where = [
     *          "foo" => ["bar","tom"],
     *          "age[>]" => 20,
     *          "OR #comment" => [
     *              "status[~]" => "fin",
     *              "isfin" => 1
     *          ],
     *          "LIMIT" => 20,
     *          "ORDER" => [
     *              "cola",
     *              "colb" => "ASC"
     *          ],
     *          "MATCH" => [
     *              "columns" => [
     *                  "colc", "cold"
     *              ],
     *              "keyword" => "foobar"
     *          ]
     *      ]
     * 修改为：
     *      where = [
     *          "table.foo" => ["bar","tom"],
     *          "table.age[>]" => 20,
     *          "OR #comment" => [
     *              "table.status[~]" => "fin",
     *              "table.isfin" => 1
     *          ],
     *          "LIMIT" => 20,
     *          "ORDER" => [
     *              "table.cola",
     *              "table.colb" => "ASC"
     *          ],
     *          "MATCH" => [
     *              "columns" => [
     *                  "table.colc", "table.cold"
     *              ],
     *              "keyword" => "foobar"
     *          ]
     *      ]
     *      
     * @param Array $where where 参数
     * @return Array 修改后的 where 参数
     */
    public function withTbnPre($where=[])
    {
        $jp = $this->curd->joinParser;
        if (!$jp instanceof JoinParser) return $where;
        if (!$jp->use || !$jp->available) return $where;

        $tbn = $this->conf->table;

        foreach ($where as $col => $colv) {
            //LIMIT
            if (strtoupper($col)=="LIMIT") continue;

            //MATCH
            if (strtoupper($col)=="MATCH") {
                if (isset($colv["columns"]) && Is::indexed($colv["columns"])) {
                    $where["MATCH"]["columns"] = array_map([$this, 'preTbn'], $colv["columns"]);
                }
                continue;
            }

            //ORDER
            if (strtoupper($col)=="ORDER") {
                if (is_string($colv) && $colv!="") {
                    $where["ORDER"] = $this->preTbn($colv);
                } else if (Is::nemarr($colv)) {
                    $where["ORDER"] = $this->withTbnPre($colv);
                }
                continue;
            }

            //AND/OR
            if (strpos($col, "AND")!==false || strpos($col, "OR")!==false) {
                $where[$col] = $this->withTbnPre($colv);
                continue;
            }

            if (is_int($col) && Is::nemstr($colv)) {
                $where[$col] = $this->preTbn($colv);
            } else if (is_string($col)) {
                $cola = $this->preTbn($col);
                $where[$cola] = $colv;
                if ($col!=$cola) unset($where[$col]);
            }
        }

        return $where;
    }

    /**
     * col      --> table.col
     * col[>]   --> table.col[>]
     * @param String $col 列名称
     * @return String 
     */
    protected function preTbn($col)
    {
        if (!Is::nemstr($col)) return $col;
        //已经是 table.col 直接返回
        if (strpos($col,".")!==false) return $col;
        $tbn = $this->conf->table;
        $fds = $this->conf->columns;
        if (strpos($col, "[")===false) {
            if (in_array($col, $fds)) {
                return "$tbn.$col";
            }
            return $col;
        } else {
            $coa = explode("[", $col);
            $coa[0] = $this->preTbn($coa[0]);
            return implode("[", $coa);
        }
    }


}
