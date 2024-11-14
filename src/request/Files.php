<?php
/**
 * cgyio/resper $_FILES 处理类
 */

namespace Cgy\request;

use Cgy\util\Is;

class Files 
{
    //原始数据
    protected $origin = [];

    //处理后数据
    public $context = [];

    /**
     * 构造
     * @return void
     */
    public function __construct()
    {
        $this->origin = $_FILES;
    }

    /**
     * 根据 上传表单 文件控件 name 获取上传文件
     * @param String $name
     * @return Array 可能上传多文件，统一返回 数组
     */
    public function name($name)
    {
        if (!Is::nemstr($name) || !isset($_FILES[$name])) return [];
        $fall = $_FILES[$name];
        $fs = [];
        if (Is::indexed($fall["name"])) {
            $ks = array_keys($fall);
            $ci = count($fall["name"]);
            for ($i=0;$i<$ci;$i++) {
                $fs[$i] = [];
                foreach ($ks as $ki => $k) {
                    $fs[$i][$k] = $fall[$k][$i];
                }
            }
        } else {
            $fs[] = $fall;
        }
        return $fs;
    }

    /**
     * __get
     * @param String $key 上传表单 文件控件 name
     * @return Array
     */
    public function __get($key)
    {
        $fs = $this->name($key);
        return $fs;
    }
}