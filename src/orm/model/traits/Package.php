<?php
/**
 * cgyio/resper 数据库操作 
 * trait 可复用功能，为各 数据模型(表) 类/实例 增加 实用功能
 * 
 * model/traits/Package 为 数据模型 增加 规格处理与计算 功能
 * 
 */

namespace Cgy\orm\model\traits;

use Cgy\orm\model\util\Packager;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Num;

trait Package 
{
    /**
     * 定义 package 规格计算必须的 字段名称
     * 数据模型可以 通过 json 预设参数为这些字段 指定别名
     */
    public static $pkgColumns = [
        "unit" => "unit",
        "netwt" => "netwt",
        "maxunit" => "maxunit",
        "minnum" => "minnum"
    ];

    /**
     * 定义在 数据记录实例上的 Packager 计算类实例
     * 专门用于操作 针对此条数据记录的 package 规格数据
     */
    public $Packager = null;

    /**
     * 数据模型(表) 自定义参数解析方法
     * 在 数据模型(表) 类 初始化时，通过 model\Config 类调用
     * 自定义参数： package
     * 定义在： json->model->column->package
     * @param Array $ctx model::$config->context 内容
     * @param Array $init 在 json 预设文件中定义的 model 参数数组
     * @return Array 要合并到 model::$config->context 数组中的 处理后的 设置内容
     */
    public static function customConfPackage($ctx, $init)
    {
        if (!Is::nemarr($ctx) || !Is::associate($ctx)) return [];
        if (!Is::nemarr($init) || !Is::associate($init)) return [];
        
        //package 参数预设
        $pkg = $init["column"]["package"] ?? [];
        $conf = [
            "package" => [
                //规格计算必须的 字段名
                "columns" => [/*
                    "unit" => "tbn_unit",
                    "netwt" => "tbn_netwt",
                    ...
                */],

                //可用于计算数量的 包含数量信息的 字段
                "num" => [/*
                    "innum", "qty"
                */],

                //可用于计算价格的 包含价格信息的 字段
                "price" => [/*
                    "price"
                */],

                //以最小规格计算数量时，是否必须是整数数量，默认是，即 不会出现 0.5袋 的结果
                "intunit" => $pkg["intunit"] ?? true,

                //规格计算保留的小数位数，默认 2
                "dig" => $pkg["dig"] ?? 2,
            ],

            //可能修改 getters 参数
            "getters" => [],

            //可能修改 column 参数
            "column" => []
        ];
        
        //package 字段包括这些
        $ks = array_keys(self::$pkgColumns);
        $cols = $pkg["columns"] ?? [];
        $nums = $pkg["num"] ?? [];
        $prcs = $pkg["price"] ?? [];
        $rcols = [];
        //解析 规格字段 别名
        if (Is::nemstr($cols)) {
            //指定了包含 package 信息的 字段，此字段的 join 关联表中包含字段数据
            $jtb = Arr::find($ctx, "join/column/$cols");
            if (Is::nemarr($jtb)) {
                //找到关联表
                $jtbn = array_keys($jtb)[0];
                $mdi = static::$db->model($jtbn);
                if (Is::nemstr($mdi)) {
                    //关联表初始化，获取关联表的 package 参数
                    $jtbc = $mdi::$config->context;
                    $jtbCols = Arr::find($jtbc, "package/columns");
                    foreach ($jtbCols as $k => $v) {
                        //为 关联表的 package 相关字段名 增加 tbn_ 前缀
                        //这样就可以在在当前表中，通过 $rs->tbn_coln 访问 关联表字段值
                        $rcols[$k] = $jtbn."_".$v;
                    }
                }
            }
        } else if (Is::nemarr($cols)) {
            if (Is::indexed($cols)) {
                //按顺序指定 package 字段名
                for ($i=0;$i<count($cols);$i++) {
                    $rcols[$ks[$i]] = $cols[$i];
                }
            } else if (Is::associate($cols)) {
                //手动指定 各 package 字段名
                $rcols = $cols;
                for ($i=0;$i<count($ks);$i++) {
                    $ki = $ks[$i];
                    if (!isset($rcols[$ki])) {
                        $rcols[$ki] = $ki;
                    }
                }
            }
        } 
        if (!empty($rcols)) {
            //得到了 package 计算必须字段
            $conf["package"]["columns"] = $rcols;

            //为 num / price 字段 增加 Getter 方法，用于输出更直观的 数量/价格
            $nps = array_merge($nums, $prcs);
            if (!empty($nums)) $conf["package"]["num"] = $nums;
            if (!empty($prcs)) $conf["package"]["price"] = $prcs;
            foreach ($nps as $coln) {
                if (!in_array($coln, $ctx["columns"])) continue;
                $coli = $ctx["column"][$coln];
                $isnum = in_array($coln, $nums);
                $mtype = $isnum ? "Num" : "Price";
                $fn = $coln."Pkg";
                $fc = [
                    "name" => $fn,
                    "title" => $coli["title"]."(件)",
                    "desc" => $coli["desc"]."，按规格显示",
                    "width" => $coli["width"],
                    "type" => [
                        "db" => "varchar",
                        "js" => $isnum ? "string" : "object",
                        "php" => $isnum ? "String" : "Array"
                    ],
                    "isGetter" => true,
                    "origin" => $coln,
                    "method" => "pkg".$mtype."AutoGetters",
                ];
                $conf["getters"][] = $fn;
                $conf["column"][$fn] = $fc;

                if ($isnum && count($prcs)>0) {
                    //如果此记录包含价格字段，则针对所有数量字段，增加一个 自动计算总价的 计算字段
                    $cfn = $coln."Cost";
                    $cfc = [
                        "name" => $cfn,
                        "title" => $coli["title"]."(总价)",
                        "desc" => $coli["desc"]."，自动计算总价",
                        "width" => $coli["width"],
                        "type" => [
                            "db" => "varchar",
                            "js" => "object",
                            "php" => "Array"
                        ],
                        "isGetter" => true,
                        "origin" => $coln,
                        "method" => "pkgCostAutoGetters",
                        //保存 价格字段名，price字段数组中的第一个
                        "price" => $prcs[0],
                    ];
                    $conf["getters"][] = $cfn;
                    $conf["column"][$cfn] = $cfc;
                }
            }

            //返回
            return $conf;
        }
        
        return [];
    }

    /**
     * initIns*** 方法
     * 数据记录实例 构造时 执行
     * 针对 package 数据 创建一个 Packager 计算类实例
     * @return $this
     */
    protected function initInsPackage()
    {
        $this->Packager = new Packager($this);
        return $this;
    }


    /**
     * 增加 getters 计算字段
     */

    /**
     * getter
     * @name pkg
     * @title 规格
     * @desc SKU 品种规格
     * @width 5
     * @type varchar
     * @jstype string
     * @phptype String
     */
    protected function pkgGetter()
    {
        return $this->Packager->pkgString;
    }

    /**
     * ****AutoGetters
     */

    /**
     * pkgNumAutoGetters 数量 按规格显示 1箱+2袋(15Kg)
     * @param Object $gc auto getter 参数
     * @return String
     */
    protected function pkgNumAutoGetters($gc)
    {
        $origin = $gc->origin;
        //读取原字段值 时间戳
        $data = $this->$origin;
        //读取原字段 参数
        $colc = $this->conf->$origin;

        //输出字符串，所有品种的数量都是以最小包装形式指定的，因此，使用 unitToStr() 方法
        return $this->Packager->unitToStr($data, false, true);
    }
    /**
     * 按规格计算显示价格 
     * 返回数据：
     *  [
     *      "克" => 0,
     *      "斤" => 0,
     *      "kg" => 0,
     *      "str" => ￥23.1000/袋，￥346.5000/箱
     *  ]
     */
    protected function pkgPriceAutoGetters($gc)
    {
        $origin = $gc->origin;
        //读取价格数值
        $data = $this->$origin;
        //读取原字段 参数
        $colc = $this->conf->$origin;
        //确认 isMoney
        if ($colc->isMoney!=true) return $data;
        //字段参数
        $mc = $colc->money;
        $prec = $mc["precision"] ?? 4;  //默认保留 4 位小数
        $icon = $mc["icon"] ?? "￥";
        //根据品种单位，决定输出内容
        $pkger = $this->Packager;
        $isBulk = $pkger->isBulk;   
        $hasMax = $pkger->hasMaxUnit;
        $isG = $pkger->unit == $pkger->minunit;
        $pcs = [];
        $str = [];
        $pcs[$pkger->unit] = round($data, $prec);
        if ($isBulk && $isG) {
            //散装品种，按 克计算
            $pcs["斤"] = round($data*500, $prec);
            $str[] = $icon.Num::roundPad($data*500, $prec)."/斤";
            $pcs["kg"] = round($data*1000, $prec);
            $str[] = $icon.Num::roundPad($data*1000, $prec)."/Kg";
        } else {
            $pcs[$pkger->unit] = round($data, $prec);
            $str[] = $icon.Num::roundPad($data, $prec)."/".$pkger->unit;
        }
        if ($hasMax) {
            if ($isBulk && $isG) {
                //散装品种，按 克计算
                $nw = $pkger->minnum * $pkger->netwt;
            } else {
                $nw = $pkger->minnum;
            }
            $pcs[$pkger->maxunit] = round($data*$nw, $prec);
            $str[] = $icon.Num::roundPad($data*$nw, $prec)."/".$pkger->maxunit;
        }

        if (empty($str)) return $pcs;
        $pcs["str"] = implode("，", $str);
        return $pcs;
    }
    /**
     * 增对所有标记为数量的字段，增加总价计算字段
     * 返回数据：
     *  [
     *      "cost" => 0,
     *      "str" => ￥23.1000
     *  ]
     */
    protected function pkgCostAutoGetters($gc)
    {
        $origin = $gc->origin;
        //读取原字段值 数量
        $data = $this->$origin;
        //读取原字段 参数
        $colc = $this->conf->$origin;
        //价格字段
        $price = $gc->price ?? null;
        if (!Is::nemstr($price)) {
            //如果不包含价格字段，直接返回
            return 0;
        }
        //当前记录的价格数值
        $pdata = $this->$price;
        if (!is_numeric($pdata)) {
            //如果当前记录不包含 价格数据，直接返回
            return 0;
        }
        //计算总价
        $cost = $data*$pdata;
        //价格字段设置
        $pc = $this->conf->$price;
        $mc = $colc->money;
        $prec = $mc["precision"] ?? 4;  //默认保留 4 位小数
        $icon = $mc["icon"] ?? "￥";
        
        //返回总价数据
        return [
            "cost" => round($cost, $prec),
            "str" => $icon.Num::roundPad($cost, $prec)
        ];
    }



}