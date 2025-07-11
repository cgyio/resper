<?php
/**
 * cgyio/resper 错误定义类
 * Resper 框架错误
 */

namespace Cgy\error;

use Cgy\Error;

class Resper extends Error
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
                "Resper 框架错误",
                "%{1}%"
            ],

            "fatal" => [
                "Resper Fatal Error",
                "框架发生严重错误，此错误不应出现在生产环境，请与管理员联系！错误原因：%{1}%"
            ],
        ]
    ];
}