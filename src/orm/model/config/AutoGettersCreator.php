<?php
/**
 * resper 框架 模型参数解析工具类
 * 
 * 为一些特殊类型的字段，自动添加 Getter 计算字段
 * 在原字段值基础上，进行一些自动计算，生成新字段值
 * !! 后期要添加 新的特殊类型，除了此处需要增加 添加方法 外，
 * !! 还需要在 orm/model/traits/SpecialColAutoGetter 中增加相应的 ***AutoGetters 方法
 * 
 * 添加方法名称统一为：createFoobarAutoGetters
 * 参数格式统一为：
 * @param String $col 字段名
 * @param Array $colc 字段参数，已经过解析的 含有字段类型信息的 字段参数
 * @param Array $conf 保存参数数据，并最终要写入 context 的参数数据 [ "column"=>[...], "getters"=>[...] ]
 * @return Array 修改后的 $conf 
 */

namespace Cgy\orm\model\config;

use Cgy\util\Is;

class AutoGettersCreator 
{
    /**
     * 新增字段     ***_str    自动格式化原字段数据
     * 字段类型     isTime / isMoney
     */
    public static function createFormatAutoGetters($col, $colc, $conf)
    {
        //先判断字段类型
        if ($colc["isTime"]!==true && $colc["isMoney"]!==true) return $conf;
        //新增字段名
        $fn = $col."_str";
        //新增字段参数
        $fc = [
            "name" => $fn,
            "title" => $colc["title"],
            "desc" => $colc["desc"]." (文字形式)",
            "width" => $colc["width"],
            "type" => [
                "db" => "varchar",
                "js" => "string",
                "php" => "String"
            ],
            "isGetter" => true,
            "origin" => $col,
            //此类型计算字段，要调用的计算方法，在 orm/model/traits/SpecialColAutoGetters 中定义
            "method" => $colc["isTime"]==true ? "timeStrAutoGetters" : "moneyStrAutoGetters"
        ];
        $conf["getters"][] = $fn;
        $conf["column"][$fn] = $fc;
        return $conf;
    }

    /**
     * 新增字段     ***_src         自动获取字段关联表记录集 ModelSet 实例
     *             ***_src_ctx     自动获取字段关联表记录集数据  
     *             ***_values      自动获取字段关联表中的所有可选数据 [ [ label=>..., value=>... ], ... ]
     * 字段类型     isSelect && select[multiple] && select[source][table] && isJson && json[type]==indexed
     */
    public static function createSourceAutoGetters($col, $colc, $conf)
    {
        //先判断字段符合条件
        if ($colc["isSelect"]!==true || $colc["isJson"]!==true) return $conf;
        //字段必须是 多选 且 关联到某个数据模型
        $multi = $colc["select"]["multiple"] ?? false;
        $srctb = $colc["select"]["source"]["table"] ?? null;
        //字段必须是 indexed 数组格式
        $jtype = $colc["json"]["type"] ?? "";
        if ($multi!==true || !Is::nemstr($srctb) || $jtype!=="indexed") return $conf;

        //新增第一个字段，获取关联表所有记录数据
        //新增字段名
        $fn = $col."_src_ctx";
        //新增字段参数
        $fc = [
            "name" => $fn,
            "title" => $colc["title"],
            "desc" => $colc["desc"]." (明细)",
            "width" => $colc["width"],
            "type" => [
                "db" => "varchar",
                "js" => "array",
                "php" => "Array"
            ],
            "isGetter" => true,
            "origin" => $col,
            "method" => "srcCtxAutoGetters",
            //关联表
            "source" => $srctb,
        ];
        $conf["getters"][] = $fn;
        $conf["column"][$fn] = $fc;

        //新增第二个字段，获取关联表所有记录的 ModelSet 实例
        //新增字段名
        $fn = $col."_src";
        //新增字段参数
        $fc = [
            "name" => $fn,
            "title" => $colc["title"],
            "desc" => $colc["desc"]." (实例)",
            "width" => $colc["width"],
            "type" => [
                "db" => "varchar",
                "js" => "array",
                "php" => "Array"
            ],
            "isGetter" => true,
            "origin" => $col,
            "method" => "srcAutoGetters",
            //关联表
            "source" => $srctb,
        ];
        $conf["getters"][] = $fn;
        $conf["column"][$fn] = $fc;

        //新增第三个字段，获取字段关联表的 所有可选数据 []
        //新增字段名
        $fn = $col."_values";
        //新增字段参数
        $fc = [
            "name" => $fn,
            "title" => $colc["title"],
            "desc" => $colc["desc"]." (可选值)",
            "width" => $colc["width"],
            "type" => [
                "db" => "varchar",
                "js" => "array",
                "php" => "Array"
            ],
            "isGetter" => true,
            "origin" => $col,
            "method" => "srcValuesAutoGetters",
            //关联表
            "source" => $srctb,
        ];
        $conf["getters"][] = $fn;
        $conf["column"][$fn] = $fc;

        return $conf;
    }

    /**
     * 新增字段     ***_label       数据源来自 api 的，单选/多选 字段，自动获取 字段值 对应的 label
     *             ***_values      自动获取 api 返回的全部数据 [ [ label=>..., value=>... ], ... ]
     * 字段类型     isSelect && select[source][api]
     */
    public static function createLabelAutoGetters($col, $colc, $conf)
    {
        //先判断字段符合条件
        if ($colc["isSelect"]!==true) return $conf;
        //字段必须是 关联到某个 api
        $selc = $colc["select"] ?? [];
        $apic = $selc["source"]["api"] ?? null;
        if (!Is::nemstr($apic)) return $conf;
        $isjson = $colc["isJson"]===true;
        $jtype = $colc["json"]["type"] ?? null;
        $jstp = $isjson ? ($jtype=="indexed" ? "array" : "object") : "string";
        $phptp = $isjson ? "Array" : "String";

        //新增第一个字段，获取字段值对应的 label，数据来自 api 返回数据
        //新增字段名
        $fn = $col."_label";
        //新增字段参数
        $fc = [
            "name" => $fn,
            "title" => $colc["title"],
            "desc" => $colc["desc"]." (标签)",
            "width" => $colc["width"],
            "type" => [
                "db" => "varchar",
                "js" => $jstp,
                "php" => $phptp
            ],
            "isGetter" => true,
            "origin" => $col,
            "method" => "apiLabelAutoGetters",
            //关联的 api 
            "source" => $apic,
        ];
        $conf["getters"][] = $fn;
        $conf["column"][$fn] = $fc;

        //新增第二个字段，获取字段关联 api 的所有返回数据 []
        //新增字段名
        $fn = $col."_values";
        //新增字段参数
        $fc = [
            "name" => $fn,
            "title" => $colc["title"],
            "desc" => $colc["desc"]." (可选值)",
            "width" => $colc["width"],
            "type" => [
                "db" => "varchar",
                "js" => "array",
                "php" => "Array"
            ],
            "isGetter" => true,
            "origin" => $col,
            "method" => "apiValuesAutoGetters",
            //关联的 api 
            "source" => $apic,
        ];
        $conf["getters"][] = $fn;
        $conf["column"][$fn] = $fc;

        return $conf;
    }
    
}