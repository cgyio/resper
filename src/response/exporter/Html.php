<?php
/**
 * cgyio/resper Response 输出类
 * Html HTML 页面输出类
 */

namespace Cgy\response\exporter;

use Cgy\Resper;
use Cgy\Response;
use Cgy\response\Exporter;
use Cgy\response\Notice;
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
            if (EXPORT_ERRPAGE!="") {
                //调用预定义的错误页面 EXPORT_ERRPAGE
                $pgps = [];
                $epn = EXPORT_ERRPAGE.EXT;
                $rdp = Resper::$resper->path;
                $pgps[] = $rdp."/".$epn;
                $pgps[] = $rdp."/error"."/".$epn;
                $pgps[] = "root/".$epn;
                $pgps[] = "root/error/".$epn;
                $pgps[] = "resper/error/".$epn;
                $errpage = Path::exists($pgps,[
                    "inDir" => "page"
                ]);
                if (file_exists($errpage)) {
                    $_Response = $this->response;
                    require($errpage);
                    //从 输出缓冲区 中获取内容
                    $this->content = ob_get_contents();
                    //清空缓冲区
                    ob_clean();
                    return $this;
                }
            }
            //调用 Notice 输出类
            $notice = new Notice("error");
            $notice->setError($error);
            $this->content = $notice->html();

            /*if (empty($errpage) || !file_exists($errpage)) {
                $this->content = Str::tpl($this->errHtml, $error);
            } else {
                $_Response = $this->response;
                require($errpage);
                //从 输出缓冲区 中获取内容
                $this->content = ob_get_contents();
                //清空缓冲区
                ob_clean();
            }*/
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