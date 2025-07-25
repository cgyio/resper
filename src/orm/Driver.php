<?php
/**
 * cgyio/resper 数据库类型 驱动 基类
 */

namespace Cgy\orm;

use Cgy\orm\Db;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Path;
use Cgy\util\Cls;
use Cgy\util\Conv;

class Driver extends Db
{

    /**
     * !! 必须实现 !!
     */

    /**
     * 数据库连接方法
     * @param Array $opt 数据库配置参数，由 orm/Config 类处理过的 保存在 Orm::$current->config->dbn 中
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
     * 实例方法
     */

    /**
     * 获取库中所有表 数组
     * !! 子类必须实现
     * @return Array [ 表名, ... ]
     */
    public function getTableNames()
    {
        //... 子类实现

        return [];
    }

    /**
     * 查看某个数据表的索引
     * !! 子类必须实现
     * @param String $tbn 表名
     * @return Array 
     */
    public function getTableIndexs($tbn)
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