<?php
/**
 * cgyio/resper 工具类
 * Path 路径处理
 */

namespace Cgy\util;

use Cgy\Util;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;

class Path extends Util 
{

    /**
     * 返回默认的起始路径
     * 默认以 
     *      ROOT_PATH / PRE_PATH
     * 常量为起始路径
     * 如果常量未定义，则返回 空字符
     * @return String
     */
    public static function root()
    {
        if (defined(ROOT_PATH)) return ROOT_PATH;
        if (defined(PRE_PATH)) return PRE_PATH;
        return "";
    }

    /**
     * 将输入的预定义路径名称，转换为路径，路径名称已定义为常量
     * foo  -->  FOO_PATH
     * @param String $cnst 常量名，不带 _PATH
     * @return String 路径
     */
    public static function cnst($cnst = "")
    {
        $cp = null;
        if (empty($cnst)) {
            $cp = self::root();
        } else {
            $cnst = strtoupper($cnst)."_PATH";
            if (defined($cnst)) {
                $cp = constant($cnst);
            }
        }
        if (empty($cp)) return null;
        return self::fix($cp);
    }

    //根据输入，返回物理路径（文件可能不存在）
    /**
     * 根据输入，返回物理路径（文件/路径可能不存在）
     * foo/bar  -->  ROOT_PATH/foo/bar
     * app/foo/bar  -->  APP_PATH/foo/bar
     * @param String $path
     * @return String
     */
    public static function mk($path = "")
    {
        if (empty($path)) return self::root();
        $path = trim(str_replace("/", DS, $path), DS);
        if (!is_null(self::relative($path))) {
            //$path 已经包含了 起始路径，表示这是一个 物理路径，直接返回
            return self::fix($path);
        }
        $parr = explode(DS, $path);
        $p = [];
        $root = array_shift($parr);
        $rootpath = self::cnst($root);
        if (!is_null($rootpath)) {
            $p[] = $rootpath;
        } else {
            $p[] = self::root();
            array_unshift($parr, $root);
        }
        $p = array_merge($p, $parr);
        return self::fix(implode(DS, $p));
    }

    /**
     * 将输入的绝对路径，转换为相对于 $root 的相对路径
     * /root-path/foo/bar  -->  foo/bar
     * @param String $path
     * @param String $root 相对路径的起始，默认为网站根目录 ROOT_PATH;
     * @return String  or  null
     * 当输入的路径 不是 起始于 $root 的路径时，返回 null
     */
    public static function relative($path = "", $root = null)
    {
        if (!Is::nemstr($path)) return null;
        if (empty($root)) $root = self::root();
        $path = self::fix($path);
        $root = self::fix($root);
        if (false !== strpos($path, $root)) {
            return str_replace($root.DS, "", $path);
        }
        return null;
    }

    //根据输入，查找真实存在的文件，返回文件路径，未找到则返回null
    public static function find(
        $path = "",     //要查找的文件或文件夹
        $options = []   //可选的参数
    ) {
        if (file_exists($path) || is_dir($path)) return $path;
        $path = trim(str_replace("/", DS, $path), DS);
        $options = Arr::extend([
            "inDir" => DIR_ASSET,
            "subDir" => "",
            "checkDir" => false
        ], $options);
        $local = [];

        $subDir = $options["subDir"];
        if (empty($subDir)) $subDir = [];
        $subDir = Is::indexed($subDir) ? $subDir : (Is::nemstr($subDir) ? explode(",", $subDir) : []);
        $checkDir = $options["checkDir"];
        $inDir = $options["inDir"];
        if (empty($inDir)) $inDir = [];
        $inDir = Is::indexed($inDir) ? $inDir : (Is::nemstr($inDir) ? explode(",", $inDir) : []);
        $appDir = [];
        if (!empty($inDir)) {
            $parr = explode(DS, $path);
            if (strtolower($parr[0]) == "app" || !is_null(cls("App/".ucfirst(strtolower($parr[0]))))) {
                if (strtolower($parr[0]) == "app") array_shift($parr);
                if (is_dir(APP_PATH.DS.$parr[0])) {
                    $app = array_shift($parr);
                    foreach ($inDir as $i => $dir) {
                        $appDir[] = "app/$app/$dir";
                    }
                    $npath = implode(DS, $parr);
                    $gl = self::_findarr($npath, [
                        "inDir" => $appDir,
                        "subDir" => $subDir,
                        "checkDir" => $checkDir
                    ], $local);
                }
            /*} else if (!is_null(self::cnst($parr[0]))) {
                $cnst = array_shift($parr);
                foreach ($inDir as $i => $dir) {
                    $cnstDir[] = "$cnst/$dir";
                }
                $npath = implode(DS, $parr);
                $gl = self::_findarr($npath, [
                    "inDir" => $cnstDir,
                    "subDir" => $subDir,
                    "checkDir" => $checkDir
                ], $local);*/
            } else {
                if (!is_null(self::cnst($parr[0]))) {
                    $cnst = array_shift($parr);
                } else {
                    $cnst = "root";
                }
                //var_dump($cnst);
                foreach ($inDir as $i => $dir) {
                    $cnstDir[] = "$cnst/$dir";
                }
                $npath = implode(DS, $parr);
                $gl = self::_findarr($npath, [
                    "inDir" => $cnstDir,
                    "subDir" => $subDir,
                    "checkDir" => $checkDir
                ], $local);
            }
        }
        $gl = self::_findarr($path, [
            "inDir" => $inDir,
            "subDir" => $subDir,
            "checkDir" => $checkDir
        ], $local);
        
        //var_dump($local); exit;
        //break_dump("break_pathfind", $local);
        
        foreach ($local as $i => $v) {
            if (file_exists($v)) return $v;
            if ($checkDir && is_dir($v)) return $v;
        }
        return null;
    }

    //建立查找路径数组
    protected static function _findarr(
        $path = "",
        $options = [],  //已经由 find 方法处理过，可直接使用，不做排错
        &$olocal = []    //将建立的路径数组 merge 到此数组中
    ) {
        $inDir = $options["inDir"];
        $subDir = $options["subDir"];
        $checkDir = $options["checkDir"];
        $inApp = isset($inDir[0]) && Str::has($inDir[0],"app/");
        $local = [];
        $info = pathinfo(self::mk($path));
        if (!$inApp) {
            $local[] = self::mk($path);
            if ($checkDir) $local[] = $info["dirname"].DS.$info["filename"];
        }
        foreach ($subDir as $k => $sdi) {
            $sdi = str_replace("/",  DS, $sdi);
            $local[] = $info["dirname"].DS.$sdi.DS.$info["basename"];
            if ($checkDir) $local[] = $info["dirname"].DS.$sdi.DS.$info["filename"];
        }
        foreach ($inDir as $i => $idi) {
            $idi = str_replace("/", DS, $idi);
            $pi = self::mk($idi.DS.$path);
            $local[] = $pi;
            $info = pathinfo($pi);
            if ($checkDir) $local[] = $info["dirname"].DS.$info["filename"];
            foreach ($subDir as $k => $sdi) {
                $sdi = str_replace("/",  DS, $sdi);
                $local[] = $info["dirname"].DS.$sdi.DS.$info["basename"];
                if ($checkDir) $local[] = $info["dirname"].DS.$sdi.DS.$info["filename"];
            }
        }
        //$local = array_unique($local);
        $olocal = array_merge($olocal, $local);
        $olocal = array_unique($olocal);
    }

    //在给定的多个path中，挑选真实存在的文件，$all = true 则返回所有存在的文件路径，否则返回第一个存在的路径
    //未找到任何存在的文件则返回null
    public static function exists(
        $pathes = [], 
        $options = []
    ) {
        if (!Is::nemarr($pathes)) return null;
        $options = Arr::extend([
            "inDir" => DIR_ASSET,
            "subDir" => "",
            "checkDir" => false,
            "all" => false
        ], $options);
        $all = $options["all"];
        unset($options["all"]);
        $exists = [];
        foreach ($pathes as $i => $v) {
            $rv = self::find($v, $options);
            if (!is_null($rv)) $exists[] = $rv;
        }
        if (empty($exists)) return null;
        return $all ? $exists : $exists[0];
    }

    //遍历路径path，callback($file)，$recursive = true时，递归遍历所有子目录
    public static function traverse($path = "", $callback = null, $recursive = false)
    {
        if (!is_dir($path) || !is_callable($callback)) return false;
        $dh = @opendir($path);
        $rst = [];
        while (false !== ($file = readdir($dh))) {
            if ($file == "." || $file == "..") continue;
            $_rst = $callback($path, $file);
            $fp = $path.DS.$file;
            if ($recursive && is_dir($fp)) {
                $_rst = self::traverse($fp, $callback, true);
            }
            if ($_rst==="_continue_") continue;
            if ($_rst==="_break_") break;
            $rst[] = [$file, $_rst];
        }
        closedir($dh);
        return $rst;
    }

    //创建文件夹，$path 从 root 根目录开始
    //！注意！！要创建子文件夹的路径必须有写权限，否则报错！！
    public static function mkdir($path = "")
    {
        if (!Is::nemstr($path)) return false;
        $parr = explode("/", $path);
        $root = self::fix(self::find("root"));
        $temp = $root;
        for ($i=0;$i<count($parr);$i++) {
            $pi = $parr[$i];
            $temp = $temp.DS.$pi;
            if (!is_dir($temp)) {
                try {
                    @mkdir($temp, 0777, true);
                } catch (\Exception $e) {
                    trigger_error("php::无法创建路径 [ ".$temp." ]", E_USER_ERROR);
                    return false;
                    break;
                }
            }
        }
        return true;
    }



    /**
     * tools
     */

    /**
     * 批量 对 path 进行处理，返回正确的路径字符串
     * @param String $path
     * @return String
     */
    public static function fix($path = "")
    {
        //去除 ..
        $path = self::up($path, DS);
        //其他操作 
        //...

        return $path;
    }

    /**
     * 计算path数组中的..标记，返回计算后的path字符串
     * foo/bar/jaz/../../tom  -->  foo/tom
     * @param String $path
     * @param String $dv 路径分隔符，默认 DS
     * @return String 路径 不含 ..
     */
    public static function up($path = "", $dv = DS)
    {
        if (empty($path) || !is_string($path)) return "";
        if ($dv!=DS) $path = str_replace(DS, $dv, $path);
        $path = str_replace("/", $dv, $path);
        $path = explode($dv, $path);
        if ($path[0] == "..") return "";
        for ($i=0; $i<count($path); $i++) {
            if ($path[$i] == "") $path[$i] = "__empty__";
            if ($path[$i] == "..") {
                for ($j=$i-1; $j>=-1; $j--) {
                    if ($j < 0) {   //越界
                        return "";
                    }
                    if ($path[$j] != ".." && !is_null($path[$j])) {
                        $path[$j] = null;
                        break;
                    }
                }
            }
        }
        $path = array_merge(array_diff($path, [null,".."]), []);
        return str_replace("__empty__", "", implode($dv, $path));
    }

    //递归删除目录，rmdir() 只能删除空文件夹，因此需要先删除内部内容
    public static function del($dir)
    {
        $dir = self::find($dir, ["inDir"=>[], "checkDir"=>true]);
        if (empty($dir)) return false;
        $files = array_diff(scandir($dir), [".", ".."]);
        foreach ($files as $file) {
        (is_dir("$dir/$file")) ? self::del("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }
}