<?php
/**
 * cgyio/resper 响应工具类
 * Exporter 输出结果到前端
 */

namespace Cgy\response;

use Cgy\Resper;
use Cgy\Response;
use Cgy\Error;

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
        if ($this->contentType != "") $this->response->setHeaders("Content-Type", $this->contentType);
        if (is_null($this->data)) $this->data = array_merge(self::$default, []);
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
        $this->response->sentHeaders();
        //var_dump($this->response);
        echo $this->content;
        //return $this->content;
        exit;
    }



    /**
     * static tools
     */

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
    

}