<?php
/**
 * cgyio/resper 核心类
 * Resource 资源处理类
 */

namespace Cgy\module;

use Cgy\Module;
use Cgy\module\Mime;
use Cgy\module\resource\Builder;
use Cgy\module\resource\Compiler;
use Cgy\request\Url;
use Cgy\request\Curl;
use Cgy\Response;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Path;
use Cgy\util\Cls;
use Cgy\util\Num;

use MatthiasMullie\Minify;  //JS/CSS文件压缩

class Resource/* extends Module*/
{
    //引入trait
    //use staticCreate;

    //资源类型
    public static $resTypes = [
        "remote",       //远程资源
        "build",        //通过 build 生成的纯文本文件，如合并多个 js 文件
        "export",       //通过调用 resper 类 动态生成内容的文件
        "required",     //通过 require 本地 php 文件动态生成内容
        "local",        //真实存在的本地文件
        "compile",      //通过编译本地文件，生成的文件
        "create",       //通过直接输入文本内容，创建纯文本文件 
    ];

    /**
     * 资源参数
     */
    //rawPath，原始调用路径，host/src/xxxx/xxxx.ext 中 src 之后的内容
    public $rawPath = "";

    //rawExt，原始调用输出 extension，即要输出的资源 extension
    public $rawExt = "";
    public $rawMime = "";

    //要输出资源的文件名，来自 rawPath
    public $rawBasename = "";
    public $rawFilename = "";

    //realPath，通过原始调用路径解析获得实际资源路径
    //可以是：远程 url，本地 php 文件，本地文件夹，本地文件
    public $realPath = "";

    //资源类型
    public $resType = "";

    //资源处理对象，根据 rawExt 指定
    //public $processor = null;

    //资源内容生成器
    //public $creator = null;

    //资源内容
    public $content = null;

    //资源输出器
    //public $exporter = null;

    //资源路径信息，从 rawPath 与 realPath 解析得到
    public $extension = "";
    public $dirname = "";
    public $basename = "";
    public $filename = "";

    //params，资源调用get参数
    public $params = [];

    /**
     * 构造
     */
    public function __construct($opt = [])
    {
        if (empty($opt) || !is_array($opt) || !Is::associate($opt)) return null;
        foreach ($opt as $k => $v) {
            if (property_exists($this, $k)) {
                $this->$k = is_array($v) ? Arr::extend($this->$k, $v) : $v;
            }
        }
        $this->rawMime = Mime::getMime($this->rawExt);
        $pinfo = pathinfo($this->rawPath);
        $this->rawBasename = $pinfo["basename"];
        $this->rawFilename = $pinfo["filename"];

        if (!empty($this->realPath) && file_exists($this->realPath)) {
            $rinfo = pathinfo($this->realPath);
            $this->extension = $rinfo["extension"];
            $this->dirname = $rinfo["dirname"];
            $this->basename = $rinfo["basename"];
            $this->filename = $rinfo["filename"];
        }

        $this->afterCreated();
    }

    /**
     * after resource created
     * if necessary, derived class should override this method
     * @return Resource $this
     */
    protected function afterCreated()
    {
        //do sth.

        return $this;
    }

    /**
     * get resource content by $resType
     * if necessary, derived class should override this method
     * @return String $this->content
     */
    protected function getContent(...$args)
    {
        $rtp = $this->resType;
        $m = "get".ucfirst($rtp)."Content";
        if (method_exists($this, $m)) {
            $this->$m(...$args);
        }
        return $this->content;
    }

    /**
     * get[$resType]Content methods
     * if necessary, derived class should override these methods
     * @return void
     */
    protected function getRemoteContent() {
        $isHttps = strpos($this->realPath, "https")!==false;
        if ($isHttps) {
            $this->content = Curl::get($this->realPath, "ssl");
        } else {
            $this->content = Curl::get($this->realPath);
        }
    }
    protected function getBuildContent() {
        $builder = new Builder($this);
        $builder->build();
    }
    protected function getExportContent() {
        $this->content = Curl::get($this->realPath);
    }
    protected function getRequiredContent() {
        //@ob_start();
        require($this->realPath); 
        $this->content = ob_get_contents(); 
        ob_clean();
    }
    protected function getLocalContent() {
        $ext = $this->rawExt;
        if (Mime::isPlain($ext)) {
            $this->content = file_get_contents($this->realPath);
            //var_dump($this->content);
        } else {
            $this->content = file_get_contents($this->realPath);
        }
    }
    protected function getCompileContent() {
        $compiler = new Compiler($this);
        $compiler->compile();
    }
    protected function getCreateContent($content = null) {
        if (Is::nemstr($content)) {
            $this->content = $content;
        }
    }



    /**
     * 解析调用路径 rawPath（通常来自于url），返回生成的资源参数
     * 未找到资源返回 null
     * @param String $path 资源调用路径 rawPath
     * @return Array | null
     */
    protected static function parse($path = "")
    {
        if (!Is::nemstr($path)) return null;
        $params = [];
        $rawPath = str_replace("/", DS, $path);
        
        if (strpos($rawPath, '.min.')) $params["min"] = "yes";

        if (file_exists($rawPath) || is_dir($rawPath)) {
            //给出的 path 参数为真实存在的文件或路径，直接返回
            $resType = is_dir($rawPath) ? "build" : "local";
            $realPath = $rawPath;
            $rawExt = self::getExtFromPath($rawPath);
        } else {
            //$rawPath = trim(str_replace("/", DS, $path), DS);
            $rawPath = trim(str_replace("/", DS, str_replace("://", "/", $path)), DS);
            $rawExt = self::getExtFromPath($rawPath);
            $rawArr = explode(DS, $rawPath);
            if (in_array(strtolower($rawArr[0]), ["http", "https"])) {
                //远程文件  https://host/src/https/foo.com/bar/jaz
                $resType = "remote";
                $realPath = strtolower($rawArr[0])."://".implode("/", array_slice($rawArr, 1));
            } else if (strtolower($rawArr[0]) == "export") {
                //通过调用本地 resper 动态生成内容的文件  https://host/src/export/foo/bar
                $resType = "export";
                $temp = implode("/", array_slice($rawArr, 1));
                $info = pathinfo($temp);
                $realPath = Url::mk($info["dirname"]."/".$info["filename"])->full;
            } else {
                $realPath = Path::find($rawPath);
                if (!is_null($realPath)) {
                    //本地真实存在文件  https://host/src/app/index/foo.json
                    $resType = "local";
                } else {
                    if (Str::has($rawPath, ".min.")) {
                        //min标记转换成  params["min"] = "yes"
                        $rawPath = str_replace(".min.", ".", $rawPath);
                        $params["min"] = "yes";
                    }
                    $realPath = Path::find($rawPath, ["checkDir" => true]);
                    if (!is_null($realPath)) {
                        if (is_dir($realPath)) {
                            //通过 build 生成的纯文本文件
                            $resType = "build";
                        } else {
                            //本地真实存在的文件
                            $resType = "local";
                        }
                    } else {
                        $realPath = Path::find($rawPath.EXT);
                        if (!is_null($realPath)) {
                            //通过 require 本地 php 文件动态生成内容
                            $resType = "required";
                        } else {
                            $realPath = Compiler::getCompilePath($rawPath);
                            if (!is_null($realPath)) {
                                //通过编译本地文件，生成纯文本内容
                                $resType = "compile";
                            } else {
                                return null;
                            }
                        }
                    }
                }
            }
        }

        if (is_null($rawExt)) return null;

        $rst = [
            "rawPath"   => $rawPath,
            "rawExt"    => $rawExt,
            "resType"   => $resType,
            "realPath"  => $realPath,
            "params"    => $params
        ];
        return $rst;
    }

    /**
     * 外部创建 Resource 入口方法
     * Resource::create($path, $params)
     * @param String $path      call path of resource (usually from url)
     * @param Array $param      resource process params
     * @return Resource instance or null
     */
    public static function create($path = "", $params = [])
    {
        if (isset($params["content"]) && Is::nemstr($params["content"])) {
            //通过直接输入内容，创建纯文本文件
            $content = $params["content"];
            unset($params["content"]);
            $params = Is::nemarr($params) ? $params : [];
            if (!empty($_GET)) $params = Arr::extend($_GET, $params);
            if (strpos($path, ".")===false) $path .= ".txt";
            $p = [
                "rawPath"   => $path,
                "rawExt"    => Mime::getExt($path),
                "resType"   => 'create',
                "realPath"  => '',
                "params"    => $params,

                "content"   => $content
            ];
        } else {
            //通过输入 调用路径 rawPath 创建 resource 实例
            if (Is::indexed($path)) $path = implode(DS, $path);
            $p = self::parse($path);
            if (is_null($p)) return null;
            $params = Is::nemarr($params) ? $params : [];
            if (!empty($_GET)) $params = Arr::extend($_GET, $params);
            if (!isset($p["params"]) || !is_array($p["params"])) $p["params"] = [];
            $p["params"] = Arr::extend($p["params"], $params);
        }
        //var_dump($p);
        //find specific resource class
        $cls = self::resCls($p);
        //var_dump($cls);
        if (is_null($cls)) return null;
        $res = new $cls($p);
        return $res;
    }

    /**
     * get resource class by parsed options
     * @param Array $option     option array returned by self::parse()
     * @return String full class name
     */
    protected static function resCls($option = [])
    {
        if (!Is::nemarr($option)) return Cls::find("module/Resource");
        $clss = [];
        $ext = $option["rawExt"];
        $clss[] = "module/resource/".ucfirst($ext);
        $processableType = Mime::getProcessableType($ext);
        if (!is_null($processableType)) {
            $clss[] = "module/resource/".ucfirst($processableType);
        } else {
            //$clss[] = "resource/Stream";
            $clss[] = "module/resource/Download";
        }
        $clss[] = "module/Resource";
        //var_dump($clss);
        return Cls::find($clss);
    }



    /**
     * export
     */

    /**
     * export resource directly
     * if necessary, derived class should override this method
     * @return void exit
     */
    public function export($params = [])
    {
        //var_dump(Mime::isPlain($this->rawExt));exit;
        //get resource content
        $this->getContent();

        //输出之前，如果需要，保存文件到本地
        $this->saveRemoteToLocal();
        
        //sent header
        //$this->sentHeader();
        Mime::setHeaders($this->rawExt, $this->rawBasename);
        Response::$current->header->sent();
        
        //echo
        echo $this->content;
        exit;

        /*
        if (is_null($this->creator) || is_null($this->exporter)) {
            Response::code(404);
        } else {
            $this->creator->create();
            $this->exporter->export($params);
        }
        exit;
        */
    }

    /**
     * return content
     * 用于非输出到前端时调用，相当于 export
     * @return Mixed
     */
    public function returnContent($params = [])
    {
        $params = empty($params) ? $this->params : Arr::extend($this->params, $params);
        //get resource content
        $this->getContent();
        return $this->content;
    }

    /**
     * sent header before export echo
     * @param String $ext       if export resource in different extension
     * @return Resource $this
     */
    protected function sentHeader($ext = null)
    {
        if (!Is::nemstr($ext) && $ext != $this->rawExt) {
            $basename = $this->rawFilename.".".$ext;
        } else {
            $ext = $this->rawExt;;
            $basename = $this->rawBasename;
        }
        Mime::header($ext, $basename);
        Response::headersSent();
        return $this;
    }

    /**
     * 输出资源 info
     */
    public function info()
    {
        return [
            "rawPath"   => $this->rawPath,
            "rawExt"    => $this->rawExt,
            "resType"   => $this->resType,
            "realPath"  => $this->realPath,
            "params"    => $this->params,
            "rawBasename"    => $this->rawBasename,
            "rawFilename"    => $this->rawFilename

        ];
    }



    /**
     * save file to local
     */
    public function saveToLocal($filepath)
    {
        $fo = @fopen($filepath, "w");
        fwrite($fo, $this->getContent());
        fclose($fo);
    }

    /**
     * 如果 resType == remote 且 $params["saveToLocal"] == "file path"
     * 将远程文件保存到本地
     */
    public function saveRemoteToLocal()
    {
        if (isset($this->params["saveToLocal"]) && !empty($this->params["saveToLocal"])) {
            $cnt = $this->content;
            $lcf = $this->params["saveToLocal"];
            $fo = @fopen($lcf, "w");
            fwrite($fo, $cnt);
            fclose($fo);
        }
    }




    /**
     * tools
     */

    /**
     * 根据 rawPath 或 url 获取文件 ext
     * foo/bar.js?version=3.0  => js
     * @return String | null
     */
    public static function getExtFromPath($path = "")
    {
        if (!Is::nemstr($path)) return null;
        if (Str::has($path, "?")) {
            $pstr = explode("?", $path)[0];
        } else {
            $pstr = $path;
        }
        $pathinfo = pathinfo($pstr);
        if (isset($pathinfo["extension"])) return strtolower($pathinfo["extension"]);
        if (strpos($pstr, "http".DS)!==false || strpos($pstr, "https".DS)!==false) {
            $pu = str_replace(DS,"/",$pstr);
            if (strpos($pstr, "http/")!==false) {
                $pu = str_replace("http/","http://", $pu);
            } else {
                $pu = str_replace("https/","https://", $pu);
            }
            $finfo = self::getRemoteFileInfo($pu);
            if (isset($finfo["ext"])) return $finfo["ext"];
        }
        return null;
        //return isset($pathinfo["extension"]) ? strtolower($pathinfo["extension"]) : null;
    }

    /**
     * file_exists，支持远程文件
     * @return Boolean
     */
    public static function exists($file = "")
    {
        if (Is::remote($file)) {
            $hds = get_headers($file);
            return in_array("HTTP/1.1 200 OK", $hds);
        } else {
            return file_exists($file);
        }
    }

    /**
     * 解析远程文件 文件头 获取文件信息，ext/size/ctime/thumb/...
     * 通过 get_headers 获取的
     * @param String $url 文件 url
     * @return Array
     */
    public static function getRemoteFileInfo($url="")
    {
        if (!Is::nemstr($url)) return [];
        $fh = get_headers($url);
        //var_dump($fh);
        $hd = [];
        for ($i=0;$i<count($fh);$i++) {
            $fhi = trim($fh[$i]);
            if (strpos($fhi, ": ")!==false) {
                $fa = explode(": ", $fhi);
                if (count($fa)<2) {
                    $hd["Http"] = $fa[0];
                } else {
                    $k = array_shift($fa);
                    $v = implode(": ", $fa);
                    $hd[$k] = $v;
                }
            }
        }
        $fi = [];
        $fi["headers"] = $hd;
        $mime = $hd["Content-Type"];
        $fi["mime"] = $mime;
        $ext = Mime::getExt($mime);
        $fi["ext"] = $ext;
        $size = $hd["Content-Length"]*1;
        $fi["size"] = $size;
        $fi["sizestr"] = Num::file_size($size);
        $fi["ctime"] = strtotime($hd["Last-Modified"]);
        $pcs = Mime::$processable;
        $ks = array_keys($pcs);
        foreach ($ks as $ki) {
            $fi["is".ucfirst($ki)] = in_array($ext, $pcs[$ki]);
        }
        if ($fi["isImage"]===true) {
            $fi["thumb"] = "/src"."/".str_replace("https://", "https/", str_replace("http://", "http/", $url))."?thumb=auto";
        }
        $name = array_pop(explode("/", $url));
        if (strpos($name, ".$ext")!==false) {
            $fi["basename"] = $name;
            $fi["name"] = str_replace(".$ext", "", $name);
        } else {
            $fi["name"] = $name;
            $fi["basename"] = "$name.$ext";
        }
        return $fi;
    }

    /**
     * 计算调用路径，  realPath 转换为 relative
     * @return String
     */
    public static function toUri($realPath = "")
    {
        $realPath = str_replace("/", DS, $realPath);
        $relative = path_relative($realPath);
        if (is_null($relative)) return "";
        return $relative;
    }

    /**
     * 计算调用路径，realPath 转换为 /src/foo/bar...
     */
    public static function toSrcUrl($realpath = "")
    {
        $relative = self::toUri($realpath);
        if ($relative=="") return "";
        $rarr = explode(DS, $relative);
        $spc = explode("/", str_replace(",","/",ASSET_DIRS));
        //$spc = array_merge($spc,["app","cphp"]);
        $spc = array_merge($spc,["app","atto"]);
        $nrarr = array_diff($rarr, $spc);
        $src = implode("/", $nrarr);
        $src = "/src".($src=="" ? "" : "/".$src);
        return $src;
    }

    /**
     * 压缩 JS/CSS/JSON 文件
     */
    public static function minify($cnt, $type="js")
    {
        if ($type=="js" || $type=="json") {
            $minifier = new Minify\JS();
        } else if ($type=="css") {
            $minifier = new Minify\CSS();
        } else {
            return $cnt;
        }
        $minifier->add($cnt);
        return $minifier->minify();
    }
    




}