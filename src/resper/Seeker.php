<?php
/**
 * cgyio/resper 核心类
 * Resper 响应者类 查找
 */

namespace Cgy\resper;

use Cgy\Resper;
use Cgy\resper\Responder;
use Cgy\request\Url;
//use Cgy\Response;
use Cgy\App;
use Cgy\Module;
use Cgy\Event;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Cls;

class Seeker 
{
    //本次会话的响应者
    public static $current = null;

    //本次会话调用的 响应者参数
    public static $params = [
        /*
        "responder" => 响应者 类全称
        "method"    => 响应方法 实例 public 方法
        "uri"       => 本次响应的 URI 参数数组 即 URI 路径
        */
    ];

    /**
     * !! Resper 核心方法
     */

    /**
     * !! 核心方法
     * 查找当前会话的 响应者
     * @return Responder 实例
     */
    public static function current()
    {
        $params = self::seek();
        self::$params = $params;
        $responderCls = $params["responder"];
        $responder = new $responderCls();
        $rtype = $responder->type;
        self::$current = $responder;

        /**
         * 触发 resper-created 事件
         */
        Event::trigger("responder-created", $responder, $responder->type);

        return $responder;
    }

    /**
     * !! 核心方法
     * 根据 Request::$current->url->path 查询响应此 request 的 类/方法
     * 可以响应的类：App类 / module类 / Responder类(route类)
     * @param Array $uri URI 路径，不指定则使用 url->path
     * @return Array 得到的目标 类，方法，uri参数，未找到 返回 null
     * 结构：[
     *          "responder" => 类全称 or 类实例
     *          "method"    => 响应方法，类实例的 public 方法
     *          "uri"       => 处理后，用作方法参数的 剩余 URI 路径数组
     *      ]
     */
    public static function seek($uri = [])
    {
        if (Is::nemstr($uri)) {
            //$uri = foo/bar/jaz 形式
            $uri = Arr::mk($uri);
        }
        if (!Is::nemarr($uri)) {
            $uri = Url::current()->path;
        }

        /**
         * 默认的 响应类 / 方法
         * 如果存在 app/index 则调用 \App\Index::empty()
         * 否则调用 \resper\Responder::empty() 
         * 
         */
        $responderCls = Resper::cls("resper/Responder");
        $responder = $responderCls;
        $method = "empty";

        /**
         * URI 为空，返回默认响应
         */
        if (empty($uri)) {
            if (false !== ($appcls = App::has("index"))) {
                //如果存在 app/index 则调用 \App\Index::empty()
                $responder = $appcls;
            }
            return [
                "responder" => $responder,
                "method"    => $method,
                "uri"       => []
            ];
        }

        /**
         *  1  判断是否存在 app / module 类
         */
        $cls = App::has($uri[0]);
        $ma = [];
        if ($cls !== false) {
            $responder = $cls;
            $ma = self::seekMethod($cls, array_slice($uri, 1));
        } else {
            $cls = Module::has($uri[0]);
            if ($cls !== false) {
                $responder = $cls;
                $ma = self::seekMethod($cls, array_slice($uri, 1));
            }
        }
        if (!empty($ma)) {
            return [
                "responder" => $responder,
                "method"    => $ma[0],
                "uri"       => $ma[1]
            ];
        }

        /**
         *  2  判断是否 resper\Responder 类 (相当于 route 类)
         */
        $rpd = Responder::has($uri[0]);
        if (false !== $rpd) {
            $responder = $rpd;
            $ma = self::seekMethod($rpd, array_slice($uri, 1));
            if (!empty($ma)) {
                return [
                    "responder" => $responder,
                    "method"    => $ma[0],
                    "uri"       => $ma[1]
                ];
            }
        }

        /**
         *  3  判断是否 app/index 类中某个 public 方法
         */
        $app = App::has("index");
        if (false !== $app) {
            $responder = $app;
            $ma = self::seekMethod($app, $uri);
            if (!empty($ma)) {
                return [
                    "responder" => $responder,
                    "method"    => $ma[0],
                    "uri"       => $ma[1]
                ];
            }
        }

        /**
         *  4  判断是否 resper/Responder 基类中的 某个 public 方法
         */
        $ma = self::seekMethod($rpd, $uri);
        if (!empty($ma)) {
            return [
                "responder" => $responderCls,
                "method"    => $ma[0],
                "uri"       => $ma[1]
            ];
        }

        /**
         *  5  全部失败，调用 resper/Responder::error()
         */
        return [
            "responder" => $responderCls,
            "method"    => "error",
            "uri"       => $uri
        ];
        
    }

    /**
     * !! 核心方法
     * 根据传入的 $uri 数组，在 $cls 类中 查找目标方法
     * @param String $cls 类全称
     * @param Array $uri 参数数组
     * @return Mixed 找到目标方法，返回 [ method name, [参数数组] ]，未找到则返回 null
     */
    public static function seekMethod($cls, $uri = [])
    {
        //如果 $cls 不是 Respond 子类，返回 null
        if (!is_subclass_of($cls, Resper::cls("resper/Responder"))) return null;
        //空 uri
        if (!Is::nemarr($uri) || !Is::indexed($uri)) {
            return ["empty", []];
        }
        //查找 响应方法
        $m = $uri[0];
        //响应方法必须是 实例方法/public方法
        $has = Cls::hasMethod($cls, $m, "public", function($mi) {
            return $mi->isStatic() === false;
        });
        if ($has) {
            return [ $m, array_slice($uri, 1) ];
        } else {
            return [ "default", $uri ];
        }
    }

    /**
     * !! 核心方法
     * 找到响应者后，由响应者创建 Response 响应实例
     * @param Mixed $data 要输出的数据，不指定则调用 Resper::$params["method"] 指定的方法生成输出数据
     * @return Response 实例
     */
    public static function __response($data = null)
    {
        //$response = Response::current();
    }
}