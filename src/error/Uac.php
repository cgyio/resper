<?php
/**
 * cgyio/resper 错误定义类
 * UAC 权限控制 错误
 */

namespace Cgy\error;

use Cgy\Error;

class Uac extends Error
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
                "UAC 权限控制错误",
                "%{1}%"
            ],

            "fatal" => [
                "UAC Fatal Error",
                "权限控制系统发生严重错误，此错误不应出现在生产环境中，请与管理员联系！错误原因：%{1}%"
            ],

            "api" => [
                "UAC 响应错误",
                "无法响应权限控制操作请求，%{1}%"
            ],

            "denied" => [
                "UAC 拒绝操作",
                "操作已被阻止，%{1}%"
            ],
        ]
    ];

}