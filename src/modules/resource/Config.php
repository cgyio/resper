<?php
/**
 * cgyio/resper Resource 模块 预设参数
 * 在 index.php 中，可通过 Resper::start([...]) 修改：
 *      Resper::start([
 *          ...
 *          "module" => [
 *              ...
 *              "resource" => [
 *                  ...
 *                  "alias" => [
 *                      "foo" => "foo/bar/jaz"
 *                  ],
 *                  ...
 *              ],
 *              ...
 *          ],
 *          ...
 *      ])
 * 
 */

namespace Cgy\resource;

use Cgy\Configer;

class Config extends Configer 
{
    /**
     * 预设的设置参数
     * !! 子类自定义
     * !! Resper::start([ ... ]) 参数格式应与此处一致
     */
    protected $init = [
        /**
         * 路径别名
         */
        "alias" => [
            /*
            "foo" => "foo/bar/jaz",     // https://[host]/src/foo/tom.js  -->  查找 foo/bar/jaz/tom.js
            */
        ],
    ];

}