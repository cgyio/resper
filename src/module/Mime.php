<?php
/**
 * cgyio/resper 工具类
 * 文件类型 MIME
 */

namespace Cgy\module;

use Cgy\Response;
use Cgy\util\Is;
use Cgy\util\Str;

class Mime
{
    //默认mime类型
    public static $default = "application/octet-stream";

    //可选的mime类型前缀
    public static $prefix = [
        "application",
        "text", "image", "audio", "video",
        "font",
    ];

    //可选mime类型
    public static $mimes = [
		// text
		"txt"   => "text/plain",
		"asp"   => "text/plain",
		"aspx"  => "text/plain",
		"jsp"   => "text/plain",
		"vue"   => "text/plain",
        "htm"   => "text/html",
        "html"  => "text/html",
		"tpl"   => "text/html",
        "php"   => "text/html",
        "css"   => "text/css",
        //"scss"    => "text/css",
        //"sass"    => "text/css",
        "scss"  => "text/x-scss",
        "sass"  => "text/x-sass",
        "csv"   => "text/csv",
        "js"    => "text/javascript",
        "json"  => "application/json",
        "map"   => "application/json",
        "xml"   => "application/xml",
        "swf"   => "application/x-shockwave-flash",
        "md"    => "text/x-markdown",

        //font
        "ttf"   => "font/ttf",
        "woff"  => "font/woff",
        "woff2" => "font/woff2",

        // images
        "png"   => "image/png",
        "jpe"   => "image/jpeg",
        "jpeg"  => "image/jpeg",
        "jpg"   => "image/jpeg",
        "gif"   => "image/gif",
        "bmp"   => "image/bmp",
        "webp"  => "image/webp",
        "ico"   => "image/vnd.microsoft.icon",
        "tiff"  => "image/tiff",
        "tif"   => "image/tiff",
        "svg"   => "image/svg+xml",
        "svgz"  => "image/svg+xml",
        "dwg"   => "image/vnd.dwg",

        // archives
        "zip"   => "application/zip",
        "rar"   => "application/x-rar-compressed",
        "7z"    => "application/x-7z-compressed",
        "exe"   => "application/x-msdownload",
        "msi"   => "application/x-msdownload",
        "cab"   => "application/vnd.ms-cab-compressed",

        // audio
        "aac"   => "audio/x-aac",
        "flac"  => "audio/x-flac",
        "mid"   => "audio/midi",
        "mp3"   => "audio/mpeg",
        "m4a"   => "audio/mp4",
        "ogg"   => "audio/ogg",
        "wav"   => "audio/x-wav",
        "wma"   => "audio/x-ms-wma",

        // video
        "3gp"   => "video/3gpp",
        "avi"   => "video/x-msvideo",
        "flv"   => "video/x-flv",
        "mkv"   => "video/x-matroska",
        "mov"   => "video/quicktime",
        "mp4"   => "video/mp4",
        "m4v"   => "video/x-m4v",
        "qt"    => "video/quicktime",
        "wmv"   => "video/x-ms-wmv",
        "webm"  => "video/webm",

        // adobe
        "pdf"   => "application/pdf",
        "psd"   => "image/vnd.adobe.photoshop",
        "ai"    => "application/postscript",
        "eps"   => "application/postscript",
        "ps"    => "application/postscript",

        // ms office
        "doc"   => "application/msword",
        "docx"  => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "rtf"   => "application/rtf",
        "xls"   => "application/vnd.ms-excel",
        "xlsx"  => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        "ppt"   => "application/vnd.ms-powerpoint",
        "pptx"  => "application/vnd.openxmlformats-officedocument.presentationml.presentation",

        // open office
        "odt"   => "application/vnd.oasis.opendocument.text",
        "ods"   => "application/vnd.oasis.opendocument.spreadsheet",
    ];

    //预定义的可处理类型
    public static $processable = [
        "plain" => [
            "txt",
            "asp",
            "aspx",
            "jsp",
            "vue",
            "htm",
            "html",
            "tpl",
            "php",
            "css","scss","sass",
            "js","json","map",
            "md",
            "svg"
        ],

        "image" => [
            "jpg","jpe","jpeg",
            "png",
            "gif",
            "bmp",
            "webp",

            'svg'
        ],

        "audio" => [
            //"aac",
            //"flac",
            "mp3",
            "m4a",
            "ogg"
        ],

        "video" => [
            "mp4",
            "m4v",
            "mov",
            //"mkv",
            //"avi",
            //"wmv",
            //"webm"
        ],

        "office" => [
            "doc","docx",
            "xls","xlsx",
            "ppt","pptx",
            "csv",
            "pdf",
            "txt"
        ]
    ];

    /**
     * 根据 给定的条件 获取 extension 文件类型（后缀名）
     * @param String $key 可以是 文件路径 / mime 字符串
     * @return String extension  or  null
     */
    public static function getExt($key = "")
    {
        if (!Is::nemstr($key)) return null;
        if (self::isMimeStr($key)) {
            $ms = self::$mimes;
            $fms = array_flip($ms);
            $k = strtolower($key);
            if (isset($fms[$k])) return $fms[$k];
        }
        if (Str::has($key, ".")) {
            if (Str::hasAny($key, DS, "/", "\\")) {
                $info = pathinfo($kay);
                if (isset($info["extension"])) return strtolower($info["extension"]);
            } else {
                $ka = explode(".",$key);
                return strtolower(array_slice($ka, -1)[0]);
            }
        }
        return null;
    }

    /**
     * 获取全部支持的 extension
     * @return Array indexed 数组
     */
    public static function getExts()
    {
        return array_keys(self::$mimes);
    }

    /**
     * 获取全部 processable 类型 exts
     * @param String $ptypes 指定要返回的 processable types 不指定则返回所有
     * @return Array indexed
     */
    public static function getProcessableExts(...$ptypes)
    {
        $ps = self::$processable;
        if (!Is::nemarr($ptypes)) {
            $ptypes = array_keys($ps);
        }
        $exts = [];
        foreach ($ptypes as $i => $v) {
            $v = strtolower($v);
            if (!isset($ps[$v])) continue;
            $exts = array_merge($exts, $ps[$v]);
        }
        return $exts;
    }
    
    /**
     * 根据文件 ext，返回 mime
     * @return String
     */
    public static function getMime($ext = "")
    {
        if (!Is::nemstr($ext) || !self::support($ext)) return self::$default;
        return self::$mimes[strtolower($ext)];
    }

    /**
     * 根据文件 ext，获取 processable 类型 包括：
     * plain,image,video,audio,office, default
     * @param String $ext extension
     * @return String | null
     */
    public static function getProcessableType($ext = "")
    {
        if (!Is::nemstr($ext) || !self::support($ext)) return null;
        $psb = self::$processable;
        $ext = strtolower($ext);
        foreach ($psb as $k => $m) {
            if (in_array($ext, $m)) return $k;
        }
        return null;
    }

    /**
     * 判断 给定的字符串 是否合法的 mime 
     * @param String $mime 给定的字符串
     * @return Bool
     */
    public static function isMimeStr($mime = "")
    {
        if (!Is::nemstr($mime)) return false;
        if (!Str::has($mime, "/")) return false;
        $ma = explode("/", strtolower($mime));
        if (!in_array($ma[0], self::$prefix)) return false;
        return true;
    }

    /**
     * 判断是否支持指定的 ext
     * @param String $ext 后缀名
     * @return Bool
     */
    public static function support($ext = "")
    {
        if (!Is::nemstr($ext)) return false;
        return isset(self::$mimes[strtolower($ext)]);
    }

    /**
     * 判断给定的 ext 是否 processable
     * @param String $ext
     * @return Mixed 如果 processable 则返回 processableType 否则返回 false
     */
    public static function processable($ext = "")
    {
        $type = self::getProcessableType($ext);
        if (!Is::nemstr($type)) return false;
        return $type;
    }

    /**
     * 检查 ext 是否支持直接输出
     * @param String $ext
     * @return Boolean
     */
    public static function exportable($ext = "")
    {
        $ext = strtolower($ext);
        if (!self::support($ext)) return false;
        $mime = self::getMime($ext);
        if (in_array($ext, ["js","json","xml","swf"])) return true;
        if (in_array($ext, ["csv","psd"])) return false;
        if ($mime == self::$default) return false;
        $pexts = self::getProcessableExts("plain","image","audio","video");
        if (in_array($ext, $pexts)) return true;
        return false;
    }

    /**
     * 设置 mime header 到 Response::$current
     * @param String $ext
     * @param String $fn 当文件需要下载时，指定文件名
     * @return void
     */
    public static function setHeaders($ext = "", $fn = "")
    {
        $mime = self::getMime($ext);
        Response::setHeaders(
            "Content-Type", 
            $mime . (self::isPlain($ext) ? "; charset=utf-8" : "")
        );
        if (!self::exportable($ext)) {
            Response::setHeaders(
                "Content-Disposition", 
                "attachment; filename=\"".$fn."\""
            );
        }
    }



    /**
     * __callStatic魔术方法
     */
    public static function __callStatic($key, $args)
    {
        /**
         * 检查是否某个processable类型，参数为 ext
         * Mime::isPlain("js") == true
         * @return Boolean
         */
        if (substr($key, 0, 2) == "is") {
            $pm = strtolower(str_replace("is", "", $key));
            $ps = self::$processable;
            if (!isset($ps[$pm]) || !Is::nemarr($ps[$pm])) return false;
            if (empty($args)) return false;
            $ext = strtolower($args[0]);
            return in_array($ext, $ps[$pm]);
        }


        return null;
    }

    /**
     * 获取远程文件的 mime
     * @param String $url
     * @return String mime
     */
    public static function getRemoteFileMime($url="")
    {
        if (!Is::nemstr($url)) return "";
        $https = strpos($url,"https://")!==false;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);

        $rst = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        return $rst;
    }








    


}