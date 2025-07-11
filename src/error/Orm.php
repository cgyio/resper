<?php
/**
 * cgyio/resper 错误定义类
 * Orm 数据库/数据模型 错误
 */

namespace Cgy\error;

use Cgy\Error;

class Orm extends Error
{
    /**
     * error code prefix
     * should be overrided
     */
    protected $codePrefix = "";

    /**
	 * errors config
	 * should be overrided
	 */
	protected $config = [
        
        "zh-CN" => [
            "base" => [
                "ORM 错误",
                "%{1}%"
            ],

            "fatal" => [
                "ORM Fatal Error",
                "数据库系统发生严重错误，此错误不应出现在生产环境中，请与管理员联系！错误原因：%{1}%"
            ],

            "api" => [
                "ORM 响应错误",
                "无法响应数据库操作请求，%{1}%"
            ],
        ]
    ];

}