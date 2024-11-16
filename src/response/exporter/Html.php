<?php
/**
 * cgyio/resper Response 输出类
 * Html HTML 页面输出类
 */

namespace Cgy\response\exporter;

use Cgy\Resper;
use Cgy\resper\Responder;
use Cgy\response\Exporter;
use Cgy\Response;
use Cgy\util\Is;
use Cgy\util\Str;
use Cgy\util\Conv;
use Cgy\util\Path;

class Html extends Exporter
{
    public $contentType = "text/html; charset=utf-8";

    //错误 html
    public $errHtml = "<html><body style=\"font-family:monospace;\"><h1>Error: %{title}%</h1><h2>%{msg}%</h2><hr style=\"height:1px;color:#000;overflow:hidden;\">file: %{file}%<br>line: %{line}%</body></html>";

    //准备输出的数据
    public function prepare()
    {
        $d = $this->data["data"];
        if (!empty($d) && isset($d["type"]) && $d["type"]=="Error") {
            //输出错误提示
            $error = $d;
            //调用 系统错误页面，在这些路径中查找 $epn.php 中查找
            $pgps = [];
            $epn = EXPORT_ERRPAGE.EXT;
            $rdp = Responder::current()->path;
            $pgps[] = $rdp."/".$epn;
            $pgps[] = "root/".$epn;
            $pgps[] = "resper/error/".$epn;
            $errpage = Path::exists($pgps,[
                "inDir" => "page"
            ]);

            if (empty($errpage) || !file_exists($errpage)) {
                $this->content = Str::tpl($this->errHtml, $error);
            } else {
                require($errpage);
                //从 输出缓冲区 中获取内容
                $this->content = ob_get_contents();
                //清空缓冲区
                ob_clean();
            }
        } else {
            if (empty($d)) {
                $this->content = "";
            } else {
                if (Is::associate($d)) {
                    $this->content = Conv::a2j($d);
                } else {
                    $this->content = Str::mk($d);
                }
            }
            //var_dump($this->content);
        }
        return $this;
    }


}