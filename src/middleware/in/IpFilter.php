<?php
/**
 * resper 框架 中间件
 * 入站
 * ip 地址 过滤
 */

namespace Cgy\middleware\in;

use Cgy\Middleware;
use Cgy\Request;
use Cgy\Response;
use Cgy\Log;
use Cgy\util\Is;
use Cgy\util\Path;
use Cgy\util\Conv;

class IpFilter extends Middleware 
{
    /**
     * 根据 预定义的 $resper->conf["middleware"]["ip"] 参数，确定 过滤类型 以及 黑|白 名单
     */
    protected $filter = false;
    protected $iplist = [];
    //被验证的 ip
    protected $ip = "";

    /**
     * !! 必须实现
     * 中间件处理核心方法
     * 根据 入站/出站 类型，中间件自行处理，并将处理结果自行保存到对应的 Request/Response 实例中
     * !! 此方法不需要参数，返回布尔值，当返回 false 时，立即终止响应
     * @return Bool
     */
    public function handle() 
    {
        //获取参数
        $conf = $this->getConf();

        //如果未定义 IP地址过滤参数，直接通过
        if (!Is::nemarr($conf)) return true;
        
        //过滤类型 black|white|false
        $this->filter = $conf["filter"];

        //不启用过滤，直接跳过
        if ($this->filter === false) return true;

        //待过滤的 IP地址列表
        $this->iplist = $conf["list"];

        //中止值，根据过滤类型，决定 通过 或 拒绝
        $rtn = $this->filter === "black";

        //如果列表为空，根据过滤类型，决定 通过 或 拒绝
        if (!Is::nemarr($this->iplist)) return $rtn;

        //获取 Request 来源 ip
        $aud = Request::audience();
        $ip = $aud["ip"] ?? null;
        //var_dump($ip);
        //未获取有效的 请求来源 ip，根据过滤类型，决定 通过 或 拒绝
        if (!Is::nemstr($ip)) return $rtn;
        $this->ip = $ip;

        //判断 ip 是否通过过滤
        return in_array($ip, $this->iplist) ? $this->filter === "white" : $rtn;
    }

    /**
     * 中间件终止响应方法
     * !! 覆盖父类，实现自有的终止响应方法
     * @return void
     */
    public function exit()
    {
        //入站中间件内部 不存在响应实例，创建
        if (empty($this->response)) $this->response = Response::current();

        //记录日志
        Log::error("IP地址 ".$this->ip." 被阻止！因为".($this->filter==="black" ? "在黑名单中" : "不在白名单中"));

        //终止方法为 返回 404
        Response::code(404);
        exit;
    }

    /**
     * 静态方法，此中间件 预设参数的 初始化处理
     * 应在响应者类参数初始化阶段，通过 不同类型 响应者的 config 类实例，调用此方法，对预设的 中间件参数执行相应初始化处理
     * 处理后的 中间件 参数，应返回 响应者的 config 类实例中，并写入 context，然后缓存
     * !! 覆盖父类，对 ip地址过滤参数进行初始化处理，得到 ip 过滤列表
     * @param Array $conf 预设的中间件参数
     * @return Array 初始化处理后 得到的最终 中间件参数，将 写回 $resper->config->context 中
     */
    public static function initConf($conf=[])
    {
        //过滤类型 默认采用 黑名单
        $filter = $conf["filter"] ?? "black";

        //不启用过滤，直接 返回
        if ($filter === false) return $conf;

        //如果指定的 过滤类型不是 black|white 默认按 black 执行
        $filter = strtolower($filter);
        if (!Is::nemstr($filter) || !in_array($filter, ["black","white"])) $filter = "black";

        //过滤类型 写回 context
        $conf["filter"] = $filter;

        //准备 ip过滤列表 这个值将被缓存
        $conf["list"] = [];

        //获取配置文件
        $cf = $conf[$filter] ?? null;
        if (Is::nemstr($cf)) $cf = Path::find($cf);
        //尝试读取文件
        if (Is::nemstr($cf) && file_exists($cf)) {
            $ls = Conv::j2a(file_get_contents($cf));
            if (Is::nemarr($ls)) {
                //将读取到的 ip 列表 写回 context
                if (Is::indexed($ls)) {
                    $conf["list"] = $ls;
                } else if (Is::associate($ls)) {
                    $conf["list"] = array_keys($ls);
                }
            }
        }

        //返回处理后的 conf
        return $conf;
    }
}