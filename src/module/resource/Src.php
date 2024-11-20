<?php
/**
 * cgyio/resper 模块 respond 响应类(路由)
 * 响应 URI 
 *      [host]/src/...
 */

namespace Cgy\module\resource;

use Cgy\Resper;
use Cgy\Response;
use Cgy\module\Resource;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;

class Src extends Resper 
{
    /**
     * 覆盖 responder 基类中定义的属性
     */
    public $intr = "资源输出";       //responder 说明，子类覆盖
    public $name = "Src";           //responder 名称，子类覆盖
    public $key = "module/resource/Src";   //responder 调用路径

    /**
     * 此 响应者 是否不受 WEB_PAUSE 设置影响
     * !! 子类覆盖
     * == true 则 WEB_PAUSE==true 时，此响应者依然可以响应 request 并输出结果
     */
    public $unpause = true;     //资源输出不受 WEB_PAUSE 影响

    

    /**
     * !! 覆盖 Responder 基类 default 方法
     * 资源输出统一入口
     * 调用方式：
     *      https://[host]/src/...
     * 
     * @param Array $args 传入的 URI 数组
     * @return Mixed
     */
    public function default(...$args)
    {
        if (!Is::nemarr($args)) {
            Response::code(404);
        }

        //var_dump(Resper::$config->ctx());
        trigger_error("res/miss::foo/bar", E_USER_ERROR);

        //检查 预定义的路径别名
        $conf = $this->conf;
        var_dump($conf);
        $alias = $conf["alias"] ?? [];
        if (isset($alias[strtolower($args[0])])) {
            $args[0] = $alias[strtolower($args[0])];
        }

        //资源查询路径
        $path = implode("/", $args);
        $path = trim(Str::replace([DS, "\\"], "/", $path), "/");
        //var_dump($path);
        return $path;

        //查找资源 创建 Resource 实例
        //$resource = 

    }


    
}