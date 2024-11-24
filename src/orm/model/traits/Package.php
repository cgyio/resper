<?php
/**
 * cgyio/resper 数据库操作 
 * trait 可复用功能，为各 数据模型(表) 类/实例 增加 实用功能
 * 
 * model/traits/Package 为 数据模型 增加 规格处理与计算 功能
 * 
 */

namespace Cgy\orm\model\traits;

use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;

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
                */]
            ],

            //可能修改 getters 参数
            "getters" => [],

            //可能修改 column 参数
            "column" => []
        ];
        
        //package 字段包括这些
        $ks = array_keys(self::$pkgColumns);

        //package 参数预设
        $pkg = $init["column"]["package"] ?? [];
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
                $fn = $coln."Pkg";
                $fc = [
                    "name" => $fn,
                    "title" => $coli["title"]."(件)",
                    "desc" => $coli["desc"]."，按规格显示",
                    "width" => $coli["width"],
                    "type" => [
                        "db" => "varchar",
                        "js" => "string",
                        "php" => "String"
                    ],
                    "isGetter" => true,
                    "origin" => $coln,
                    "method" => $isnum ? "pkgNumAutoGetters" : "pkgPriceAutoGetters"
                ];
                $conf["getters"][] = $fn;
                $conf["column"][$fn] = $fc;
            }

            //返回
            return $conf;
        }
        
        return [];
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
        $conf = $this->conf->package["columns"];
        if (empty($conf)) return null;
        $ks = array_keys($this::$pkgColumns);
        $pkg = [];
        foreach ($ks as $ki) {
            $coli = $conf[$ki];
            $pkg[$ki] = $this->$coli;
        }
        return $pkg["netwt"]."克/".$pkg["unit"]."，".$pkg["minnum"].$pkg["unit"]."/".$pkg["maxunit"];
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

        return $data."-pkg-num";
    }

    protected function pkgPriceAutoGetters($gc)
    {
        $origin = $gc->origin;
        //读取原字段值 时间戳
        $data = $this->$origin;
        //读取原字段 参数
        $colc = $this->conf->$origin;

        return $data."-pkg-price";
    }
}