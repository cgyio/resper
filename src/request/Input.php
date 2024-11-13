<?php
/**
 * cgyio/resper 输入数据处理类
 * 处理 $_GET / $_POST / $_FILES / php://input 数据
 */

namespace Cgy\request;

class Input 
{
    //原始数据
    protected $origin = [];

    //处理后的数据
    public $context = [];

    /**
     * 构造
     * @param Array $data 待处理数据 $_GET / $_POST / $_FILES / php://input
     * @return void
     */
    public function __construct($data = null)
    {
        $this->origin = $data;
    }

    /**
     * 去除危险字符
     * 针对 $_GET / $_POST
     * @param String[] $chars 可以指定要去除的字符
     * @return Array
     */
    public function trimIllegalChar(...$chars)
    {
        
    }
}