<?php
/**
 * cgyio/resper 核心类
 * Resper 响应者 默认响应方法
 * Resper 类继承自此类
 */

namespace Cgy;

//use Cgy\Resper;
//use Cgy\resper\Seeker;
use Cgy\App;
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
        trigger_error("custom::error test", E_USER_ERROR);
        exit;
        //默认方法 返回未找到 Resper 实例的预设页面
        $pg = RESPER_PATH.DS."page".DS."not-found.php";
        if (file_exists($pg)) {
            Response::page($pg, [
                "resper" => $this
            ]);
        }
        Response::code(404);
    }

    /**
     * 响应 空 URI
     * !! 子类可覆盖
     * @return Mixed
     */
    public function empty()
    {
        //URI 为空时执行
        if ($this->type == "App") {
            //如果响应者是 App 则查找路径下 page/index.php
            $pg = $this->path("page/index.php");
            if (file_exists($pg)) {
                Response::page($pg, [
                    "resper" => $this
                ]);
            }
        }

        Response::code(404);
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