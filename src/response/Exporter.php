<?php
/**
 * cgyio/resper 响应工具类
 * Exporter 输出结果到前端
 */

namespace Cgy\response;

use Cgy\Resper;
use Cgy\Response;
use Cgy\Error;
use Cgy\util\Is;

class Exporter
{
    //关联的Response实例
    public $response = null;
    //默认输出数据的结构
    protected static $default = [
        "error" => false,
        "errors" => [],
        "data" => null
    ];

    //当前format的页头
    public $contentType = "";

    //要输出的数据
    public $data = null;

    //要输出的数据，字符串，用于echo
    public $content = "";


    /**
     * 构造
     */
    public function __construct($response = null) 
    {
        $this->response = $response;
        if ($this->contentType != "") {
            $this->response->header->set("Content-Type", $this->contentType);
        }
        if (is_null($this->data)) {
            $this->data = array_merge(self::$default, []);
        }
        $this->data["data"] = $this->response->data;
        $this->prepareError();
    }

    /**
     * 解析 data["data"]，写入 content，用于最终输出
     * 子类覆盖
     * @return Exporter
     */
    public function prepare()
    {
        //子类实现...
        //$this->content = ...;

        return $this;
    }

    /**
     * 处理要输出的error
     * @return Exporter
     */
    private function prepareError()
    {
        $errs = $this->response->errors;
        $this->data["errors"] = [];
        foreach ($errs as $i => $err) {
            if ($err instanceof Error) {
                $this->data["errors"][] = $err->data;
                if ($err->mustThrow()) {
                    $this->data["error"] = true;
                }
            }
        }
        return $this;
    }


    /**
     * 最终输出
     * 执行完毕后 exit
     * 子类可覆盖
     * @return String content
     */
    public function export()
    {
        //$this->prepare();
        $this->response->header->sent();
        //var_dump($this->response);
        echo $this->content;
        //return $this->content;
        exit;
    }



    /**
     * static tools
     */

    /**
     * 创建 Response 实例指定的 exporter 实例
     * @param Response $response 实例
     * @return Exporter 实例
     */
    public static function create(Response $response)
    {
        $expcls = $response->exporter;
        if (Is::nemstr($expcls)) {
            if (!class_exists($expcls)) {
                $response->setStatus(500);
                return self::create($response);
            }
            return new $expcls($response);
        }

        if ($expcls instanceof Exporter) {
            $expcls->response = $response;
            return $expcls;
        }

        $response->setFormat();
        return self::create($response);
    }

    /**
     * __callStatic
     * 以 Exporter::Format() 方式 创建指定 format 的 输出类实例
     * @param String $format 指定的输出格式
     * @param Array $args 输出类的 构造参数
     * @return Mixed 输出类存在则返回 输出类实例，不存在则返回 null
     */
    public static function __callStatic($format, $args)
    {
        $cls = self::get($format);
        if (empty($cls)) return null;
        $ins = new $cls(...$args);
        return $ins;
    }

    /**
     * 判断是否有 指定格式的 exporter 输出类
     * @param String $format 要检查的输出格式
     * @return Mixed 找到输出类则返回 类全称，否则返回 false
     */
    public static function has($format)
    {
        $cln = "response/exporter/".$format;
        $cls = Resper::cls($cln);
        if (empty($cls)) return false;
        return $cls;
    }

    /**
     * 返回 指定格式的 exporter 输出类 全称
     * @param String $format 指定输出格式
     * @return Mixed 找到返回类全称，未找到返回 null
     */
    public static function get($format)
    {
        $cls = self::has($format);
        if ($cls === false) return null;
        return $cls;
    }
    

}