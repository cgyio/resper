<?php
/**
 * cgyio/resper 框架入口
 * 
 * resper 框架使用单入口形式，按以下步骤：
 * 
 *  0   设置 服务器参数，将 所有用户请求 都通过 index.php 执行
 *  1   webroot 路径下 建立 index.php
 *  2   require 此文件，或 拷贝此文件全部代码 到 index.php  !!! 注意需要修改 require 文件路径
 *  3   执行 \Cgy\Resper::start([ ...框架启动参数... ])
 *      启动参数格式 参考：vendor/cgyio/resper/src/resper/Config.php
 * 
 */

/**
 * 使用 Composer 类自动加载
 */
require_once(__DIR__."/../../../autoload.php");

/**
 * 根据 Cgy\util\*** 类创建 global functions 保存到文件 util/functions.php
 * !! 如果 util 中工具类 被修改，必须执行一次此方法
 * 如需执行，取消注释，将自动 生成/修改 util/functions.php
 */
//\Cgy\Util::defineGlobalFunctions();

/**
 * 调用 util functions 全局工具函数
 * Cgy\util\Str::replace()  -->  cgy_str_replace()
 */
require_once(__DIR__."/util/functions.php");

