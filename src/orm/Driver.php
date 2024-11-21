<?php
/**
 * cgyio/resper 数据库类型 驱动 基类
 */

namespace Cgy\orm;

use Cgy\orm\Db;

class Driver extends Db
{
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
}