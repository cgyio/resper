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
use Cgy\uac\Jwt;
use Cgy\Log;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Cls;

use Cgy\traits\staticCurrent;

class Uac
{
    //引入trait
    use staticCurrent;

    /**
     * current
     * 缓存已实例化的 Uac 类
     */
    public static $current = null;

    //依赖 Resper 实例
    public $resper = null;

    //Orm 初始化参数
    public $config = [];

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

        //启动 Uac
        $this->config = $uacc;
        $this->enable = true;

        //实例化 jwt 处理类
        $this->jwt = new Jwt($this);

        /**
         * 触发 orm-created 事件
         */
        Event::trigger("uac-created", $this);

    }

    /**
     * UAC 实例创建以后，执行一些 必要检查
     * 例如：必须的数据源检查，Jwt-Token 验证 等
     * 如果检查不通过，某些情况下，应终止响应
     * !! 此方法在响应者创建 Response 响应实例之前必须执行
     * @return Array 
     *  [
     *      "abort" => false,   如果需要终止响应，应在此处返回 true
     *  ]
     */
    public function verify()
    {
        if ($this->enable!==true) {
            //如果不启用 UAC 直接返回
            return true;
        }

        //检查必须的 usr/role 数据表是否存在
        $orm = $this->resper->orm;
        $mdc = $this->config["model"] ?? [];
        $utb = $mdc["usr"] ?? null;
        $rtb = $mdc["role"] ?? null;
        if ($orm->hasModel($utb)===false || $orm->hasModel($rtb)===false) {
            /**
             * 缺少必要的数据表，终止响应
             * !! 作为框架错误（生产环境中不应存在此错误），直接返回错误信息，并终止响应
             */
            header("Content-Type: text/html; charset=utf-8");
            echo "Resper Framework Error!";
            exit;
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

        }

    }



    /**
     * __callStatic
     */
    public static function __callStatic($key, $args)
    {
        $uac = self::$current;
        //如果 Uac 还未实例化，直接返回 null
        if (!$uac instanceof Uac) return null;

        /**
         * 快捷判断 是否启用 UAC
         * Uac::on()        --> Uac::$current->enable
         */
        if ($key==="on") return $uac->enable;

        /**
         * 快速判断 用户是否已登陆
         * Uac::isLogin()   --> Uac::$current->usr instanceof Record
         */
        if ($key==="isLogin") return $uac->enable && Uac::$current->usr instanceof Record;

        //未启用权限控制，之后的 方法直接返回
        if ($uac->enable!==true) return null;

        /**
         * 访问 Uac 权限控制必须的 数据表
         * 因为在 Uac 实例化时，已经检查过必要的数据表是否存在，此处不再检查
         * Uac::Usr()       --> Orm::[Usr table name]()
         * Uac::Role()      --> Orm::[Role table name]()
         */
        $orm = Orm::$current;
        $mdc = $uac->config["model"] ?? [];
        $tbn = $mdc[strtolower($key)] ?? null;
        if (Is::nemstr($tbn) && $orm->hasModel($tbn)!==false) {
            //返回 Db 实例，并将 Db 实例内部指针指向 $tbn
            return Orm::$tbn();
        }


    }
}