<?php
/**
 * cgyio/resper 数据库 config.json 解析器
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
     * @param Array $opt 数据库设置文件参数 [type=>"sqlite", dbkey=>"", conf=>"设置文件.json 路径"]
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
     * 解析设置文件 db_path/config/db_name.json
     * !! 子类可覆盖
     * @param Array $opt 用户设置
     * @return $this
     */
    public function setConf($opt = [])
    {
        $init = $this->init;
        $tbs = $init["model"] ?? [];
        //一般设置
        foreach ($init as $k => $v) {
            if ($k=="model") continue;
            $this->context[$k] = $v;
        }
        $this->context = Arr::extend($this->context, [
            "models" => array_keys($tbs),
            "model" => $tbs
        ]);
        //解析数据表(模型)参数
        foreach ($tbs as $tbn => $tbc) {

        }

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
     * 读取 json file
     */
    public static function parse($db=null)
    {
        $dbname = $db->name;
        $pathinfo = $db->pathinfo;
        $confp = self::getConfPath($pathinfo["dirname"]);
        $conf = $confp.DS.$dbname.".json";
        if (file_exists($conf)) {
            $conf = j2a(file_get_contents($conf));
        } else {
            $conf = [];
        }
        $cfg = new self($conf);
        $cfg->db = $db;
        return $cfg;
    }

    /**
     * static tools
     */
    //从数据库路径，解析 config 路径
    protected static function getConfPath($dbpath="")
    {
        $dpa = explode(DS, $dbpath);
        $l = array_pop($dpa);
        if (strtolower($l)=="db") $dpa[] = "db";
        $dpa[] = "config";
        $confp = implode(DS, $dpa);
        return $confp;
    }
}