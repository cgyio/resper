<?php
/**
 * cgyio/resper Response 输出类
 * Pause 暂停输出类
 * WEB_PAUSE == true 时 调用此类
 */

namespace Cgy\response\exporter;

use Cgy\Resper;
use Cgy\Response;
use Cgy\response\Exporter;
use Cgy\response\Notice;
use Cgy\module\Mime;
use Cgy\util\Str;
use Cgy\util\Conv;

class Pause extends Exporter 
{
    public $contentType = "text/plain; charset=utf-8";

    //实际输出的 format 
    protected $realFormat = "html";

    //预定义的 pause msg
    protected $pauseMsg = "Website Paused!";

    //准备输出的数据
    public function prepare()
    {
        //查找输出格式参数
        $this->realFormat = Resper::$request->gets->format(EXPORT_FORMAT);

        if ($this->realFormat != "json") {
            //如果输出格式不是 json 则显示 pause 页面
            if (EXPORT_PAUSEPAGE!="") {
                //查找预定义的 EXPORT_PAUSEPAGE
                $pgps = [];
                $ppn = EXPORT_PAUSEPAGE.EXT;
                $rdp = Resper::$resper->path;
                $pgps[] = $rdp."/".$ppn;
                $pgps[] = $rdp."/error"."/".$epn;
                $pgps[] = "root/".$ppn;
                $pgps[] = "root/error/".$ppn;
                $pgps[] = "resper/error/".$ppn;
                $ppage = Path::exists($pgps,[
                    "inDir" => "page"
                ]);
    
                if (file_exists($ppage)) {
                    $_Response = $this->response;
                    require($ppage);
                    //从 输出缓冲区 中获取内容
                    $this->content = ob_get_contents();
                    //清空缓冲区
                    ob_clean();
                    return $this;
                }
            }
            
            //调用 Notice 输出类
            $notice = new Notice("warn", "站点已暂停", $this->pauseMsg);
            //$notice->setError($error);
            $this->content = $notice->html();
        } else {
            //输出 json 
            $this->content = Conv::a2j([
                "error" => false,
                "errors" => [],
                "data" => [
                    "paused" => true,
                    "msg" => $this->pauseMsg
                ]
            ]);
        }
        //$this->content = "Website Paused!";
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
        $ext = $this->realFormat=="json" ? "json" : "html";
        Mime::setHeaders($ext);
        Response::sentHeaders();
        echo $this->content;
        exit;
    }
}