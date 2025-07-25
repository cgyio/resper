<?php
/**
 * resper 框架 UAC 权限控制类 参数处理类
 */

namespace Cgy\uac;

use Cgy\module\Configer;

class Config extends Configer
{
    /**
     * 预设的设置参数
     * !! 子类自定义
     */
    protected $init = [
        
    ];

    //用户设置 origin 数据
    protected $opt = [
        # 数据结构应与 module/configer/BaseConfig 类中 $init["uac"] 属性 一致
    ];

    //经过处理后的 运行时参数
    protected $context = [];

    

    /**
     * 在 应用用户设置后 执行
     * !! 覆盖父类
     * @return $this
     */
    public function afterSetConf()
    {
        //子类可自定义方法
        //...

        return $this;
    }
}