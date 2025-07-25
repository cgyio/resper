<?php
/**
 * cgyio/resper 类型数据库 驱动
 * Sqlite 数据库驱动
 */

namespace Cgy\orm\driver;

use Cgy\Orm;
use Cgy\orm\Db;
use Cgy\orm\Driver;
use Cgy\orm\Config;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Path;
use Medoo\Medoo;

class Sqlite extends Driver 
{
    /**
     * 通用的 sqlite 连接参数
     */
    protected static $dftMedooParams = [
        "type" => "sqlite",
        "database" => "",
    ];

    //数据库文件后缀名
    public static $ext = ".db";

    /**
     * !! 必须实现 !!
     */
    
    /**
     * 数据库连接方法
     * @param Array $opt 数据库配置参数，在 Orm::$current->config->dbn 中，数据结构参考：orm/Config::$context 属性
     * @return Dbo 数据库实例
     */
    public static function connect($opt=[])
    {
        //获取参数
        $conf = $opt["config"] ?? null;     //数据库配置文件实际路径
        $dbkey = $opt["key"] ?? null;
        $dbn = $opt["name"] ?? null;
        $type = $opt["type"] ?? null;
        $driver = $opt["driver"] ?? null;
        $medoo = $opt["medoo"] ?? null;
        //检查参数合法性
        if (
            !Is::nemstr($conf) || !file_exists($conf) ||
            !Is::nemstr($dbkey) || !Is::nemstr($dbn) ||
            !Is::nemstr($type) || $type!=="sqlite" ||
            /*!Is::nemstr($driver) || $driver !== static::class ||*/
            !Is::nemarr($medoo) || !isset($medoo["database"]) || !Is::nemstr($medoo["database"])
        ) {
            return null;
        }
        //检查是否存在缓存的数据库实例
        if (isset(Orm::$DB[$dbkey]) && Orm::$DB[$dbkey] instanceof Db) {
            return Orm::$DB[$dbkey];
        }

        //用默认值 填充连接参数
        $medoo = Arr::extend(static::$dftMedooParams, $medoo);
        //处理数据库文件后缀名
        $medoo["database"] = static::autoSuffix($medoo["database"]);

        //创建数据库实例
        $db = new $driver($medoo);
        //写入参数
        $db->type = $type;
        $db->name = $dbn;
        $db->key = $dbkey;
        //sqlite 类型 数据库路径信息应为：  pathinfo( $medoo["database"] )
        $db->pathinfo = pathinfo($medoo["database"]);
        $db->driver = $driver;
        //返回创建的数据库实例
        return $db;
    }

    /**
     * 创建数据库
     * !!! 数据库必须初始化完成，在 Orm::$DB[dbkey] 中保存数据库实例
     * @param String $dbkey 数据库唯一 key
     * @param Array $opt 数据库创建参数
     *  [
     *      models => [
     *          表名 => [
     *              creation => [
     *                  字段名 => SQL
     *              ],
     *              indexs => [
     *                  idx_foo => (`foo`) 索引
     *              ],
     * 
     *              withrs => true 重建数据表时，是否保留 原数据记录，默认 true
     *          ]
     *          ...
     *      ],
     *      ... 其他参数
     *  ]
     * @return Bool
     */
    public static function create($dbkey, $opt=[])
    {
        if (!Is::nemstr($dbkey) || !isset(Orm::$DB[$dbkey]) || !Is::nemarr($opt) || !isset($opt["models"])) return false;
        $db = Orm::$DB[$dbkey];
        $dbt = $db->type;
        $pi = $db->pathinfo;
        $dbf = $pi["dirname"].DS.$pi["basename"];
        if (!Is::nemstr($dbt) || $dbt!="sqlite" || !Is::nemstr($dbf)) return false;
        $mds = $opt["models"]; unset($opt["models"]);
        if (!Is::nemarr($mds)) return false;
        //是否需要 drop 删除原数据表
        $needDrop = true;

        //db 文件不存在则创建
        if (!file_exists($dbf)) {
            $fh = @fopen($dbf, "w");
            fclose($fh);
            $needDrop = false;
        }

        //获取现有表 列表
        $tbs = $db->getTableNames();
        if (!Is::nemarr($tbs)) $tbs = [];
        //依次处理数据表
        $sql = [];
        foreach ($mds as $mdn => $mdc) {
            if (!Is::nemarr($mdc["creation"])) continue;
            $sql[$mdn] = [];

            //此表是否已存在
            $exi = in_array($mdn, $tbs);

            //withrs = true 获取原数据记录
            $withrs = $mdc["withrs"] ?? true;
            if (!is_bool($withrs)) $withrs = true;
            if ($withrs===true && $exi===true) {
                $rs = $db->medoo("select", $mdn, "*");
            } else {
                $rs = null;
            }

            //判断是否需要 drop table
            if ($needDrop===true && $exi===true) {
                //存在现有表，先 drop table
                $sqlDrop = "DROP TABLE IF EXISTS `".$mdn."`";
                //$db->medoo("query", $sqlDrop);
                $sql[$mdn][] = $sqlDrop;
            }

            //开始创建表
            //创建字段 语句
            $sqlCreate = [];
            foreach ($mdc["creation"] as $col => $colc) {
                $sqlCreate[] = "`".$col."` ".$colc;
            }
            $sql[$mdn][] = "CREATE TABLE IF NOT EXISTS `".$mdn."` (".implode(", ", $sqlCreate).")";
            //创建索引 语句
            if (Is::nemarr($mdc["indexs"])) {
                foreach ($mdc["indexs"] as $idxk => $idxc) {
                    $sql[$mdn][] = "CREATE INDEX `".$idxk."` ON `".$mdn."`".$idxc;
                }
            }

            //恢复记录
            if ($withrs===true && Is::nemarr($rs)) {
                $cols = array_keys($mdc["creation"]);
                for ($i=0;$i<count($rs);$i++) {
                    $ri = $rs[$i];
                    $cstr = [];
                    $vstr = [];
                    foreach ($cols as $coli) {
                        if ($coli=="id" || !isset($ri[$coli])) continue;
                        $cstr[] = "`".$coli."`";
                        $vi = $ri[$coli];
                        if (is_numeric($vi)) {
                            $vstr[] = $vi;
                        } else {
                            $vstr[] = "'".$vi."'";
                        }
                    }
                    $sqlIns = "INSERT INTO `".$mdn."` (".implode(", ", $cstr).") VALUES (".implode(", ", $vstr).")";
                    //$d->query($sqlIns);
                    $sql[$mdn][] = $sqlIns;
                }
            }
        }

        //使用 medoo 执行事务
        $result = [
            "sql" => $sql,
            "result" => []
        ];
        if (Is::nemarr($sql)) {
            $db->medoo("action", function($d) use ($sql, &$result){
                foreach ($sql as $mdn => $sqls) {
                    if (!Is::nemarr($sqls) || !Is::indexed($sqls)) continue;
                    $result["result"][$mdn] = [];
                    for ($i=0;$i<count($sqls);$i++) {
                        $rst = $d->query($sqls[$i]);
                        if ($rst instanceof \PDOStatement) {
                            $result["result"][$mdn][] = $rst->queryString;
                        } else {
                            $result["result"][$mdn][] = $rst;
                        }
                    }
                } 
            });
        }

        return $result;
    }



    /**
     * 实例方法
     */

    /**
     * 获取库中所有表 数组
     * @return Array [ 表名, ... ]
     */
    public function getTableNames()
    {
        $rs = $this->medoo(
            "query", 
            "SELECT `name` FROM `sqlite_master` WHERE `type`='table' ORDER BY `name`"
        )->fetchAll();
        $tbs = array_map(function($i) {
            if (Is::nemstr($i)) {
                $tbn = $i;
            } else if (Is::nemarr($i)) {
                $tbn = $i["name"];
            } else {
                $tbn = "";
            }
            return $tbn;
        }, $rs);
        $tbs = array_filter($tbs, function($i) {
            if (!Is::nemstr($i)) return false;
            if (strpos($i, "sqlite")!==false) return false;
            return true;
        });
        return array_values($tbs);
    }

    /**
     * 查看某个数据表的索引
     * @param String $tbn 表名
     * @return Array 
     */
    public function getTableIndexs($tbn)
    {
        $rs = $this->medoo(
            "query", 
            "SELECT `name` FROM `sqlite_master` WHERE `type`='index' AND `tbl_name`='".$tbn."'"
        )->fetchAll();
        $idxs = array_map(function($i) {
            if (Is::nemstr($i)) {
                $idxn = $i;
            } else if (Is::nemarr($i)) {
                $idxn = $i["name"];
            } else {
                $idxn = "";
            }
            return $idxn;
        }, $rs);
        $idxs = array_filter($idxs, function($idxn) {return Is::nemstr($idxn);});
        $idxs = array_values($idxs);
        return $idxs;
    }



    /**
     * tools
     */

    /**
     * 自动补全 sqlite 数据库文件后缀名
     * 如果定义了 DB_EXT 则使用常量 作为后缀名，
     * 如果未定义常量，则使用 static::$ext 作为后缀名
     * @param String $dbf 数据库文件路径
     * @return String|null
     */
    public static function autoSuffix($dbf)
    {
        if (!Is::nemstr($dbf)) return null;
        //确认 数据库文件后缀名
        $ext = defined("DB_EXT") ? DB_EXT : static::$ext;
        //数据库文件路径信息
        $pi = pathinfo($dbf);
        $cext = $pi["extension"] ?? "";
        if (".".strtolower($cext) !== $ext) {
            //如果给出的 dbf 中不包含后缀名，则添加后缀名
            $dbf .= $ext;
        }
        return $dbf;
    }

}