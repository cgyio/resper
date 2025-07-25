<?php
/**
 * resper 框架中间件 基类
 */

namespace Cgy;

use Cgy\Resper;
use Cgy\Request;
use Cgy\Response;
use Cgy\Log;

abstract class Middleware 
{
    /**
     * 依赖项
     */
    //当前 resper 响应者实例
    protected $resper = null;
    //当前 Request 请求实例
    protected $request = null;
    //当前 Response 响应实例
    protected $response = null;

    /**
     * 构造
     */
    public function __construct(Resper $resper)
    {
        $this->resper = $resper;
        //获取 依赖的 当前 Request/Response 请求/响应 实例
        $this->request = Request::$current;
        /**
         * 不能使用 Response::current() 获取响应实例
         * 在 入站中间件中，因为 Response 响应实例还未创建，而使用 current() 方法会创建响应实例
         * 这会导致 入站中间件还未全部处理完之前 就创建了响应实例
         * !! 因此 必须使用 Response::$current 来获取响应实例，如果相应未创建，只会获取到 null 而不会创建响应实例
         */
        $this->response = Response::$current;
    }

    /**
     * 获取此中间件 在 响应者实例的 config 实例中，缓存的 参数
     * 这些参数已经过 此中间件的 initConf 方法处理过，因此在 handle 方法中，不再对参数进行校验
     * !! 如果有需要，子类可覆盖这个方法
     * @return Array|null
     */
    public function getConf()
    {
        //获取此中间件的类全称
        $clsn = get_class($this);
        //保存在 当前响应者 config 实例中的 中间件参数
        $midc = $this->resper->conf["middleware"];
        $conf = $midc[$clsn] ?? null;
        return $conf;
    }

    /**
     * !! 子类必须实现
     * 中间件处理核心方法
     * 根据 入站/出站 类型，中间件自行处理，并将处理结果自行保存到对应的 Request/Response 实例中
     * !! 此方法不需要参数，返回布尔值，当返回 false 时，立即终止响应
     * @return Bool
     */
    abstract public function handle();

    /**
     * 中间件终止响应方法
     * !! 子类可覆盖，实现自有的终止响应方法
     * @return void
     */
    public function exit()
    {
        //入站中间件内部 不存在响应实例，创建
        if (empty($this->response)) $this->response = Response::current();

        //记录日志
        Log::error("中间件验证失败");

        //默认终止方法为 返回 404
        Response::code(404);
        exit;
    }

    /**
     * 静态方法，此中间件 预设参数的 初始化处理
     * 应在响应者类参数初始化阶段，通过 不同类型 响应者的 config 类实例，调用此方法，对预设的 中间件参数执行相应初始化处理
     * 处理后的 中间件 参数，应返回 响应者的 config 类实例中，并写入 context，然后 缓存
     * !! 子类可覆盖此方法，执行各自不同的 中间件预设参数处理逻辑
     * @param Array $conf 预设的中间件参数
     * @return Array 初始化处理后 得到的最终 中间件参数，将 写回 $resper->config->context 中
     */
    public static function initConf($conf=[])
    {
        //子类实现 预设参数的初始化处理逻辑
        //...

        //返回处理后的 conf
        return $conf;
    }

}