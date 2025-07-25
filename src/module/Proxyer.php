<?php
/**
 * resper 框架 响应代理类 基类
 * 当 指定的响应方法为 uac|db|... 时，
 * 使用此类 来代理 响应者作出响应
 * 
 * 通常用于一些通用的 操作方法，例如：
 * 
 *      用户请求数据库相关操作时，url = https://[host]/[resper]/db/[dbn]/[tbn]/foo
 *          由 module/proxyer/OrmProxyer 类代理执行 response 响应方法
 * 
 *      用户请求 uac 相关操作时，url = https://[host]/[resper]/uac/foo
 *          由 module/proxyer/UacProxyer 类代理执行 response 响应方法
 * 
 * !! 通过此代理类执行请求的响应方法时，Resper|Request|Response 实例都已创建，因此可在此类内部直接访问并操作这些实例
 */

namespace Cgy\module;

use Cgy\Resper;
use Cgy\Request;
use Cgy\Response;
use Cgy\Uac;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;

abstract class Proxyer 
{
    /**
     * 依赖项
     */
    public $resper = null;
    public $request = null;
    public $response = null;
    public $uac = null;
    /**
     * !! 子类可增加的其他 依赖项
     */
    //protected $orm = null;
    //...

    /**
     * 传入的 URI 参数序列
     */
    protected $uri = [];

    /**
     * input 传入的 post 数据
     */
    protected $post = [];

    /**
     * 解析得到的 此类中的响应方法 和 操作标识
     */
    protected $responseMethod = [];
    protected $operate = "";

    /**
     * 构造
     * @param Array $args 传入的 URI 参数序列
     * @return void
     */
    public function __construct(...$args)
    {
        //注入通用依赖项，这些依赖的核心单例都 必须 已创建
        $this->resper = Resper::$resper;
        $this->request = Request::$current;
        $this->response = Response::$current;
        if (
            !$this->resper instanceof Resper ||
            !$this->request instanceof Request ||
            !$this->response instanceof Response
        ) {
            return null;
        }

        //缓存 uac 实例
        if ($this->hasUac()===true) $this->uac = $this->resper->uac;

        //记录 传入的 URI 序列
        $this->uri = $args;

        //缓存 input 传入的 post 数据
        $this->post = $this->request->inputs->json;

        //执行子类实现的后续初始化方法
        $this->initProxyer();

        //执行子类实现的 响应方法 和 操作标识 解析方法
        $this->getProxyMethod();

    }

    /**
     * 后续 自定义初始化方法
     * !! 子类必须实现
     * @return $this
     */
    abstract protected function initProxyer();

    /**
     * 根据传入的 URI 参数，解析对应的 此类中的 响应方法 和 操作标识
     * 缓存到对应属性中
     * 例如：https://[host]/[resper]/db/[dbn]/[tbn]/create 解析得到：
     *      $this->responseMethod = [$this->model, "create"],
     *      $this->operate = "db/[dbn]/[tbn]:create"
     * !! 子类必须实现
     * @return $this
     * 
     */
    abstract protected function getProxyMethod();

    /**
     * 如果当前响应者开启了 uac，且解析得到了 proxy 代理响应 操作标识，则进行 权限检查
     * !! 子类可覆盖此方法
     * @return Array 标准的权限验证返回结果 Uac::rtn(true|false, [ 拒绝的操作列表... ], "说明...")
     */
    protected function proxyUacVerify()
    {
        if ($this->hasUac()!==true) {
            //未启用 uac 直接通过
            return Uac::rtn(true);
        }

        //解析得到的 操作标识
        $opr = $this->operate;
        if (is_bool($opr)) {
            //操作标识 == true|false 直接返回
            return Uac::rtn($opr);
        }
        if (!Is::nemstr($opr)) {
            //没有解析出 操作标识，拒绝操作
            return Uac::rtn(false, [], "非法操作");
        }

        //调用 Uac::granted(...) 快速判断权限
        return Uac::granted($opr);
    }

    /**
     * 具体的 response 响应方法实现
     * !! 子类可覆盖此方法，必须实现权限控制相关逻辑
     * @return Mixed 响应方法的结果 将被 setData 到 response 实例中
     */
    public function response()
    {
        if ($this->hasUac()===true) {
            //启用了 uac 执行权限验证
            $ac = $this->proxyUacVerify();
            if (!isset($ac["granted"]) || $ac["granted"]!==true) {
                //权限拒绝，报错
                trigger_error("uac/denied::".$ac["msg"], E_USER_ERROR);
            }
        }

        //权限验证通过，执行具体响应方法
        $marr = $this->responseMethod;
        if (!Is::nemarr($marr)) {
            //没有有效的响应方法，直接返回空数据
            return null;
        } else {
            //使用 call_user_func_array
            //var_dump($marr[0]);
            //var_dump($marr[1]);
            //var_dump($this->uri);
            return call_user_func_array($marr, $this->uri);
        }

    }


    
    /**
     * tools
     */
    
    /**
     * 判断当前响应者是否启用了 uac 且 已实例化
     * @return Bool
     */
    protected function hasUac() { return Uac::on()===true && $this->resper->uac instanceof Uac; }
}