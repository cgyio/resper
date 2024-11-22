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
    //设置文件后缀名
    public static $confExt = ".json";

    //默认 数据库文件 保存路径，默认 [webroot | app/appname]/db
    //public static $DBDIR = "db";



    /**
     * !! 必须实现 !!
     */

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
     * @param Array $opt 数据库创建参数
     *  [
     *      type => sqlite
     *      database => 数据库文件完整路径
     *      table => [
     *          表名 => [
     *              recreate => 是否重新创建(更新表结构)，默认 false
     *              fields => [ 字段名数组 ]
     *              creation => [
     *                  字段名 => SQL
     *              ]
     *          ]
     *          ...
     *      ]
     *  ]
     * @return Bool
     */
    public static function create($opt=[])
    {
        $dbt = $opt["type"] ?? null;
        $dbf = $opt["database"] ?? null;
        if (!is_notempty_str($dbt) || $dbt!="sqlite" || !is_notempty_str($dbf)) return false;
        $tbs = $opt["table"] ?? [];
        if (empty($tbs)) return false;
        if (!file_exists($dbf)) {
            //db 文件不存在则创建
            $fh = @fopen($dbf, "w");
            fclose($fh);
        }
        //medoo 连接
        $db = new Medoo([
            "type" => "sqlite",
            "database" => $dbf
        ]);
        //创建表
        foreach ($tbs as $tbn => $tbc) {
            $c = [];
            $fds = $tbc["fields"] ?? [];
            $cfd = $tbc["creation"] ?? [];
            for ($i=0;$i<count($fds);$i++) {
                $fdn = $fds[$i];
                if (!isset($cfd[$fdn])) continue;
                $c[] = "`".$fdn."` ".$cfd[$fdn];
            }
            $c = implode(",",$c);
            $sql = "CREATE TABLE IF NOT EXISTS `".$tbn."` (".$c.")";
            $db->query($sql);
        }
        return true;
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


        /*if (empty($database) || !is_notempty_str($database)) return null;
        //路径分隔符设为 DS
        $database = str_replace("/", DS, trim($database, "/"));
        //统一添加 后缀名
        if (strtolower(substr($database, strlen(self::$ext)*-1))!==self::$ext) $database .= self::$ext;
        //获取数据库路径
        $path = $opt["path"] ?? null;
        if (is_notempty_str($path)) {
            $path = path_find($path, ["checkDir"=>true]);
            if (empty($path)) {
                $path = self::dftDbDir();
            }
        } else {
            $path = self::dftDbDir();
        }
        //数据库文件
        $dbf = $path.DS."sqlite".DS.$database;
        return $dbf;*/
    }

    //获取默认数据库文件存放位置
    /*public static function dftDbDir()
    {
        return __DIR__.DS."..".DS."..".DS.trim(self::$DBDIR);
    }*/
}