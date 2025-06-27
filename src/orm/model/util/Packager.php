<?php
/**
 * resper 框架 model 模型类通用工具类
 * 可以在模型类中使用的工具类
 * 需要传入 当前模型实例 作为依赖项
 * 
 * Packager 规格计算类
 */

namespace Cgy\orm\model\util;

use Cgy\orm\Model;
use Cgy\orm\model\Util;
use Cgy\util\Str;
use Cgy\util\Arr;
use Cgy\util\Is;

class Packager extends Util 
{
    /**
     * 规格计算必须的参数
     */
    public $unit = "克";
    public $netwt = 1;
    public $maxunit = "无";
    public $minnum = 1;
    //以最小规格计算数量时，是否只能是整数，默认是，即 不能出现 0.5 袋 的数据
    public $mustInt = true;
    //保留小数位数
    public $dig = 2;

    /**
     * 经过参数处理 得到的其他计算参数
     */
    //此品种是否散装
    public $isBulk = true;
    //此品种是否有最大包装
    public $hasMaxUnit = false;

    //经计算得到的品种规格字符串
    public $pkgString = ""; //袋装，150克/袋，10袋/箱  |  散装  |  散装，20Kg/袋

    /**
     * 实际计算的最小单位，默认 克
     * 此最小单位表示：在所有需要计算装量的情况下，都已此单位作为最小计算单位
     * 还可以选择 ml 毫升
     * !!! 不推荐选择其他最小单位 !!!
     */
    public $minunit = "克";

    /**
     * 可能存在的 中间规格
     * 按 小->大 依次排列
     * midunit 表示 当前规格的计量单位
     * midnum 表示 当前规格 每单位 包含 上一个规格的数量
     * minnum 表示 当前规格 每单位 包含 最小规格的数量
     */
    public $midpkg = [
        /*
        [
            "midunit" => "",
            "midnum" => 1,
            "minnum" => 1
        ],
        ...
        */
    ];

    /**
     * !! 子类必须覆盖
     * 工具类初始化方法
     * @return $this
     */
    protected function initUtil()
    {
        //从当前模型实例record中读取 规格计算必须的参数
        $record = $this->record;
        $pkgConf = $record->conf->package;
        if (!Is::nemarr($pkgConf)) return $this;
        $cols = $pkgConf["columns"] ?? [];
        if (!Is::nemarr($cols)) return $this;
        //从模型实例中读取指定字段值
        foreach ($cols as $col => $ocol) {
            $colv = $record->$ocol;
            if (empty($colv)) continue;
            if ($col=="netwt") $colv = (float)$colv;
            if ($col=="minnum") $colv = (int)$colv;
            $this->$col = $colv;
        }
        //读取可能存在的 计算参数
        if (isset($pkgConf["intunit"])) $this->mustInt = $pkgConf["intunit"];
        if (isset($pkgConf["dig"])) $this->dig = $pkgConf["dig"];

        //计算取得更多参数
        $this->isBulk = $this->unit==$this->minunit || $this->netwt==1;
        $this->hasMaxUnit = $this->maxunit!=="无";

        //生成规格字符串
        $this->pkgString = $this->getPkgString();

        return $this;
    }



    /**
     * 规格计算 工具方法
     */

    /**
     * 输出品种规格字符串
     */
    public function getPkgString()
    {
        $str = [];
        if ($this->isBulk) {
            $str[] = $this->unit==$this->minunit ? "散装" : "单".$this->unit."装";
        } else {
            $str[] = $this->unit."装";
            $nw = $this->netwt;
            $str[] = ($nw<1000 ? $nw.$this->minunit."/" : $this->gnumToKg($nw)."Kg/").$this->unit;
        }
        if ($this->hasMaxUnit) {
            if ($this->isBulk) {
                $nw = $this->netwt * $this->minnum;
                if ($this->unit==$this->minunit) {
                    $nw = $nw<1000 ? $nw.$this->minunit : $this->gnumToKg($nw)."Kg";
                } else {
                    $nw = $nw.$this->unit;
                }
                $str[] = $nw."/".$this->maxunit;
            } else {
                $str[] = $this->minnum.$this->unit."/".$this->maxunit;
            }
        }
        return implode("，", $str);
    }

    /**
     * 解析克重，计算对应的 小包装总数/大包装总数/[大包装+小包装数]/余数克重
     * @param Float $gnum 克重
     * @param Bool $real 是否按实际数量计算，默认 true，当需要计算采购数量时，选false，计算将向上凑整，不满1袋的，凑为1袋
     * @return Array
     *  [
     *      "g" => 克重，
     *      "kg" => Kg,
     *      "min" => 小包装总数,
     *      "max" => 大包装总数,
     *      "min_max" => [
     *          大包装数,
     *          小包装数
     *      ],
     *      "odd" => 余数克重
     *      "oddkg" => 余数kg
     *  ]
     */
    public function calcGnum($gnum, $real=true)
    {
        if (!is_numeric($gnum) || $gnum=='') $gnum = 0;
        $gnum = (float)$gnum;
        $nw = $this->netwt;
        $minn = $this->minnum;
        $mint = $this->mustInt;
        $isBulk = $this->isBulk;
        $hasMax = $this->hasMaxUnit;
        //$round = $this->round;
        //$gtk = $this->gnumToKg;
        $rtn = [
            "g" => $this->round($gnum),
            "kg" => $this->gnumToKg($gnum),
            "min" => 0,     //小包装总数
            "max" => 0,     //大包装总数
            "min_max" => [  //m箱+n袋
                0,
                0
            ],
            "odd" => 0,     //余数克重
            "oddkg" => 0,   //余数Kg
        ];
        //计算小包装总数
        $min = 0;
        if ($isBulk) {
            $min = $this->round($gnum);
        } else {
            $min = $gnum/$nw;
            if ($mint) {
                $min = $real ? floor($min) : ceil($min);
            } else {
                $min = $real ? $this->round($min) : ceil($min);
            }
        }
        $rtn["min"] = $min;
        //计算大包装总数
        $max = 0;
        if ($hasMax) {
            $max = $gnum/($nw*$minn);
            $max = $real ? floor($max) : ceil($max);
        }
        $rtn["max"] = $max;
        //计算 m箱+n袋
        $mm = [0,0];
        if ($hasMax) {
            $mm0 = $min/$minn;
            $mm[0] = floor($mm0);
            $mm[1] = $min - $mm[0]*$minn;
        } else {
            $mm = [0, $min];
        }
        $rtn["min_max"] = $mm;
        //计算余数克重
        $odd = $gnum - $min*$nw;
        $oddkg = 0;
        if (!$real || $odd<=0) {
            $odd = 0;
            $oddkg = 0;
        } else {
            $oddkg = $this->gnumToKg($odd);
            $odd = $this->round($odd);

        }
        $rtn["odd"] = $odd;
        $rtn["oddkg"] = $oddkg;
        //var_dump($rtn);
        return $rtn;
    }

    /**
     * 解析小包装数量
     * 返回相同格式数据
     * @param Float|Int $units 小包装数量
     * @param Bool $real 是否按实际数量计算，默认 true，当需要计算采购数量时，选false，计算将向上凑整，不满1袋的，凑为1袋
     * @return Array
     */
    public function calcUnit($units, $real=true)
    {
        $units = (float)$units;
        $gnum = $units * $this->netwt;
        return $this->calcGnum($gnum, $real);
    }

    /**
     * 输出字符串 m箱+n袋+k克(*Kg)
     * 根据克重
     * @param Float|Int $gnum 克重
     * @param Bool $showOdd 是否显示余数克重，默认 false
     * @param Bool $real 是否按实际数量计算，默认 true，当需要计算采购数量时，选false，计算将向上凑整，不满1袋的，凑为1袋
     * @return String
     */
    public function gnumToStr($gnum, $showOdd=false, $real=true)
    {
        if (!is_numeric($gnum) || $gnum=='' || $gnum<=0) {
            //$u = $this->hasMaxUnit ? $this->maxunit : ($this->isBulk ? $this->minunit : $this->unit);
            $u = $this->isBulk ? $this->minunit : $this->unit;
            return "0".$u;
        }
        $res = $this->calcGnum($gnum, $real);
        $mm = $res["min_max"] ?? [];
        $odd = $res["odd"] ?? 0;
        $oddkg = $res["oddkg"] ?? 0;
        $g = $res["g"] ?? 0;
        $kg = $res["kg"] ?? 0;
        $kgstr = $gnum<0 ? "" : "(".($gnum<1000 ? $g.$this->minunit : $kg."Kg").")";
        $str = [];
        if (!Is::nemarr($mm)) return "";
        if ($mm[0]>0) $str[] = $mm[0].$this->maxunit;
        if ($mm[1]>0) $str[] = $mm[1].$this->unit;
        if ($showOdd && $odd>0) {
            if ($odd<1000) {
                $str[] = $odd.$this->minunit;
            } else {
                $str[] = $oddkg."Kg";
            }
        }
        if (!Is::nemarr($str)) return "";
        return implode("+",$str).$kgstr;
    }

    /**
     * 输出字符串 m箱+n袋+k克(*Kg)
     * 根据小包装数
     * @param Float|Int $units 小包装数量
     * @param Bool $showOdd 是否显示余数克重，默认 false
     * @param Bool $real 是否按实际数量计算，默认 true，当需要计算采购数量时，选false，计算将向上凑整，不满1袋的，凑为1袋
     * @return String
     */
    public function unitToStr($units, $showOdd=false, $real=true)
    {
        $units = (float)$units;
        $gnum = $units * $this->netwt;
        return $this->gnumToStr($gnum, $showOdd, $real);
    }

    /**
     * 数量转换
     */
    /**
     * 将克重转换为 最小包装数
     * @param Float $gnum 克重
     * @return Int|Float 最小包装数 整数|小数，根据 mustInt 属性决定
     */
    public function gnumToUnit($gnum)
    {
        $gnum = (float)$gnum;

        //散装品种 直接返回 克重
        if ($this->isBulk) return $gnum;

        $netwt = $this->netwt;
        $units = $gnum/$netwt;
        if ($this->mustInt) {
            //只输出整数个 最小包装数量，直接丢弃小数部分
            return floor($units);
        } else {
            //如果可以有 小数数量的 小包装数，保留 dig 位小数
            return $this->round($units);
        }
    }
    /**
     * 克重转换为 最大包装数量 只返回整数，舍弃小数部分
     * @param Float $gnum 克重
     * @return Int 最大包装数量
     */
    public function gnumToMaxUnit($gnum)
    {
        $units = $this->gnumToUnit($gnum);
        if ($this->hasMaxUnit!=true) return 0;
        $minn = $this->minnum;
        return floor($units/$minn);
    }
    /**
     * 最小包装数量 转换为 克重
     * @param Float $units 包装数
     * @return Float 克重
     */
    public function unitToGnum($units)
    {
        $units = (float)$units;
        if ($this->isBulk) return $units;
        $netwt = $this->netwt;
        $gnum = $this->round($units * $netwt);
        return $gnum;
    }
    /**
     * 最大包装数 转为 克重
     * @param Float $maxunits 大包装数
     * @return Float 克重
     */
    public function maxUnitToGnum($maxunits)
    {
        $maxunits = (float)$maxunits;
        $gnum = $this->round($maxunits * $this->minnum * $this->netwt);
        return $gnum;
    }
    /**
     * 最大包装数 转为 最小包装数
     * @param Float $maxunits 大包装数
     * @return Float|Int 最小包装数 整数|小数，根据 mustInt 属性决定
     */
    public function maxUnitToUnit($maxunits)
    {
        $maxunits = (float)$maxunits;
        $units = $this->round($maxunits * $this->minnum);
        if ($this->mustInt) return floor($units);
        return $units;
    }
    /**
     * 克重 转换为 Kg
     * @param Float $gnum 克重
     * @return Float Kg数量
     */
    public function gnumToKg($gnum) 
    {
        return $this->round($gnum/1000);
    }

    /**
     * 保留 dig 位小数
     * @param float $n 数值
     * @param Int $dig 保留位数，不指定使用 $this->dig
     * @return Float
     */
    public function round($n, $dig=null)
    {
        if (empty($dig) || !is_numeric($dig)) $dig = $this->dig;
        return round($n, $dig);
    }



    /**
     * 静态工具
     */
    /**
     * 为整个系统提供统一的 可选单位列表
     * Packager::units()
     */
    public static function units()
    {
        return [
            "克", "千克", "斤", "公斤",
            "袋", "瓶", "盒", "桶", "罐", "包", "箱", "板", "提",
            "张", "卷", "条",
            "片", "只", "把",
            "个",
            "无"
        ];
    }

}