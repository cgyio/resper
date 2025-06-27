<?php
/**
 * resper 框架 App config 基类
 */

namespace Cgy\module\configer;

use Cgy\Resper;
use Cgy\module\Configer;
use Cgy\orm\Driver;
use Cgy\orm\Model;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Is;
use Cgy\util\Path;

class AppConfig extends Configer 
{
    /**
     * 定义统一的 App 应用类预设数据结构
     * !! App 子类可以覆盖
     */
    protected $init = [
        "name" => "App应用类名称",

        //App 数据库参数
        "orm" => [
            /**
             * !! 必须的
             */
            //是否启用 orm
            "enable" => true,
            //数据库类型
            "type" => "sqlite",
            
            /**
             * 数据库相关路径参数，数据库配置文件/模型文件 所在路径
             * 开发阶段 或 使用MySql数据库 数据库文件可能不存在，因此以数据库配置文件作为数据库存在的依据
             * 如果在 dirs 路径下存在 config/Foo.json 则表示存在数据库 Foo
             */
            "dirs" => "root/app/qypms/db",  //必须使用绝对路径
            "models" => "root/model/pms",   //必须使用绝对路径

            /**
             * !! mysql 数据库 必须的设置
             * Medoo 库 连接 MySql 的参数
             */
            "mysql" => [
                "host" => "127.0.0.1",
                "port" => 3306,
                "database" => "",
                "username" => "",
                "password" => "",

                "charset" => "utf8mb4",                 //字符集
	            "collation" => "utf8mb4_general_ci",    //排序方式

                //更多参数 参考 Medoo 库
                //...
            ],

            /**
             * !! 可选的
             */
            //默认必须加载的数据库模型(表) 名称
            "required" => [
                //"usr"
            ],
        ],
    ];

    /**
     * 在 应用用户设置后 执行
     * !! 子类可覆盖
     * @return $this
     */
    public function afterSetConf()
    {
        /**
         * 自动处理 App 应用类设置参数
         */

        //处理 orm 参数 $this->init["orm"]
        $this->initOrmConf();

        return $this;
    }

    /**
     * 处理各预设参数项 内容
     */
    //处理 $this->init["orm"]
    protected function initOrmConf() 
    {
        $ctx = $this->context;
        $ormc = $ctx["orm"];
        //如果未指定 orm 参数，直接返回
        if (!Is::nemarr($ormc)) return $this;
        $enable = $ormc["enable"] ?? false;
        //如果未启用 orm，直接返回
        if (!is_bool($enable) || $enable!==true) return $this;

        //取得 dbtype
        $dbtp = $ormc["type"] ?? (defined("DB_TYPE") ? DB_TYPE : "sqlite");

        /**
         * 根据 dbtype 数据库类型，计算数据库参数
         */
        //var_dump("Driver::initOrmConf()");
        //var_dump($ormc);
        $driver = Driver::support($dbtp);
        if ($driver===false) {
            //不支持此类型数据库，直接返回
            return $this;
        }
        $ormc["type"] = $dbtp;
        //调用数据库模型的 initOrmConf() 方法，得到数据库参数 [path=>'数据库文件参数', dbns=>[可用数据库名称数组], ...]
        $ormc = Arr::extend($ormc, $driver::initOrmConf($ormc));
        //var_dump($ormc);

        /**
         * 处理预设的 model 模型文件路径，得到模型相关参数
         */
        $ormc["model"] = Model::initOrmConf($ormc);
        //var_dump($ormc["model"]);
        
        //将解析后的参数写入 $this->context
        $this->context["orm"] = $ormc;

        return $this;
    }

}