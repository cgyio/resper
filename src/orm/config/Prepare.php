<?php
/**
 * resper 框架 数据模型参数 前置处理类
 * 在 数据库初始化时，读取数据库参数，针对每个数据模型，如果定义了 prepare 参数，则调用此类进行前置处理
 * 此操作可能会：为数据模型参数增加一些内容，例如：为数据模型统一增加一些通用字段 等
 * 可以简化数据库参数 json 文件的内容
 */

namespace Cgy\orm\config;

use Cgy\Orm;
use Cgy\orm\Db;
use Cgy\orm\Model;
use Cgy\orm\Config;
use Cgy\util\Is;
use Cgy\util\Str;
use Cgy\util\Arr;
use Cgy\util\Path;
use Cgy\util\Conv;

class Prepare 
{
    //数据库设置参数内容
    public $ctx = [];
    //数据模型设置参数内容
    public $model = [];
    


    /**
     * 构造
     * @param Config $conf 数据库的设置类实例
     * @return void
     */
    public function __construct($conf=null)
    {
        if (!Is::nemarr($conf)) return null;
        $this->ctx = $conf;
        $this->model = $this->ctx["model"] ?? [];
    }

    /**
     * 入口方法，在 数据库初始化时，$db->config->setConf() 中执行
     * @return Array 经过前置处理的数据库参数 context
     */
    public function parse()
    {
        foreach ($this->model as $mdn => $mdc) {
            //无论模型有没有定义 prepare 参数，都必须调用默认的 prepareCommon() 方法
            $this->prepareCommon($mdn);
            //更新 prepare 参数
            $mdc = $this->model[$mdn];
            if (isset($mdc["prepare"]) && Is::nemarr($mdc["prepare"])) {
                //如果 model 模型参数中定义了 prepare 参数，则解析
                $pp = $mdc["prepare"];
                foreach ($pp as $m => $mc) {
                    //跳过 extends 参数，因为在 prepareCommon 中执行过
                    if ("extends" == strtolower($m)) continue;
                    //查找预定义的迁至处理方法，如：columns  -->  prepareColumns($mdn, [...config])
                    $m = "prepare".ucfirst(strtolower($m));
                    if (method_exists($this, $m)) {
                        $this->$m($mdn, $mc);
                    }
                }
            }
        }

        //var_dump($this->model);
        //将更新后的 model 参数写入 $this->ctx
        $this->ctx["model"] = $this->model;
        //返回处理后的 参数 context
        //var_dump($this->ctx);
        return $this->ctx;
    }

    /**
     * 将解析结果写入 $db->config->context["model"][$model] 
     * @param String $model 模型名称
     * @param Array $conf 要写入的设置数据
     * @return Bool
     */
    protected function setModelCtx($model, $conf=[])
    {
        if ($this->hasModel($model)!==true || !Is::nemarr($conf)) return false;
        $this->model[$model] = $conf;
        return true;
    }



    /**
     * 预定义的前置处理方法
     * @param String $model 数据模型名称
     * @param Array $conf 在 json 中定义的 此前置处理方法的 参数
     * @return Bool
     */
    /**
     * 默认前置处理方法
     *  0   检查是否定义了 prepare["extends"] 参数，如果有则执行 prepareExtends()
     *  1   向 prepare["columns"] 参数数组的 开头处/结尾处 分别添加 common/final 用于增加 id 以及 info/extra/enable 字段
     */
    protected function prepareCommon($model, $conf=[])
    {
        if ($this->hasModel($model)!==true) return false;
        $mdc = $this->model[$model];
        $pp = $mdc["prepare"] ?? [];

        // 0   检查是否定义了 prepare["extends"] 参数，如果有则执行 prepareExtends()
        if (Is::nemstr($pp["extends"])) {
            $this->prepareExtends($model, $pp["extends"]);
            //更新 prepare 参数
            $mdc = $this->model[$model];
            $pp = $mdc["prepare"] ?? [];
        }

        // 1   向 prepare["columns"] 参数数组的 开头处/结尾处 分别添加 common/final 用于增加 id 以及 info/extra/enable 字段
        $cols = $pp["columns"] ?? [];
        if (!Is::nemarr($cols) || !Is::indexed($cols)) $cols = [];
        if (!in_array("common", $cols)) array_unshift($cols, "common");
        if (!in_array("final", $cols)) array_push($cols, "final");
        //更新模型参数
        $mdc["prepare"]["columns"] = $cols;
        $this->model[$model] = $mdc;

        return true;
    }
    /**
     * extends 方法
     * 使用预定义的 模型结构参数 覆盖当前数据模型
     * 适用于一些拥有相同结构的 数据模型 共用相同的模型参数
     * prepare 参数指定了 公用 json 的文件路径，
     * 读取这个公用的 json ，将数据覆盖到当前模型 $db->config->context["model"][$model]
     * 然后更新缓存
     */
    protected function prepareExtends($model, $jsf="")
    {
        if ($this->hasModel($model)!==true || !Is::nemstr($jsf)) return false;
        if (substr($jsf, -5)!=".json") $jsf .= ".json";
        $jsf = Path::find($jsf);
        if (!empty($jsf) && file_exists($jsf)) {
            $mc = file_get_contents($jsf);
            $mc = Conv::j2a($mc);
            $mdc = $this->model[$model];
            //移除 prepare 中的 extends 参数
            unset($mdc["prepare"]["extends"]);
            $mc = Arr::extend($mdc, $mc);
            //写入 数据库参数 context
            $this->setModelCtx($model, $mc);
            return true;
        }
        return false;
    }
    /**
     * columns 方法
     * 为数据模型统一增加一些通用字段
     * @param Array $conf 在 json 中定义的 此前置处理方法的 参数
     * @return Bool
     */
    protected function prepareColumns($model, $conf=[])
    {
        if ($this->hasModel($model)!==true || !Is::nemarr($conf) || !Is::indexed($conf)) return false;
        //根据 prepare 中的 columns 参数，按顺序为当前模型增加预定义的字段，修改相关模型参数项
        foreach ($conf as $i => $coltype) {
            $m = "add".ucfirst(strtolower($coltype))."Columns";
            if (method_exists($this, $m)) {
                $this->$m($model);
            }
        }
        return true;
    }
    /**
     * idx 方法
     * 为数据模型表增加索引列
     * !!! 必须在 columns 方法之后执行，在设置文件 json 中，idx 必须在 columns 之后定义
     * @param Array $conf 在 json 中定义的 此前置处理方法的 参数
     * @return Bool
     */
    protected function prepareIdx($model, $conf=[])
    {
        if ($this->hasModel($model)!==true || !Is::nemarr($conf) || !Is::associate($conf)) return false;
        $mdc = $this->model[$model];
        //增加 unique 索引，隐式定义
        $uni = $conf["unique"] ?? [];
        if (Is::nemarr($uni) && Is::indexed($uni)) {
            $crt = $mdc["creation"] ?? [];
            $ucols = $mdc["column"]["unique"] ?? [];
            foreach ($uni as $i => $coln) {
                if (!isset($crt[$coln]) || !Is::nemstr($crt[$coln])) continue;
                if (strpos($crt[$coln], ' UNIQUE')===false) {
                    $crt[$coln] .= " UNIQUE";
                }
                //unique 属性保存到字段参数中
                $ucols[] = $coln;
            }
            $mdc["creation"] = $crt;
            if (!empty($ucols)) {
                $mdc["column"]["unique"] = $ucols;
            }
        }

        //其他索引使用 显式定义，参数保存在 context["model"][$model]["column"]["indexs"]
        $colc = $mdc["column"];
        $idxs = $colc["indexs"] ?? [];
        //索引前缀 idx_
        $pre = "idx_";
        //增加复合索引
        $multi = $conf["multiple"] ?? [];
        if (Is::nemarr($multi) && Is::associate($multi)) {
            foreach ($multi as $idxkey => $cols) {
                if (!Is::nemarr($cols) || !Is::indexed($cols)) continue;
                $idxs[$pre.$idxkey] = "(`".implode("`,`", $cols)."`)";
            }
        }
        //增加普通索引
        $nor = $conf["normal"] ?? [];
        if (Is::nemarr($nor) && Is::indexed($nor)) {
            foreach ($nor as $i => $coln) {
                $idxs[$pre.$coln] = "(`".$coln."`)";
            }
        }
        
        //更新模型参数
        if (!empty($idxs)) $mdc["column"]["indexs"] = $idxs;
        $this->model[$model] = $mdc;

        return true;
    }



    /**
     * 通用方法
     */

    //判断给定的 模型名称 是否存在
    protected function hasModel($model)
    {
        if (!Is::nemstr($model)) return false;
        $mds = $this->model;
        return isset($mds[$model]) && Is::nemarr($mds[$model]);
    }

    /**
     * 为数据模型参数 增加字段
     * @param String $model 数据模型名称
     * @param String $coln 字段名称
     * @param Array $conf 字段参数
     *  [
     *      "columns" => ["title","desc",width],
     *      "creation" => "varchar NOT NULL DEFAULT ''",
     *      "join" => [
     *          "use" => true,
     *          "[>]table" => $coln
     *      ],
     *      "column" => [
     *          "includes" => [$clon],
     *          "sort" => [$coln],
     *          "filter" => [$coln],
     *          "time" => [
     *              $coln => [
     *                  时间参数
     *              ]
     *          ]
     *      ]
     *  ]
     * @param Bool $append 是否添加到现有字段列表的末尾，默认 true，false 则添加到现有字段列表的开头
     * @return Bool
     */
    protected function addColumn($model, $coln, $conf=[], $append=true)
    {
        if ($this->hasModel($model)!==true) return false;
        $mdc = $this->model[$model];
        
        //按参数项目 添加
        if (isset($conf["columns"])) {
            //添加 columns 参数项
            $ocols = $mdc["columns"];
            if ($append) {
                $ocols[$coln] = $conf["columns"];
            } else {
                $ocols = [];
                $ocols[$coln] = $conf["columns"];
                foreach ($mdc["columns"] as $n => $c) {
                    if ($n==$coln) continue;
                    $ocols[$n] = $c;
                }
            }
            //$this->db->config->context["model"][$model]["columns"] = $ocols;
            $mdc["columns"] = $ocols;
            unset($conf["columns"]);
        }
        if (isset($conf["creation"])) {
            //添加到 creation 参数项
            $ocrea = $mdc["creation"];
            if ($append) {
                $ocrea[$coln] = $conf["creation"];
            } else {
                $ocrea = [];
                $ocrea[$coln] = $conf["creation"];
                foreach ($mdc["creation"] as $n => $c) {
                    if ($n==$coln) continue;
                    $ocrea[$n] = $c;
                }
            }
            //$this->db->config->context["model"][$model]["creation"] = $ocrea;
            $mdc["creation"] = $ocrea;
            unset($conf["creation"]);
        }
        //通过 Arr::extend 方法合并参数
        if (isset($conf["join"])) {
            //添加到 join 参数
            $ojoin = $mdc["join"] ?? [];
            //$this->db->config->context["model"][$model]["join"] = Arr::extend($ojoin, $conf["join"]);
            $mdc["join"] = Arr::extend($ojoin, $conf["join"]);
            unset($conf["join"]);
        }
        if (isset($conf["column"])) {
            //添加到 column 参数
            $ocolc = $mdc["column"];
            foreach ($conf["column"] as $k => $cfc) {
                if (!isset($ocolc[$k])) {
                    $ocolc[$k] = $cfc;
                    continue;
                } 
                $ocolck = $ocolc[$k];
                if (Is::indexed($ocolck) && Is::indexed($cfc)) {
                    if ($append) {
                        $ocolc[$k] = array_unique(array_merge($ocolck, $cfc), SORT_REGULAR);
                    } else {
                        $ocolc[$k] = array_unique(array_merge($cfc, $ocolck), SORT_REGULAR);
                    }
                    continue;
                }
                if (Is::associate($ocolck) && Is::associate($cfc)) {
                    $ocolc[$k] = Arr::extend($ocolck, $cfc);
                } else {
                    $ocolc[$k] = $cfc;
                }
            }
            //$this->db->config->context["model"][$model]["column"] = $ocolc;
            $mdc["column"] = $ocolc;
            unset($conf["column"]);
        }

        //写入 参数 context
        $this->setModelCtx($model, $mdc);
        return true;
    }

    /**
     * 增加一些通用类型字段
     * 在 prepareColumns() 方法中 根据数据库 json 中的设置参数 来调用
     * @param String $model 模型名称
     * @return Bool 
     */
    //增加 common 通用字段 id 字段，所有数据模型都必须包含这些字段，字段列表开头
    protected function addCommonColumns($model)
    {
        if ($this->hasModel($model)!==true) return false;
        //增加字段 id 到字段列表开头
        $this->addColumn($model, "id", [
            "columns" => ["ID","自增序号",3],
            "creation" => "integer PRIMARY KEY AUTOINCREMENT",
            "column" => [
                "includes" => ["id"],
                "sort" => ["id"],
                "filter" => ["id"]
            ]
        ], false);
        return true;
    }
    //增加 final 通用字段 info/extra/enable 字段，所有数据模型都必须包含这些字段，字段列表结尾
    protected function addFinalColumns($model)
    {
        if ($this->hasModel($model)!==true) return false;
        //增加字段 info, extra, enable
        $cols = [
            "info" => [
                "columns" => ["备注","此记录的备注",3],
                "creation" => "varchar",
                "column" => [
                    "includes" => ["info"],
                    "search" => ["info"],
                ]
            ],
            "extra" => [
                "columns" => ["更多","此记录的更多数据",5],
                "creation" => "varchar NOT NULL DEFAULT '{}'",
                "column" => [
                    "includes" => ["extra"],
                    "search" => ["extra"],
                    "json" => [
                        "extra" => "associate"
                    ]
                ]
            ],
            "enable" => [
                "columns" => ["生效","此记录是否生效",3],
                "creation" => "integer NOT NULL DEFAULT 1",
                "column" => [
                    //索引
                    //"indexs" => [
                    //    "idx_enable" => "(`enable`)",
                    //],
                    "includes" => ["enable"],
                    "filter" => ["enable"],
                    "switch" => ["enable"],
                ]
            ],
        ];
        foreach ($cols as $coln => $conf) {
            $this->addColumn($model, $coln, $conf, true);
        }
        return true;
    }
    //增加 creator 记录创建/修改 相关的字段
    protected function addCreatorColumns($model)
    {
        if ($this->hasModel($model)!==true) return false;
        $cols = [
            "creator" => [
                "columns" => ["创建人","此记录的创建人",3],
                "creation" => "varchar NOT NULL",
                "join" => [
                    "[>]usr" => [
                        "creator" => "uid"
                    ]
                ],
                "column" => [
                    "includes" => ["creator"],
                    "filter" => ["creator"]
                ]
            ],
            "addtime" => [
                "columns" => ["创建时间","此记录的创建时间",5],
                "creation" => "integer NOT NULL DEFAULT 0",
                "column" => [
                    "time" => [
                        "addtime" => [
                            "type" => "datetime",
                            //"default" => "now"
                        ]
                    ]
                ]
            ],
            "modlog" => [
                "columns" => ["修订记录","此通用产品品种数据的修订记录",4],
                "creation" => "varchar NOT NULL DEFAULT '[]'",
                "column" => [
                    "json" => [
                        "modlog" => "indexed"
                    ]
                ]
            ],
            "modtime" => [
                "columns" => ["最新修订","此记录的最新修订时间",5],
                "creation" => "integer NOT NULL DEFAULT 0",
                "column" => [
                    "time" => [
                        "modtime" => [
                            "type" => "datetime",
                            //"default" => "now"
                        ]
                    ]
                ]
            ],
        ];
        foreach ($cols as $coln => $conf) {
            $this->addColumn($model, $coln, $conf, true);
        }
        return true;
    }
    //增加 status 记录状态 相关的字段
    protected function addStatusColumns($model)
    {
        if ($this->hasModel($model)!==true) return false;
        $cols = [
            "status" => [
                "columns" => ["状态","此记录的当前状态",3],
                "creation" => "varchar",
                "column" => [
                    //索引
                    //"indexs" => [
                    //    "idx_status" => "(`status`)",
                    //],
                    "includes" => ["status"],
                ]
            ],
            "stnum" => [
                "columns" => ["状态码","此记录的当前状态编码，可通过比较大小来确定状态的先后关系",3],
                "creation" => "integer NOT NULL DEFAULT 0",
                "column" => [
                    //索引
                    //"indexs" => [
                    //    "idx_stnum" => "(`stnum`)",
                    //],
                    "includes" => ["stnum"],
                    /*"highlight" => [
                        "stnum" => [

                        ]
                    ]*/
                ]
            ],
            "stlog" => [
                "columns" => ["状态记录","此配方状态变更记录，记录变更的时间节点以及相关人员",5],
                "creation" => "varchar NOT NULL DEFAULT '[]'",
                "column" => [
                    "includes" => ["stlog"],
                    "json" => [
                        "stlog" => "indexed"
                    ]
                ]
            ],
        ];
        foreach ($cols as $coln => $conf) {
            $this->addColumn($model, $coln, $conf, true);
        }
        return true;
    }
    //增加 uac 权限控制 相关的字段
    protected function addUacColumns($model)
    {
        if ($this->hasModel($model)!==true) return false;
        $cols = [
            "name" => [
                "columns" => ["账号","此账号登录系统的账号名称",4],
                "creation" => "varchar NOT NULL",
                "column" => [
                    //索引
                    //"indexs" => [
                    //    "idx_name" => "(`name`)",
                    //],
                    "includes" => ["name"],
                    "search" => ["name"]
                ]
            ],
            "pwd" => [
                "columns" => ["密码","此账号登录系统的密码",3],
                "creation" => "varchar",
                "column" => [
                    "hideintable" => ["pwd"],
                ]
            ],
            "role" => [
                "columns" => ["角色","账号角色，赋予的操作权限",3],
                "creation" => "varchar NOT NULL DEFAULT '[]'",
                "column" => [
                    "filter" => ["role"],
                    "select" => [
                        "role" => [
                            "dynamic" => true,
                            "multiple" => true,
                            "source" => [
                                "table" => "role",
                                "value" => "key",
                                "label" => "name"
                            ]
                        ]
                    ],
                    "json" => [
                        "role" => "indexed"
                    ],
                ]
            ],
            "auth" => [
                "columns" => ["权限","除账号角色权限外，此账号还拥有的操作权限",3],
                "creation" => "varchar NOT NULL DEFAULT '[]'",
                "column" => [
                    "filter" => ["auth"],
                    "select" => [
                        "auth" => [
                            "dynamic" => true,
                            "multiple" => true,
                            "source" => [
                                "api" => "uac/authvalues",
                            ]
                        ]
                    ],
                    "json" => [
                        "auth" => "indexed"
                    ],
                ]
            ],
        ];
        foreach ($cols as $coln => $conf) {
            $this->addColumn($model, $coln, $conf, true);
        }
        return true;
    }
    //增加 lisence 资质 相关字段
    protected function addLisenceColumns($model)
    {
        if ($this->hasModel($model)!==true) return false;
        //与记录 创建/修改 相关的字段
        $cols = [
            "bzname" => [
                "columns" => ["全称","此主体在资质文件中的全称",5],
                "creation" => "varchar",
                "column" => [
                    "includes" => ["bzname"],
                    "search" => ["bzname"]
                ]
            ],
            "bztel" => [
                "columns" => ["联系电话","此主体的联系电话",4],
                "creation" => "varchar",
                "column" => [
                    "search" => ["bztel"]
                ]
            ],
            "lisence" => [
                "columns" => ["许可证","此主体的资质许可证号",4],
                "creation" => "varchar",
                "column" => [
                    "includes" => ["lisence"],
                    "search" => ["lisence"]
                ]
            ],
            "bzinfo" => [
                "columns" => ["资质详情","此主体的资质信息详情记录",3],
                "creation" => "varchar NOT NULL DEFAULT '{}'",
                "column" => [
                    "search" => ["bzinfo"],
                    "json" => [
                        "bzinfo" => [
                            "type" => "associate",
                            "default" => [
                                "公司全称" => "",
                                "公司电话" => "",
                                "公司地址" => "",
                                "法人姓名" => "",
                                "法人证件" => "",
                                "法人电话" => "",
                                "营业执照" => "",
                                "经营范围" => "",
                                "许可证号" => "",
                                "许可范围" => ""
                            ]
                        ]
                    ],
                ]
            ],
            "bzlogo" => [
                "columns" => ["Logo","此主体的Logo文件",4],
                "creation" => "varchar NOT NULL DEFAULT '[]'",
                "column" => [
                    "file" => [
                        "bzlogo" => [
                            "uploadTo" => "__assets_files__/logos",
                            "accept" => "image/*",
                        ]
                    ],
                    "json" => [
                        "bzlogo" => "indexed"
                    ],
                ]
            ],
            "bzbrand" => [
                "columns" => ["拥有品牌","此主体注册的品牌商标",4],
                "creation" => "varchar NOT NULL DEFAULT '[]'",
                "column" => [
                    "filter" => ["bzbrand"],
                    "search" => ["bzbrand"],
                    "json" => [
                        "bzbrand" => "indexed"
                    ],
                ]
            ],
            "bzverify" => [
                "columns" => ["核验状态","此主体资质文件的核验状态",3],
                "creation" => "integer NOT NULL DEFAULT 0",
                "column" => [
                    "filter" => ["bzverify"],
                    "switch" => ["bzverify"]
                ]
            ],
            "bzvrlog" => [
                "columns" => ["核验记录","此主体资质文件的核验记录",4],
                "creation" => "varchar NOT NULL DEFAULT '{}'",
                "column" => [
                    "search" => ["bzvrlog"],
                    "json" => [
                        "bzvrlog" => "associate"
                    ],
                ]
            ],
        ];
        foreach ($cols as $coln => $conf) {
            $this->addColumn($model, $coln, $conf, true);
        }
        return true;
    }
    //增加 package 品种规格 相关的字段
    protected function addPackageColumns($model)
    {
        if ($this->hasModel($model)!==true) return false;
        $cols = [
            "unit" => [
                "columns" => ["单位","此品种的计量单位，散装货品选「克」",3],
                "creation" => "varchar NOT NULL DEFAULT '克'",
                "column" => [
                    "includes" => ["unit"],
                    "package" => [
                        "columns" => ["unit","netwt","maxunit","minnum","midunit"],
                        "intunit" => true,
                        "dig" => 2
                    ]
                ]
            ],
            "netwt" => [
                "columns" => ["净含量","此品种的计量单位重量「克」，散装货品填 1",4],
                "creation" => "float NOT NULL DEFAULT 1",
                "column" => [
                    "includes" => ["netwt"],
                    "number" => [
                        "netwt" => [
                            "precision" => 2,
                            "step" => 0.01
                        ]
                    ]
                ]
            ],
            "maxunit" => [
                "columns" => ["外包装单位","此品种的外包装单位，散装货品选「无」",3],
                "creation" => "varchar NOT NULL DEFAULT '无'",
                "column" => [
                    "includes" => ["maxunit"],
                ]
            ],
            "minnum" => [
                "columns" => ["小包装数","此品种的每个外包装包含小包装个数，散装货品填 1",3],
                "creation" => "integer NOT NULL DEFAULT 1",
                "column" => [
                    "includes" => ["minnum"],
                    "number" => [
                        "minnum" => [
                            "precision" => 0,
                            "step" => 1
                        ]
                    ]
                ]
            ],
            "midunit" => [
                "columns" => ["中间包装","此品种的中间包装规格，可选，可以有多个，从小到大排列",3],
                "creation" => "varchar NOT NULL DEFAULT '[]'",
                "column" => [
                    "includes" => ["midunit"],
                    "json" => [
                        "midunit" => "indexed"
                    ]
                ]
            ],
        ];
        foreach ($cols as $coln => $conf) {
            $this->addColumn($model, $coln, $conf, true);
        }
        return true;
    }
    

}