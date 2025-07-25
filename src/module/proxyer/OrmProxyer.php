<?php
/**
 * resper 框架 响应代理类
 * 代理 数据库 相关的请求方法
 * 当前响应者的 响应方法为 db 时，则会由此类作为 响应代理，处理用户的请求
 * 
 * 请求的 url 可能是这些形式：
 *  https://[host]/[resper]/db/
 *                              [dbn/]tbn/[create|update|retrieve|delete|toggle|...]
 *                              [dbn/]tbn/api/foo
 *                              dbn/[install|manual|...]
 */

namespace Cgy\module\proxyer;

use Cgy\Resper;
use Cgy\Request;
use Cgy\Response;
use Cgy\Orm;
use Cgy\orm\Db;
use Cgy\orm\Model;
use Cgy\orm\model\ModelSet;
use Cgy\Uac;
use Cgy\uac\Operation;
use Cgy\module\Proxyer;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Cls;
use Cgy\util\Path;

class OrmProxyer extends Proxyer 
{
    /**
     * 自定义依赖项
     */
    //当前响应者的 orm 实例
    protected $orm = null;
    //当前操作的 目标数据库 实例
    protected $db = null;
    //当前操作的 目标数据模型 类全称
    protected $model = "";

    //临时的 数据模型实例，用于访问 数据模型的 实例方法
    protected $modelIns = null;

    //如果指定了目标数据模型，且 post 传入了 query 数据，则依据此创建 数据记录集 实例
    protected $rs = null;

    /**
     * 后续初始化方法
     * !! 子类必须实现
     * @return $this
     */
    protected function initProxyer()
    {
        //缓存 orm 实例
        $this->orm = $this->resper->orm;

        //根据 传入的 URI 解析得到当前操作的目标 数据库|数据模型
        $this->getTarget();

        //如果指定了目标数据模型，且 post 传入了 query 数据，则依据此创建 数据记录集 实例
        $this->getModelSet();

        return $this;
    }
    
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
    protected function getProxyMethod()
    {
        //从 URI 中读取 操作方法
        if (!Is::nemarr($this->uri)) return $this;
        $m = $this->uri[0];
        $isapi = strtolower($m)=="api";
        if ($isapi) {
            array_shift($this->uri);
            if (!Is::nemarr($this->uri)) return $this;
            $m = $this->uri[0];
        }
        $mn = Orm::camel($m, false);    //方法名 驼峰，首字母小写
        $mk = Orm::snake($m);           //操作标识中的 操作 key 下划线_
        //是否开启的 uac 权限控制
        $hasuac = $this->hasUac();
        $oprarr = $hasuac ? ["db"] : [];
        //$oprcls = $hasuac ? get_class($this->uac->opr) : Operation::class;

        if ($this->hasDb()==true) {
            //指定了目标数据库
            if ($hasuac) $oprarr[] = Orm::snake($this->db->name);
            if ($this->hasModel()===true) {
                //指定了目标数据模型
                if ($hasuac) $oprarr[] = $this->model::tbn();
                if ($isapi) {
                    //请求的是 api 方法，检查是否 数据模型(静态方法)|记录实例(实例方法) 的 api 方法
                    //数据模型所有 apis
                    $apis = $this->model::$config->api;
                    if (isset($apis[$mk])) {
                        //存在请求的 api
                        //方法名 增加 -Api 后缀
                        $mn .= "Api";
                        if ($apis[$mk]["isModel"]!==true) {
                            //此 api 是实例方法，记录实例的 api
                            //创建 临时 数据模型实例
                            if (!$this->modelIns instanceof Model) $this->modelIns = new $this->model([]);
                            //调用临时实例的 方法
                            $this->responseMethod = [$this->modelIns, $mn];
                            if ($hasuac) $oprarr[] = "api";
                        } else {
                            //此 api 是静态方法，数据模型的 api
                            $this->responseMethod = [$this->model, $mn];
                            if ($hasuac) $oprarr[] = "model/api";
                        }
                    }
                } else {
                    //请求的是 普通方法，在模型类定义中，这些方法带有 Proxy 后缀，fooBarProxy
                    //当前模型的静态 public 方法集合
                    $stms = Cls::methodNames($this->model, "public,&static");
                    //var_dump($stms);
                    //方法名 增加 -Proxy 后缀
                    $mn .= "Proxy";
                    if (in_array($mn, $stms)) {
                        //存在 静态方法
                        $this->responseMethod = [$this->model, $mn];
                    }
                }
            } else {
                //未指定目标数据模型，访问的是 数据库实例方法
                //数据库实例没有 api 方法，因此丢弃 $isapi 状态，直接查找实例方法，在数据库类定义中，这些方法带有 Proxy 后缀，fooBarProxy
                //数据库实例方法集合
                $pbms = Cls::methodNames($this->db, "public,&!static");
                //方法名 增加 -Proxy 后缀
                $mn .= "Proxy";
                if (in_array($mn, $pbms)) {
                    //存在实例方法
                    $this->responseMethod = [$this->db, $mn];
                }
            }
        }
        
        if (Is::nemarr($this->responseMethod)) {
            //找到了响应方法，处理 $this->uri
            array_shift($this->uri);
            //生成 操作标识
            if ($hasuac && count($oprarr)>1) {
                //开启了 uac，需要生成 操作标识
                $this->operate = implode("/", $oprarr).":$mk";
            }
        }

        return $this;
    }



    /**
     * tools
     */

    /**
     * 根据 传入的 URI 解析得到当前操作的目标 数据库|数据模型
     * @return $this
     */
    protected function getTarget()
    {
        if (!Is::nemarr($this->uri)) return $this;

        $dbn = $this->uri[0];
        if ($this->orm->hasDb($dbn)) {
            //uri = [host]/[resper name]/db/[dbn][/...]
            array_shift($this->uri);
            $this->db = $this->orm->db($dbn);
            if (Is::nemarr($this->uri)) {
                $mdn = $this->uri[0];
                $model = $this->db->hasModel($mdn);
                if ($model!==false) {
                    //uri = [host]/[resper]/db/[dbn]/[mdn][/...]
                    array_shift($this->uri);
                    //初始化 目标数据模型
                    $this->model = $this->db->model($model);

                }
            }
        } else {
            $mi = $this->orm->hasModel($dbn);
            if ($mi!==false) {
                //uri = [host]/[resper]/db/[mdn][/...]
                array_shift($this->uri);
                $dbn = $mi["dbn"];
                $model = $mi["mcls"];
                $this->db = $this->orm->db($dbn);
                //初始化 目标数据模型
                $this->model = $this->db->model($model);
            }
        }

        return $this;
    }

    /**
     * 如果指定了目标数据模型，且 post 传入了 query 数据，则依据此创建 数据记录集
     * @return $this 
     */
    protected function getModelSet()
    {
        if ($this->hasModel()!==true) return $this;
        $query = $this->post["query"] ?? [];
        if (!Is::nemarr($query)) return $this;
        //开始执行 curd 操作
        $rs = $this->model::md()->query($query, false)->select();
        if (!empty($rs)) $this->rs = $rs;
        return $this;
    }

    /**
     * 当前请求是否指定了目标数据库
     * @return Bool
     */
    protected function hasDb() { return $this->db instanceof Db; }

    /**
     * 当前请求是否指定了目标数据模型
     * @return Bool
     */
    protected function hasModel() { return Is::nemstr($this->model) && class_exists($this->model); }

    /**
     * 判断当前请求是否拥有 目标数据记录集实例
     * @return Bool
     */
    protected function hasModelSet() { return !is_null($this->rs) && $this->rs instanceof ModelSet; }
    
}