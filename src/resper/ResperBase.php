<?php
/**
 * cgyio/resper 核心类
 * Resper 响应者 默认响应方法
 * Resper 类继承自此类
 */

namespace Cgy;

//use Cgy\Resper;
//use Cgy\resper\Seeker;
use Cgy\Response;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Path;

class ResperBase
{
    /**
     * 响应者实例参数
     */
    
    /**
     * 响应类(路由类)信息
     * !! 子类必须覆盖
     */
    public $intr = "";  //resper 说明，子类覆盖
    public $name = "";  //resper 名称，子类覆盖
    public $key = "";   //resper 调用路径

    /**
     * 此 响应者类 是否需要 UAC 权限控制，
     * 如仅部分方法需要控制权限，设为 false，在需要控制权限的方法内部 if (Uac::grant("$app->key/method")===true) { 方法逻辑 }
     * 如所有方法都需要控制权限，设为 true
     * !! 子类覆盖
     */
    public $uac = false;

    /**
     * 此 响应者 是否不受 WEB_PAUSE 设置影响
     * !! 子类覆盖
     * == true 则 WEB_PAUSE==true 时，此响应者依然可以响应 request 并输出结果
     */
    public $unpause = false;

    

    /**
     * 默认的 预定义的 响应方法
     */

    /**
     * 未指定响应方法时 使用此方法
     * !! 子类可覆盖
     * @param Array $args 传入的 URI 数组
     * @return Mixed
     */
    public function default(...$args)
    {
        Response::dump($this->path);
        //trigger_error("php::这是错误说明，可以很长很长，for English path/foo/bar，Responder->path == ".$this->path, E_USER_ERROR);
        //Resper::foo();
        //Response::code(500);

        //return [
        //    "foo" => "bar",
        //    "tom" => "jaz"
        //];
    }

    /**
     * 响应 空 URI
     * !! 子类可覆盖
     * @return Mixed
     */
    public function empty()
    {
        var_export("Empty Respond Class");
        exit;
    }

    /**
     * 响应 Orm 数据库操作 请求
     * !! 子类 不要 覆盖 !!
     * @return Mixed
     */
    public function db(...$args)
    {
        if (empty($this->orm)) {
            trigger_error("orm::ORM 对象未初始化", E_USER_ERROR);
            return null;
        }

        //调用 Orm 实例的 response 方法，响应此请求
        return $this->orm->response(...$args);
    }

    /**
     * 解析 URI 最终返回错误
     * !! 子类 不要 覆盖 !!
     * @return Mixed
     */
    public function error()
    {
        var_export("Respond Error");
        exit;
    }

    
}