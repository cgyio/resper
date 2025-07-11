<?php
/**
 * cgyio/resper 类型数据库 驱动
 * Mysql 数据库驱动
 */

namespace Cgy\orm\driver;

use Cgy\Orm;
use Cgy\orm\Db;
use Cgy\orm\Driver;
use Cgy\orm\Config;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Path;
use Medoo\Medoo;

class Mysql extends Driver 
{
    /**
     * 缓存当前连接到的 MySql 服务器参数
     */
    protected $mysqlServer = [
        "type" => "mysql",
        "host" => "",
        "port" => 3306,
        "username" => "",
        "password" => "",
    ];


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
            "dbns" => [],
            "mysql" => [
                "host" => "127.0.0.1",
                "port" => 3306,
                "username" => "",
                "password" => "",
            ]
        ];

        //解析数据库路径预设
        $dirs = $conf["dirs"] ?? DIR_DB;
        $dbp = static::parseDirs($dirs);
        if (empty($dbp))  {
            //指定的数据库配置文件路径不存在，报错
            trigger_error("orm/fatal::指定的数据库路径不存在，DIRS = ".implode(", ",$dirs), E_USER_ERROR);
        }
        $ormc["path"] = Path::fix($dbp);
        //查询数据库列表
        $dbns = static::findDbnsIn($ormc["path"], "mysql");
        $ormc["dbns"] = $dbns;

        //获取 mysql 参数
        $mysql = $conf["mysql"] ?? [];
        $usr = $mysql["username"] ?? null;
        $pwd = $mysql["password"] ?? null;
        if(!Is::nemarr($mysql) || !Is::nemstr($usr)||!Is::nemstr($pwd)) {
            //缺少 mysql 参数，报错
            trigger_error("orm/fatal::未指定数据库连接参数", E_USER_ERROR);
        }
        $ormc["mysql"] = Arr::extend($ormc["mysql"], $mysql);
        if (isset($mysql["database"])) {
            unset($ormc["mysql"]["database"]);
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
        $mysql = $conf["mysql"];

        //创建 Medoo 连接参数
        $medoo = [];
        foreach ($dns as $i => $dbn) {
            $medoo[$dbn] = Arr::extend($mysql, [
                "type" => "mysql",
                "database" => $dbn,
                //缓存 path 参数到 medoo 连接参数中
                "_path" => $dbp,
            ]);
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
        //db-key
        $dbkey = static::dbkey($opt);
        if (!Is::nemstr($dbkey)) return null;
        //检查是否存在缓存的数据库实例
        if (isset(Orm::$DB[$dbkey]) && Orm::$DB[$dbkey] instanceof Db) {
            return Orm::$DB[$dbkey];
        }

        /**
         * 确认要连接的数据库存在（不存在将自动创建）
         * 尝试连接一个 MySqlServer 中不存在的数据库将报错
         */
        $exi = self::ensureDbExists($opt);
        if ($exi!==true) {
            //数据库创建失败，报错
            trigger_error("orm/fatal::无法连接数据库 [".$opt["database"]."]，数据库不存在", E_USER_ERROR);
        }

        //创建数据库实例
        $driver = static::class;
        $db = new $driver($opt);
        //写入参数
        $dbp = $opt["_path"];
        $dbn = $opt["database"];
        $db->type = "mysql";
        $db->name = $dbn;
        $db->key = $dbkey;
        $db->pathinfo = pathinfo($dbp);
        $db->driver = $driver;
        //缓存 MySql Server 连接参数
        $db->cacheMySqlServer($opt);
        //缓存
        Orm::$DB[$dbkey] = $db;
        //解析数据库参数文件
        $db->config = new Config([
            "type" => "mysql",
            //"database" => $dbf,
            "dbkey" => $dbkey,
            //设置文件保存在 db_path/config/db_name.json
            "conf" => $dbp.DS."config".DS.$dbn.self::$confExt
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
        if (!Is::nemstr($dbt) || $dbt!="mysql") return false;
        $mds = $opt["models"]; unset($opt["models"]);
        if (!Is::nemarr($mds)) return false;

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
            if ($exi===true) {
                //存在现有表，先 drop table
                $sqlDrop = "DROP TABLE IF EXISTS `".$mdn."`";
                //$db->medoo("query", $sqlDrop);
                $sql[$mdn][] = $sqlDrop;
            }

            //开始创建表
            //创建字段 语句
            $sqlCreate = [];
            foreach ($mdc["creation"] as $col => $colc) {
                /**
                 * 处理 mysql 与 sqlite 语法区别
                 *      mysql               sqlite
                 *      AUTO_INCREMENT  --> AUTOINCREMENT 
                 *      varchar(255)    --> varchar
                 */
                if (strpos($colc,"AUTOINCREMENT")!==false) {
                    $colc = str_replace("AUTOINCREMENT", "AUTO_INCREMENT", $colc);
                }
                if (strpos($colc, "varchar")!==false) {
                    $colc = str_replace("varchar", "varchar(255)", $colc);
                }
                
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

        //return $sql;

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
     * static tools
     */

    //根据 某个数据库的 medoo 连接参数中 获取 数据库配置文件路径
    public static function getDbPath($opt=[])
    {
        $database = $opt["database"] ?? null;
        $path = $opt["_path"] ?? null;
        if (!Is::nemstr($database) || !Is::nemstr($path)) return null;
        $cf = $path.DS."config".DS.$database.static::$confExt;
        if (!file_exists($cf)) return null;
        return Path::fix($cf);
    }

    /**
     * 根据 Medoo 连接参数判断是否存在数据库
     * 如果不存在则创建
     * @param Array $opt Medoo 连接参数
     * @return Bool
     */
    protected static function ensureDbExists($opt=[])
    {
        $dbn = $opt["database"] ?? null;
        if (!Is::nemstr($dbn)) return false;
        $tdb = self::connectMySql($opt, "information_schema");
        if (empty($tdb)) return false;
        //检查数据库是否存在
        $exi = $tdb->has("SCHEMATA", [
            "SCHEMA_NAME" => $dbn
        ]);
        $tdb = null;
        //存在则返回
        if ($exi) return true;
        //不存在则创建
        $tdb = self::connectMySql($opt, null,[
            "charset" => "utf8mb4",
            //"error" => Medoo::ERROR_EXCEPTION
        ]);

        try {
            $tdb->query("CREATE DATABASE IF NOT EXISTS `".$dbn."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $tdb = null;
            return true;
        } catch (\Exception $e) {
            //创建数据库失败，报错
            trigger_error("orm/fatal::无法创建数据库 [".$dbn."]，可能的原因：".$e->getMessage(), E_USER_ERROR);
            return false;
        }
    }

    /**
     * 连接指定数据库
     * @param Array $opt Medoo 连接参数
     * @param String $dbn 指定数据库名称
     * @param Array $extra 自定义额外的 Medoo 连接参数
     * @return Medoo 实例
     */
    protected static function connectMySql($opt=[], $dbn=null, $extra=[])
    {
        if (!Is::nemarr($opt)) return null;
        $host = $opt["host"] ?? null;
        $port = $opt["port"] ?? 3306;
        $usr = $opt["username"] ?? null;
        $pwd = $opt["password"] ?? null;
        if (!Is::nemstr($host) || !Is::nemstr($usr) || !Is::nemstr($pwd)) return null;
        //测试连接
        $copt = [
            "type" => "mysql",
            "host" => $host,
            "port" => $port,
            "username" => $usr,
            "password" => $pwd
        ];
        if (Is::nemstr($dbn)) {
            $copt["database"] = $dbn;
        }
        if (Is::nemarr($extra)) {
            $copt = Arr::extend($copt, $extra);
        }

        try {
            return new Medoo($copt);
        } catch (\Exception $e) {
            return null;
        }
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
            "SHOW TABLES"
        )->fetchAll();
        $tbs = array_map(function($i) {
            if (Is::nemstr($i)) {
                $tbn = $i;
            } else if (Is::nemarr($i)) {
                $tbn = $i[0];
            } else {
                $tbn = "";
            }
            return $tbn;
        }, $rs);
        $tbs = array_filter($tbs, function($i) {
            return Is::nemstr($i);
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
            "SHOW INDEX FROM `".$tbn."`"
        )->fetchAll();
        return $rs;
        /*$idxs = array_map(function($i) {
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
        return $idxs;*/
    }

    /**
     * 缓存当前连接到的 MySql Server 连接参数 到 数据库实例
     * @param Array $opt Medoo 连接参数
     * @return Bool
     */
    public function cacheMySqlServer($opt=[])
    {
        $ks = ["host","port","username","password"];
        if (!Is::nemarr($opt)) return false;
        foreach ($ks as $ki) {
            if (isset($opt[$ki])) {
                $this->mysqlServer[$ki] = $opt[$ki];
            }
        }
        return true;
    }

    /**
     * 判断当前数据库是否已存在于 MySqlServer 中
     * @return Bool
     */
    /*public function existsInMySqlServer()
    {
        $dbn = $this->name;
        $db = self::connectMySql($this->mysqlServer, "information_schema");
        return $db->has("SCHEMATA", [
            "SCHEMA_NAME" => $dbn
        ]);
    }*/



    

}