<?php
/**
 * resper 框架 日志处理
 * 使用 Monolog 库
 */

namespace Cgy;

use Cgy\Resper;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Path;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class Log 
{
    /**
     * 实例缓存
     */
    //响应者
    protected static $resper = null;
    //日志记录器
    protected static $logger = null;

    //是否启用日志功能
    protected static $enable = true;

    //可用的 Monolog 记录日志的实例方法
    protected static $ms = [
        //使用这些方法
        "debug", "info", "notice", "warning", "error",
        "critical", "alert", "emergency"
    ];


    /**
     * 工厂方法
     * 启动日志处理，返回 monolog 实例
     * @param Resper $resper 响应者实例
     * @return Logger 实例
     */
    public static function current($resper)
    {
        if (!$resper instanceof Resper) return null;
        //缓存 响应者类
        self::$resper = $resper;

        //读取参数
        $params = $resper->ctx; //$resper::$params
        $conf = $resper->conf;
        $log = $conf["log"] ?? [];
        $enable = $log["enable"] ?? true;   //默认启用 log
        $path = $log["path"] ?? null;
        $ext = $log["ext"] ?? ".log";
        $format = $log["format"] ?? "%datetime% | %channel% | %level_name% | %message% | %context% | %level%\n";
        $clsn = trim(get_class($resper), "\\");

        //缓存 启用状态
        self::$enable = $enable;

        //日志文件路径合法
        if (!Is::nemstr($path)) return null;
        $path = Path::find($path, ["checkDir"=>true]);
        if (empty($path)) return null;

        //日志记录器名称，请求响应方法路径 method/foo/bar)
        $resn = $params["method"]."/".implode("/", $params["uri"]);
        //var_dump($resn);

        //获取日志文件名
        $logfn = str_replace("\\","_",$clsn).$ext;
        //var_dump($logfn);
        //var_dump($path.DS.$logfn);

        //创建日志记录器
        $logger = new Logger($resn);
        //创建日志处理器，使用默认的 DEBUG 级别
        $handler = new StreamHandler($path.DS.$logfn, Logger::DEBUG);
        //设置日志样式，LineFormatter
        $formatter = new LineFormatter(
            $format,
            "Y-m-d H:i:s",  //日期格式
            false,          //是否允许消息内包含换行符
            true            //是否忽略空的 上下文/额外数据
        );
        //应用样式
        $handler->setFormatter($formatter);
        //应用处理器
        $logger->pushHandler($handler);
        //var_dump($logger);

        //缓存 logger
        self::$logger = $logger;

        //返回创建好的 Monolog 实例
        return $logger;

    }

    /**
     * __callStatic
     */
    public static function __callStatic($m, $args)
    {
        /**
         * 如果日志功能未启用，或缓存的实例不存在
         * 直接返回 null
         * 不报异常
         */
        if (self::$enable!==true || empty(self::$resper) || empty(self::$logger)) return null;
        $resper = self::$resper;
        $logger = self::$logger;
        $request = Request::$current;   //请求实例肯定存在
        $aud = Request::audience();     //获取请求来源信息

        /**
         * Log::error(...)  --> self::$logger->error(...)
         */
        if (in_array($m, self::$ms)) {
            //调用 $logger 实例方法
            $msg = $args[0] ?? "未指定消息";
            
            //上下文
            $ctx = [
                //请求来源
                "audience" => $aud,
            ];

            //如果启动 UAC 且用户信息存在，则自动添加用户信息到 上下文
            if (!empty($resper->uac) && $resper->uac->enable===true) {
                $uac = $resper->uac;
                if (!empty($uac->usr)) {
                    //使用 用户 Record 实例的输出方法
                    $ctx["usr"] = $uac->usr->ctx(
                        "uid:用户编码",
                        "name:用户名称"
                    );
                }
            }

            //添加手动指定的其他上下文内容
            if (count($args)>1 && Is::nemarr($args[1])) {
                $ctx = Arr::extend($ctx, $args[1]);
            }

            //输出 log
            return $logger->$m($msg, $ctx);
        }

        return null;
    }
}