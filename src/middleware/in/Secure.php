<?php
/**
 * resper 框架 中间件
 * 入站
 * 用户输入数据 过滤
 */

namespace Cgy\middleware\in;

use Cgy\Middleware;
use Cgy\util\Secure as utilSecure;

class Secure extends Middleware 
{
    /**
     * !! 必须实现
     * 中间件处理核心方法
     * 根据 入站/出站 类型，中间件自行处理，并将处理结果自行保存到对应的 Request/Response 实例中
     * !! 此方法不需要参数，返回布尔值，当返回 false 时，立即终止响应
     * @return Bool
     */
    public function handle() 
    {
        /*$gets = $this->request->gets->context;
        $posts = $this->request->posts->context;
        foreach ($gets as $k => $v) {
            $sec = utilSecure::str($v);
            $this->request->gets->context[$k] = $sec->context;
        }
        foreach ($posts as $k => $v) {
            $sec = utilSecure::str($v);
            $this->request->posts->context[$k] = $sec->context;
        }*/

        return true;
    }
}