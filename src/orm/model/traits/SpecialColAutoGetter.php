<?php
/**
 * cgyio/resper 数据库操作 
 * trait 可复用功能，为各 数据模型(表) 类/实例 增加 实用功能
 * 
 * 如果数据模型中 存在某些特殊类型的字段，在模型初始化阶段，将为这些字段 自动添加 Getter 方法，
 * 用于对一些特殊字段进行 格式化/自动计算 等处理，输出为新数据
 * 
 * 会自动添加 Getter 方法的 字段类型：
 *      isTime          --> 新增 columnStr 字段，根据原字段的 time 参数，生成 时间/日期 字符串
 *      isMoney         --> 新增 columnStr 字段，将金额数字，转换为 ￥0.5000 格式文本
 * 
 * 
 */

namespace Cgy\orm\model\traits;

use Cgy\Request;
use Cgy\request\Url;
use Cgy\request\Curl;
use Cgy\Orm;
use Cgy\orm\Db;
use Cgy\orm\model\ModelSet;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Conv;
use Cgy\util\Num;

trait SpecialColAutoGetter 
{

    /**
     * AutoGetter 方法统一参数格式
     * 统一传入的参数，是模型初始化阶段 自动生成的 getter 字段的参数，
     * 保存在 $model::$config->context["column"]["新增字段"] 中
     * 
     * $gc = [
     *      "name" => 新增计算字段名,
     *      "title" => 与原字段 title 相同,
     *      "desc" => 新增字段介绍文字,
     *      "width" => 在前端表格组件中的显示宽度，与原字段一致,
     *      "type" => [
     *           "db" => 在数据库中的保存格式,
     *           "js" => 在前端的数据格式,
     *           "php" => 在后端 php 环境中的数据格式
     *      ],
     *      "isGetter" => true,
     *      "origin" => 原字段名称,
     *      "method" => 此处定义的 ***AutoGetters 方法名,
     * 
     *      ...可能有其他参数
     * ]
     */

    /**
     * 包含原始字段关联表中的多条 符合原始字段值的 记录集数据
     * 输出数据 ModelSet->ctx()
     * @param Object $gc auto getter 参数
     * @return Array
     */
    protected function srcCtxAutoGetters($gc)
    {
        //调用获取记录集实例的 方法
        $rs = $this->selectListRsAutoGetters($gc);
        //如果返回空值 或 返回的不是 记录集实例
        if (empty($rs) || !$rs instanceof ModelSet) return [];
        //只返回关联表的 主表数据+getter计算字段数据 不返回关联表的关联表数据
        $ctx = $rs->ctx();
        //返回
        return $ctx;
    }

    /**
     * 包含原始字段关联表中的 全部可选记录集数据
     * 输出数据 [ [ label=>..., value=>... ], ... ]
     * @param Object $gc auto getter 参数
     * @return Array
     */
    protected function srcValuesAutoGetters($gc)
    {
        //原字段名
        $origin = $gc->origin;
        //读取原字段 参数
        $colc = $this->conf->$origin;
        //select 参数
        $selc = $colc["select"] ?? [];
        $srcc = $selc["source"] ?? [];
        //关联表
        $mdn = $srcc["table"] ?? null;
        //关联字段，对应 label/value
        $colvn = $srcc["value"] ?? null;
        $colln = $srcc["label"] ?? null;

        //如果参数不正确
        if (!Is::nemstr($mdn) || !Is::nemstr($colvn) || !Is::nemstr($colln)) return [];
        //连接关联表
        $mdna = explode("/", $mdn);
        if (count($mdna)==1) {
            $mdn = ucfirst($mdn);
        } else {
            $mdn = ucfirst($mdna[0]).ucfirst($mdna[1]);
        }
        $mdo = Orm::$mdn();
        //如果连接失败
        if (!$mdo instanceof Db) return [];
        //查询记录集
        $rs = $mdo->column($colvn, $colln)->whereEnable(1)->select();
        //如果返回空值 或 返回的不是 记录集实例
        if (empty($rs) || !$rs instanceof ModelSet) return [];
        //返回关联表记录数据，整理为 [ [ label=>..., value=>... ], ... ]
        $vals = $rs->ctx(
            $colvn.":value",
            $colln.":label"
        );
        //返回
        return $vals;
    }

    /**
     * 包含原始字段关联表中的多条 符合原始字段值的 记录集
     * 输出数据 ModelSet 实例
     * @param Object $gc auto getter 参数
     * @return ModelSet|null 记录集
     */
    protected function srcAutoGetters($gc)
    {
        //原字段名
        $origin = $gc->origin;
        //读取原字段值
        $data = $this->$origin;
        //如果原字段数据为空，或不是 indexed 数组
        if (!Is::nemarr($data) || !Is::indexed($data)) return [];
        //读取原字段 参数
        $colc = $this->conf->$origin;
        //select 参数
        $selc = $colc["select"] ?? [];
        $srcc = $selc["source"] ?? [];
        //关联表
        $mdn = $srcc["table"] ?? null;
        //关联字段
        $lcoln = $srcc["value"] ?? null;
        //如果参数不正确
        if (!Is::nemstr($mdn) || !Is::nemstr($lcoln)) return $data;
        //连接关联表
        $mdna = explode("/", $mdn);
        if (count($mdna)==1) {
            $mdn = ucfirst($mdn);
        } else {
            $mdn = ucfirst($mdna[0]).ucfirst($mdna[1]);
        }
        $mdo = Orm::$mdn();
        //如果连接失败
        if (!$mdo instanceof Db) return $data;
        //查询记录集
        $rs = $mdo->where([
            $lcoln => $data
        ])->select();
        //返回查询结果
        return $rs;
    }

    /**
     * 包含原始字段关联的 api 全部返回数据
     * 输出数据 [ [ label=>..., value=>... ], ... ]
     * @param Object $gc auto getter 参数
     * @return Array
     */
    protected function apiValuesAutoGetters($gc)
    {
        //原字段名
        $origin = $gc->origin;
        //读取原字段 参数
        $colc = $this->conf->$origin;
        //select 参数
        $selc = $colc["select"] ?? [];
        $srcc = $selc["source"] ?? [];
        //关联 api
        $apin = $srcc["api"] ?? null;
        //如果参数不正确
        if (!Is::nemstr($apin)) return [];
        //访问 api
        $apiu = Url::mk($apin);
        $res = Curl::get($apiu);
        $res = Conv::j2a($res);
        //返回获取到的全部数据 []
        return $res;
    }

    /**
     * 包含原始字段关联的 api 返回数据中 符合原始字段值的 label 
     * 输出数据 label 或 [ label, ... ]
     * @param Object $gc auto getter 参数
     * @return String|Array
     */
    protected function apiLabelAutoGetters($gc)
    {
        //获取 api 全部可用数据
        $vals = $this->apiValuesAutoGetters($gc);
        //原字段名
        $origin = $gc->origin;
        //读取原字段值
        $data = $this->$origin;
        //读取原字段 参数
        $colc = $this->conf->$origin;
        //原字段 json 参数
        $jsonc = $colc["isJson"] ?  ($colc["json"] ?? []) : [];
        $jtype = $jsonc["type"] ?? null;
        $isarr = $jtype=="indexed";
        //如果原字段值 为空
        if ($isarr===true && (!Is::indexed($data) || !Is::nemarr($data))) return [];
        if ($isarr!==true && !Is::nemstr($data)) return "";
        //从可用数据中查找符合 原字段值的 label
        if ($isarr!=true) {
            //原字段是 单选
            foreach ($vals as $i => $val) {
                if (isset($val["value"]) && $val["value"]==$data) {
                    return $val["label"];
                }
            }
            return "";
        } else {
            //原字段是 多选
            $lbs = [];
            foreach ($vals as $i => $val) {
                if (!isset($val["value"]) || !isset($val["label"])) continue;
                if (in_array($val["value"], $data)) {
                    $lbs[] = $val["label"];
                }
            }
            return $lbs;
        }

    }

    /**
     * timeStrAutoGetters 时间戳输出字符串
     * @param Object $gc auto getter 参数
     * @return String
     */
    protected function timeStrAutoGetters($gc)
    {
        //原字段名
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