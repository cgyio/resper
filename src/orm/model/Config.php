<?php
/**
 * cgyio/resper Orm 数据库操作类
 * model/Config 数据表(模型) 类 参数设置工具类
 * 
 * 数据表(模型) 类 预设参数应保存在：
 *      [dbpath]/config/[dbname].json 文件中 [ "model" => [ "modelname" => [ ..预设.. ] ] ]
 * 解析后的 数据表(模型) 参数文件保存为：
 *      [dbpath]/runtime/[dbname]/[modelname].json
 * 预设文件可通过 build 生成：
 *      $model::$config->build();
 *      在每次修改预设参数后，都应执行 build 方法
 */

namespace Cgy\orm\model;

use Cgy\Orm;
use Cgy\orm\Db;
use Cgy\orm\Model;
use Cgy\orm\model\config\AutoGettersCreator;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Conv;
use Cgy\util\Cls;

class Config
{
    /**
     * 缓存 config 实例
     * key = "CFG_".md5($model::$cls 类全称)
     */
    public static $CACHE = [];

    /**
     * 依赖：
     * 数据表(模型) 类
     */
    public $model = null;

    //在 dbn.json 中定义的参数
    public $init = [];

    //解析得到的 数据表(模型) 类 参数
    public $context = [
        //表预设参数结构， 文件中查看
    ];

    /**
     * 特殊字段设置项
     *  [
     *      设置项 => 处理方法 foobar  -->  parseSpecColFoobar()
     *  ]
     */
    public $columnConfKeys = [
        //hidein
        "hideintable"   => "hidein",
        "hideinform"    => "hidein",

        //able
        "sort"      => "able",
        "filter"    => "able",
        "search"    => "able",
        "sum"       => "able",

        //indexed
        "includes"  => "indexed",
        "switch"    => "indexed",
        "unique"    => "indexed",   //字段唯一属性
        
        //associate
        "time"      => "associate",
        "number"    => "associate",
        "json"      => "associate",
        "money"     => "associate",
        "validator" => "associate",
        "generator" => "associate",

        //单独的处理方法
        "select"    => "select",
    ];

    /**
     * build 方法参数
     */
    //解析 model::预设 序列，按此顺序分别解析
    /*public $buildQueue = [
        "meta",     //解析 $model::$creation/$meta 参数，得到基本的 field 信息
        "special",  //解析 $model::$special 参数

        "final",    //最后再次解析
    ];*/


    /**
     * 静态检查 model 是否已有 config 实例，如果有则返回，没有则返回 false
     * @param Model $model 类全称
     * @return Config instance  or  false
     */
    public static function hasConfig($model)
    {
        if (!class_exists($model)) return false;
        $key = "CFG_".md5($model);
        if (!isset(self::$CACHE[$key])) return false;
        $cfger = self::$CACHE[$key];
        if (!$cfger instanceof Config) return false;
        return $cfger;
    }

    /**
     * 构造
     * @param Model $model 类全称
     * @param Array $conf 在 dbn.json 中定义的预设参数
     * //@param String $mdn 在 dbn.json 中 此 model 的 name
     * @return void
     */
    public function __construct($model, $conf = [])
    {
        if (!class_exists($model)) return null;
        $this->model = $model;
        //保存初始参数
        $this->init = $conf;
        
        /**
         * 生成 context
         * 读取 [dbpath]/runtime/[dbname]/[modelname].json
         * 或 根据 init 解析生成
         */
        $this->getContext();
    }

    /**
     * 生成 context
     * 如果有 modelname.json 则读取
     * 如果没有 json 文件，则调用 build 方法，根据 model 预设参数，生成 json
     * @return Config $this
     */
    public function getContext()
    {
        $conf = $this->getRuntimeConfig();
        if (Is::nemarr($conf)) {
            $this->context = $conf;
        } else {
            //解析 dbn.json 中 此 model 的预设参数
            // 1  解析 columns 字段参数
            $this->parseColumns();
            // 2  获取默认值
            $this->parseDefault();
            // 3  解析关联表参数
            $this->parseJoin();
            // 4  解析计算字段 虚拟字段
            $this->parseGetters();
            // 5  解析 API
            $this->parseApi();
            // 6  解析其他参数
            $this->parseModelMeta();
            // 7  调用 各 数据模型 自定义的 (可能通过 trait 引入的) 参数解析方法
            $this->parseCustomConf();

            // 最后  创建运行时 config 文件，加快运行速度
            //TODO: 保存到 [dbpath]/runtime/[dbname]/[modelname].json
            //...
            
        }

        return $this;
    }

    /**
     * 参数写入 context
     * @param Array $conf 
     * @return Config $this
     */
    protected function setContext($conf=[])
    {
        if (!Is::nemarr($conf)) return $this;
        $this->context = Arr::extend($this->context, $conf);
        return $this;
    }

    /**
     * 外部获取 context 内容
     * @param String $key 按项目 key 获取 context 内容
     * @return Mixed
     */
    public function ctx($key=null)
    {
        if (!Is::nemstr($key)) return $this->context;
        return Arr::find($this->context, $key);
    }

    /**
     * __get
     * @param String $key 
     * @return Mixed
     */
    public function __get($key)
    {
        /**
         * $config->ctx  -->  $config->context
         */
        if ($key=="ctx") return $this->context;
        
        /**
         * $config->foo  -->  $config->context["foo"]
         */
        if (isset($this->context[$key])) return $this->context[$key];

        /**
         * $config->columnName  -->  $config->context["column"]["foo"]
         */
        $fdc = $this->context["column"];
        if (isset($fdc[$key]) || substr($key, -6)==="Column") {
            $fdn = $key;
            if (substr($key, -6)==="Column") $fdn = substr($key, 0, -6);
            if (isset($fdc[$fdn])) return (object)$fdc[$fdn];
        }

        /**
         * $config->searchColumns          -->  [ context["column"][*]["searchable"]==true, ... ]
         * $config->jsonColumns            -->  [ context["column"][*]["isJson"]==true ]
         */
        if (strlen($key)>7 && substr($key, -7)=="Columns") {
            $k = strtolower(substr($key, 0,-7));
            $k1 = "is".ucfirst($k);
            $k2 = $k."able";
            $idf = $this->model::idf();
            $kk = isset($fdc[$idf][$k1]) ? $k1 : (isset($fdc[$idf][$k2]) ? $k2 : null);
            if (Is::nemstr($kk)) {
                $fds = array_filter($this->context["columns"], function($fi) use ($kk, $fdc) {
                    return $fdc[$fi][$kk]===true;
                });
                return array_merge($fds);
            }
        }
        

        return null;
    }



    /**
     * parse 解析
     */

    /**
     * 解析 columns 参数
     * @param Array $init json 中定义的参数
     * @return Config $this
     */
    protected function parseColumns($init = [])
    {
        $init = !Is::nemarr($init) ? $this->init : $init;
        $cols = $init["columns"] ?? [];
        $crts = $init["creation"] ?? [];
        if (empty($cols) || empty($crts)) {
            //缺少参数，报错
            trigger_error("orm/fatal::数据表无法初始化，缺少必要参数", E_USER_ERROR);
        }

        //准备要写入 context 的 内容
        $conf = [
            "columns" => array_keys($cols),
            "creation" => $crts,
            "column" => [],
            "special" => []
        ];
        $colsc = [];
        $specs = [];

        //分别解析 column
        foreach ($cols as $coln => $colc) {
            if (!Is::nemarr($colc) || !Is::indexed($colc)) continue;
            $crt = $crts[$coln] ?? "varchar";
            $coli = [
                "creation" => $crt,
                "name" => $coln,
                "title" => $colc[0],
                "desc" => $colc[1] ?? "",
                "width" => $colc[2] ?? 3,
                "isGetter" => false
            ];

            //解析字段类型 等基本参数
            $colmeta = $this->parseColumnMeta($coli, $init);
            $coli = Arr::extend($coli, $colmeta);

            //解析特殊字段参数
            $colspec = $this->parseColumnSpecial($coli, $init);
            $coli = Arr::extend($coli, $colspec);

            //对已解析出的字段参数，再次处理
            $colrevi = $this->parseColumnReview($coli, $init);
            $coli = Arr::extend($coli, $colrevi);

            //字段参数 写入 $conf
            $colsc[$coln] = $coli; 

            //记录特殊字段
            $specs = Arr::extend($specs, $this->markSpecialColumn($coli, $init));
        }

        //数据写入 context
        $conf["column"] = $colsc;
        $conf["special"] = $specs;
        
        return $this->setContext($conf);
    }

    /**
     * 解析字段类型 等 基本参数
     * @param Array $coli 已有的 字段参数
     * @param Array $init 完整的 json 预设参数
     * @return Array 解析结果
     */
    protected function parseColumnMeta($coli = [], $init = [])
    {
        $conf = [
            "type" => [
                "db" => "varchar",
                "js" => "string",
                "php" => "String"
            ],
            "isPk" => false,
            "isId" => false,
            "isRequired" => false,
            //"isNumber" => false,
            //"isSwitch" => false,
            "isJson" => false,
            "default" => null
        ];
        $ci = $coli["creation"];
        if (strpos($ci, "PRIMARY KEY")!==false) {
            $conf["isPk"] = true;
            $ci = str_replace("PRIMARY KEY","", $ci);
        }
        if (strpos($ci, "AUTOINCREMENT")!==false) {
            $conf["isId"] = true;
            $ci = str_replace("AUTOINCREMENT","", $ci);
        }
        if (strpos($ci, "NOT NULL")!==false) {
            $conf["isRequired"] = true;
            $ci = str_replace("NOT NULL", "", $ci);
        }
        if (strpos($ci, "DEFAULT ")!==false) {
            $cia = explode("DEFAULT ", $ci);
            $dv = $cia[1] ?? null;
            if (Is::nemstr($dv)) {
                if (substr($dv, 0,1)=="'" && substr($dv, -1)=="'") {
                    $dv = str_replace("'","",$dv);
                } else {
                    $dv = $dv*1;
                }
            }
            $conf["default"] = $dv;
            $ci = $cia[0];
        }
        $tps = explode(",", "integer,varchar,float,text,blob,numeric");
        for ($j=0;$j<count($tps);$j++) {
            $tpi = $tps[$j];
            if (strpos($ci, $tpi)===false && strpos($ci, strtoupper($tpi))===false) continue;
            $conf["type"]["db"] = $tpi;
            switch ($tpi) {
                case "integer":
                case "float":
                    //$conf["isNumber"] = true;
                    $conf["type"]["js"] = $tpi;
                    $conf["type"]["php"] = $tpi=="integer" ? "Int" : "Number";
                    break;
                case "numeric":
                    //$conf["isNumber"] = true;
                    $conf["type"]["js"] = "float";
                    $conf["type"]["php"] = "Number";
                    break;
                case "varchar":
                case "text":
                    $conf["type"]["js"] = "string";
                    $conf["type"]["php"] = "String";
                    if (!is_null($conf["default"])) {
                        $dft = $conf["default"];
                        if (
                            (substr($dft, 0, 1)=="{" && substr($dft, -1)=="}") ||
                            (substr($dft, 0, 1)=="[" && substr($dft, -1)=="]")
                         ) {
                            $jdft = Conv::j2a($dft);
                            $conf["default"] = $jdft;
                            $conf["isJson"] = true;
                            $jtype = substr($dft, 0, 1)=="{" ? "object" : "array";
                            $conf["json"] = [
                                "type" => $jtype,
                                "default" => $jdft
                            ];
                            $conf["type"]["js"] = $jtype;
                            $conf["type"]["php"] = "JSON";
                        }
                    }
                    break;
                default:
                    $conf["type"]["js"] = $tpi;
                    $conf["type"]["php"] = $tpi;
                    break;

            }
        }

        return $conf;
    }

    /**
     * 解析特殊字段参数
     * @param Array $coli 已有的 字段参数
     * @param Array $init 完整的 json 预设参数
     * @return Array 解析结果
     */
    protected function parseColumnSpecial($coli = [], $init = [])
    {
        $conf = [];
        $coln = $coli["name"];

        //json 中特殊字段预设参数保存在 $init["column"] 
        $ocf = $init["column"] ?? [];
        if (!Is::nemarr($ocf)) return $conf;

        //解析 $this->columnConfKeys 指定的 设置项目
        $keys = $this->columnConfKeys;
        foreach ($keys as $key => $m) {
            $cf = isset($ocf[$key]) ? $ocf[$key] : null;
            $m = "parseSpecCol".ucfirst($m);
            $args = [$coln, $key, $cf, $coli, $init];
            $confi = [];
            if (method_exists($this, $m)) {
                $confi = $this->$m(...$args);
            } else {
                //未指定处理方法，默认处理
                if (empty($cf)) {
                    $k = "is".ucfirst(strtolower($key));
                    $confi = [ $k => false ];
                } else if (Is::associate($cf)) {
                    $confi = $this->parseSpecColAssociate(...$args);
                } else if (Is::indexed($cf)) {
                    $confi = $this->parseSpecColIndexed(...$args);
                } else {
                    $confi = [ $key => $cf ];
                }
            }
            $conf = Arr::extend($conf, $confi);
        }

        return $conf;
    }

    /**
     * 对已解析出的字段参数进行 再次检查
     * @param Array $coli 已有的 字段参数
     * @param Array $init 完整的 json 预设参数
     * @return Array 解析结果
     */
    protected function parseColumnReview($coli = [], $init = [])
    {
        $conf = [];
        $oconf = $coli;
        $fdn = $coli["name"];
        /*if (!isset($oconf["isSwitch"])) {
            var_dump($fdn);
            var_dump($this->model);
            var_dump($oconf);
        }*/
        if ($fdn=="enable") $conf["isSwitch"] = true;
        if ($oconf["isSwitch"]==true || (isset($conf["isSwitch"]) && $conf["isSwitch"]==true)) {
            $conf["type"] = [
                "js" => "boolean",
                "php" => "Bool"
            ];
        }
        if ($oconf["isJson"]==true) {
            if (isset($oconf["json"]["default"])) {
                $conf["default"] = $oconf["json"]["default"];
            }
        }
        if ($oconf["isSelect"]==true) {
            if (isset($oconf["select"]) && $oconf["select"]["multiple"]==true) {
                $conf["type"] = [
                    "js" => "array",
                    "php" => "JSON"
                ];
                if ($oconf["isJson"]!=true) {
                    $conf["isJson"] = true;
                    $conf["json"] = [
                        "type" => "indexed"
                    ];
                }
            }
        }
        /*if ($oconf["isTime"]==true && isset($oconf["time"]["default"])) {
            //指定了 默认值的 time 类型字段
            $ttp = $oconf["time"]["type"];
            $dft = $oconf["time"]["default"];
            $dv = null;
            //根据指定的 time type 和 default 值 计算 实际 default 值
            switch ($dft) {
                case "now" :

                    break;
            }
            $conf["default"] = $oconf["json"]["default"];
        }*/

        return $conf;
    }

    /**
     * 记录特殊字段
     * @param Array $coli 已有的 字段参数
     * @param Array $init 完整的 json 预设参数
     * @return Array 解析结果
     */
    protected function markSpecialColumn($coli, $init)
    {
        $func = function ($fdn, $key, $out) {
            if (!isset($out[$key])) $out[$key] = [];
            if (!in_array($fdn, $out[$key])) $out[$key][] = $fdn;
            return $out;
        };

        $cf = [];
        $fdn = $coli["name"];
        $oconf = $coli;

        //记录特殊字段
        if ($oconf["isPk"]==true) $cf = $func($fdn, "pk", $cf);
        if ($oconf["isId"]==true) $cf = $func($fdn, "id", $cf);
        if ($oconf["isUnique"]==true) $cf = $func($fdn, "unique", $cf);
        if ($oconf["type"]["php"]=="JSON") $cf = $func($fdn, "json", $cf);
        if ($oconf["isIncludes"]==true) $cf = $func($fdn, "includes", $cf);
        if ($oconf["isTime"]==true) $cf = $func($fdn, "time", $cf);
        if ($oconf["isMoney"]==true) $cf = $func($fdn, "money", $cf);
        if ($oconf["isSelect"]==true) $cf = $func($fdn, "select", $cf);
        if ($oconf["isGenerator"]==true) $cf = $func($fdn, "gid", $cf);

        return $cf;
    }

    /**
     * 获取 默认值 数组
     * !! 必须在 columns 字段参数解析完成后 执行
     * @param Array $init json 中定义的参数
     * @return Config $this
     */
    protected function parseDefault($init = [])
    {
        $init = !Is::nemarr($init) ? $this->init : $init;
        $colsc = $this->context["column"];
        $dft = [];
        foreach ($colsc as $coln => $colc) {
            if (!isset($colc["default"]) || is_null($colc["default"])) continue;
            $dft[$coln] = $colc["default"];
        }
        return $this->setContext([
            "default" => $dft
        ]);
    }

    /**
     * 解析 json 中 join 参数，得到关联表参数信息
     * @param Array $init json 中定义的参数
     * @return Config $this
     */
    protected function parseJoin($init = [])
    {
        $init = !Is::nemarr($init) ? $this->init : $init;
        $join = $init["join"] ?? [];
        $use = $join["use"] ?? false;
        if (isset($join["use"])) unset($join["use"]);
        $conf = [
            "param" => $join,
            "availabel" => !empty($join),   //join 参数是否可用
            "use" => $use,                  //是否每次查询都默认启用 join 关联表查询
            "tables" => [],                 //关联表 表名 数组，全小写
            "column" => [                   //有关联表的 字段参数
                /*
                "column name" => [
                    "table name" => [
                        "linkto" => "关联表中字段名"
                        "relate" => ">|<|<>|>< == left|right|full|inner join"
                    ],
                ]
                */
            ],
        ];
        if (empty($join)) return $this->setContext(["join"=>$conf]);
        //获取 关联表 表明列表
        $tbs = [];
        $jfd = [];
        foreach ($join as $k => $v) {
            //$k like '[>]table (alias)'
            $ka = explode("]", $k);
            $rl = str_replace("[","",$ka[0]);   //join 类型
            $ka = explode("(", $ka[1]);
            $tbn = trim($ka[0]);
            //$v like 'fdn' or [fdn1,fdn2] or [fdn1=>fdn2, ... ]
            if (is_string($v)) {
                $fdn = [$v];
                $lfdn = [$v];
            } else if (Is::indexed($v)) {
                $fdn = $v;
                $lfdn = $v;
            } else if (Is::associate($v)) {
                $fdn = array_keys($v);
                $fdn = array_filter($fdn, function($i) {
                    //join 参数中 [ "table.field" => "..." ] 是其他 表的关联参数，此处不做处理
                    return strpos($i, ".")===false;
                });
                $fdn = array_merge($fdn);
                $lfdn = array_map(function ($i) use ($v) {
                    return $v[$i];
                }, $fdn);
            } else {
                $fdn = [];
                $lfdn = [];
            }
            //写入 关联表 名称数组
            if (!in_array($tbn, $tbs)) $tbs[] = $tbn;
            //写入 有关联表的 字段数组
            if (empty($fdn)) continue;
            for ($i=0;$i<count($fdn);$i++) {
                $fdi = $fdn[$i];
                $lfdi = $lfdn[$i];
                if (!isset($jfd[$fdi])) $jfd[$fdi] = [];
                if (!isset($jfd[$fdi][$tbn])) $jfd[$fdi][$tbn] = [];
                $jfd[$fdi][$tbn] = [
                    "linkto" => $lfdi,
                    "relate" => $rl
                ];
            }
        }
        $conf["tables"] = $tbs;
        $conf["column"] = $jfd;
        //写入 column 字段参数 isJoin, join
        $fdc = [];
        $fds = $this->context["columns"];
        foreach ($fds as $fi => $fdi) {
            if (empty($jfd) || !isset($jfd[$fdi])) {
                $fdc[$fdi] = [
                    "join" => [],
                    "isJoin" => false
                ];
            } else {
                $fdc[$fdi] = [
                    "join" => $jfd[$fdi],
                    "isJoin" => true
                ];

                //当字段存在 join 关联表时，修改 字段的 select 参数
                $srctbn = array_keys($jfd[$fdi])[0];
                $colcf = $this->context["column"][$fdi];
                $colsel = $colcf["isSelect"] ?? false;
                $ncf = [
                    "source" => [
                        "table" => $srctbn,
                        "value" => $jfd[$fdi][$srctbn]["linkto"]
                    ]
                ];
                if ($colsel) {
                    $ncf = Arr::extend([
                        "select" => $colcf["select"]
                    ], [
                        "select" => $ncf
                    ]);
                } else {
                    $ncf = $this->parseSpecColSelect($fdi, "select", [
                        $fdi => $ncf
                    ], $colcf, $init);
                }
                $fdc[$fdi] = Arr::extend($fdc[$fdi], $ncf);
            }
        }

        if (isset($this->context["join"])) {
            $this->context["join"] = [];
        }
        return $this->setContext([
            "join" => $conf,
            "column" => $fdc
        ]);
    }

    /**
     * 获取 计算字段 (虚拟字段)
     * 在 model 类中定义了 protected fooBarGetter() 方法，
     * 且 有注释：
     *      /**
     *       * getter
     *       * @name fooBar
     *       * @title 字段名
     *       * @desc 字段说明
     *       * @width 3
     *       * @type varchar
     *       * @jstype object
     *       * @phptype JSON
     *       * ...
     * 
     * 则有计算字段 fooBar
     * 
     * 针对 一些特殊类型的字段，自动添加 计算字段
     * 
     * @param Array $init json 中定义的参数
     * @return Config $this
     */
    protected function parseGetters($init = [])
    {
        $init = !Is::nemarr($init) ? $this->init : $init;
        $conf = [
            "getters" => [],
            "column" => []
        ];

        //手动定义的计算字段
        $model = $this->model;
        $methods = Cls::methods($model, "protected", function($mi) {
            if (substr($mi->name, -6)==="Getter") {
                $doc = $mi->getDocComment();
                if (strpos($doc, "* getter")!==false || strpos($doc, "* Getter")!==false) {
                    return true;
                }
            }
            return false;
        });
        if (!empty($methods)) {
            //对找到的方法，进行处理
            foreach ($methods as $k => $mi) {
                $doc = $mi->getDocComment();
                $doc = str_replace("\\r\\n", "", $doc);
                $doc = str_replace("\\r", "", $doc);
                $doc = str_replace("\\n", "", $doc);
                $doc = str_replace("*\/", "", $doc);
                $da = explode("* @", $doc);
                array_shift($da);   //* getter
                $confi = [];
                foreach ($da as $i => $di) {
                    $dai = explode(" ", trim(explode("*", $di)[0]));
                    if (count($dai)<2) continue;
                    if (in_array($dai[0],["param","return"])) continue;
                    $dk = $dai[0];
                    $dv = implode(" ",array_slice($dai, 1));
                    if (strpos($dk, "type")!==false) {
                        $dkk = str_replace("type","", $dk);
                        if ($dkk=="") $dkk = "db";
                        $confi["type"][$dkk] = $dv;
                    } else {
                        $confi[$dk] = $dv;
                    }
                }
                $name = $confi["name"] ?? "";
                if (!Is::nemstr($name)) {
                    $name = str_replace("Getter","", $k);
                    $confi["name"] = $name;
                }
                $confi["isGetter"] = true;
                $conf["getters"][] = $name;
                $conf["column"][$name] = $confi;
            }
        }

        //针对 特殊类型的字段 自动定义计算字段 ***Str
        //查找 AutoGettersCreator 类中所有 createFoobarAutoGetters 方法
        $cags = Cls::methodNames(AutoGettersCreator::class, "static,public", function($mi) {
            $mn = $mi->name;
            return substr($mn, 0, 6)==="create" && substr($mn, -11)==="AutoGetters";
        });
        if (Is::nemarr($cags) && Is::indexed($cags)) {
            $colc = $this->context["column"] ?? [];
            foreach ($colc as $coln => $coli) {
                //分别调用 createFoobarAutoGetters 方法
                foreach ($cags as $i => $cag) {
                    //传入 $conf 参数，返回值是经过修改的 $conf
                    $conf = AutoGettersCreator::$cag($coln, $coli, $conf);
                }
            }
        } 

        return $this->setContext($conf);
    }

    /**
     * 解析 数据表(模型) 类/实例 Api
     * 在 model 类中定义了 public [static] fooBarApi() 方法，
     * 且 有注释：
     *      /**
     *       * api
     *       * @role foo,bar 或 all
     *       * @desc Api说明
     *       * @param String $argname 参数说明
     *       * ...
     *       * @return Mixed 返回值说明
     * 
     * @param Array $init json 中定义的参数
     * @return Config $this
     */
    protected function parseApi($init = [])
    {
        $init = !Is::nemarr($init) ? $this->init : $init;
        $conf = [
            "api" => [],
            "apis" => [],
            "modelApis" => []
        ];
        $model = $this->model;
        $methods = Cls::methods($model, "public", function($mi) {
            if (substr($mi->name, -3)==="Api") {
                $doc = $mi->getDocComment();
                if (strpos($doc, "* api")!==false || strpos($doc, "* Api")!==false) {
                    return true;
                }
            }
            return false;
        });
        if (empty($methods)) return $this->setContext($conf);
        //对找到的方法，进行处理
        foreach ($methods as $k => $mi) {
            $isStatic = $mi->isStatic();
            $doc = $mi->getDocComment();
            $doc = str_replace("\\r\\n", "", $doc);
            $doc = str_replace("\\r", "", $doc);
            $doc = str_replace("\\n", "", $doc);
            $doc = str_replace("*\/", "", $doc);
            $da = explode("* @", $doc);
            array_shift($da);   //* api
            $confi = [
                "name" => "",
                "role" => "all",
                "desc" => "",
                "authKey" => "",    //用户 auth 数组中 如果包含 authKey 则有访问此 api 的权限
                "isModel" => $isStatic, //静态方法 是 数据表 api 而不是 记录实例 api
            ];
            foreach ($da as $i => $di) {
                $dai = explode(" ", trim(explode("*", $di)[0]));
                if (count($dai)<2) continue;
                if (!in_array($dai[0],["desc","role","name","title","authKey"])) continue;
                $confi[$dai[0]] = implode(" ",array_slice($dai, 1));
            }
            $name = $confi["name"] ?? "";
            if (!Is::nemstr($name)) {
                $name = str_replace("Api","", $mi->name);
                $confi["name"] = $name;
            }
            if (is_string($confi["role"]) && $confi["role"]!="all") {
                $confi["role"] = Arr::mk($confi["role"]);
            }
            //$akey = $model::apikey($name);
            $mda = explode("model\\", strtolower($model));
            $akey = str_replace("\\","-", trim($mda[1], "\\"));
            $akey .= ($isStatic ? "-model-api-" : "-api-").$name;
            $confi["authKey"] = $akey;
            if ($isStatic) {
                $conf["modelApis"][] = $name;
            } else {
                $conf["apis"][] = $name;
            }
            $conf["api"][$name] = $confi;
        }
        return $this->setContext($conf);
    }

    /**
     * 解析 model meta 数据：name，title，...
     * @param Array $init json 中定义的参数
     * @return Config $this
     */
    protected function parseModelMeta($init = [])
    {
        $init = !Is::nemarr($init) ? $this->init : $init;
        $conf = [];

        //meta 字段
        $ms = explode(",", "name,title,desc,directedit");
        foreach ($ms as $i => $mi) { 
            if (isset($init[$mi])) {
                $conf[$mi] = $init[$mi];
            }
        }
        $conf["table"] = lcfirst($conf["name"]);
        $conf["name"] = ucfirst($conf["name"]);

        //indexs 索引
        $idxs = $init["column"]["indexs"] ?? [];
        $conf["indexs"] = $idxs;

        //includes 每次查询必须包含字段
        $incs = $conf["includes"] ?? [];
        $fds = $this->context["columns"];
        $specs = $this->context["special"];
        $sincs = $specs["includes"] ?? [];
        $ids = $specs["id"] ?? [];
        $gids = $specs["gid"] ?? [];
        if (!empty($sincs)) $incs = array_merge($sincs, $incs);
        if (!empty($gids)) $incs = array_merge($gids, $incs);
        if (!empty($ids)) $incs = array_merge($ids, $incs);
        //extra/enable 字段必须包含
        if (in_array("extra", $fds) && !in_array("extra", $incs)) $incs[] = "extra";
        if (in_array("enable", $fds) && !in_array("enable", $incs)) $incs[] = "enable";
        $incs = array_merge(array_flip(array_flip($incs)));     //去重
        $conf["includes"] = $incs;
        $conf["special"] = [
            "includes" => $incs
        ];

        return $this->setContext($conf);
    }

    /**
     * 当 数据模型 自定义 (或 通过 trait 引入了) 设置参数解析方法
     * 则 在此处 调用
     * @param Array $init json 中定义的参数
     * @return Config $this
     */
    protected function parseCustomConf($init = [])
    {
        $init = !Is::nemarr($init) ? $this->init : $init;
        $conf = [];
        $model = $this->model;
        
        //在 model 类中 查找 customConf*** 方法 必须是 public static
        $methods = Cls::methods($model, "public", function($mi) {
            if (substr($mi->name, 0,10)==="customConf") {
                return $mi->isStatic();
            }
            return false;
        });
        if (empty($methods)) return $this->setContext($conf);
        
        //执行 找到的 自定义参数解析方法
        foreach ($methods as $k => $mi) {
            $mn = $mi->name;
            //执行 参数解析方法
            $confi = $model::$mn($this->context, $init);
            //解析得到的参数 合并到 context
            if (Is::nemarr($confi) && Is::associate($confi)) {
                $conf = Arr::extend($conf, $confi);
            }
        }

        return $this->setContext($conf);
    }



    /**
     * json 中 特殊字段设置项 解析方法，设置内容保存在 $init["column"] 中
     * parseSpecColFoobar()
     * 遵循下列参数要求：
     * @param String $coln 字段名
     * @param Array $key 要解析的 json 中的某项特殊字段设置项 名称
     * @param Array $cf 要解析的 json 中的某项特殊字段设置项 的内容
     * @param Array $coli 此字段当前已解析得到的参数内容
     * @param Array $init json 预设参数全部内容
     * @return Array 解析得到的字段参数
     */
    //解析 hideintable / hideinform
    protected function parseSpecColHidein($coln, $key, $cf, $coli, $init)
    {
        $k = "showIn".ucfirst(strtolower(str_replace("hidein", "", $key)));
        $conf = [ $k => true ];
        if (!Is::nemarr($cf) || !Is::indexed($cf)) return $conf;
        $conf[$k] = !in_array($coln, $cf);
        return $conf;
    }
    //解析 sort / filter / search / sum
    protected function parseSpecColAble($coln, $key, $cf, $coli, $init)
    {
        $k = $key."able";
        $conf = [ $k => false ];
        if (!Is::nemarr($cf) || !Is::indexed($cf)) return $conf;
        $conf[$k] = in_array($coln, $cf);
        return $conf;
    }
    //解析 设置值为 associate array 以 字段名称 作为数组键名的 设置项
    protected function parseSpecColAssociate($coln, $key, $cf, $coli, $init)
    {
        $pk = strtolower($key);
        $k = "is".ucfirst($pk);
        $conf = [
            $k => false
        ];
        if (!Is::nemarr($cf)) return $conf;
        //各设置项的默认值
        $dft = [
            //定义项目默认值 time,number,json,money,validator,generator
            "time" => [
                "type" => "datetime"
            ],
            "number" => [
                "precision" => 4,
                "step" => 0.0001
            ],
            "json" => [
                "type" => "associate",
                "default" => Conv::j2a("{}")
            ],
            "money" => [
                "precision" => 4,
                "icon" => "￥"
            ],
            "validator" => [],
            "generator" => []
        ];
        if (Is::indexed($cf)) {
            //如果设置项未指定具体参数，则使用默认值
            $conf[$k] = in_array($coln, $cf);
            if ($conf[$k]==true) $conf[$pk] = $dft[$pk] ?? [];
        } else {
            //如果指定了具体参数
            $conf[$k] = isset($cf[$coln]);
            if ($conf[$k]==true) {
                $cv = $cf[$coln];
                //根据 设置项目 决定设置参数
                switch ($pk) {
                    //case "json": break;

                    //默认情况
                    default:
                        if (Is::nemarr($cv) && Is::associate($cv)) {
                            $conf[$pk] = Arr::extend($dft[$pk], $cv);
                        } else if (Is::nemstr($cv)) {
                            if (isset($dft[$pk]["type"])) {
                                $conf[$pk] = Arr::extend($dft[$pk], [ "type" => $cv ]);
                            } else {
                                $conf[$pk] = $cv;
                            }
                        } else {
                            $conf[$pk] = $cv;
                        }
                        break;
                }
            }
        }
        
        return $conf;
    }
    //解析 设置值为 indexed array 字段名称数组的 设置项
    protected function parseSpecColIndexed($coln, $key, $cf, $coli, $init)
    {
        $k = "is".ucfirst(strtolower($key));
        $conf = [ $k => false ];
        if (!Is::nemarr($cf) || !Is::indexed($cf)) return $conf;
        $conf[$k] = in_array($coln, $cf);
        return $conf;
    }
    //解析 select
    protected function parseSpecColSelect($coln, $key, $cf, $coli, $init)
    {
        $pk = strtolower($key);
        $k = "is".ucfirst($pk);
        $conf = [
            $k => false
        ];
        if (!Is::nemarr($cf) || !Is::associate($cf) || !isset($cf[$coln])) return $conf;
        $cv = $cf[$coln];
        $conf[$k] = Is::nemarr($cv) && Is::associate($cv);
        if ($conf[$k]!=true) return $conf;
        $conf[$pk] = Arr::extend([
            "dynamic" => true,
            "multiple" => false,
            "allow-create" => false,
            "source" => [
                "values" => [],
                "api" => "",
                "table" => "",
                "label" => "",
                "value" => "",
                "where" => []
            ]
        ], $cv);
        return $conf;
    }




    /**
     * tools
     */

    /**
     * tools
     * 读取 被缓存的 model 参数
     * 保存在 [dbpath]/runtime/[dbname]/modelname.json
     * 如果不存在，则返回 null
     * @return Array json --> []
     */
    public function getRuntimeConfig()
    {
        $db = $this->model::$db;
        $mda = explode("\\", $this->model);
        $mdn = lcfirst(array_pop($mda));
        if (!$db instanceof Db) return null;
        //查找文件
        $cf = $db->path("runtime/%{name}%/".$mdn.".json", true);
        if (empty($cf)) return null;
        return Conv::j2a(file_get_contents($cf));
    }

    
}