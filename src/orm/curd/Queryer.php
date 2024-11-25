<?php
/**
 * cgyio/resper 数据库操作
 * 处理复合查询条件，生成 medoo 查询参数
 * 
 * 通常通过 php://input 传入符合查询条件，其格式为：
 * 
 *  [
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

class Queryer 
{
    //关联的 Curd 实例
    public $curd = null;

    //缓存 input 参数
    protected $input = [];

    //curd\****Parser 各类型参数处理工具类实例
    protected $wp = null;
    protected $cp = null;
    protected $jp = null;

    //符合查询条件 参数可用键名，将按顺序解析这些参数
    protected $keys = [
        "sort",
        "search",
        "filter",
        "where",
        "page",
        "limit",

        //额外的
        "column",
        "match"
    ];

    //解析后得到的参数
    public $context = [];

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
    }

    /**
     * 读取当前已解析完成的 curd 查询参数
     * @return Array
     */
    protected function args()
    {
        return $this->curd->parseArguments();
    }

    /**
     * 解析参数
     * @param Array $extra 传入的复合查询参数
     * @param Bool $mixin 是否 合并 php://input 数据，默认 true
     * @return Queryer $this
     */
    public function apply($extra = [], $mixin = true)
    {
        $hasExtra = Is::nemarr($extra) && Is::associate($extra);

        //未指定查询参数
        if (!$hasExtra && !$mixin) {
            $this->context = [];
            return $this;
        }

        //合并 复合查询条件数据
        if (!$hasExtra) $extra = [];
        if ($mixin) {
            //读取 当前 Request 实例的 inputs 数据
            $input = Request::$current->inputs->json;
            if (Is::nemarr($input)) {
                if (isset($input["query"])) {
                    $input = $input["query"];
                } else {
                    $inp = [];
                    foreach ($this->keys as $key) {
                        if (!isset($input[$key])) continue;
                        $inp[$key] = $input[$key];
                    }
                    $input = array_merge([], $inp);
                }
            } else {
                $input = [];
            }
            if (!empty($input)) $this->input = $input;
        }
        $this->context = Arr::extend($input, $extra);

        //开始 按 参数可用的 键名 顺序解析
        for ($i=0;$i<count($this->keys);$i++) {
            $key = $this->keys[$i];
            $m = "parse".ucfirst($key);
            if (method_exists($this, $m)) {
                $this->$m();
            }
        }

        return $this;
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
        $sort = $this->context["sort"] ?? [];
        if (!Is::nemarr($sort)) return $this;

        $sort = array_filter($sort, function($i) {
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
        $filter = $this->context["filter"] ?? [];
        $search = $this->context["search"] ?? "";
        $where = $this->context["where"] ?? [];

        $this->wp->filter($filter);
        $this->wp->search($search);
        $this->wp->setParam($where);

        return $this;
    }

    /**
     * parsePage 同时解析 page/limit 参数
     * @return QueryBuilder $this
     */
    protected function parsePage()
    {
        $page = $this->context["page"] ?? [];
        $limit = $this->context["limit"] ?? [];

        $pagesize = $page["size"] ?? 100;
        $ipage = $page["ipage"] ?? 1;
        $this->wp->page($ipage, $pagesize);

        if (!empty($limit)) {
            $this->wp->limit($limit);
        }

        return $this;
    }


}