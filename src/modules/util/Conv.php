<?php
/**
 * cgyio/resper 工具类
 * 类型转换工具
 */

namespace Cgy\util;

use Cgy\Util;
use Cgy\util\Is;
use Cgy\util\Str;

class Conv extends Util 
{
    /**
     * array --> json
     * @param Array $var
     * @return String
     */
    public static function a2j($var = [])
    {
        if (Is::nemarr($var)) {
            return json_encode($var, JSON_UNESCAPED_UNICODE);
        }
        return "{}";
    }

    /**
     * json --> array
     * @param String $var
     * @return Array
     */
    public static function j2a($var = null)
    {
        if (!Is::json($var)) return [];
        return json_decode($var, true);
    }

    /**
     * array  -->  queryString
     * @param Array $var associate array
     * @return String
     */
    public static function a2u($var = [])
    {
        if (!Is::nemarr($var)) {
            return "";
        } else {
            $vars = [];
            foreach ($var as $k => $v) {
                if (empty($v)) continue;
                $vars[] = $k."=".urlencode(str($v));
            }
            return empty($vars) ? "" : implode("&", $vars);
        }
    }

    /**
     * queryString  -->  array
     * @param String $var queryString: foo=bar&jaz=tom
     * @return Array
     */
    public static function u2a($var = null)
    {
        if (!Is::nemstr($var) || !Is::query($var)) return [];
        $rst = [];
        if (false === strpos($var, "&")){
            $sarr = explode("=", $var);
            $sarr[1] = urldecode($sarr[1]);
            if (Is::ntf($sarr[1])) {
                eval("\$v = ".$sarr[1].";");
                $rst[$sarr[0]] = $v;
            } else {
                $rst[$sarr[0]] = $sarr[1];
            }
        } else {
            $sarr = explode("&", $var);
            for ($i=0; $i<count($sarr); $i++) {
                $rst = array_merge($rst, self::u2a($sarr[$i]));
            }
        }
        return $rst;
    }

    /**
     * array --> xml
     * @param Array $var
     * @param DOMDocument $dom 向指定的 xml dom 中 append 用于递归
     * @param DOMElement $item 向指定的 xml element 中 append 用于递归
     * @return String xml
     */
    public static function a2x($var = [], $dom = null, $item = null)
    {
        if (!Is::associate($var, true)) return "";
        if (is_null($dom)) {
            $dom = new DOMDocument("1.0");
        }
        if (is_null($item)) {
            $item = $dom->createElement("root"); 
            $dom->appendChild($item);
        }
        foreach ($var as $key => $val) {
            $itemx = $dom->createElement(is_string($key) ? $key : "item");
            $item->appendChild($itemx);
            if (!is_array($val)) {
                $text = $dom->createTextNode($val);
                $itemx->appendChild($text);
            } else {
                self::a2x($val, $dom, $itemx);
            }
        }
        return $dom->saveXML();
    }

    /**
     * xml --> array
     * @param String $var xml 代码
     * @return Array
     */
    public static function x2a($var = null)
    {
        if (!Is::xml($var)) return [];
        $xo = simplexml_load_string($var);
        $json = json_encode($xo);
        return json_decode($json, TRUE);
    }

    /**
     * array --> html property
     * like:  `[pre-]data="value" [pre-]data2="value" ...`
     * @param Array $var
     * @param String $pre 属性前缀
     * @return String
     */
    public static function a2p($var = [], $pre = "")
    {
        if (!Is::associate($var, true) || empty($var)) return "";
        if (!Is::nemstr($pre)) {
            $pre = "";
        } else {
            if (substr($pre, -1)!=="-") {
                $pre .= "-";
            }
        }
        $rtn = [];
        foreach ($var as $k => $v) {
            $rtn[] = $pre.$k.'="'.Str::mk($v).'"'; 
        }
        return implode(" ", $rtn);
    }


    /**
     * 其他
     */

    /**
     * 1024 * 1024  =>  1 Mb
     * 文件 size(int) 转为 字符串
     * @param Int $size
     * @return String
     */
    public static function sizeToStr($size = 0)
    {
        if ($size < 1024) {
            return $size . " bety";
        } elseif ($size < 1024 * 1024) {
            return round($size/1024, 2) . " Kb";
        } elseif ($size < 1024 * 1024 * 1024) {
            return round($size/(1024 * 1024), 2) . " Mb";
        } elseif ($size < 1024 * 1024 * 1024 * 1024) {
            return round($size/(1024 * 1024 * 1024), 2) . " Gb";
        } else {
            return round($size/(1024 * 1024 * 1024 * 1024), 2) . " Tb";
        }
    }

}