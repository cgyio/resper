<?php
/**
 * cgyio/resper 数据库类型 驱动 基类
 */

namespace Cgy\orm;

use Cgy\orm\Db;
use Cgy\util\Is;
use Cgy\util\Path;
use Cgy\util\Cls;
use Cgy\util\Conv;

class Driver extends Db
{
    //数据库配置文件后缀名
    public static $confExt = ".json";


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

        return [
            "path" => "",
            "dbns" => []
        ];
    }

    /**
     * 根据 Orm 参数，创建每个 Db 的 Medoo 连接参数
     * @param Array $conf Orm 参数
     * @return Array Medoo 连接参数 [ dbn => [ type=>"", database=>"", host=>"", ... ], ... ]
     */
    public static function initMedooParams($conf = [])
    {

        return [];
    }

    /**
     * 创建某个数据库 key
     * @param Array $opt Medoo 连接参数
     * @return String DB_KEY 
     */
    public static function dbkey($opt = [])
    {

        return "";
    }
    
    /**
     * 数据库连接方法
     * @param Array $opt medoo 连接参数
     * @return Db 数据库实例
     */
    public static function connect($opt=[])
    {
        //... 子类实现

        return new Db();
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
     *              rs => [] 重建数据表时，如果传入 原数据记录，则在重建表后，批量写入这些数据
     *          ]
     *          ...
     *      ],
     *      ... 其他参数
     *  ]
     * @return Bool
     */
    public static function create($dbkey, $opt=[])
    {
        if (!Is::nemstr($dbkey) || !isset(Orm::$DB[$dbkey])) return false;
        $db = Orm::$DB[$dbkey];

        //... 子类实现

        return true;
    }



    /**
     * 静态方法
     */

    /**
     * 解析 orm 参数中的 dirs 得到真实存在的 数据库路径
     * @param String $dirs 在预设中指定的 orm 参数中的 dirs 数据库路径
     * @return Mixed 路径解析成功 则返回真实路径 否则返回 null
     */
    public static function parseDirs($dirs)
    {
        if (!Is::nemstr($dirs)) return null;
        $dirs = explode(",", trim($dirs, ","));
        $dbp = Path::exists($dirs, [
            "checkDir" => true,
            "all" => false
        ]);
        if (empty($dbp)) $dbp = Path::find("root/library/db", ["checkDir"=>true]);
        if (empty($dbp)) return null;
        return $dbp;
    }

    /**
     * 在指定的 db 路径下，查找可用的 数据库列表
     * 在开发阶段 或 使用 MySql 数据库 情况下，可能不存在数据库文件
     * 因此通过检查 数据库配置文件 来确定 数据库列表
     * 数据库配置文件应保存在 [dbpath]/config/Dbn.json
     * 
     * @param String $path 参数中指定的 数据库路径
     * @param String $dbtype 不同的数据库类型 sqlite/mysql 
     * @return Array [ db1, db2, ... ]
     */
    public static function findDbnsIn($path, $dbtype="sqlite")
    {
        if (!Is::nemstr($path) || !is_dir($path)) return [];
        $dbns = [];
        $confp = $path.DS."config";
        $cfext = static::$confExt;
        if (!is_dir($confp)) return [];
        //输入的数据库类型
        if (!Is::nemstr($dbtype)) $dbtype = "sqlite";
        $dbtln = strlen($dbtype);
        //查找路径下 可能存在的 配置文件
        $ph = @opendir($confp);
        while(false !== ($dbn = readdir($ph))) {
            if (in_array($dbn, [".",".."]) || is_dir($confp.DS.$dbn) || strpos($dbn, $cfext)===false) continue;
            //确定数据库配置文件中指定的 数据库类型 与 传入的 数据库类型一致
            $cf = $confp.DS.$dbn;
            $cfa = file_get_contents($cf);
            $cfa = Conv::j2a($cfa);
            $dsn = $cfa["dsn"] ?? null;
            if (!Is::nemstr($dsn) || substr($dsn, 0, $dbtln)!=$dbtype) continue;
            //保存到 dbns
            $dbn = str_replace($cfext,"",$dbn);
            if (!in_array($dbn, $dbns)) $dbns[] = $dbn;
        }
        @closedir($ph);
        //返回找到的 数据库列表
        return $dbns;
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
        //... 子类实现

        return [];
    }



    /**
     * 通用方法
     */
    //判断是否支持某种类型的数据库，支持则返回 类全称，不支持则返回 false
    public static function support($dbtype)
    {
        if (!Is::nemstr($dbtype)) return false;
        $driver = Cls::find("orm/driver/".$dbtype);
        if (Is::nemstr($driver) && class_exists($driver)) return $driver;
        return false;
    }
}