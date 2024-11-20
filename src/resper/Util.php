<?php
/**
 * cgyio/resper 工具类基类
 */

namespace Cgy;

use Cgy\util\Cls;

class Util 
{
    public static function defineGlobalFunctions()
    {
        $ud = __DIR__."/../util";
        $fcnt = ["<?php", ""];
        $udh = @opendir($ud);
        while(false !== ($un = readdir($udh))) {
            if ($un=="." || $un=="..") continue;
            if (is_dir($ud."/".$un)) continue;
            $un = str_replace(".php", "", strtolower($un));
            $cls = "\\Cgy\\util\\".ucfirst($un);
            if (!class_exists($cls)) continue;
            $ref = new \ReflectionClass($cls);
            $ms = $ref->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_STATIC);
            if (empty($ms)) continue;
            for ($i=0;$i<count($ms);$i++) {
                $mi = $ms[$i];
                $mn = $mi->name;
                if (in_array($mn, ["defineGlobalFunctions"])) continue;
                $fcnt[] = "function cgy_".strtolower($un)."_".$mn."(...\$args) {return ".$cls."::".$mn."(...\$args); }";
            }
        }
        @closedir($udh);
        $fcnt[] = "";
        $fcnt = implode("\r\n", $fcnt);
        $fp = $ud."/functions.php";
        file_put_contents($fp, $fcnt);
    }
}