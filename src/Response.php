<?php
/**
 * cgyio/resper 核心类
 * Response 响应类
 */

namespace Cgy;

use Cgy\Resper;
use Cgy\Request;
use Cgy\response\Header;
use Cgy\response\Exporter;
use Cgy\util\Is;
use Cgy\util\Arr;

use Cgy\traits\staticCurrent;

class Response 
{
    //引入trait
    use staticCurrent;

    /**
     * current
     */
    public static $current = null;

    //默认 response 参数
    private static $defaultParams = [
        "status" => 0,
        "headers" => [],
        "protocol" => "",
        "data" => null,
        "format" => "",
        "errors" => []
    ];

    //Request 请求实例
    public $request = null;

    /**
     * 响应者实例 resper\Resper
     * !! 在创建 Response 实例之前，必须建立 响应者实例
     */
    public $resper = null;

    //响应头实例
    public $header = null;

    //响应参数
    public $protocol = "1.1";
    public $status = 200;
    public $format = EXPORT_FORMAT;

    //准备输出的内容
    public $data = null;

    //本次会话发生的错误收集，Error实例
    public $errors = [];
    //public $throwError = null;  //需要立即抛出的错误对象

    //数据输出类
    public $exporter = null;

    //是否只输出 data，默认 false，输出 [error=>false, errors=>[], data=>[]]
    public $exportOnlyData = false;


    /**
     * 构造
     * @return void
     */
    public function __construct()
    {
        //Request 实例
        $this->request = Resper::$request;
        //响应者实例
        $this->resper = Resper::$resper;
        //创建响应头
        $this->header = new Header();
        
        //从 url 中输入可能存在的 format 参数
        $this->setFormat();
    }

    /**
     * 生成 response 参数
     * @param Mixed $data 要输出的 内容
     * @param Array $params 额外的 response 参数
     * @return Response
     */
    public function create($data = null, $params = [])
    {
        $req = $this->request;
        $rpr = $this->resper;
        
        if ($req->pause && !$rpr->unpause) {
            //WEB_PAUSE == true 网站已暂停 且 响应者接收 WEB_PAUSE 参数控制
            $params = Arr::extend($params, [
                "format" => "pause",
                "data" => null,
                "extra" => "额外的属性" 
            ]);
        } else {
            //$data = $this->rou->run();
            $params = self::createParams($data, $params);
        }
        return $this->setParams($params);
    }

    /**
     * 输出
     */
    public function export($usePsr7 = false)
    {
        $exporter = $this->createExporter();
        $exporter->prepare();
        //var_dump($this->info());

        if (RESPONSE_PSR7 == true || $usePsr7 == true) {
            $status = $this->status;
            $headers = $this->headers;
            $body = $exporter->content;
            $protocol = $this->protocol;
            $response = new Psr7\Response($status, $headers, $body, $protocol);
            var_dump($response);
        } else {
            return $exporter->export();
        }
        exit;
    }

    /**
     * throw error
     */
    public function throwError($error = null)
    {
        if (is_object($error) && $error instanceof Error) {
            $this->setError($error);
            if ($error->mustThrow()) {

                $errdata = $error->data;
                $errdata["exporter"] = $this->exporter;
                $this->setData($errdata);
                
                //$this->setExporter("error");
                $exporter = $this->createExporter();
                $exporter->prepare();
                return $exporter->export();
            }
        }
        exit;
    }


    /**
     * set response params
     */

    /**
     * 设定 statu
     * @param Int $code 服务器响应状态码
     * @return Response
     */
    public function setStatus($code = 200)
    {
        $this->status = (int)$code;
        return $this->setExporter();
    }

    /**
     * 设定 headers
     * @param Mixed $key 键名 或 [ associate ]
     * @param Mixed $val 参数内容
     * @return Response
     */
    public function setHeaders($key = [], $val = null)
    {
        $this->header->set($key, $val);
        return $this;
    }

    /**
     * 设定输出内容 data
     * @param Mixed $data 当 $reset==true 时可以为 null 表示清除当前 data
     * @param Bool $reset 是否重置
     * @return Response
     */
    public function setData($data = null, $reset = false)
    {
        if (is_null($this->data) || $reset) {
            $this->data = $data;
        } else {
            if (is_array($this->data) && is_array($data)) {
                $this->data = Arr::extend($this->data, $data);
            } else {
                $this->data = $data;
            }
        }
        return $this;
    }

    /**
     * 设定 export format
     * @param String $format 指定输出格式
     * @return Response
     */
    public function setFormat($format = null)
    {
        $req = Request::current();
        if ($req->debug) {
            //如果 WEB_DEBUG == true
            $this->format = "dump";
            return $this->setExporter();
        }

        if (!Is::nemstr($format)) {
            //未指定 format
            $format = $req->gets->format(strtolower(EXPORT_FORMAT));
            if (!Is::nemstr($format)) $format = "json";
        } else {
            $format = strtolower($format);
        }

        //检查 给定的 format 是否可用
        $fs = array_map(function($i) {
            return strtolower($i);
        }, EXPORT_FORMATS);
        if (in_array($format, $fs) && false !== Exporter::has($format)) {
            $this->format = $format;
            return $this->setExporter();
        }
        return $this;
    }

    /**
     * 设定 errors
     * @return Response
     */
    public function setError($error = null)
    {
        if (is_object($error) && $error instanceof Error) {
            $this->errors[] = $error;
        }
        return $this;
    }
    
    /**
     * 设定 exporter 类
     * 需要根据 statu 状态码 / format 输出格式 来确定需要哪一个 exporter 类
     * @param String $format 指定的 format
     * @return Response
     */
    public function setExporter($format = null)
    {
        if ($this->status != 200) {
            $exporter = Exporter::has("code");
        } else {
            if (is_null($format)) {
                $exporter = Exporter::has($this->format);
            } else {
                $exporter = Exporter::has($format);
            }
        }
        if (Is::nemstr($exporter) && class_exists($exporter)) {
            $this->exporter = $exporter;
        } else {
            $this->setFormat();
        }
        return $this;
    }

    /**
     * 手动设定多个参数
     * @return Response
     */
    public function setParams($params = [])
    {
        //var_dump($params);
        if (isset($params["headers"]) && is_array($params["headers"]) && !empty($params["headers"])) {
            $this->setHeaders($params["headers"]);
        }
        if (isset($params["headers"])) unset($params["headers"]);
        foreach (["data","format","status","exportOnlyData"] as $k => $v) {
            if (isset($params[$v])) {
                $m = "set".ucfirst($v);
                if (method_exists($this, $m)) {
                    $this->$m($params[$v]);
                } else {
                    $this->$v = $params[$v];
                }
                unset($params[$v]);
            }
        }
        foreach ($params as $k => $v) {
            //if (property_exists($this, $k)) $this->$k = $v;
            if (!property_exists($this, $k)) $this->$k = $v;
            //$this->$k = $v;
        }
        
        return $this;
    }


    /**
     * 创建 exporter 对象
     * @return Exporter
     */
    public function createExporter()
    {
        if (empty($this->exporter)) $this->setStatus(500);
        $exporterClass = $this->exporter;
        return new $exporterClass($this);
    }

    /**
     * sent headers
     * @return Response
     */
    public function sentHeaders($key = [], $val = null)
    {
        if (headers_sent() === true) return $this;
        if (!empty($key)) {
            if (is_associate($key)) {
                foreach ($key as $k => $v) {
                    header("$k: $v");
                }
            } else if (is_string($key) && is_string($val)) {
                header("$key: $val");
            }
        } else {
            foreach ($this->headers as $k => $v) {
                header("$k: $v");
            }
        }
        return $this;
    }


    /**
     * response info
     * @return Array
     */
    public function info()
    {
        $rtn = [];
        $keys = array_keys(self::$defaultParams);
        for ($i=0;$i<count($keys);$i++) {
            $ki = $keys[$i];
            $rtn[$ki] = $this->$ki;
        }
        $rtn["route"] = $this->rou->info();
        return $rtn;
    }


    /**
     * 静态调用，按 format 输出
     * 输出后退出
     * @return void
     */
    private static function _export($format = "html", $data = null, $params = [])
    {
        $params = Arr::extend($params, [
            "data" => $data,
            "format" => $format
        ]);
        return self::$current->setParams($params)->export();
        exit;
    }

    public static function json($data =  [], $params = [])
    {
        return self::_export("json", $data, $params);
    }

    public static function str($str = "", $params = [])
    {
        return self::_export("str", $str, $params);
    }
    
    public static function html($html = "", $params = [])
    {
        return self::_export("html", $html, $params);
    }
    
    public static function dump(...$todump)
    {
        return self::_export("dump", count($todump)==1 ? $todump[0] : $todump);
    }

    public static function code($code = 404)
    {
        return self::$current->setStatus($code)->export();
    }
    
    public static function page($path = "", $params = [])
    {
        $path = path_find($path, ["inDir"=>"page"]);
        if (empty($path)) return self::code(404);
        return self::_export("page", $path, $params);
    }

    public static function error($errmsg=[], $errtype="custom")
    {
        if (is_notempty_str($errmsg)) $errmsg = [$errmsg];
        $errtit = $errtype."::".implode(",",$errmsg);
        trigger_error($errtit, E_USER_ERROR);
        exit;
    }

    public static function errpage($params = [])
    {
        $path = path_find("box/page/error.php");
        $params = Arr::extend([
            "title" => "发生错误",
            "msg" => "发生错误"
        ], $params);
        return self::_export("page", $path, $params);
    }
    
    public static function pause()
    {
        $pages = func_get_args();
        $page = path_exists(array_merge($pages, [
            "pause.php",
            "cphp/pause.php"
        ]));
        if (empty($page)) return self::code(404);
        return self::_export("page", $page, $params);
    }

    //不通过exporter，直接输出内容，headers已经手动指定
    //用于输出文件资源
    public static function echo($content = "")
    {
        self::headersSent();
        echo $content;
        exit;
    }

    //header("Location:xxxx") 跳转
    public static function redirect($url="", $ob_clean=false)
    {
        if (headers_sent() !== true) {
            if ($ob_clean) ob_end_clean();
            header("Location:".$url);
        }
        exit;
    }




    /**
     * tools
     */

    /**
     * 静态调用 Response::$current->setHeaders
     * @return Response
     */
    public static function headers()
    {
        $args = func_get_args();
        return self::$current->setHeaders(...$args);
    }

    /**
     * 静态调用 Response::$current->sentHeaders
     * @return Response
     */
    public static function headersSent()
    {
        $args = func_get_args();
        return self::$current->sentHeaders(...$args);
    }

    /**
     * 获取要输出的 format 类型
     * format 类型在 EXPORT_FORMATS 中定义
     * @return String
     */
    public static function getExportFormat($format = null)
    {
        if (Request::$current->debug) return "dump";
        $fs = arr(strtolower(EXPORT_FORMATS));
        $format = empty($format) ? Request::get("format", EXPORT_FORMAT) : $format;
        $format = strtolower($format);
        if (is_notempty_str($format)) {
            if (in_array($format, $fs) && !is_null(Exporter::has($format))) {
                return $format;
            }
        }
        return strtolower(EXPORT_FORMAT);
    }

    /**
     * 获取 format 对应的 exporter 类，返回类全名
     * @return String | NULL
     */
    public static function getExporterClass($format = null)
    {
        if (is_notempty_str($format)) {
            return cls("response/exporter/".ucfirst($format));
        }
        return null;
    }

    /**
     * 将 route 运行结果 data 与 response params 合并，生成包含 data 的新 params
     * @return Array
     */
    public static function createParams($data = [], $params = [])
    {
        if (is_associate($data) && !empty($data)) {
            $ps = [];
            foreach (self::$defaultParams as $k => $v) {
                if (isset($data[$k])) {
                    $ps[$k] = $data[$k];
                    unset($data[$k]);
                }
            }
            if (!empty($ps)) $params = Arr::extend($params, $ps);
            if (!empty($data)) {
                if (isset($params["data"]) && !empty($params["data"])) {
                    /*if (is_associate($params["data"]) && is_associate($data)) {
                        $params["data"] = Arr::extend($params["data"], $data);
                    } else {
                        $params["data"] = $data;
                    }*/
                    $params = Arr::extend($params, $data);
                } else {
                    $params["data"] = $data;
                }
            }
        } else {
            $params["data"] = $data;
        }
        return $params;
    }

    /**
     * 获取 defaultParams
     * @return Array
     */
    public static function getDefaultParams()
    {
        return self::$defaultParams;
    }

}