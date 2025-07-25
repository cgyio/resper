<?php
/**
 * cgyio/resper 类型数据库 驱动
 * Mysql 数据库驱动
 */

namespace Cgy\orm\driver;

use Cgy\Orm;
use Cgy\orm\Db;
use Cgy\orm\Driver;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Path;
use Medoo\Medoo;

class Mysql extends Driver 
{
    /**
     * 通用的 mysql 连接参数
     */
    protected static $dftMedooParams = [
        "type" => "mysql",
        "host" => "127.0.0.1",
        "port" => 3306,
        "database" => "",
        "username" => "",
        "password" => "",

        "charset" => "utf8mb4",
        "collation" => "utf8mb4_general_ci",    //排序方式
    ];

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
            !Is::nemstr($type) || $type!=="mysql" ||
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

        /**
         * 确认要连接的数据库存在（不存在将自动创建）
         * 尝试连接一个 MySqlServer 中不存在的数据库将报错
         */
        $exi = self::ensureDbExists($medoo);
        if ($exi!==true) {
            //数据库创建失败，报错
            trigger_error("orm/fatal::无法连接数据库 [".$medoo["database"]."]，数据库不存在", E_USER_ERROR);
        }

        //创建数据库实例
        $db = new $driver($medoo);
        //写入参数
        $db->type = $type;
        $db->name = $dbn;
        $db->key = $dbkey;

        /**
         * mysql 类型 数据库路径信息
         * 根据 配置文件路径，应在其上一级目录，例如：
         * 配置文件：       app/foo/library/db/config/dbn.json 
         * 则路径信息应为：  pathinfo( app/foo/library/db/dbn.mysql )
         */
        $dbfarr = explode(DS, $conf);
        $dbfarr = array_slice($dbfarr, 0, -2);
        $dbfarr[] = $dbn.".mysql";
        $db->pathinfo = pathinfo(implode(DS, $dbfarr));

        $db->driver = $driver;
        //在 Db 数据库实例中 缓存 MySql Server 连接参数
        $db->cacheMySqlServer($medoo);
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
     * !! 覆盖父类
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
     * !! 覆盖父类
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
        if (!Is::nemarr($opt)) return false;
        foreach ($opt as $k => $v) {
            if (in_array($k, ["database"])) continue; 
            $this->mysqlServer[$k] = $v;
        }
        return true;
    }

    /**
     * 获取已缓存的 MySql Server 连接参数
     * @return Array
     */
    public function getMySqlServerParams()
    {
        $mss = $this->mysqlServer;
        $rtn = [];
        foreach ($mss as $k => $v) {
            if ($k=="password") {
                $rtn[$k] = "****";
            } else {
                $rtn[$k] = $v;
            }
        }
        return $rtn;
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