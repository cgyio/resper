<?php
/**
 * cgyio/resper 错误定义类
 * Resource 资源错误
 * Res error
 */

namespace Cgy\error;

use Cgy\Error;

class Res extends Error
{
    /**
     * error code prefix
     * should be overrided
     */
    protected $codePrefix = "101";

    /**
	 * errors config
	 * should be overrided
	 */
	protected $config = [
        
        "zh-CN" => [
            "miss"      => ["资源不存在", "未找到要访问的资源，访问路径：%{1}%"],
        ]
        
	];
}