<?php
/**
 * cgyio/resper 数据库类型 驱动 基类
 */

namespace Cgy\orm;

use Cgy\orm\Db;
use Cgy\util\Is;
use Cgy\util\Cls;

class Driver extends Db
{
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
     * @param Array $opt 数据库创建参数
     * @return Bool
     */
    public static function create($opt=[])
    {
        //... 子类实现

        return true;
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