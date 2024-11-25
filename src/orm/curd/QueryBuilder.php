<?php
/**
 * cgyio/resper 数据库操作
 * 直接调用 php://input 传入的 json 参数 作为查询参数，生成 medoo 查询参数
 * 
 *  input 传入的 json 参数格式：[
 *      "query" => [
 *          "search" => "foo,bar，jaz",
 *          "filter" => [
 *              "column" => [
 *                  "logic" => ">=",
 *                  "value" => 123
 *              ],
 *              ...
 *          ],
 *          "sort" => [
 *              "column" => "DESC",
 *              "column2" => "",
 *              ...
 *          ],
 *          "page" => [
 *              "size" => 100,
 *              "ipage" => 3
 *          ],
 * 
 *          # 手动指定 额外的 查询参数
 *          "where" => [
 *              "column[!~]" => "foobar",
 *              ...
 *          ],
 * 
 *          # 还可以指定其他参数
 *          "limit" => 10,
 *          "match" => ...
 *      ]
 *  ]
 * 
 */

namespace Cgy\orm\curd;

use Cgy\Orm;
use Cgy\orm\Curd;
use Cgy\Request;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;

class QueryBuilder 
{
    //关联的 Curd 实例
    public $curd = null;

    //缓存 input 参数
    protected $input = [];

    //curd\****Parser 各类型参数处理工具类实例
    protected $wp = null;
    protected $cp = null;
    protected $jp = null;

    //json 参数可用键名，将按顺序解析这些参数
    protected $keys = [
        "sort",
        //"search",
        //"filter",
        "where",
        "page",
        //"limit",

        //额外的
        //"column",
        //"match"
    ];

    /**
     * 构造
     * @param Curd $curd 当前 curd 实例
     * @param Array $extra 在 input 传入的参数基础上，手动增加 extra 额外查询参数
     * @return void
     */
    public function __construct($curd, $extra = [])
    {
        if (!$curd instanceof Curd) return null;
        $this->curd = $curd;
        $this->wp = $curd->whereParser;
        $this->cp = $curd->columnParser;
        $this->jp = $curd->joinParser;

        //读取 当前 Request 实例的 inputs 数据
        $input = Request::$current->inputs->json;
        if (Is::nemarr($input) && isset($input["query"])) {
            $this->input = $input["query"];
        }

        //应用 手动指定的 额外查询参数
        if (Is::nemarr($extra)) {
            $this->input = Arr::extend($this->input, $extra);
        }

        //开始 按 参数可用的 键名 顺序解析
        for ($i=0;$i<count($this->keys);$i++) {
            $key = $this->keys[$i];
            $m = "parse".ucfirst($key);
            if (method_exists($this, $m)) {
                $this->$m();
            }
        }
    }

    /**
     * 读取当前已解析完成的 curd 查询参数
     * @return Array
     */
    protected function parsed()
    {
        return $this->curd->parseArguments();
    }



    /**
     * 各参数 解析方法
     */

    /**
     * parseSort 排序参数
     * @return QueryBuilder $this
     */
    protected function parseSort()
    {
        $sort = $this->input["sort"] ?? [];
        if (!Is::nemarr($sort)) return $this;

        $sort = array_filter(function($i) {
            return Is::nemstr($i) && in_array(strtoupper($i), ["ASC","DESC"]);
        });

        //调用 WhereParser
        $this->wp->order($sort);

        return $this;
    }

    /**
     * parseWhere 同时解析 search/filter/where 参数
     * @return QueryBuilder $this
     */
    protected function parseWhere()
    {
        $filter = $this->input["filter"] ?? [];
        $search = $this->input["search"] ?? "";
        $where = $this->input["where"] ?? [];

        $this->wp->filter($filter);
        $this->wp->keyword($search);
        $this->wp->setParam($where);

        return $this;
    }

    /**
     * parsePage 同时解析 page/limit 参数
     * @return QueryBuilder $this
     */
    protected function parsePage()
    {
        $page = $this->input["page"] ?? [];
        $limit = $this->input["limit"] ?? [];

        $pagesize = $page["size"] ?? 100;
        $ipage = $page["ipage"] ?? 1;
        $this->wp->page($ipage, $pagesize);

        if (!empty($limit)) {
            $this->wp->limit($limit);
        }

        return $this;
    }


}