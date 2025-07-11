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
use Cgy\util\Path;
use Medoo\Medoo;

class Sqlite extends Driver 
{
    //数据库文件后缀名
    public static $ext = ".db";

    /**
     * !! 必须实现 !!
     */
    
    /**
     * 根据 Orm 参数，解析获取数据库相关的必须参数
     * @param Array $conf Orm 参数
     * @return Array 数据库路径，文件地址等必要参数 [ path=>'', dbns=>[ 路径下所有可用数据库 名称数组 ], ... ]
     */
    public static function initOrmConf($conf = [])
    {
        $ormc = [
            "path" => "",
            "dbns" => []
        ];

        //解析数据库路径预设
        $dirs = $conf["dirs"] ?? DIR_DB;
        $dbp = static::parseDirs($dirs);
        if (!empty($dbp)) {
            $ormc["path"] = Path::fix($dbp);
            //查询数据库列表
            $dbns = static::findDbnsIn($ormc["path"], "sqlite");
            $ormc["dbns"] = $dbns;
        } else {
            //指定的数据库文件路径不存在，报错
            trigger_error("orm/fatal::指定的数据库路径不存在，DIRS = ".implode(", ",$dirs), E_USER_ERROR);
        }

        return $ormc;
    }

    /**
     * 根据 Orm 参数，创建每个 Db 的 Medoo 连接参数
     * @param Array $conf Orm 参数
     * @return Array Medoo 连接参数 [ dbn => [ type=>"", database=>"", host=>"", ... ], ... ]
     */
    public static function initMedooParams($conf = [])
    {
        $dbp = $conf["path"];
        $dns = $conf["dbns"];

        //创建 Medoo 连接参数
        $medoo = [];
        foreach ($dns as $i => $dbn) {
            $medoo[$dbn] = [
                "type" => "sqlite",
                "database" => $dbp.DS.$dbn.self::$ext
            ];
        }

        return $medoo;
    }

    /**
     * 创建某个数据库 key
     * @param Array $opt Medoo 连接参数
     * @return String DB_KEY 
     */
    public static function dbkey($opt = [])
    {
        $dbf = self::getDbPath($opt);
        if (!Is::nemstr($dbf)) return null;
        return "DB_".md5($dbf);
    }
    
    /**
     * 数据库连接方法
     * @param Array $opt medoo 连接参数
     * @return Dbo 数据库实例
     */
    public static function connect($opt=[])
    {
        //数据库文件
        $dbf = self::getDbPath($opt);
        //var_dump($dbf);
        if (!Is::nemstr($dbf)) return null;
        $pathinfo = pathinfo($dbf);
        $dbname = $pathinfo["filename"];
        $dbkey = self::dbkey($opt);
        //检查是否存在缓存的数据库实例
        if (isset(Orm::$DB[$dbkey]) && Orm::$DB[$dbkey] instanceof Db) {
            return Orm::$DB[$dbkey];
        }
        //创建数据库实例
        $driver = static::class;
        $db = new $driver([
            "type" => "sqlite",
            "database" => $dbf
        ]);
        //写入参数
        $db->type = "sqlite";
        $db->name = $dbname;
        $db->key = $dbkey;
        $db->pathinfo = $pathinfo;
        $db->driver = $driver;
        //缓存
        Orm::$DB[$dbkey] = $db;
        //解析数据库参数文件
        $db->config = new Config([
            "type" => "sqlite",
            "database" => $dbf,
            "dbkey" => $dbkey,
            //设置文件保存在 db_path/config/db_name.json
            "conf" => $pathinfo["dirname"].DS."config".DS.$dbname.self::$confExt
        ]);
        
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

    //根据 连接参数中 获取 数据库文件路径
    public static function getDbPath($opt=[])
    {
        $database = $opt["database"] ?? null;
        if (!Is::nemstr($database) || !file_exists($database)) return null;
        return Path::fix($database);
    }

}