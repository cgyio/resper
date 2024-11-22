<?php
/**
 * cgyio/resper 数据库 参数 解析器
 * 解析 [dbpath]/config/[dbname].json
 * 解析得到 Configer 实例对象
 */

namespace Cgy\orm;

use Cgy\Orm;
use Cgy\module\Configer;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Conv;

class Config extends Configer
{
    //关联的 数据库实例
    public $db = null;

    /**
     * 构造
     * @param Array $opt 数据库设置文件参数，格式：
     *      [
     *          type        => "sqlite", 
     *          database    => "数据库路径" 
     *          dbkey       => "", 
     *          conf        => "设置文件.json 路径"
     *      ]
     * @return void
     */
    public function __construct($opt = [])
    {
        $conf = $opt["conf"] ?? null;
        if (!Is::nemstr($conf) || !file_exists($conf)) return null;

        //读取 json 内容到 $this->init
        $this->init = Conv::j2a(file_get_contents($conf));

        //关联到 数据库实例
        $dbkey = $opt["dbkey"];
        $this->db = Orm::$DB[$dbkey];

        //解析 json 文件，保存到 $this->context
        $this->setConf();
    }

    /**
     * 解析设置文件 [dbpath]/config/[dbname].json
     * !! 子类可覆盖
     * @param Array $opt 用户设置
     * @return $this
     */
    public function setConf($opt = [])
    {
        $init = $this->init;
        $mds = $init["model"] ?? [];

        //解析 除 model参数 外的 一般设置
        foreach ($init as $k => $v) {
            if ($k == "model") continue;
            $this->context[$k] = $v;
        }
        $this->context = Arr::extend($this->context, [
            "models" => array_keys($mds),
            "model" => $mds
        ]);

        return $this;
    }

    /**
     * 在 应用用户设置后 执行
     * !! 子类可覆盖
     * @return $this
     */
    public function afterSetConf()
    {
        //子类可自定义方法
        //...

        return $this;
    }

    /**
     * 解析 model 参数
     * 解析 context["model"][$model] 中保存的参数
     * 在 $db->model($model) 时，执行此方法，对 数据模型(表) 类进行参数初始化，并保存到类
     * @param String $model 数据模型(表) name
     * @return Array 解析得到的 model 参数
     */
    public function parseModelConf($model)
    {
        if (!Is::nemstr($model)) return null;
        $mdcs = $this->context["model"];
        if (isset($mdcs[$model])) {
            $mdc = $mdcs[$model];
            $mdn = $model;
        } else {
            $mdn = lcfirst($model);
            if (isset($mdcs[$mdn])) {
                $mdc = $mdcs[$mdn];
            } else {
                return null;
            }
        }

        /**
         * 开始解析 model 参数
         */
        $conf = [];
        //数据模型(表) 类全称
        $mdcls = $this->db->resper->cls($mdn);
        //解析 columns 字段参数
        $mdc = $this->parseModelColumnsConf($mdc, $mdcls);
        //基本参数
        $ks = explode(",","name,title,desc,columns");
        foreach ($ks as $i => $k) {
            if (!isset($mdc[$k])) continue;
            $conf[$k] = $mdc[$k];
            $mdcls::$$k = $mdc[$k];
        }
        //开始解析
    }

    /**
     * 解析 model 参数
     * 解析字段参数
     * @param Array $mdc 设置内容
     * @param String $mdcls 数据模型(表) 类全称
     * @return Array 经过处理的 $mdc
     */
    private function parseModelColumnsConf($mdc, $mdcls)
    {

    }

}