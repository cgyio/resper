<?php
/**
 * cgyio/resper 响应工具类
 * 输出前端 警告页面
 */

namespace Cgy\Response;

use Cgy\Resper;
use Cgy\Response;
use Cgy\Error;
use Cgy\module\Mime;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Path;

class Notice 
{
    /**
     * notice 参数
     */
    //类型，可选 success/error/warn/info
    public $type = "error";
    //标题
    public $title = "";
    //信息
    public $msg = "";
    //其他参数值
    public $params = [];
    //$type 类型默认参数
    protected $dftps = [
        "success" => [
            "color" => "#07c160",
            "icon" => "app-checkcircle",
        ],
        "error" => [
            "color" => "#fa5151",
            "icon" => "app-error",
            "file" => "",
            "line" => ""
        ],
        "warn" => [
            "color" => "#fa9d3b",
            "icon" => "app-warning",
        ],
        "info" => [
            "color" => "#1485ee",
            "icon" => "app-info",
        ],
    ];

    //icon source
    public $iconPre = "https://io.cgy.design/icon";

    //source page
    protected $source = "resper/page/notice.php";

    //构造
    public function __construct($type="error", $title="", $msg="", $opt=[])
    {
        if (!isset($this->dftps[$type])) return null;
        $this->type = $type;
        $this->title = $title;
        $this->msg = $msg;
        $ps = $this->dftps[$type];
        $ps = Arr::extend($ps, $opt);
        $ps["type"] = $type;
        $this->params = $ps;
    }

    //写入 error 参数
    public function setError($error = [])
    {
        if ($this->type!="error" && empty($error)) return $this;
        if (is_array($error)) $error = (object)$error;
        $this->title = $error->title;
        $this->msg = $error->msg;
        $this->params = Arr::extend($this->params, [
            "file" => $error->file,
            "line" => $error->line
        ]);
        return $this;
    }

    //处理 params
    protected function fixParams()
    {
        $ps = $this->params;
        $clr = $ps["color"];
        $ico = $ps["icon"];
        if ("http" != substr($ico, 0, 4)) {
            $ico = $this->iconPre."/".trim($ico, "/");
        }
        if (false === strpos($ico, "?fill=")) {
            $ico = $ico."?fill=".substr($clr, 1);
        }

        $this->params = Arr::extend($ps, [
            "title" => $this->title,
            "msg" => $this->msg,
            "icon" => $ico
        ]);
        return $this;
    }

    //获取 notice page 路径
    public function page()
    {
        $pg = Path::find($this->source);
        if (file_exists($pg)) return $pg;
        return null;
    }

    //准备输出
    protected function prepare($extra = [])
    {
        $pg = $this->page();
        if (!file_exists($pg)) {
            trigger_error("custom::Notice页面未找到！", E_USER_ERROR);
        }
        $this->fixParams();
        $ps = Arr::extend($this->params, $extra);
        return [
            "page" => $pg,
            "params" => $ps
        ];
    }

    //生成 notice html content
    public function html($extra = [])
    {
        $pre = $this->prepare($extra);
        $pg = $pre["page"];
        $ps = $pre["params"];
        foreach ($ps as $k => $v) {
            $$k = $v;
        }
        //调用页面
        require($pg);
        //从 输出缓冲区 中获取内容
        $html = ob_get_contents();
        //清空缓冲区
        ob_clean();

        return $html;
    }

    //输出 notice page
    public function export($extra = [])
    {
        $pre = $this->prepare($extra);
        $pg = $pre["page"];
        $ps = $pre["params"];

        Response::page($pg, $ps);
        exit;
    }
}