<?php
/**
 * cgyio/resper 事件 订阅/触发/处理
 * 
 * 事件订阅者可以是：类全称 / 实例对象
 * 
 * 订阅事件：
 *      Event::addListener($listener, $event, $once=false)
 * 触发事件：
 *      Event::trigger($event, $triggerBy, ...$args)
 * 取消订阅：
 *      Event::removeListener($listener, $event)
 * 将 $listener 对象内部的 handle***Event() 方法批量创建 事件订阅：
 *      Event::regist($listener)
 */

namespace Cgy;

//use Cgy\Resper;
use Cgy\App;
use Cgy\Module;
use Cgy\Request;
use Cgy\Response;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Cls;

use Cgy\traits\staticCurrent;

class Event 
{
    //引入trait
    use staticCurrent;

    /**
     * current
     */
    public static $current = null;

    /**
     * 已创建的事件以及订阅者
     */
    public static $event = [
        /*
        "event-name" => [
            [
                object | class | null,      订阅者
                method | callable,          事件处理方法/函数
                ... 一个订阅者可以有多个事件处理方法
            ],
            [ 其他订阅者, handler, handler, ... ],
            ...
        ],
        "event-name-once" => [
            ...
        ],
        */
    ];

    /**
     * 订阅/触发 事件 log
     */
    public static $log = [
        /*
        [
            timestamp,
            "listen",
            "event-name",
            "listener object | class | null"
        ],
        [
            timestamp,
            "trigger",
            "event-name",
            "triggerBy object | class | null"
        ],
        */
    ];

    

    /**
     * 订阅事件
     * Event::addHandler(
     *      "foo-bar",                  事件名称
     *      object | class | null,      订阅者，可以是 类 / 实例 / null
     *      method | callable,          事件处理方法，可以是 类方法名 / 实例方法名 / callable 函数
     *      false,                      是否一次性事件
     * )
     * @param String $event 事件名称
     * @param Mixed $listener 订阅者
     * @param Mixed $handler 事件处理方法
     * @param Bool $once 是否一次性事件
     * @return Bool
     */
    public static function addHandler($event, $listener, $handler, $once=false)
    {
        if (true !== self::isLegalListener($listener)) return false;
        if (true !== self::isLegalHandler($listener, $handler)) return false;

        //查找 $listener 现有的 事件处理方法
        $evt = self::getHandler($event, $listener);
        $idk = $once ? "once" : "event";
        $evk = $once ? $event."-once" : $event;
        $idx = $evt["idx"][$idk];
        if ($idx<0) {
            if (!isset(self::$event[$evk])) self::$event[$evk] = [];
            self::$event[$evk][] = [$listener, $handler];
        } else {
            if (!in_array($handler, $evt[$idk])) {
                self::$event[$evk][$idx][] = $handler;
            } else {
                //重复订阅，返回 false
                return false;
            }
        }

        //log
        self::log("listen", $event, $listener);

        return true;
    }
    public static function addHandlerOnce($event, $listener, $handler)
    {
        return self::addHandler($event, $listener, $handler, true);
    }

    /**
     * 取消订阅事件
     * @param String $event 事件名称
     * @param Mixed $listener 订阅者
     * @param Mixed $handler 要取消的 事件处理方法 不指定则取消此订阅者的 所有 事件处理方法
     * @param Bool $once 是否一次性事件
     * @return Bool
     */
    public static function removeHandler($event, $listener, $handler=null, $once=false)
    {
        if (true !== self::isLegalListener($listener)) return false;
        if (!is_null($handler) && true !== self::isLegalHandler($listener, $handler)) return false;

        //查找 $listener 现有的 事件处理方法
        $evt = self::getHandler($event, $listener);
        $idk = $once ? "once" : "event";
        $evk = $once ? $event."-once" : $event;
        $idx = $evt["idx"][$idk];
        if ($idx<0) {
            //$listener 当前并无 handler 无需取消
            return true;
        } else {
            if (is_null($handler)) {
                //未指定要取消的 handler 则取消全部
                array_splice(self::$event[$evk], $idx, 1);
            } else {
                //指定了 要取消的 handler
                //但是不在当前 handler 列表中，直接返回
                if (!in_array($handler, $evt[$idk])) return true;
                //查找 handler 在列表中的 idx
                $hidx = array_search($handler, $evt[$idk], true);
                if ($hidx>=0) {
                    array_splice(self::$event[$evk][$idx], $hidx, 1);
                } else {
                    return true;
                }
            }
            return true;
        }
    }
    public static function removeHandlerOnce($event, $listener, $handler=null)
    {
        return self::removeHandler($event, $listener, $handler, true);
    }

    /**
     * 触发事件
     * @param String $event 事件名称
     * @param Mixed $triggerBy 触发者
     * @param Array $args 传递给 handler 的参数
     * @return Bool
     */
    public static function trigger($event, $triggerBy, ...$args)
    {
        //查找 $event 的所有事件处理方法
        $evt = self::getHandler($event);
        //合并所有 handlers
        $hdls = array_merge([], $evt["event"], $evt["once"]);
        
        /**
         * 循环执行 handler
         */
        for ($i=0;$i<count($hdls);$i++) {
            $hdl = $hdls[$i];
            if (!Is::nemarr($hdl) || !Is::indexed($hdl) || count($hdl)<=1) continue;
            $listener = array_shift($hdl);
            //执行每个 listener 的 handler
            for ($j=0;$j<count($hdl);$j++) {
                $hdi = $hdl[$j];

                /**
                 * 根据 handler 类型，调用方法
                 */
                if (is_callable($hdi)) {
                    //callable 函数
                    if (is_object($listener)) {
                        //如果 listener 是一个实例对象 则将 callable 转为 Closure 对象 并 bind(listener)
                        if (!$hdi instanceof \Closure) {
                            //将普通 callable 转换为 Closure 对象
                            $hdi = \Closure::fromCallable($hdi);
                        }
                        //绑定 listener 到 handler 方法内部的 $this
                        $hdi = $hdi->bindTo($listener);
                        //执行
                        $hdi($triggerBy, ...$args);
                    } else {
                        //listener == null 直接执行 handler
                        $hdi($triggerBy, ...$args);
                    }
                } else if (Is::nemstr($hdi)) {
                    //类方法 / 实例方法
                    $hdlcall = [];
                    if (Is::nemstr($listener)) {
                        //类方法 静态方法
                        $hdlcall[] = class_exists($listener) ? $listener : Cls::find($listener);
                    } else if (is_object($listener)) {
                        //实例方法
                        $hdlcall[] = $listener;
                    } else {
                        continue;
                    }
                    $hdlcall[] = $hdi;
                    //call
                    call_user_func($hdlcall, $triggerBy, ...$args);
                }

            }
        }

        /**
         * 删除一次性事件处理方法
         */
        $ek = $event."-once";
        if (isset(self::$event[$ek])) unset(self::$event[$ek]);

        //log
        self::log("trigger", $event, $triggerBy);

        //完成
        return true;
    }

    /**
     * log 订阅/触发 事件
     * @param String $type 订阅/触发 listen/trigger
     * @param String $event 事件名称
     * @param Mixed $by listener/triggerBy 订阅者/触发者
     * @return Array log array
     */
    public static function log($type, $event, $by)
    {
        $t = time();
        $ts = date("Y-m-d H:i:s");
        $log = [
            $ts,
            $type,
            $event,
            $by
        ];
        self::$log[] = $log;
        return self::$log;
    }



    /**
     * 获取 $event 事件 的 所有 订阅者 和 处理方法
     * @param String $event 事件名称
     * @param Mixed $listener 可以查找指定订阅者的 事件处理方法
     * @return Array
     */
    protected static function getHandler($event, $listener = false)
    {
        $evts = self::$event;
        $evt = (!isset($evts[$event]) || !Is::nemarr($evts[$event]) || !Is::indexed($evts[$event])) ? [] : $evts[$event];
        $ock = $event."-once";
        $once = (!isset($evts[$ock]) || !Is::nemarr($evts[$ock]) || !Is::indexed($evts[$ock])) ? [] : $evts[$ock];

        $rst = [
            "event" => $evt,
            "once" => $once
        ];
        
        if ($listener===false) {
            //返回全部 订阅者的处理方法
            return $rst;
        }

        //查找指定 订阅者的 事件处理方法
        $rst = Arr::extend($rst, [
            "idx" => [
                "event" => -1,
                "once" => -1
            ],
        ]);
        if (true !== self::isLegalListener($listener)) return $rst;
        //在普通事件中查找
        for ($i=0;$i<count($evt);$i++) {
            $evi = $evt[$i];
            if ($evi[0] === $listener) {
                $rst["event"] = $evi;
                $rst["idx"]["event"] = $i;
                break;
            }
        }
        //在一次性事件中查找
        for ($i=0;$i<count($once);$i++) {
            $evi = $once[$i];
            if ($evi[0] === $listener) {
                $rst["once"] = $evi;
                $rst["idx"]["once"] = $i;
                break;
            }
        }
        return $rst;
    }

    /**
     * 判断是否合法的 listener 
     * listener 可以是 类全称 / 类实例
     * @param Object $listener 订阅者
     * @return Bool
     */
    protected static function isLegalListener($listener)
    {
        //可以是 null
        if (is_null($listener)) return true;

        //可以是 类名/全称
        if (Is::nemstr($listener)) {
            if (class_exists($listener)) return true;
            if (!empty(Cls::find($listener))) return true;
            return false;
        }

        //可以是 类实例
        if (is_object($listener)) {
            return true;
        }

        return false;
    }

    /**
     * 判断是否合法的 handler 事件处理方法
     * 可以是 类方法 / 实例方法 / callable 函数
     * @param Mixed $listener 订阅者
     * @param Mixed $handler 事件处理方法
     * @return Bool
     */
    protected static function isLegalHandler($listener, $handler)
    {
        //先检查 $listener 是否合法
        if (true !== self::isLegalListener($listener)) return false;

        //可以是 callable 函数
        if (is_callable($handler)) return true;
        
        //可以是 类方法 / 实例方法
        if (Is::nemstr($handler)) {
            if (is_null($listener)) return false;
            if (is_object($listener)) {
                return method_exists($listener, $handler);
            }
            $cls = class_exists($listener) ? $listener : Cls::find($listener);
            return Cls::hasMethod($cls, $handler, "public", function($mi) {
                return $mi->isStatic();
            });
        }

        return false;
    }

    

    

    /**
     * 检查 订阅者 对象内部，自动将 handleFooBarEvent() 方法创建为 事件订阅
     * @param Object $listener 要检查的 订阅者 对象
     * @return Bool
     */
    public static function regist($listener)
    {
        if (!self::isLegalListener($listener)) return false;
        //检查 订阅者 对象内部 handle***Event() 方法
        $ms = cls_get_ms(get_class($listener), function($mi) {
            if (substr($mi->name, 0, 6)=="handle" && substr($mi->name, -5)=="Event") {
                return true;
            }
            return false;
        }, "public");
        
        if (empty($ms)) return false;
        foreach ($ms as $mn => $mi) {
            $evt = substr($mi->name, 6, -5);
            $evt = trim(strtosnake($evt, "-"), "-");
            self::addListener($listener, $evt);
        }
        return true;
    }

}