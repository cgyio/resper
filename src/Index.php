<?php
/**
 * cgyio/resper 框架入口
 * 
 * resper 框架使用单入口形式，需要在根目录建立 index.php 文件，并引用此文件
 * 所有用户请求都需要通过 index.php 
 * 
 */

//使用 composer autoload
require_once(__DIR__."/../../../autoload.php");

//根据 Cgy\util\*** 类创建 global functions 保存到文件 util/functions.php
//如果 util 中工具类被修改，必须执行一次此方法
//\Cgy\Util::defineGlobalFunctions();

//调用 util functions
require_once(__DIR__."/util/functions.php");

//var_dump(cgy_cls_find("resper"));
