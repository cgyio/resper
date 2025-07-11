<?php
/**
 * resper 框架 通用的 数据模型类
 * 一些具有相同结构的数据模型(表)，可以继承这些通用的类，以实现一些通用的 数据模型方法
 * 
 * Usr 用户表 通用数据模型
 * 用户表在用于 Uac 权限控制时，有一些通用的方法
 */

namespace Cgy\orm\model\common;

use Cgy\Orm;
use Cgy\orm\Model;
use Cgy\orm\model\ModelSet;
use Cgy\orm\model\Record;
use Cgy\Uac;
use Cgy\util\Is;

class Usr extends Model 
{
    /**
     * !! 必须声明覆盖 Model 父类的 $cls 静态属性
     * 否则会出现 多个模型类使用相同的 db实例/模型类全称
     */
    public static $db = null;
    public static $cls = "";
    public static $config = null;

    /**
     * 用户权限列表 缓存
     */
    protected $AUTH = [];



    /**
     * 表方法（静态方法）
     */

    /**
     * 根据 uid 获取用户实例
     * !! uid 字段是必须的
     * @param String $uid 传入的 uid
     * @return Record 模型实例 未找到则返回 null
     */
    public static function getByUid($uid=null)
    {
        if (!Is::nemstr($uid)) return null;
        $rs = static::md()->whereUid($uid)->get();
        return $rs;
    }



    /**
     * 实例方法
     */

    /**
     * getter
     * @name auth_list
     * @title 权限列表
     * @desc 此用户拥有的所有操作权限列表
     * @type varchar
     * @jstype array
     * @phptype json
     * 
     * @return Array [ "操作标识", ... ]
     */
    protected function authListGetter()
    {
        //首先尝试读取缓存
        if (Is::nemarr($this->AUTH)) return $this->AUTH;

        //准备权限列表
        $auth = [
            /**
             * 增加一些所有用户都有权限的 操作标识
             * 通用响应方法 empty/error/default 都不需要权限，在此统一为用户添加权限
             */
            //"resper:common",

            /*
            "opr 操作标识",
            ...
            */
        ];

        //首先将用户拥有的角色，转为操作标识，存入 权限列表
        $roleKeys = $this->role;    // [ 'role:normal', 'role:dev/manager', roleKey3, ... ]
        if (Is::nemarr($roleKeys) && Is::indexed($roleKeys)) {
            foreach ($roleKeys as $i => $rk) {
                if (!in_array($rk, $auth)) {
                    $auth[] = $rk;
                }
            }
        }

        //获取用户角色 列表 ModelSet，合并角色的权限列表
        $roles = $this->role_src;
        if (!empty($roles)) {
            $roles->each(function($role) use (&$auth) {
                //操作标识 []
                $as = $role->auth;
                //写入权限列表
                if (Is::nemarr($as)) {
                    $auth = array_unique(array_merge($auth, $as));
                }
            });
        }

        //合并用户自有的权限
        $uauth = $this->auth;
        if (Is::nemarr($uauth)) {
            $auth = array_unique(array_merge($auth, $uauth));
        }

        //缓存
        $this->AUTH = $auth;

        //返回用户权限列表
        return $auth;
    }

    /**
     * getter
     * @name is_super
     * @title 超级管理员
     * @desc 此用户是否超级管理员
     * @type integer
     * @jstype boolean
     * @phptype Bool
     * 
     * @return Bool
     */
    protected function isSuperGetter()
    {
        $auth = $this->auth_list;
        if (!Is::nemarr($auth)) return false;
        return in_array("role:super", $auth);
    }
    


    /**
     * 判断用户是否某个用户角色
     * @param Array $roles 要检查权限的 角色 key，第一个可以是 boolean 用来指定是否必须全部满足
     * @return Bool
     */
    public function isRole(...$roles)
    {
        //SUPER 用户拥有全部角色
        if ($this->is_super===true) return true;

        if (empty($roles)) return false;

        //是否必须全部满足，默认 false 只需满足其一即可
        $all = false;
        if (is_bool($roles[0])) {
            //如果第一个参数是 boolean 则作为 $all 参数
            $all = array_shift($roles);
        }

        //进行 角色判断
        $role = $this->role;
        $roles = $this->fixRoleKeyInArray($roles);
        //diff
        $diff = array_diff($roles, $role);

        if ($all===true) {
            //必须拥有所有 $roles 角色
            return empty($diff);
        } else {
            //只需拥有 $roles 其中之一即可
            return count($diff) < count($roles);
        }
    }
    //快捷
    public function isRoleAny(...$roles) { 
        array_unshift($roles, false);
        return $this->isRole(...$roles); 
    }
    public function isRoleAll(...$roles) { 
        array_unshift($roles, true);
        return $this->isRole(...$roles); 
    }

    /**
     * 判断用户是否拥有 操作权限
     * @param Array $oprs 要检查权限的 操作标识，第一个可以是 boolean 用来指定是否必须全部满足
     * @return Array  统一的权限验证返回结果
     */
    public function ac(...$oprs)
    {
        //SUPER 用户拥有全部权限
        if ($this->is_super===true) return $this->acRtn(true);

        if (empty($oprs)) return $this->acRtn(false);

        //是否必须全部满足，默认 false 只需满足其一即可
        $all = false;
        if (is_bool($oprs[0])) {
            //如果第一个参数是 boolean 则作为 $all 参数
            $all = array_shift($oprs);
        }

        //执行权限判断
        $auth = $this->auth_list;
        if (!Is::nemarr($auth)) return $this->acRtn(false);
        //diff
        $diff = array_diff($oprs, $auth);

        if ($all===true) {
            //必须拥有所有 $oprs 权限
            $granted = empty($diff);
        } else {
            //只需拥有 $oprs 其中之一即可
            $granted = count($diff) < count($oprs);
        }
        //返回验证结果
        return $this->acRtn($granted, $diff);
    }
    //快捷
    public function acAny(...$oprs) { 
        array_unshift($oprs, false);
        return $this->ac(...$oprs); 
    }
    public function acAll(...$oprs) { 
        array_unshift($oprs, true);
        return $this->ac(...$oprs); 
    }



    /**
     * tools
     */

    /**
     * 返回统一的 权限验证结果数据
     * 直接通过 Uac::rtn() 方法来执行
     */
    protected function acRtn(...$args)
    {
        return Uac::rtn(...$args);
    }

    // ['foo','bar'] --> ['role:foo','role:bar']
    protected function fixRoleKeyInArray($roles=[])
    {
        if (!Is::nemarr($roles)) return [];
        $roles = array_map(function($ri) {
            if (!Is::nemstr($ri)) return null;
            if (substr($ri, 0, 5)!=="role:") return "role:$ri";
            return $ri;
        }, $roles);
        $roles = array_filter($roles, function($ri) {
            return Is::nemstr($ri);
        });
        return array_merge($roles);
    }
}