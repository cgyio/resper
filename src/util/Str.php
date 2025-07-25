<?php
/**
 * cgyio/resper 工具类
 * Str 字符串处理
 */

namespace Cgy\util;

use Cgy\Util;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Conv;

class Str extends Util 
{

    /**
     * conv 方法
     * 任意类型 转为 string
     * @param Mixed $var
     * @return String
     */
    public static function mk($var = null)
    {
        if (empty($var)) {
            if (is_null($var)) return "null";
            if (is_bool($var)) return $var ? "true" : "false";
            return (string)$var;
        } else if (is_bool($var)) {
            return $var ? "true" : "false";
        } else if (is_array($var)) {
            return Conv::a2j($var);
        } else if (is_string($var)) {
            if (substr(strtolower($var), 0, 5) == "nonce") {    //生成8位随机字符串
                return self::nonce(8);
            }
            return $var;
        } else if (is_object($var)) {
            return Conv::a2j(Arr::mk($var));
        } else {
            return (string)$var;
        }
    }
    
    /**
     * each，正则匹配，并循环
     * @param String $str
     * @param String $reg 要匹配的正则表达式
     * @param Closure $closure 对每个匹配项执行回调函数
     * @return Mixed false  or  []
     */
    public static function each($str = "", $reg = "", $closure = null)
    {
        if (!Is::nemstr($str)) return false;
        preg_match_all($reg, $str, $matches);
        $ms = $matches[0];
        if (!empty($ms)) {
            $rst = [];
            foreach ($ms as $k => $v) {
                $rstk = $closure($v, $k);
                if ($rstk === false) {
                    break;
                } else if ($rstk === true) {
                    continue;
                }
                $rst[] = $rstk;
            }
            return $rst;
        }
        return false;
    }

    /**
     * 分割字符串
     * delimiter 为字符时调用 explode
     * delimiter 为 int 时调用 str_split
     * @param String $str
     * @param Mixed $delimiter 分割符 或 每段长度
     * @return Array
     */
    public static function split($str = "", $delimiter = null)
    {
        if (!Is::nemstr($str)) return [];
        if (Is::nemstr($delimiter)) {
            $arr = explode($delimiter, $str);
        } else {
            if (is_null($delimiter)) {
                //分割成单字符数组
                $arr = str_split($str);   
            } else if (is_int($delimiter)) {
                //分割成 指定长度 字符数组
                $arr = str_split($str, (int)$delimiter);
            } else {
                $arr = [ $str ];
            }
        }
        return $arr;
    }

    /**
     * 字符串(正则) 替换
     * @param String $search 要查找的 字符串 或 正则 可以是数组
     * @param String $replace 要替换为新字符串
     * @param String $str
     * @return String
     */
    public static function replace($search, $replace, $str)
    {
        //[ search, search, ...]
        if (Is::nemarr($search)) {
            for($i=0;$i<count($search);$i++) {
                if (!Is::nemstr($search[$i])) continue;
                $str = self::replace($search[$i], $replace, $str);
            }
            return $str;
        }
        //search != string
        if (!Is::nemstr($search)) return $str;

        //执行替换
        if (strlen($search)>2 && substr($search, 0, 1) == "/" && substr($search, -1) == "/") {
            return preg_replace($search, $replace, $str);
        } else {
            return str_replace($search, $replace, $str);
        }
    }

    /**
     * 批量执行 replace()
     * @param Array $kv replace() 方法前2个参数组成的 2维数组 [ [$search, $replace], [], ... ]
     * @param String $str 要操作的字符串
     * @return String
     */
    public static function replaceAll($kv, $str)
    {
        for ($i=0;$i<count($kv);$i++) {
            $ki = $kv[$i];
            $str = self::replace($ki[0], $ki[1], $str);
        }
        return $str;
    }

    /**
     * case 转换
     */

    /**
     * to camel case
     * foo-bar-jaz  -->  fooBarJaz
     * $ucfirst==true 则 foo-bar-jaz  -->  FooBarJaz
     * 可以用作分隔符的字符：- _ / , \ 空格
     * @param String $str
     * @param Bool $ucfirst 首字符是否大写
     * @return String
     */
    public static function camel($str, $ucfirst=false)
    {
        if (!Is::nemstr($str)) return $str;
        //可以用作分隔符的字符：- _ / , \ 空格
        $str = preg_replace("/\_|\/|\,|\\|\s*/","-",$str);
        if (strpos($str,"-")===false) {
            $bgu = Str::beginUp($str);
            if ($bgu && !$ucfirst) return lcfirst($str);
            if (!$bgu && $ucfirst) return ucfirst($str);
            return $str;
        } else {
            //带有分隔符的，必须全小写
            $str = strtolower($str);
            $str = str_replace("-"," ",$str);
            $str = ucwords($str);
            $str = str_replace(" ","",$str);
            if (!$ucfirst) $str = lcfirst($str);
            return $str;
        }
    }

    /**
     * camel case to snake case
     * fooBarJaz  -->  foo-bar-jaz
     * FooBarJaz  -->  foo-bar-jaz
     * @param String $str
     * @param String $glup 连接符 默认 -
     * @return String
     */
    public static function snake($str, $glup="-")
    {
        $snakeCase = strtolower(preg_replace('/([a-z])([A-Z])/', '$1'.$glup.'$2', $str));
        return $snakeCase;
    }

    /**
     * 判断 是否包含 字符串
     * @param String $str
     * @param String $var 要查找的字符串
     * @return Bool
     */
    public static function has($str, $var)
    {
        if (!Is::nemstr($str) || !Is::nemstr($var)) return false;
        return false !== strpos($str, $var);
    }

    /**
     * 判断 是否包含 给定 字符串 中的任意一个
     * @param String $str
     * @param Array $vars 要查找的字符串数组
     * @return Bool
     */
    public static function hasAny($str, ...$vars)
    {
        if (!Is::nemstr($str)) return false;
        $flag = false;
        foreach ($vars as $i => $var) {
            if (!Is::nemstr($var)) continue;
            if (false !== strpos($str, $var)) {
                $flag = true;
                break;
            }
        }
        return $flag;
    }

    /**
     * 判断 是否包含 全部 给定的字符串
     * @param String $str
     * @param Array $vars 要查找的字符串数组
     * @return Bool
     */
    public static function hasAll($str, ...$vars)
    {
        if (!Is::nemstr($str)) return false;
        $flag = true;
        foreach ($vars as $i => $var) {
            if (!Is::nemstr($var)) return false;
            if (false === strpos($str, $var)) {
                $flag = false;
                break;
            }
        }
        return $flag;
    }

    /**
     * 判断 是否以 var 开头
     * @param String $str
     * @param String $var 要查找的字符串
     * @return Bool
     */
    public static function begin($str, $var)
    {
        if (!Is::nemstr($str) || !Is::nemstr($var)) return false;
        if ($str === $var) return true;
        $len = strlen($var);
        if ($len>strlen($str)) return false;
        return substr($str, 0, $len) == $var;
    }

    //判断是否首字母大写
    /**
     * 判断是否首字母大写
     * @param String $str
     * @return Bool
     */
    public static function beginUp($str)
    {
        $rst = preg_match("/^[A-Z]+.*$/", $str, $matches);
        //var_dump($matches);
        return $rst!==false && $rst>0;
    }

    /**
     * 判断 是否以 var 结尾
     * @param String $str
     * @param String $var 要查找的字符串
     * @return Bool
     */
    public static function end($str, $var)
    {
        if (!Is::nemstr($str) || !Is::nemstr($var)) return false;
        if ($str === $var) return true;
        $len = strlen($var);
        if ($len>strlen($str)) return false;
        return substr($str, strlen($str) - $len) == $var;
    }

    /**
     * 字符串模板 替换
     * %{***}%
     * @param String $str
     * @param Array $val 用于替换 模板字符串 的 数据源
     * @param String $reg 模板字符串正则
     * @param Array $sign 模板字符串 起止字符 默认 ["%{", "}%"]
     * @return String
     */
    public static function tpl($str = "", $val = [], $reg = "/\%\{[^\}\%]+\}\%/", $sign = ["%{", "}%"])
    {
        self::each($str, $reg, function($v, $k) use (&$str, $val, $sign) {
            $s = str_replace($sign[0],"",$v);
            $s = str_replace($sign[1],"",$s);
            if (is_numeric($s)) $s = (int)$s - 1;  //如果是%{1}%形式，从1开始计数
            $tval = Arr::find($val, $s);
            if(!is_null($tval)){
                $str = self::replace($v, $tval, $str);
                //$this->set($this->replace($v, $tval)->val());
            }else{
                $str = self::replace($v, "null", $str);
                //$this->set($this->replace($v, "null")->val());
            }
        });
        return $str;
    }

    /**
     * 提取 模板字符串 中的 内容
     * %{foo.bar}%  -->  foo.bar
     * @param String $str
     * @param String $reg 模板字符串正则
     * @param Array $sign 模板字符串 起止字符 默认 ["%{", "}%"]
     * @return Array
     */
    public static function tplkey($str = "", $reg = "/\%\{[^\}\%]+\}\%/", $sign = ["%{", "}%"])
    {
        $keys = [];
        self::each($str, $reg, function($v, $k) use (&$keys, $sign) {
            $s = str_replace($sign[0],"",$v);
            $s = str_replace($sign[1],"",$s);
            $keys[] = $s;
        });
        return $keys;
    }

    /**
     * 生成随机字符串
     * @param Int $length 生成字符串的长度
     * @param Bool $symbol 是否包含特殊字符，false 则只包含 大小写字母和数字
     * @return String
     */
    public static function nonce($length = 16, $symbol = true)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $symbols = "_-+@#$%^&*";
        if ($symbol) {
            $chars .= $symbols;
        }
        $str = "";
        for ($i=0; $i<$length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }


}