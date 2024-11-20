<?php
/**
 * cgyio/resper 工具类基类
 */

namespace Cgy;

use Cgy\util\Cls;

class Util 
{
    /**
     * 将所有 util\**** 工具类中的 public static 方法，定义为 global functions
     * util\Str::replace()  --定义为-->  cgy_str_replace()
     * 保存到 util/functions.php 
     * @return String 定义语句
     */
    public static function defineGlobalFunctions()
    {
        //获取 Util 基类方法，这些方法不作为 global functions
        $self = new \ReflectionClass("\\Cgy\\Util");
        $sms = $self->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_STATIC);
        $smn = array_map(function ($mi) {return $mi->name;}, $sms);

        //查找全部 util\**** 工具类
        $ud = __DIR__."/../util";
        $fcnt = ["<?php", ""];
        $udh = @opendir($ud);
        while(false !== ($un = readdir($udh))) {
            if ($un=="." || $un=="..") continue;
            if (is_dir($ud."/".$un)) continue;
            $un = str_replace(".php", "", strtolower($un));
            $cls = "\\Cgy\\util\\".ucfirst($un);
            if (!class_exists($cls)) continue;
            //查找 工具类 public static 方法
            $ref = new \ReflectionClass($cls);
            $ms = $ref->getMethods(\ReflectionMethod::IS_PUBLIC);
            if (empty($ms)) continue;
            for ($i=0;$i<count($ms);$i++) {
                $mi = $ms[$i];
                $mn = $mi->name;
                //排除从 Util 基类继承的 方法
                if (in_array($mn, $smn)) continue;
                //排除 实例方法
                if ($mi->isStatic() === false) continue;
                $fcnt[] = "function cgy_".strtolower($un)."_".$mn."(...\$args) {return ".$cls."::".$mn."(...\$args); }";
            }
        }
        @closedir($udh);
        $fcnt[] = "";
        $fcnt = implode("\r\n", $fcnt);
        $fp = $ud."/functions.php";
        $fh = @fopen($fp, "w");
        @fwrite($fh, $fcnt);
        @fclose($fh);

        return $fcnt;
    }
}