<?php
/**
 * cgyio/resper 核心类
 * Uac 权限控制类
 * 
 * 权限控制类通常需要启动 Orm 数据库功能
 * 
 */

namespace Cgy;

use Cgy\Resper;
use Cgy\Request;
use Cgy\Response;
use Cgy\Event;
use Cgy\Orm;
use Cgy\orm\Db;
use Cgy\orm\Model;
use Cgy\orm\model\Record;
use Cgy\uac\Config;
use Cgy\uac\Jwt;
use Cgy\uac\Operation;
use Cgy\Log;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Cls;

use Cgy\traits\staticCurrent;
use Cgy\traits\staticExtra;

class Uac
{
    //引入trait
    use staticCurrent;
    use staticExtra;

    /**
     * current
     * 缓存已实例化的 Uac 类
     */
    public static $current = null;

    /**
     * extra 
     * 此类正常情况下是以单例模式运行，但是也支持 另外创建实例
     * 如果有响应者被劫持，则被劫持的响应者关联的 Uac 实例就需要另外创建
     * 另外创建的 Uac 实例缓存到此属性下，并不会影响已有的 Uac::$current 单例
     */
    public static $extra = [
        /*
        "EX_md5(resper::class)" => Uac 实例
        */
    ];
    //标记此 Uac 实例是否是 被劫持的响应者实例关联的
    public $isExtra = false;
    //如果是被劫持响应者关联的实例，则此实例在 Uac::$extra 数组中的键名
    public $exKey = "";

    //依赖 Resper 实例
    public $resper = null;

    //Orm 初始化参数
    public $config = null;

    //是否启用 UAC 控制
    public $enable = false;

    /**
     * 缓存用户数据记录实例 Record
     */
    public $usr = null;

    /**
     * jwt 处理类实例
     */
    protected $jwt = null;

    /**
     * 权限操作列表 生成/管理 类实例
     */
    public $opr = null;

    /**
     * 构造
     * @param Resper $resper 传入关联的 Resper 实例
     * @return void
     */
    public function __construct($resper)
    {
        //依赖 Resper 实例
        $this->resper = $resper;
        $conf = $resper->conf;

        //Uac 参数
        $uacc = $conf["uac"] ?? [];
        //检查参数中是否定义了 不启动 UAC
        if (empty($uacc) || (isset($uacc["enable"]) && $uacc["enable"]!==true)) {
            //标记为 不启动 UAC 然后返回，以便 resper 类外部检验
            $this->enable = false;
            return $this;
        }
        //实例化 uac/Config
        $this->config = new Config($uacc);

        //启动 Uac
        $this->enable = true;

        //实例化 jwt 处理类
        $this->jwt = new Jwt($this);

        //实例化 权限操作列表处理类
        $oprc = $uacc["operation"] ?? [];
        $oprHandler = $oprc["handler"] ?? null;
        if (Is::nemstr($oprHandler) && class_exists($oprHandler)) {
            //可自定义 操作列表工具类
            $this->opr = new $oprHandler($this);
        } else {
            //未自定义，则使用默认的
            $this->opr = new Operation($this);
        }

        /**
         * 触发 orm-created 事件
         */
        Event::trigger("uac-created", $this);

    }

    /**
     * UAC 实例创建以后，
     *  1   执行一些 必要检查，例如：必须的数据源检查，Jwt-Token 验证 等
     *  2   检查通过，用户实例已关联，则 验证 当前请求的操作 是否具有权限
     * 
     * 如果检查不通过，可 跳转登陆界面 / 终止响应 / 报错 
     * 
     * !! 此方法 在响应者创建 Response 响应实例之前 必须执行
     * 
     * @return Bool|exit
     */
    public function verify()
    {
        if ($this->enable!==true) {
            //如果不启用 UAC 直接返回
            return true;
        }

        //获取 当前请求的 操作标识
        $copr = Uac::copr();
        if ($copr===true) {
            /**
             * 当前 操作标识 返回 true 
             * 表示此时不需要验证权限，应在请求的响应方法内部 自行验证权限
             */
            return true;
        }


        //检查必须的 usr/role 数据表是否存在
        $orm = $this->resper->orm;
        $mdc = $this->config->model ?? [];
        $utb = $mdc["usr"] ?? null;
        $rtb = $mdc["role"] ?? null;
        if ($orm->hasModel($utb)===false || $orm->hasModel($rtb)===false) {
            /**
             * 缺少必要的数据表，终止响应
             * !! 作为框架错误（生产环境中不应存在此错误），直接返回错误信息，并终止响应
             */
            trigger_error("uac/fatal::当前响应者开启了权限控制，但是未指定必需的用户以及权限数据表", E_USER_ERROR);
        }

        //验证 Jwt-Token 正确性，此步骤还不会验证用户的权限，仅解析获取保存在 token 中的用户数据
        $vali = $this->jwt->validate();
        if ($vali["success"]!==true) {
            //Token 未通过验证，根据验证返回的错误状态，决定之后的处理方法
            if ($vali["status"]=="emptyToken" || $vali["status"]=="expired") {
                //用户未登录 或 Token 已过期，执行 当前响应者的 responseLogin() 方法，跳转到登陆界面
                $this->resper->responseLogin($vali);
                exit;
            }

            //此步骤 不可能返回 用户无权限 状态，不做处理
            if ($vali["status"]=="noauth") return true;

            /**
             * !! 其他错误状态：来源不一致/解码错误 说明 Token 可能被篡改，直接报错，终止响应
             * 调用 当前响应者的 responseTokenError() 方法，报错
             */
            $this->resper->responseTokenError($vali);
            exit;
        } else {
            //Token 通过验证，获取到用户数据，执行用户权限检查
            $payload = $vali["payload"] ?? [];

            if (!Is::nemarr($payload) || !Is::nemstr($payload["uid"])) {
                //token 中未包含数据，跳转登录界面
                $this->resper->responseLogin($vali);
                exit;
            }

            //获取 usr 登录用户记录实例
            $uid = $payload["uid"];
            $usr = Uac::Usr()->getByUid($uid);
            if (!$usr instanceof Record) {
                //token 中包含的用户数据不正确，重新登陆
                $this->resper->responseLogin($vali);
                exit;
            }

            //缓存用户记录实例
            $this->usr = $usr;

            /**
             * 前置验证成功，开始验证用户权限
             * 验证用户是否拥有 当前请求的响应方法的权限
             */
            if ($copr===false || is_null($copr) || (!is_bool($copr) && !Is::nemstr($copr))) {
                /**
                 * 获取 操作标识 发生错误，不能通过验证
                 * !! 作为框架错误（生产环境中不应存在此错误），直接返回错误信息，并终止响应
                 */
                trigger_error("uac/fatal::无法取得当前请求的操作标识，导致无法执行权限验证", E_USER_ERROR);
            }

            //正常返回 当前请求的 操作标识，调用 用户实例 验证权限
            $ac = $this->usr->ac($copr);
            if ($ac["granted"]!==true) {
                //用户无权限，报错
                trigger_error("uac/denied::".$ac["msg"], E_USER_ERROR);
                exit;
            }

            return true;
        }

    }

    /**
     * 代替 resper 响应权限管理相关的请求
     * 发送到 https://host/[foobar/]uac/*** 的请求，会被转发到此，进行响应处理
     * @param Array $args 请求的 URI
     * @return Mixed
     */
    public function response(...$args)
    {
        if ($this->enable!==true) {
            //如果不启用 UAC 直接返回
            return true;
        }

        if (empty($args)) {
            //空路由，
            return false;
        }

        $action = array_shift($args);
        $rm = "response".ucfirst($action);
        if (method_exists($this, $rm)) {
            return $this->$rm(...$args);
        }

        exit;
    }
    //输出全部 权限操作列表 label-value 形式
    protected function responseOperates()
    {
        return $this->opr->values();
    }



    /**
     * 静态方法
     */

    /**
     * 从当前的请求中解析出 操作标识，以供权限确认
     * 调用 Operation::current() 方法
     * @return String|Bool|null
     * 返回 true 时，直接通过权限验证，针对一些不需要权限的 操作
     * 返回 false|null 时，直接权限验证失败，可能是缺少必要的权限验证条件
     * 返回 操作标识 则需要进一步验证权限
     */
    public static function copr()
    {
        //如果当前未启用 Uac 则返回 true 直接通过验证
        if (Uac::on()!==true) return true;
        $uac = Uac::$current;
        //获取当前的 权限操作处理类实例，不存在则返回 null 直接验证失败
        $opr = $uac->opr;
        if (!$opr instanceof Operation) return null;
        //从请求中解析出 操作标识
        $copr = $opr::current();    //$opr->current();
        //如果此操作标识，不在 操作列表 中，表示此操作不需要权限控制，直接返回 true
        //不需要权限控制的方法（注释中含有 @auth false）也返回 true
        if (!$opr->hasOperation($copr)) return true;

        return $copr;
    }

    /**
     * 返回统一的 权限验证结果数据
     * @param Bool $granted
     * @param Array $oprs 如果 granted==false 此处传入 拒绝的操作标识列表
     * @param String $msg 权限拒绝说明，不指定使用默认
     * @return Array 统一的权限验证结果
     *  [
     *      "granted" => true|false,
     *      "msg" => "权限验证通过|无操作权限 [ opr1, opr2, ... ]",
     *      "oprs" => [ 验证不通过的情况下，返回拒绝的操作列表 ]
     *  ]
     */
    public static function rtn($granted, $oprs=[], $msg="")
    {
        if (!Is::nemstr($msg)) {
            if ($granted===true) {
                $msg = "权限验证通过";
            } else {
                $msg = "无操作权限";
            }
        }

        if (!Is::nemarr($oprs)) $oprs = [];
        if ($granted!==true && Is::nemarr($oprs)) {
            $msg .= " [".implode(", ", $oprs)."]";
        }

        return [
            "granted" => $granted,
            "msg" => $msg,
            "oprs" => $oprs
        ];
    }

    /**
     * 全局快速权限判断
     * Uac::granted([true|false,] opr1, opr2, ...)
     * @param Array $oprs 要验证权限的 操作标识列表
     * @return Array 统一的 权限验证结果数据
     */
    public static function granted(...$oprs)
    {
        //未开启权限控制，直接返回通过
        if (Uac::on()!==true) return Uac::rtn(true);
        //当前 uac 实例
        $uac = Uac::$current;
        //关联到 uac 实例的 操作管理类 operation 类实例
        $opr = $uac->opr;
        
        $all = false;
        if (is_bool($oprs[0])) {
            //如果第一个参数是 boolean 则作为 $all 参数
            $all = array_shift($oprs);
        }
        //先将 要验证的操作列表中的 不需要权限控制的 操作 排除掉
        $alloprs = $opr->getAllAuthOprs();
        $diff = array_diff($oprs, $alloprs);    //不需要验证权限的操作
        if (empty($diff)) {
            $noprs = $oprs;
        } else {
            $noprs = array_diff($oprs, $diff);
        }
        if (empty($noprs)) {
            //最终需要验证权限的 操作列表为空，直接返回通过
            return Uac::rtn(true);
        }

        //将 all 参数插回 oprs 参数数组首位
        array_unshift($noprs, $all);
        
        //已登录的用户
        $usr = $uac->usr;
        if (!$usr instanceof Record) {
            //用户还未登录
            return Uac::rtn(false, [], "用户未登录");
        }

        //调用 用户实例 执行权限验证
        return $usr->ac(...$noprs);
    }

    /**
     * __callStatic
     * 全局 Uac 相关操作，大部分通过此方法
     */
    public static function __callStatic($key, $args)
    {
        //Uac 实例
        $uac = Uac::$current;
        //Uac 是否已实例化
        $uacInsed = $uac instanceof Uac;
        //Uac 是否启用
        $uacEnable = $uacInsed ? $uac->enable : false;
        //登录用户实例
        $usr = $uacEnable ? $uac->usr : null;
        //Uac 是否用户已登录 存在 用户实例
        $uacLogin = $usr instanceof Record;

        /**
         * 快捷判断 是否启用 UAC
         * Uac::on()        --> Uac::$current->enable
         * @return Bool
         */
        if ($key==="on") return $uacEnable;

        /**
         * 快速判断 用户是否已登陆
         * Uac::isLogin()   --> Uac::$current->usr instanceof Record
         * @return Bool
         */
        if ($key==="isLogin") return $uacLogin;

        /**
         * 全局快速权限判断
         * //Uac::granted([true|false,] opr1, opr2, ...)
         * Uac::grantedDbPmsPsku([true|false,] 'create', 'delete')
         * Uac::grantedDbPmsPskuApi([true|false,] 'foo', 'bar')
         * Uac::grantedApiAppPms([true|false,] 'foo', 'bar')
         * Uac::grantedFoo([true|false,] 'foo', 'bar')
         * @return Array 统一的 权限验证结果数据
         */
        if (substr($key, 0,7)==="granted") {
            if ($uacLogin!==true) return Uac::rtn(false, [], "用户未登录");
            
            //Uac::granted()
            //if ($key=="granted") return $usr->ac(...$args);

            /**
             * Uac::grantedDbPmsPsku()
             * Uac::grantedDbPmsPskuApi()
             * 
             * Uac::grantedDbPmsPsku(false, 'create','update')  将生成操作列表：
             * [ false, 'db/pms/psku:create', 'db/pms/psku:delete' ]
             */
            $opk = substr($key, 7);
            //DbPmsPsku  -->  db/pms/psku
            $opk = strtolower(Str::snake($opk,"/"));
            //操作列表
            $oprs = [];
            for ($i=0;$i<count($args);$i++) {
                $opi = $args[$i];
                if (is_bool($opi) && $i==0) {
                    $oprs[] = $opi;
                    continue;
                }
                if (Is::nemstr($opi)) {
                    //生成操作标识 grantedDbPmsPskuApi()
                    $oprs[] = "$opk:$opi";
                }
            }
            //验证 操作列表
            return $usr->ac(...$oprs);
        }
        

        /**
         * 访问 Uac 权限控制必须的 数据表
         * 因为在 Uac 实例化时，已经检查过必要的数据表是否存在，此处不再检查
         * Uac::Usr()       --> Orm::[Usr table name]()
         * Uac::Role()      --> Orm::[Role table name]()
         */
        if (in_array($key, ["Usr","Role"])) {
            if ($uacEnable!==true) return null;
            $orm = Orm::$current;
            $mdc = $uac->config->model ?? [];
            $tbn = $mdc[strtolower($key)] ?? null;
            if (Is::nemstr($tbn) && $orm->hasModel($tbn)!==false) {
                //返回 Db 实例，并将 Db 实例内部指针指向 $tbn
                return Orm::$tbn();
            }
        }


        return null;

    }
}