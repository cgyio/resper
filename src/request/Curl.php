<?php
/**
 * cgyio/resper cURL toolkit
 */

namespace Cgy\request;

use Cgy\request\Url;
use Cgy\util\Is;
use Cgy\util\Arr;

class Curl
{
    //curl handler
    public $curl = null;

    //预定义的curlopt参数组，适用于不同的需求，可叠加
    public static $usefor = [

        //default
        "default" => [
            "returntransfer" => true
        ],

        //post data
        "post" => [
            "post" => true,
            "postfields" => "%1"
        ],

        //请求头部信息
        "header" => [
            "header" => true,
        ],

        //ssl
        "ssl" => [
            "header" => false,
            "ssl_verifypeer" => false,
            "ssl_verifyhost" => false
        ],

    ];

    //当前参数
    public $url = null;
    public $options = [];

    //请求结果
    public $result = null;


    //构造
    public function __construct(
        $url,
        $opt = []
    ) {
        $this->curl = curl_init();
        $this->url = Url::mk($url);
        $opt = Is::nemarr($opt) ? $opt : [];
        //$opts = Vars::Arr(["url" => $this->url->val()]);
        $opts = Arr::extend([
            "url" => $this->url->full
        ], self::$usefor["default"]);
        if (strtolower($this->url->protocol) == "https") {
            $opts = Arr::extend($opts, self::$usefor["ssl"]);
        }
        if (!empty($opt)) $opts = Arr::extend($opts, $opt);
        $this->options = $opts;
        $this->setOpt($this->options);
    }

    //析构
    public function __destruct()
    {
        if (!is_null($this->curl)) {
            $this->close();
        }
    }

    //setOpt
    public function setOpt($key, $val = null)
    {
        if (Is::nemstr($key)) {
            $cnst = "CURLOPT_" . strtoupper($key);
            if (defined($cnst)) {
                //var_dump($cnst);
                curl_setopt($this->curl, constant($cnst), $val);
            }
        } else if (Is::associate($key)) {
            foreach ($key as $k => $v) {
                $this->setopt($k, $v);
            }
        }
        return $this;
    }

    //增加需求
    public function addUsage(
        /* 
        $usefor_1, 
        [ $usefor_2, $data, $extra, ... ],
        $usefor_3,
        ...
        */
    ) {
        $args = func_get_args();
        $opts = $this->options;
        if (!empty($args)) {
            $usefor = self::$usefor;
            foreach ($args as $k => $v) {
            //$args->each(function($v, $k) use (&$opts, $usefor) {
                if (Is::nemstr($v)) {
                    if (isset($usefor[$v])) {
                        $opts = Arr::extend($opts, $usefor[$v]);
                    }
                } elseif (Is::indexed($v)) {
                    //$v = Vars::Arr($v);
                    $vkey = array_shift($v);
                    if (isset($usefor[$vkey])) {
                        $vopt = $usefor[$vkey];
                        foreach ($vopt as $kk => $vv) {
                        //$vopt->each(function($vv, $kk) use ($v) {
                            if (strpos($vv, "%") !== false) {
                                $vv = str_replace("%", "", $vv);
                                $vv = (int)$vv - 1;
                                $vopt[$kk] = array_slice($v, $vv, 1)[0];
                            } else {

                            }
                            //return $vv;
                        //});
                        }
                        $opts = Arr::extend($opts, $vopt);
                    }
                }
            //});
            }
        }
        $this->options = $opts;
        $this->setOpt($this->options);
        return $this;
    }

    //exec
    public function exec()
    {
        $this->result = curl_exec($this->curl);
        //var_dump($result);
        return $this->fetch();
    }

    //提取结果
    public function fetch($closure = null)
    {  
        if (!is_null($closure) && $closure instanceof \Closure) {
            return $closure($this->result);
        }
        return $this->result;
    }

    //close curl
    public function close()
    {
        curl_close($this->curl);
        $this->curl = null;
    }



    /*
     *  静态调用
     */
    //get
    public static function get($url)
    {
        $curl = new Curl($url);
        if (func_num_args() > 1) {
            $args = func_get_args();
            array_shift($args);
            call_user_func_array([$curl, "addUsage"], $args);
        }
        $result = $curl->exec();
        $curl->close();
        return $result;
    }

    //post
    public static function post($url, $data = null)
    {
        $curl = new Curl($url);
        $args = func_get_args();
        $aa = array_slice($args, 2);
        $aa0 = ["post", $data];
        array_unshift($aa, $aa0);
        $curl->addUsage(...$aa);
        //$curl->addUsage(["post", $data]);
        //if (func_num_args() > 2) {
        //    $args = func_get_args();
        //    array_shift($args); //$url
        //    array_shift($args); //$data
        //    call_user_func_array([$curl, "addUsage"], $args);
        //}
        //var_dump($curl->options);
        //return $curl->options;
        //return $aa;
        $result = $curl->exec();
        $curl->close();
        return $result;
    }

    //wx用于微信平台
    public static function wx($url, $data = null)
    {
        $curl = new Curl($url);
        if (!empty($data)) $curl->addUsage(["post", $data]);
        $result = $curl->exec();
        $curl->close();
        return $result;

        /*
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		if (!empty($data)){
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($curl);
		curl_close($curl);
		return $output;
        */
	}

}