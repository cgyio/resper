<?php
/**
 * cgyio/resper 资源输出类
 * 输出 JS
 */

namespace Cgy\module\resource;

use Cgy\module\Resource;
use Cgy\module\Mime;
use Cgy\Response;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;

use MatthiasMullie\Minify;  //JS/CSS文件压缩

class Js extends Resource
{
    /**
     * @override export
     * export plain file
     * @return void exit
     */
    public function export($params = [])
    {
        $params = empty($params) ? $this->params : Arr::extend($this->params, $params);

        //get resource content
        $this->getContent();

        //process
        $cnt = $this->content;
        $ps = $this->params;
        if (isset($ps["export"])) {
            if (isset($ps["es6fix"]) && $ps["es6fix"] !== "no") {   
                //针对一些 module 的不规范输出方式与 es6 不匹配的，使用 module.exports 输出
                $cnt = "let module = {exports:{}},exports = {};\r\n".$cnt;
                $cnt .= ";\r\nlet ".$ps["export"]." = module.exports; export default ".$ps["export"].";";
            } else if (isset($ps["wxfix"]) && $ps["wxfix"] !== "no") {  
                //微信jssdk文件处理
                $carr = explode("(this,", $cnt);
                $cnt = implode("(cgy.global(),", $carr);
            } else if (isset($ps["fix"])) {
                $exp = $ps["export"];
                switch ($ps["fix"]) {
                    case "iconfont" :
                        $c = substr($cnt, 1);   //去除开头 ！
                        $c = substr($c, 0, strlen($c)-9);   //去除尾部 (window);
                        $cnt = "export default ".$c;
                        break;
                }
            } else {
                $cnt .= ";\r\nexport default ".$ps["export"].";";
            }
        }
        if (isset($ps["min"]) && $ps["min"] !== "no") { //压缩
            $minifier = new Minify\JS();
            $minifier->add($cnt);
            $cnt = $minifier->minify();
        }
        $this->content = $cnt;

        //输出之前，如果需要，保存文件到本地
        $this->saveRemoteToLocal();

        //sent header
        //$this->sentHeader();
        Mime::setHeaders($this->rawExt, $this->rawBasename);
        //var_dump(Response::$current->headers);
        Response::sentHeaders();

        //echo
        echo $this->content;
        exit;
    }
}