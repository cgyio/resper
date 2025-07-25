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
use Cgy\orm\Db;
use Cgy\orm\Model;
use Cgy\orm\model\Record;
use Cgy\Uac;
use Cgy\module\configer\traits\runtimeCache;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Cls;
use Cgy\util\Path;
use Cgy\util\Conv;

class Operation 
{
    /**
     * 使用 runtime 缓存功能
     */
    use runtimeCache;

    /**
     * 依赖项
     */
    //关联的 resper 响应者实例
    public $resper = null;
    //关联的 uac 权限控制实例
    public $uac = null;

    /**
     * 预定义 sysOprs 系统操作
     * !! 子类如果要覆盖，请在此基础上扩展
     */
    public static $dftSysOprs = [
        //登录
        "sys:login" => "系统操作：登录",
        //整站暂停服务
        "sys:pause" => "系统操作：暂停服务",
    ];

    /**
     * 预定义 任意数据库的 固有操作
     * !! 子类如果要覆盖，请在此基础上扩展
     */
    public static $dftDbOprs = [
        "install"   => "安装/重建数据库",
        "manual"    => "手动管理",
    ];

    /**
     * 预定义 任意数据模型的 固有操作
     * !! 子类如果要覆盖，请在此基础上扩展
     */
    public static $dftModelOprs = [
        "create"    => "新建记录",
        "update"    => "修改记录",
        "retrieve"  => "查询记录",
        "delete"    => "删除记录",
        "toggle"    => "切换记录生效/失效",
        "direct"    => "直接通过表单编辑记录",
        //"full"      => "拥有所有权限"
    ];

    /**
     * 解析得到的各类型 权限操作列表 缓存
     */
    protected $context = [
        /*
        role:super                  => [
            "desc" => "用户角色：超级管理员",
            ...
        ],
        role:storage/manager        => ["desc"=>用户角色：仓库主管, ...]
        sys:pause                   => ["desc"=>系统操作：暂停服务, ...]
        db/pms/psku:create          => ["desc"=>产品表：新建记录, ...]
        db/pms/psku:update          => ["desc"=>产品表：修改记录, ...]
        api/app/pms:foo             => ["desc"=>PMS系统API：api说明文字, ...]
        api/module/foo:bar          => ["desc"=>模块foo的名称API：api说明文字, ...]
        ...
        */
    ];

    /**
     * 定义 某个操作信息的 数据结构
     * !! 不要覆盖
     */
    protected $dftOprc = [
        //操作标识，全小写，下划线_ 格式，以 / 作为路径间隔符，如：db/db_name/model_name/api:api_name
        "oprn" => "",
        //操作说明，中文说明，例如：某某数据表：新建记录
        "desc" => "",
        //操作名称，操作标识 : 后面的部分，通常是 方法名，全小写，下划线_ 格式
        "name" => "",
        //操作标题，操作说明 ： 后面的部分，通常是 方法的标题
        "title" => "",
        //操作对应的 实际方法名，驼峰，首字母小写，例如：fooBarApi / createProxy / foo
        "method" => "",
        //操作对应的实际方法的 所在类的 类全称，例如：某个数据模型类 / 某个自定义响应者类 / 某个app类 / 某个module类
        "class" => "",
        //此操作是否启用 uac 权限控制，不启用时，在权限验证时，将不针对此操作验证用户权限，可通过在对应的方法注释中定义 @auth false
        "auth" => true,
        //操作针对的用户角色，如果指定了角色(可有多个)，则在权限验证中，优先于用户权限匹配操作
        "role" => "all",
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
        $uacc = $uac->config;   //$conf["uac"] ?? [];
        $oprc = $uacc->operation ?? [];
        $oprs = $oprc["operates"] ?? [];
        if (Is::nemarr($oprs) && Is::associate($oprs)) {
            $this->context = Arr::extend($this->context, $oprs);
        }

        //runtime 缓存路径 [webroot]/runtime/[$resper->cls全小写]/operations[.json]
        $this->runtimeCache = $this->resper->rtp."operations";

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
        //首先检查 runtime 缓存
        $rc = $this->getRuntimeContext();
        if (Is::nemarr($rc)) {
            //存在可用缓存，直接使用缓存数据
            $this->context = $rc;
        } else {
            //不存在可用缓存，依次获取 多种类型的 操作列表
            $this->addRoleOperates();
            $this->addSysOperates();
            $this->addDbOperates();
            $this->addApiOperates();
            $this->addResperOperates();
    
            //最后执行 自定义的 其他类型的 操作列表 的获取方式
            $this->addCustomOperates();

            //缓存生成的 context 到 runtime
            $this->cacheRuntimeContext();
        }

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
            $rnm = explode(":",$rk)[1];
            //写入 操作列表
            $this->setOperate($rk, [
                "name" => $rnm,
                "desc" => "用户角色：".$rn,
                "title" => $rn
            ]);
        }
        //返回
        return $this;
    }
    //生成 系统单独指定的 特殊操作列表，子类可覆盖此方法
    protected function addSysOperates()
    {
        //定义一些通用的 系统操作
        foreach (static::$dftSysOprs as $oprn => $opri) {
            $rn = explode(":",$oprn)[1];
            $rt = array_slice(explode("：",$opri),-1)[0];
            //写入 操作列表
            $this->setOperate($oprn, [
                "name" => $rn,
                "desc" => $opri,
                "title" => $rt
            ]);
        }

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
        $dbns = $orm->config->dbns ?? [];
        if (!Is::nemarr($dbns)) return $this;
        //每个数据库的固有权限
        $dbauth = static::$dftDbOprs;
        //每个数据表的固有权限
        $mdauth = static::$dftModelOprs;
        //循环添加所有可用数据表
        foreach ($dbns as $i => $dbn) {
            //实例化数据库
            $db = $orm->db($dbn);

            /**
             * 生成数据库 固有的 操作列表
             */
            //操作标识前缀
            $dpre = "db/".Orm::snake($dbn);
            $dbt = $db->config->title;
            $dprms = $db->config->proxyMethods;
            $dbcls = get_class($db);
            foreach ($dbauth as $dopr => $dopi) {
                $dopr = Orm::snake($dopr);
                $doprn = $dpre.":".$dopr;
                if (isset($dprms[$dopr])) {
                    //数据库 config 中包含了 此 proxy 方法
                    $this->setOperate($doprn, $dprms[$dopr]);
                } else {
                    //写入 操作列表
                    $this->setOperate($doprn, [
                        "desc" => $dbt."：".$dopi,
                        "name" => $dopr,
                        "title" => $dopi,
                        "method" => Orm::camel($dopr, false)."Proxy",
                        "class" => $dbcls,
                    ]);
                }
            }

            /**
             * 数据库 api 操作列表
             * !! 数据库不定义 api 操作，因此不做处理
             */
            //...

            //获取数据表名，同时初始化这些表，因为后面还需要读取这些数据表中的 api 方法
            $mdns = $db->modelNames(true);
            if (!Is::nemarr($mdns)) continue;
            foreach ($mdns as $k => $mdn) {
                //表名称
                $mdt = $db->$mdn->title;
                //操作标识前缀
                $pre = "db/".Orm::snake($dbn)."/".Orm::snake($mdn);
                //数据模型类全称
                $mcls = $db->hasModel($mdn);
                
                /**
                 * 生成数据表 固有的 操作列表
                 */
                $mprms = $db->$mdn->proxyMethods;
                foreach ($mdauth as $opr => $opi) {
                    $opr = Orm::snake($opr);
                    $moprn = $pre.":".$opr;
                    if (isset($mprms[$opr])) {
                        //数据模型 config 中包含了 此 proxy 方法
                        $this->setOperate($moprn, $mprms[$opr]);
                    } else {
                        //写入 操作列表
                        $this->setOperate($moprn, [
                            "desc" => $mdt."：".$opi,
                            "name" => $opr,
                            "title" => $opi,
                            "method" => Orm::camel($opr, false)."Proxy",
                            "class" => $mcls,
                        ]);
                    }
                }

                /**
                 * 生成数据表 api 操作列表
                 */
                $apis = $db->$mdn->api;
                if (Is::nemarr($apis) && Is::associate($apis)) {
                    foreach ($apis as $apin => $apic) {
                        //记录 api 操作标识
                        $oprn = $apic["oprn"] ?? null;
                        if (!Is::nemstr($oprn)) continue;
                        $desc = $apic["desc"] ?? $mdt."：".Orm::camel($apin,false)."()";
                        $apic["desc"] = $desc;
                        //写入 操作列表
                        $this->setOperate($oprn, $apic);
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
        //直接从 当前响应者 config 中获取 apis 列表
        $apis = $this->resper->conf["apis"] ?? [];
        foreach ($apis as $ak => $ac) {
            $oprn = $ac["oprn"];
            //写入 操作列表
            $this->setOperate($oprn, $ac);
        }
        return $this;
    }
    //生成 当前响应者实例方法 操作列表
    protected function addResperOperates()
    {
        //直接从 当前响应者 config 中获取 respers 列表
        $rms = $this->resper->conf["respers"] ?? [];
        foreach ($rms as $rk => $rc) {
            $oprn = $rc["oprn"];
            //写入 操作列表
            $this->setOperate($oprn, $rc);
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
                "label" => $opk." > ".$opi["desc"],
                "value" => $opk
            ];
        }
        return $vals;
    }



    /**
     * tools
     */

    /**
     * 将 操作标识 和 操作说明 写入 context
     * @param String $oprn 操作标识
     * @param Array $oprc 此操作的相关数据
     * @return $this
     */
    protected function setOperate($oprn, $oprc=[])
    {
        if (!Is::nemstr($oprn) || !Is::nemarr($oprc)) return $this;
        //默认 操作信息的 数据结构
        $doprc = array_merge($this->dftOprc, []);
        //已有的 操作信息
        $ooprc = $this->context[$oprn] ?? [];
        //处理后的 操作信息
        $coprc = Arr::extend($doprc, $ooprc, $oprc);

        /**
         * 对于 role|sys 形式的 操作标识，特别处理
         */
        if (substr($oprn, 0, 5)==="role:") {
            $coprc = Arr::extend($coprc, [
                "method" => "",
                "class" => "role",
                "auth" => true,
                "role" => "all"
            ]);
        }
        if (substr($oprn, 0, 4)==="sys:") {
            $coprc = Arr::extend($coprc, [
                "method" => "",
                "class" => "sys",
                "auth" => true,
                "role" => "all"
            ]);
        }

        //自动写入 oprn
        $coprc["oprn"] = $oprn;

        //写入 context
        $this->context[$oprn] = $coprc;
        return $this;
    }

    /**
     * 获取所有需要 权限控制的 操作列表，即 $this->context[oprn]["auth"] !== false
     * @return Array 所有 操作标识 数组  [ sys:login, db/foo:bar, ... ]
     */
    public function getAllAuthOprs()
    {
        $oprs = [];
        foreach ($this->context as $oprn => $oprc) {
            if ($oprc["auth"]!==false) {
                $oprs[] = $oprn;
            }
        }
        return $oprs;
    }

    /**
     * 判断给定的 操作标识 是否在 $this->context 操作列表中
     * !! 如果操作标识不在操作列表中，表示此操作不受权限控制，可直接访问
     * @param String $opr 操作标识
     * @return Bool
     */
    public function hasOperation($opr=null)
    {
        if (!Is::nemstr($opr)) return false;
        //操作列表
        $ctx = $this->getAllAuthOprs();
        //检查给定 操作标识 是否在 操作列表中
        return in_array($opr, $ctx);
    }



    /**
     * static tools
     */

    /**
     * 获取当前响应者 相关信息
     * @param Resper $resper 响应者实例，默认 null 使用 Resper::$resper
     * @return Array 
     */
    protected static function getResperInfo($resper=null)
    {
        //获取当前响应者实例
        if (!$resper instanceof Resper) $resper = Resper::$resper;
        if (!$resper instanceof Resper) return [];
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
        $pre = ($rtype=="Resper" ? "" : strtolower($rtype)."/").Orm::snake($rname);

        return [
            "type" => $rtype,
            "rtnm" => $rtns[$rtype],
            //当前响应者类名，不包含 namespace，如：\Cgy\app\Pms  -->  Pms
            "name" => $rname,
            //当前响应者的 intr 说明，通常在 resper 子类中定义 intr 属性，未定义则显示为 应用Foo  或  模块Foo  或  自定义Foo
            "intr" => $rintr,
            //当前响应者中 操作标识的 前缀  app/pms  or  module/foo_bar  or  foo_bar
            "pre" => $pre,
        ];
    }

    /**
     * 从当前的请求中解析出 操作标识，以供权限确认
     * @return String|Bool|null
     * 返回 true 时，直接通过权限验证，针对一些不需要权限的操作 或 在操作内部自行进行权限控制的方法
     * 返回 false|null 时，直接权限验证失败，可能是缺少必要的权限验证条件
     * 返回 操作标识 则需要进一步验证权限
     */
    public static function current()
    {
        //如果当前未启用 Uac 则返回 true 直接通过验证
        if (Uac::on()!==true) return true;
        $uac = Uac::$current;

        //获取当前响应者实例，不存在 则返回 null 直接验证失败
        $resper = $uac->resper;
        if (!$resper instanceof Resper) return null;

        //获取当前响应者的相关信息，主要需要 操作标识前缀
        $resi = static::getResperInfo($resper);

        //获取当前响应参数
        $ps = $resper->ctx;
        //获取当前请求的响应方法
        $rm = $ps["method"] ?? "default";
        //获取当前传递给响应方法的 $args 参数序列，即 uri
        $uri = $ps["uri"] ?? [];

        /**
         * 当请求的是 一些通用的的响应方法时
         * 直接返回 true 此处不做权限控制，应在对应的响应方法内部进行 权限控制
         * !! 通常这些响应方法会调用对应的 FooBarProxyer 代理类，来代理响应者执行响应方法，在 代理类 内部通常会进行 权限控制
         */
        if (in_array($rm, Resper::$methods["common"])) {
            return true;
        }

        /**
         * 请求的是 响应者自定义的 响应方法（包含 * resper）注释的 方法
         * 请求的 url 类似：
         * https://host/[appname | custom resper name]/foo[/arg1/arg2/...]
         */
        //返回操作标识，其中方法名为 全小写，下划线_ 格式
        return $resi["pre"].":".Orm::snake($rm);

    }

    /**
     * 根据 任意类型的 响应者 类全称，生成 操作标识前缀
     * NS\module\FooBar  -->  module/foo_bar
     * @param String $resper 响应者 类全称 或 类实例
     * @return String|null 操作标识前缀
     */
    public static function getResperOperatePrefix($resper) 
    {
        if (!Is::nemstr($resper) && !$resper instanceof Resper) return null;
        if (Is::nemstr($resper)) {
            //传入 类全称，此类必须存在 且 已初始化
            if (!class_exists($resper)) return null;
        } else if ($resper instanceof Resper) {
            //传入 类实例
            $resper = get_class($resper);
        } else {
            return null;
        }

        //去除类全称中的 通用 NS 前缀
        $resper = str_replace(NS,"",$resper);
        //转为 []
        $rarr = explode("\\", trim($resper, "\\"));
        //将 响应者 类名 由 FooBar 转为 foo_bar 形式
        $rn = array_pop($rarr);
        $rk = Str::snake($rn, "_");
        //回填
        $rarr[] = $rk;
        //返回前缀 一定是全小写
        return strtolower(implode("/", $rarr));
    }

    /**
     * 根据 数据模型 类全称，生成 操作标识前缀
     * @param String $model 数据模型 类全称 或 类实例
     * @return String|null 操作标识前缀
     */
    public static function getModelOperatePrefix($model=null)
    {
        if (!Is::nemstr($model) && !$model instanceof Model) return null;
        if (Is::nemstr($model)) {
            //传入 类全称，此类必须存在 且 已初始化
            if (!class_exists($model) || !$model::$db instanceof Db) return null;
        } else if ($model instanceof Model) {
            //传入 类实例
            $model = get_class($model);
        } else {
            return null;
        }
        
        //操作标识 []
        $oparr = ["db"];

        //关联的 数据库实例
        $db = $model::$db;
        $oparr[] = $db->name;
        $oparr[] = $model::tbn();

        //操作标识前缀  一定是全小写
        return strtolower(implode("/", $oparr));
    }
}