<?php
/**
 * cgyio/resper $_GET/$_POST 处理类
 */

namespace Cgy\request;

use Cgy\util\Secure;
use Cgy\util\Arr;

class Gets 
{
    //原始数据
    protected $origin = [];

    //处理后数据
    public $context = [];

    /**
     * 构造
     * @param Array $gets
     * @return void
     */
    public function __construct($gets = [])
    {
        $this->origin = Arr::copy($gets);

        //使用 Secure 工具处理
        foreach ($gets as $k => $v) {
            $sec = Secure::str($v);
            $this->context[$k] = $sec->context;
        }
    }

    /**
     * __get
     * @param String $key 访问 context[$key]
     * @return Mixed
     */
    public function __get($key)
    {
        if ($this->has($key)) {
            return $this->context[$key];
        }

        return null;
    }

    /**
     * __call
     * @param String $key 访问 context[$key]
     * @param Array $dft 不存在则 返回 默认值 $dft[0]
     * @return Mixed
     */
    public function __call($key, $dft)
    {
        if ($this->has($key)) {
            return $this->context[$key];
        }
        if (empty($dft)) return null;
        return $dft[0];
    }

    /**
     * 判断 键 是否存在
     * @param String $key
     * @return Bool
     */
    public function has($key)
    {
        return isset($this->context[$key]);
    }



}