<?php
/**
 * resper 框架 Resper config 类
 * 自定义 Resper 响应者类 应使用此类作为 参数配置器
 */

namespace Cgy\module\configer;

use Cgy\Resper;
use Cgy\module\configer\BaseConfig;
use Cgy\orm\Driver;
use Cgy\orm\Model;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Is;
use Cgy\util\Path;
use Cgy\util\Cls;

class ResperConfig extends BaseConfig 
{
    /**
     * 定义统一的 Resper 类预设数据结构
     * !! 子类可以覆盖
     */
    protected $init = [
        
    ];

    //此 config 类 关联的 自定义 Resper 类型响应者 类全称
    protected $rcls = null;



    /**
     * 构造
     * !! 覆盖父类
     * @param Array $opt 输入的设置参数
     * @param String $resperName 响应者类名称，自定义 Resper 类型响应者配置初始化时，需要传入响应者名称，类名 或 类文件名
     * @return void
     */
    public function __construct($opt=[], $resperName=null)
    {
        if (Is::nemstr($resperName)) {
            //指定了 Resper 类型 响应者类的 类名 或 类文件名
            $rn = Str::camel($resperName, true);    //统一转为类名 驼峰，首字母大写
            //生成类全称
            $this->rcls = Cls::find($rn);
        }

        //应用用户设置
        $this->setConf($opt);
    }

    /**
     * 在 应用用户设置后 执行
     * !! 子类可覆盖
     * @return $this
     */
    public function afterSetConf()
    {

        return $this;
    }

}