<?php
/**
 * resper 框架 权限操作管理
 * 生成/管理 系统中所有需要权限的 操作列表
 * 
 * 系统中的操作可以分为以下几个类型：
 *  1   用户角色转化为权限操作标识，例如：
 *          role:super                  > 用户角色：超级管理员
 *          role:storage/manager        > 用户角色：仓库主管
 *          role:storage/gsku           > 用户角色：原料库管
 *          role:dev/recipe             > 用户角色：配方管理员
 *          role:quality/qc             > 用户角色：QC
 *  2   单独指定的系统操作，例如：
 *          sys:pause                       > 系统操作：暂停服务
 *          sys/wx/scan:confirm/delivery    > 微信扫码：发货确认
 *  3   数据库操作，例如：
 *          db/pms/psku:create          > 产品表：新建记录
 *          db/pms/psku:update          > 产品表：修改记录
 *          db/pms/psku:retrieve        > 产品表：查询记录
 *          db/pms/psku:delete          > 产品表：删除记录
 *          db/pms/psku:toggle          > 产品表：切换记录生效/失效
 *          db/pms/psku:direct          > 产品表：直接通过表单编辑
 *  !!TODO: db/pms/psku:column/category/01      > 产品表：管理01类型记录
 *          db/pms/psku:full            > 产品表：所有权限
 *          db/pms/psku/api:foo         > 产品表API：api说明文字
 *  4   API 操作（方法名应为：fooBarApi，应有注释 * api），例如：
 *          api/app/pms:foo             > PMS系统API：api说明文字
 *          api/module/foo:bar          > 模块foo的名称API：api说明文字
 *          api/foo:bar                 > 自定义响应者foo的名称API：api说明文字
 *  5   各类型响应者实例方法（此实例方法可以作为响应方法被请求到，应有注释 * resper），例如：
 *          app/foo:bar                 > APP名称：bar
 *          module/foo:bar              > 模块foo的名称：bar
 *          foo:bar                     > 自定义响应者foo的名称：bar
 *          
 * 此类将从对应的 resper 响应者类，以及对应的 Orm 数据库实例中，查找符合的 方法，生成 权限操作列表
 * 然后在 响应流程中，通过用户实例 对这些操作 进行权限验证
 */

namespace Cgy\uac;

use Cgy\Resper;
use Cgy\Request;
use Cgy\Response;
use Cgy\Orm;
use Cgy\orm\Model;
use Cgy\orm\model\Record;
use Cgy\Uac;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Cls;
use Cgy\util\Path;
use Cgy\util\Conv;

class Operation 
{
    /**
     * 依赖项
     */
    //关联的 resper 响应者实例
    public $resper = null;
    //关联的 uac 权限控制实例
    public $uac = null;

    /**
     * 解析得到的各类型 权限操作列表 缓存
     */
    protected $context = [
        /*
        role:super                  => 用户角色：超级管理员
        role:storage/manager        => 用户角色：仓库主管
        sys:pause                   => 系统操作：暂停服务
        db/pms/psku:create          => 产品表：新建记录
        db/pms/psku:update          => 产品表：修改记录
        api/app/pms:foo             => PMS系统API：api说明文字
        api/module/foo:bar          => 模块foo的名称API：api说明文字
        ...
        */
    ];

    /**
     * 构造
     */
    public function __construct($uac)
    {
        if (!$uac instanceof Uac) return null;
        $this->resper = $uac->resper;
        $this->uac = $uac;

        //如果 uac 参数中定义了 operation/operates 操作列表，则合并到 context
        $conf = $this->resper->conf;
        $uacc = $conf["uac"] ?? [];
        $oprc = $uacc["operation"] ?? [];
        $oprs = $oprc["operates"] ?? [];
        if (Is::nemarr($oprs) && Is::associate($oprs)) {
            $this->context = Arr::extend($this->context, $oprs);
        }

        //创建 操作列表
        $this->createOperatesList();
    }

    /**
     * 创建当前响应者的 操作列表
     * !! 如果有需要，子类可以覆盖此方法
     * @return $this
     */
    protected function createOperatesList()
    {
        //依次获取 多种类型的 操作列表
        $this->addRoleOperates();
        $this->addSysOperates();
        $this->addDbOperates();
        $this->addApiOperates();
        $this->addResperOperates();

        //最后执行 自定义的 其他类型的 操作列表 的获取方式
        $this->addCustomOperates();

        return $this;
    }

    /**
     * 生成操作列表方法
     * !! 子类可覆盖这些方法，在 uac 参数中可以指定子类 作为当前响应者的 权限操作列表管理类
     * 这些方法，获取当前响应的环境数据，计算生成 操作列表，保存到 context
     * @return $this
     */
    //生成 用户角色对应的 操作列表
    protected function addRoleOperates()
    {
        //通过 Uac 实例，访问 Role 数据表
        $roleMdo = Uac::Role();
        if (empty($roleMdo)) return $this;
        //获取所有 生效的 用户角色记录
        $rolers = $roleMdo->whereEnable(1)->select();
        if (empty($roles)) return $this;
        //role key 作为操作标识 role:foobar
        foreach ($rolers as $i => $role) {
            $rk = $role->key;
            $rn = $role->name;
            if (!isset($this->context[$rk])) {
                $this->context[$rk] = $rn;
            }
        }
        //返回
        return $this;
    }
    //生成 系统单独指定的 特殊操作列表，子类可覆盖此方法
    protected function addSysOperates()
    {
        //定义一些通用的 系统操作
        $this->context = Arr::extend($this->context, [
            //登录
            "sys:login" => "系统操作：登录",
            //整站暂停服务
            "sys:pause" => "系统操作：暂停服务",
        ]);

        //如果有需要，子类可以自定义 特殊操作权限
        //...

        return $this;
    }
    //生成 数据库 操作列表
    protected function addDbOperates()
    {
        //获取当前可用的 Orm 数据库列表
        $orm = $this->resper->orm;
        if (!$orm instanceof Orm) return $this;
        //数据库列表
        $dbns = $orm->config["dbns"] ?? [];
        if (!Is::nemarr($dbns)) return $this;
        //每个数据表的固有权限
        $mdauth = [
            "create" => "新建记录",
            "update" => "修改记录",
            "retrieve" => "查询记录",
            "delete" => "删除记录",
            "toggle" => "切换记录生效/失效",
            "direct" => "直接通过表单编辑记录",
            "full" => "拥有所有权限"
        ];
        //循环添加所有可用数据表
        foreach ($dbns as $i => $dbn) {
            //实例化数据库
            $db = $orm->db($dbn);
            //获取数据表名，同时初始化这些表，因为后面还需要读取这些数据表中的 api 方法
            $mdns = $db->modelNames(true);
            if (!Is::nemarr($mdns)) continue;
            foreach ($mdns as $k => $mdn) {
                //表名称
                $mdt = $db->$mdn->title;
                //操作标识前缀
                $pre = "db/".strtolower($dbn)."/".strtolower($mdn);
                
                /**
                 * 生成数据表 固有的 操作列表
                 */
                foreach ($mdauth as $opr => $opi) {
                    $opk = $pre.":".$opr;
                    if (!isset($this->context[$opk])) {
                        $this->context[$opk] = $mdt."：".$opi;
                    }
                }

                /**
                 * 生成数据表 api 操作列表
                 */
                $apis = $db->$mdn->api;
                if (Is::nemarr($apis) && Is::associate($apis)) {
                    foreach ($apis as $apin => $apic) {
                        //区分 表/记录实例 api
                        $mid = $apic["isModel"]===true ? "model/api" : "api";
                        $opk = $pre."/".$mid.":".$apin;
                        $opi = $apic["desc"] ?? $apin."()";
                        if (!isset($this->context[$opk])) {
                            $this->context[$opk] = $mdt."：".$opi;
                        }
                    }
                }

                /**
                 * TODO:
                 * 针对一些特殊的列，进行 按字段值 分配相应权限
                 * 需要在子类中定义 protected 方法 addColumnFoobarOperates
                 */
                $cols = $db->$mdn->columns;
                if (Is::nemarr($cols) && Is::indexed($cols)) {
                    foreach ($cols as $j => $col) {
                        $m = "addColumn".Str::camel($col, true)."Operates";
                        if (method_exists($this, $m)) {
                            //要传入 操作标识前缀 db/pms/psku 在方法中，自行处理操作列表，添加到 context
                            $this->$m($pre);
                        }
                    }
                }
            }
        }

        return $this;
    }
    //生成 api 操作列表
    protected function addApiOperates()
    {
        //获取当前响应者信息
        $resper = $this->getResperInfo();
        //当前响应者的说明
        $rintr = $resper["intr"];
        //操作标识前缀
        $pre = "api/".$resper["pre"];

        //获取符合条件的 api 方法
        $apis = Cls::methods($this->resper, "public", function($mi) {
            //必须是实例方法
            if ($mi->isStatic()===true) return false;
            if (substr($mi->name, -3)==="Api") {
                $doc = $mi->getDocComment();
                if (strpos($doc, "* api")!==false || strpos($doc, "* Api")!==false) {
                    return true;
                }
            }
            return false;
        });
        //生成 当前响应者的 api 操作列表
        if (!empty($apis)) {
            foreach ($apis as $i => $mi) {
                $doc = $mi->getDocComment();
                $doc = str_replace("\\r\\n", "", $doc);
                $doc = str_replace("\\r", "", $doc);
                $doc = str_replace("\\n", "", $doc);
                $doc = str_replace("*\/", "", $doc);
                $da = explode("* @", $doc);
                array_shift($da);   //* api
                $confi = [
                    "name" => "",
                    "desc" => "",
                ];
                foreach ($da as $i => $di) {
                    $dai = explode(" ", trim(explode("*", $di)[0]));
                    if (count($dai)<2) continue;
                    if (!in_array($dai[0],["desc","role","name","title","authKey"])) continue;
                    $confi[$dai[0]] = implode(" ",array_slice($dai, 1));
                }
                $name = $confi["name"] ?? "";
                if (!Is::nemstr($name)) {
                    $name = str_replace("Api","", $mi->name);
                    $confi["name"] = $name;
                }
                //创建 api 操作
                $opk = $pre.":".$name;
                $opi = $confi["desc"];
                if (!Is::nemstr($opi)) $opi = $name."()";
                if (!isset($this->context[$opk])) {
                    $this->context[$opk] = $rintr."：".$opi;
                }
            }
        }
        return $this;
    }
    //生成 当前响应者实例方法 操作列表
    protected function addResperOperates()
    {
        //获取当前响应者信息
        $resper = $this->getResperInfo();
        //当前响应者的说明
        $rintr = $resper["intr"];
        //操作标识前缀
        $pre = $resper["pre"];

        //获取符合条件的 可用于响应请求的 方法
        $fns = Cls::methods($this->resper, "public", function($mi) {
            //必须是实例方法
            if ($mi->isStatic()===true) return false;
            $doc = $mi->getDocComment();
            //方法必须包含 注释 * resper
            if (strpos($doc, "* resper")!==false || strpos($doc, "* Resper")!==false) {
                return true;
            }
            return false;
        });
        //生成 当前响应者的 可响应 操作列表
        if (!empty($fns)) {
            foreach ($fns as $i => $mi) {
                $doc = $mi->getDocComment();
                $doc = str_replace("\\r\\n", "", $doc);
                $doc = str_replace("\\r", "", $doc);
                $doc = str_replace("\\n", "", $doc);
                $doc = str_replace("*\/", "", $doc);
                $da = explode("* @", $doc);
                array_shift($da);   //* resper
                $confi = [
                    "name" => "",
                    "desc" => "",
                ];
                foreach ($da as $i => $di) {
                    $dai = explode(" ", trim(explode("*", $di)[0]));
                    if (count($dai)<2) continue;
                    if (!in_array($dai[0],["desc","role","name","title","authKey"])) continue;
                    $confi[$dai[0]] = implode(" ",array_slice($dai, 1));
                }
                $name = $confi["name"] ?? "";
                if (!Is::nemstr($name)) {
                    $name = $mi->name;
                    $confi["name"] = $name;
                }
                //创建 响应者 可响应 操作
                $opk = $pre.":".$name;
                $opi = $confi["desc"];
                if (!Is::nemstr($opi)) $opi = $name."()";
                if (!isset($this->context[$opk])) {
                    $this->context[$opk] = $rintr."：".$opi;
                }
            }
        }
        return $this;
    }
    //子类必须覆盖，生成 其他类型的 操作列表
    protected function addCustomOperates()
    {
        /**
         * 不同的实际应用中，可能需要一些 特别的 权限操作
         * 
         * 例如：
         * 在不同的后台管理应用中，nav 导航菜单 通常需要用户有对应的权限，
         * 因此需要把所有的 nav 导航菜单，转为 操作列表，
         * 这个转换方法，可在此执行
         * 
         * !! 子类实现
         */

        return $this;
    }

    /**
     * 将生成的操作列表，输出为 label-value 结构，可用于前端生成下拉列表
     * @return Array [ [ label=>说明, value=>操作标识], ... ]
     */
    public function values()
    {
        $ctx = $this->context;
        $vals = [];
        foreach ($ctx as $opk => $opi) {
            $vals[] = [
                "label" => $opk." > ".$opi,
                "value" => $opk
            ];
        }
        return $vals;
    }



    /**
     * tools
     */

    /**
     * 获取当前响应者 相关信息
     * @return Array 
     */
    protected function getResperInfo()
    {
        //获取当前响应者实例
        $resper = $this->resper;
        //当前响应者类型
        $rtype = $resper->type;
        //类型名称
        $rtns = [
            "App" => "应用",
            "Module" => "模块",
            "Resper" => "自定义",
        ];
        //当前响应者的类名称，不包含 namespace
        $rname = $resper->cls;
        //当前响应者的说明
        $rintr = $resper->intr;
        if (!Is::nemstr($rintr)) $rintr = $rtns[$rtype].$rname;
        //操作标识前缀
        $pre = ($rtype=="Resper" ? "" : strtolower($rtype)."/").strtolower($rname);

        return [
            "type" => $rtype,
            "rtnm" => $rtns[$rtype],
            //当前响应者类名，不包含 namespace，如：\Cgy\app\Pms  -->  Pms
            "name" => $rname,
            //当前响应者的 intr 说明，通常在 resper 子类中定义 intr 属性，未定义则显示为 应用Foo  或  模块Foo  或  自定义Foo
            "intr" => $rintr,
            //当前响应者中 操作标识的 前缀  app/pms  or  module/foo  or  foo
            "pre" => $pre,
        ];
    }

    /**
     * 从当前的请求中解析出 操作标识，以供权限确认
     * @return String|Bool|null
     * 返回 true 时，直接通过权限验证，针对一些不需要权限的 操作
     * 返回 false|null 时，直接权限验证失败，可能是缺少必要的权限验证条件
     * 返回 操作标识 则需要进一步验证权限
     */
    public function current()
    {
        //如果当前未启用 Uac 则返回 true 直接通过验证
        if (Uac::on()!==true) return true;
        $uac = Uac::$current;

        //获取当前响应者实例，不存在 则返回 null 直接验证失败
        $resper = $uac->resper;
        if (!$resper instanceof Resper) return null;

        //获取当前响应者的相关信息，主要需要 操作标识前缀
        $resi = $this->getResperInfo();

        //获取当前响应参数
        $ps = $resper->ctx;
        //获取当前请求的响应方法
        $rm = $ps["method"] ?? "default";
        //获取当前传递给响应方法的 $args 参数序列，即 uri
        $uri = $ps["uri"] ?? [];

        //请求的是 一些通用的的响应方法，统一处理
        if (in_array($rm, ["default", "empty", "uac", "db", "api", "error"])) {
            switch ($rm) {
                case "default":
                case "empty":
                case "error":
                case "uac":
                    /**
                     * 这些通用响应方法，在响应流程的初始阶段，不需要验证权限，直接返回 true
                     * 如果需要验证权限，应在这些方法内部 自行验证权限
                     */
                    return true;
                    break;

                case "db":  
                    /**
                     * 请求的是数据库操作，解析获取 db/dbn/mdn:foo/bar 形式的 操作标识
                     * 请求数据库操作的 url 类似：
                     * https://host/[appname/]db/dbn/mdn/update[/arg1/arg2/...]
                     * https://host/[appname/]db/dbn/mdn/api/apiname[/arg1/arg2/...]
                     */
                    //请求的是 api 方法
                    $isapi = in_array("api", $uri);
                    //缺少参数，直接返回 false 验证失败
                    if (count($uri)<3 || ($isapi && count($uri)<4)) return false;
                    //操作标识
                    $opr = "db/".implode("/", array_slice($uri, 0,2)).($isapi ? "/api" : "");
                    //实际请求的 数据模型方法（或数据模型api方法）
                    $args = $isapi ? array_slice($uri, 3) : array_slice($uri, 2);
                    //返回操作标识
                    return "$opr:".$args[0];
                    break;

                case "api":
                    /**
                     * 请求的是 响应者 api 方法，解析获取 api/app/pms:foo 形式的 操作标识
                     * 请求 响应者 某个 api 方法的 url 类似：
                     * https://host/pms/api/foo[/arg1/arg2/...]
                     */
                    //缺少参数 api 名称 直接返回 false 验证失败
                    if (empty($uri)) return false;
                    //返回操作标识
                    return "api/".$resi["pre"].":".$uri[0];
                    break;
                
            }
        }

        /**
         * 请求的是 响应者自定义的 响应方法（包含 * resper）注释的 方法
         * 请求的 url 类似：
         * https://host/[appname | custom resper name]/foo[/arg1/arg2/...]
         */
        //返回操作标识
        return $resi["pre"].":".$rm;

    }
}