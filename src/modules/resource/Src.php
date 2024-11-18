<?php
/**
 * cgyio/resper 模块 respond 响应类(路由)
 * 响应 URI 
 *      [host]/src/...
 */

namespace Cgy\resource;

use Cgy\resper\Responder;

class Src extends Responder 
{


    public function foo(...$args) {
        return implode("/", $args);
    }
}