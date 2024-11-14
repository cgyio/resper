<?php
/**
 * cgyio/resper request 工具类
 * Seeker 响应查找
 * 根据 Request::$current->url->path 查询响应此 request 的 类/方法
 * 可以响应 request 的类：App类 / module类 / Response类
 */

namespace Cgy\request;

use Cgy\Resper;
use Cgy\App;
use Cgy\Module;
use Cgy\request\Url;
use Cgy\response\Respond;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Cls;

class Seeker 
{

    //本次会话调用的 响应类/方法
    public $context = [];

    /**
     * 构造
     * @return void
     */
    public function __construct()
    {
        $this->context = self::seek();
    }

    /**
     * __get
     * @param String $key
     */
    public function __get($key)
    {
        if (isset($this->context[$key])) {
            return $this->context[$key];
        }
        return null;
    }

    /**
     * 如果找到的响应类是 app 类 则返回类全称
     * @return Mixed 类全称 or null
     */
    public function app()
    {
        if (empty($this->context)) return null;
        $cls = $this->context["response"];
        if (is_subclass_of($cls, Resper::cls("App"))) return $cls;
        return null;
    }


    /**
     * !! 核心方法
     * 根据 Request::$current->url->path 查询响应此 request 的 类/方法
     * 可以响应的类：App类 / module类 / Respond类(route类)
     * @param Array $uri URI 路径，不指定则使用 url->path
     * @return Array 得到的目标 类，方法，uri参数，未找到 返回 null
     * 结构：[
     *          "response"  => 类全称 or 类实例
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
         * 否则调用 \response\Respond::empty() 
         * 
         */
        $response = Resper::cls("response/Respond");
        $method = "empty";

        /**
         * URI 为空，返回默认响应
         */
        if (empty($uri)) {
            if (false !== ($appcls = App::has("index"))) {
                //如果存在 app/index 则调用 \App\Index::empty()
                $response = $appcls;
            }
            return [
                "response"  => $response,
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
            $response = $cls;
            $ma = self::seekMethod($cls, array_slice($uri, 1));
        } else {
            $cls = Module::has($uri[0]);
            if ($cls !== false) {
                $response = $cls;
                $ma = self::seekMethod($cls, array_slice($uri, 1));
            }
        }
        if (!empty($ma)) {
            return [
                "response"  => $response,
                "method"    => $ma[0],
                "uri"       => $ma[1]
            ];
        }

        /**
         *  2  判断是否 response\Respond 类 (相当于 route 类)
         */
        $rpd = Respond::has($uri[0]);
        if (false !== $rpd) {
            $response = $rpd;
            $ma = self::seekMethod($rpd, array_slice($uri, 1));
            if (!empty($ma)) {
                return [
                    "response"  => $response,
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
            $response = $app;
            $ma = self::seekMethod($app, $uri);
            if (!empty($ma)) {
                return [
                    "response"  => $response,
                    "method"    => $ma[0],
                    "uri"       => $ma[1]
                ];
            }
        }

        /**
         *  4  判断是否 response/Respond 基类中的 某个 public 方法
         */
        $rpd = Resper::cls("response/Respond");
        $ma = self::seekMethod($rpd, $uri);
        if (!empty($ma)) {
            return [
                "response"  => $rpd,
                "method"    => $ma[0],
                "uri"       => $ma[1]
            ];
        }

        /**
         *  5  全部失败，调用 response/Respond::error()
         */
        return [
            "response"  => Resper::cls("response/Respond"),
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
        if (!is_subclass_of($cls, Resper::cls("response/Respond"))) return null;
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
}