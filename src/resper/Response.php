<?php
/**
 * cgyio/resper 核心类
 * Response 响应类
 */

namespace Cgy;

use Cgy\Resper;
use Cgy\Request;
use Cgy\Event;
use Cgy\response\Header;
use Cgy\response\Exporter;
use Cgy\Mime;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Path;

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
    /*private static $defaultParams = [
        "status" => 0,
        "headers" => [],
        "protocol" => "",
        "data" => null,
        "format" => "",
        "errors" => []
    ];*/

    //Request 请求实例
    public $request = null;

    /**
     * 响应者实例 Resper
     * !! 在创建 Response 实例之前，必须建立 响应者实例
     */
    public $resper = null;

    //响应头实例
    public $header = null;

    //响应参数
    public $paused = false;
    public $protocol = "1.1";
    public $status = 200;
    public $format = "html";
    public $psr7 = true;

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
        //Resper 实例
        $this->resper = Resper::$resper;

        /**
         * !! 创建响应实例时，响应者实例 和 请求实例 必须都已创建
         * 否则 作为框架错误（生产环境中 不应存在此错误），直接返回错误信息，并终止响应
         */
        if (!$this->request instanceof Request || !$this->resper instanceof Resper) {
            header("Content-Type: text/html; charset=utf-8");
            echo "Resper Framework Error!";
            exit;
        }
        
        //创建响应头
        $this->header = new Header();
        //是否暂停响应
        //$this->paused = $this->request->pause && !$this->resper->unpause;
        $this->paused = $this->resper->responsePaused();
        //是否允许以 PSR-7 标准输出
        $this->psr7 = EXPORT_PSR7;

        /**
         * 暂停响应
         * !!! 此逻辑应在 response 实例化后执行，而不能在实例化过程中执行
         * !!! 因此，此段代码被转移到 Resper->response() 方法中
         */
        /*if ($this->paused) {
            $this->setFormat("pause");
            //直接输出
            $this->export();
            exit;
        }*/
        
        /**
         * 初始化 response 参数
         */
        //从 url 中输入可能存在的 format 参数
        $this->setFormat();

        //触发 response-created 事件
        Event::trigger("response-created", $this);

    }

    /**
     * 输出
     * @param Bool $usePsr7 是否使用 PSR-7 标准输出
     * @return Exit
     */
    public function export($usePsr7 = false)
    {
        $exporter = Exporter::create($this);
        $exporter->prepare();

        if ($this->psr7 == true || $usePsr7 == true) {
            $status = $this->status;
            $headers = $this->header->context;
            $body = $exporter->content;
            $protocol = $this->protocol;
            $response = new Psr7\Response($status, $headers, $body, $protocol);
            var_dump($response);
        } else {
            return $exporter->export();
        }

        //会话结束
        exit;
    }

    /**
     * throw error 抛出错误
     * @param Error $error 错误实例
     * @return Exit
     */
    public function throwError($error = null)
    {
        if (is_object($error) && $error instanceof Error) {
            $this->setError($error);
            if ($error->mustThrow()) {

                $errdata = $error->data;
                $errdata["exporter"] = $this->exporter;
                $this->setData($errdata);
                
                ////$this->setExporter("error");
                $exporter = Exporter::create($this);
                //$exporter = Exporter::Error($this);
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
     * 设定输出内容 data
     * @param Mixed $data
     * @param Mixed $val 以 key=>val 形式设置输出内容
     * @return Response
     */
    public function setData($data = null, $val = null)
    {
        if (Is::associate($data) && Is::associate($this->data)) {
            $this->data = Arr::extend($data);
            return $this;
        }
        if (!Is::nemstr($data)) {
            $this->data = $data;
        } else {
            if (is_null($val)) {
                //$this->data 赋值为 String
                $this->data = $data;
            } else {
                //以 key=>val 形式设置输出内容
                if (!Is::associate($this->data)) $this->data = [];
                $this->data[$data] = $val;
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
        $req = $this->request;
        /*if ($req->debug) {
            //如果 WEB_DEBUG == true
            $this->format = "dump";
            return $this->setExporter();
        }*/

        if ($req->ajax->true) {
            //AJAX 调用 默认输出 json
            $this->format = EXPORT_AJAX;    //"json";
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
            $this->format = "code";
            $exporter = Exporter::get("code");
        } else {
            if (is_null($format)) {
                $exporter = Exporter::get($this->format);
            } else {
                $exporter = Exporter::get($format);
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
     * @param Array $params 要设置的参数
     * @return Response
     */
    public function setParams($params = [])
    {
        if (isset($params["headers"])) {
            $hds = $params["headers"];
            if (Is::nemarr($hds) && Is::associate($hds)) {
                $this->header->set($hds);
            }
            unset($params["headers"]);
        }
        
        //已定义的 response 参数
        $ks = explode(",", "data,format,status,exportOnlyData");
        foreach ($ks as $k => $v) {
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

        //其他参数
        foreach ($params as $k => $v) {
            if (!property_exists($this, $k)) $this->$k = $v;
        }

        //var_dump($this->info());
        
        return $this;
    }

    /**
     * response info
     * @return Array
     */
    public function info()
    {
        $rtn = [];
        $keys = explode(",", "format,status,protocol,paused,psr7,data,errors,exporter,exportOnlyData");
        for ($i=0;$i<count($keys);$i++) {
            $ki = $keys[$i];
            $rtn[$ki] = $this->$ki;
        }
        //$rtn["route"] = $this->rou->info();
        $rtn["headers"] = $this->header->context;
        return $rtn;
    }


    /**
     * 静态调用，按 format 输出
     * 输出后退出
     * @param String $format
     * @param Mixed $data 要输出的内容
     * @param Array $params 额外的 response 参数
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

    /**
     * __callStatic
     * 静态调用，按 format 输出
     * 输出后退出
     * @param String $format 输出的 format  or  _export
     * @param Array $args 输出参数
     * @return Exit;
     */
    public static function __callStatic($format, $args)
    {
        $response = self::$current;

        /**
         * Response::code()
         */
        if ($format == "code") return $response->setStatus(...$args)->export();

        /**
         * Response::error() == trigger_error("custom::....")
         * 但是无法获得正确的 file/line 数据
         */
        if ($format == "error") {
            $errtit = "custom::".implode(",",$args);
            trigger_error($errtit, E_USER_ERROR);
            exit;
        }

        /**
         * Response::page()
         */
        if ($format == "page") {
            $page = $args[0] ?? null;
            if (Is::nemstr($page)) $page = Path::find($page, ["inDir"=>"page"]);
            if (!Is::nemstr($page) || !file_exists($page)) return self::code(404);
            array_shift($args); //page
            return self::_export("page", $page, ...$args);
        }

        /**
         * Response::format() == call Response::_export()
         */
        if ($format == "format") return self::_export(...$args);

        /**
         * 输出已定义的 format
         */
        $fs = EXPORT_FORMATS;
        if (in_array($format, $fs)) {
            return self::_export($format, ...$args);
        }

        /**
         * 
         */

        return null;
    }

    /**
     * 不通过exporter，直接输出内容
     * !! headers已经手动指定
     * !! 通常 用于输出文件资源
     * @param Mixed $content 要输出的文件内容，不一定是字符串，还可能是 二进制内容
     * @param String $ext 可指定文件 ext，指定了 ext 则不需要手动写入 headers，默认 $ext==null 不指定 ext
     * @param String $fn 如果要输出的文件需要下载，可指定文件名
     */
    public static function echo($content = "", $ext = null, $fn = "")
    {
        if (Is::nemstr($ext)) {
            Mime::setHeaders($ext, $fn);
        }
        self::sentHeaders();
        echo $content;
        exit;
    }

    /**
     * 重定向 header("Location:xxxx") 跳转
     * @param String $url 跳转目标 url
     * @param Bool $ob_clean 是否清空 输出缓冲区 默认 false
     * @return Exit
     */
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
     * 静态调用 Response::$current->header->set()
     * @return Response
     */
    public static function setHeaders(...$args)
    {
        return self::$current->header->set(...$args);
    }

    /**
     * 静态调用 Response::$current->header->sent()
     * @return Response
     */
    public static function sentHeaders(...$args)
    {
        return self::$current->header->sent(...$args);
    }

}