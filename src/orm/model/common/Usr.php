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
     * 快速权限验证
     * @param String $uid
     * @param Bool $all 是否要求全部有权限，默认 false
     * @param Array $oprs
     * @return Array [ "granted"=>false, "msg"=>"权限拒绝说明" ]
     */
    public static function checkAuthByUid($uid, $all=false, ...$oprs)
    {
        $usr = static::getByUid($uid);
        if (!$usr instanceof Record) {
            return [
                "granted" => false,
                "msg" => "用户ID不存在"
            ];
        }
        $okrtn = [
            "granted" => true,
            "msg" => "权限验证通过"
        ];
        $m = $all ? "checkAuthAll" : "checkAuthAny";
        if ($usr->$m(...$oprs)===true) return $okrtn;
        return [
            "granted" => false,
            "msg" => "用户没有权限",
            "opr" => $oprs
        ];
    }
    //快捷
    public static function checkAnyAuthByUid($uid, ...$oprs) { return static::checkAuthByUid($uid, false, ...$oprs); }
    public static function checkAllAuthByUid($uid, ...$oprs) { return static::checkAuthByUid($uid, true, ...$oprs); }



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
        //准备权限列表
        $auth = [
            /*
            "opr 操作标识",
            ...
            */
        ];

        //获取用户角色 列表 ModelSet
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
        return in_array("sys-super", $auth);
    }
    


    /**
     * 判断用户是否拥有 操作权限
     * 使用 || 逻辑，只需其中有一个有权限 即可
     * @param Array $oprs 要检查权限的 操作标识
     * @return Bool
     */
    public function checkAuthAny(...$oprs)
    {
        //SUPER 用户拥有全部权限
        if ($this->is_super===true) return true;

        $auth = $this->auth_list;
        if (!Is::nemarr($auth)) return false;
        //diff
        $diff = array_diff($oprs, $auth);
        return count($diff) < count($oprs);
    }

    /**
     * 判断用户是否拥有 操作权限
     * 使用 && 逻辑，必须要用所有权限
     * @param Array $oprs 要检查权限的 操作标识
     * @return Bool
     */
    public function checkAuthAll(...$oprs)
    {
        //SUPER 用户拥有全部权限
        if ($this->is_super===true) return true;
        
        $auth = $this->auth_list;
        if (!Is::nemarr($auth)) return false;
        //diff
        $diff = array_diff($oprs, $auth);
        return empty($diff);
    }
}