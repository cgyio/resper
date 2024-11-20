<?php
/**
 * cgyio/resper header 处理类
 * Request/Response Header 处理类
 */

namespace Cgy\module;

use Cgy\util\Server;
use Cgy\util\Str;

class Header 
{
    //headers 参数数组
    public $context = [];

    /**
     * __get
     * 访问 context
     */
    public function __get($key)
    {
        /**
         * Header->AcceptLanguage  -->  context["Accept-Language"]
         */
        $snk = Str::snake($key, "-");
        $snk = str_replace(" ", "-", ucwords(str_replace("-", " ", $snk)));
        if (isset($this->context[$snk])) {
            return $this->context[$snk];
        }

        return null;
    }


    
}