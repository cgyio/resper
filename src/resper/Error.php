<?php
/**
 * cgyio/resper 框架 错误处理
 * Error 错误处理类 基类
 * 
 * 任何响应者类 都可以自定义各自的 错误处理类，定义各自的 错误信息 $config[EXPORT_LANG] 参数
 * 并且可以自定义对应 code 的 自定义 beforeThrow 方法
 */

namespace Cgy;

use Cgy\Response;
use Cgy\Log;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Path;
use Cgy\util\Cls;

class Error
{
    //error info
	public $level = 256;
	public $type = "Error";
	public $file = "";
	public $line = 0;
	public $title = "";
	public $msg = "";
	public $code = "";
	public $data = [];

    public $errkey = "";    //errConfigArrKeyPath

    /**
     * error code prefix
     * should be overrided
     */
    protected $codePrefix = "000";

    /**
	 * errors config
	 * should be overrided
	 */
	protected $config = [
        /*
        
        "zh-CN" => [
            "php" => ["title ...", "msg %{1}%, msg %{2}%"]
        ]
        
        */
	];

    /**
     * construct
     */
    public function __construct()
    {

    }

    /**
     * 设置错误信息
     * set error infos
     * @param Int $level 错误等级
     * @return Error $this
     */
    protected function setLevel($level = 256)
    {
        $lvls = [
			1 		=> "Error",
			2 		=> "Warning",
			4 		=> "Parse",
			8 		=> "Notice",
			256 	=> "Error",
			512 	=> "Warning",
			1024 	=> "Notice"
		];
		$this->level = $level;
		if (isset($lvls[$level])) {
			$this->type = $lvls[$level];
		} else {
			$this->type = "Error";
		}
		return $this;
    }

    /**
     * 设置错误信息
     * 设置错误信息中的 文件信息
     * @param String $file 捕获的错误发生 php 文件
     * @param Int $line 错误行号
     */
    protected function setFile($file, $line = 0)
	{
		//var_dump($file);
		$rf = Path::relative($file);
		$this->file = is_null($rf) ? $file : $rf;
		$this->line = $line;
		return $this;
	}

    /**
     * 设置错误信息
     * 替换 预设的错误信息中的 模板字符
     * @param String $errConfigArrKeyPath 预设的错误信息 key path like: system/foo/bar
     * @param Array $msgReplacement 用于替换的数据
     * @return Error $this
     */
    protected function setMsg($errConfigArrKeyPath = "", $msgReplacement = [])
    {
        $title = "Undefined";
        $msg = "Undefined";
        //$lang = EXPORT_LANG;
        //var_dump($this->config[EXPORT_LANG]);
        if (isset($this->config[EXPORT_LANG])) {
            $conf = Arr::find($this->config[EXPORT_LANG], $errConfigArrKeyPath);
            if (Is::nemstr($errConfigArrKeyPath) && !is_null($conf)) {
                $title = $conf[0];
                $msg = self::replaceErrMsg($conf[1], $msgReplacement);
            }
        }
        $this->errkey = $errConfigArrKeyPath;
        $this->title = $title;
        $this->msg = $msg;
        return $this;
    }

    /**
     * 设置错误信息
     * 设置错误信息 code
     * @param String $key 错误信息 code
     * @return Error $this
     */
    protected function setCode($key)
    {
		$this->code = $key;
        return $this;
    }

    /**
     * 设置错误信息
     * 生成错误汇总数据
     * @return Error $this
     */
    protected function setData()
	{
		$self = $this;
		$props = Arr::mk("level,type,file,line,title,msg,code");
		$data = [];
		foreach ($props as $i => $v) {
			if (property_exists($this, $v)) {
				$data[$v] = is_object($self->$v) ? Arr::mk($self->$v) : $self->$v;
			}
		}
		$this->data = $data;
		return $this;
	}

    /**
     * 判断捕获的错误是否必须抛出
     * 抛出错误 将 终止当前会话
     * 根据 level 判断
     * @return Bool
     */
    public function mustThrow()
	{
		return in_array($this->level, [1,2,4,256,512]) || $this->level > 1024;
	}

	/**
	 * 在抛出错误之前，执行自定义操作
	 * 根据 $error->code 执行 ***ErrorBeforeThrow 方法，
	 * 		$error->code == foo/bar  -->  fooBarErrorBeforeThrow
	 * 子类可自定义针对不同 code 的 beforeThrow 方法
	 * @return Error $this
	 */
	public function beforeThrow()
	{
		$code = $this->code;
		if (strpos($code, "/")!==false) {
			$code = Str::camel($code, false);
		}
		$cm = $code."ErrorBeforeThrow";
		if (method_exists($this, $cm)) {
			//执行针对此 $error->code 的 beforeThrow 方法
			$this->$cm();
		}

		return $this;
	}



    /**
	 * create error object
	 * @param Integer $level    error level
	 * @param String $file		php file path
	 * @param Integer $line		error at line in php file
	 * @param Array $cls        error class fullname
     * @param Array $key        errConfigArrKeyPath
	 * @param Array $msg		msg array for replacing error msgs
	 * @return Error instance  or  null
	 */
	public static function create($level, $file, $line, $cls, $key, $msg = [])
    {
        if (!class_exists($cls)) return null;
        $err = new $cls();
		return $err->setLevel($level)->setFile($file, $line)->setMsg($key, $msg)->setCode($key)->setData();
	}



    /**
	 * global error handler
     * 全局错误处理
	 * @param Integer $errno		error level
	 * @param String $errstr		error msg could be customize
	 * @param String $errfile		php file path
	 * @param Integer errline		error at line in php file
	 * @return Error instance
	 */
	public static function handler(
		$errno,		//错误级别
		$errstr,	//错误信息
		$errfile,	//发生错误的文件
		$errline	//发生错误的行号
	) {
		if ($errno > 1024 || $errno < 256) {	//php system error
			//var_dump(func_get_args());
			$cls = self::cls("base/php");
			$msg = [ $errstr ];
		} else {	//customize error
			if (Is::nemstr($errstr)) {
				$arr = explode("::", $errstr);
				$cls = self::cls($arr[0]);
				$msg = count($arr)>1 ? explode(",", $arr[1])/*arr($arr[1])*/ : [];
			} else {
				$cls = self::cls("base/unknown");
				$msg = [];
			}
		}
        if (is_null($cls)) {
            $cls = [ Cls::find("error/base"), "unknown" ];
        }
        //var_dump($msg);
		//create error instance
		$err = self::create($errno, $errfile, $errline, $cls[0], $cls[1], $msg);
        if (!is_null($err) && $err instanceof Error) {
			
			if (Is::nemstr($errstr) && false!==strpos($errstr, "::") && $err->title=="Undefined") {
				//自定义错误标题：标题::信息
				$earr = explode("::", $errstr);
				$err->title = $earr[0];
				$err->msg = $earr[1] ?? "No Error Message";
				$err->code = "custom";
				$err->setData();
			}

			//记录 error 日志
			Log::error(
				$err->title."：".$err->msg,
				[
					"file" => $err->file,
					"line" => $err->line
				]
			);

            if ($err->mustThrow()) {
				//必须抛出错误时，执行自定义错误类中的 beforeThrow 方法
                Response::current()->throwError($err->beforeThrow());
            } else {
                Response::current()->setError($err);
            }
        }
	}

	/**
	 * PHP Fatal Error handler
	 * 致命错误必须抛出
	 */
	public static function fatalHandler() 
	{
		//error_reporting(0);
		$err_last = error_get_last();
		if (!empty($err_last)) {
			//var_dump($err_last);
			$type = $err_last["type"] ?? null;
			//var_dump($type);
			if (!empty($type) && $type == 1) {
				//fatal error
				$cls = [ Cls::find("error/base"), "fatal" ];
				$file = $err_last["file"] ?? "";
				$line = $err_last["line"] ?? "";
				$msg = $err_last["message"] ?? "";
				//var_dump($msg);
				$msga = explode("\n", $msg);
				//var_dump($msga);
				array_pop($msga);
				$pmsg = array_shift($msga);
				$pmsga = explode(" in ", $pmsg);
				array_pop($pmsga);
				$pmsg = implode(" in ", $pmsga);
				$msgh = [];
				$msgh[] = '<h3>'.$pmsg.'</h3>';
				$msgh[] = '<ul><li>';
				$msgh[] = implode('</li><li>', $msga);
				$msgh[] = '</li>';
				$msgh = implode("", $msgh);

				$err = self::create(9999, $file, $line, $cls[0], $cls[1], [$msgh]);
				//var_dump($err);

				//记录 error 日志
				Log::error(
					$pmsg,
					[
						"file" => $file,
						"line" => $line
					]
				);

				if (!is_null($err) && $err instanceof Error) {
					//fatal error 必须抛出错误，执行自定义错误类中的 beforeThrow 方法
					//if ($err->mustThrow()) {
						Response::current()->throwError($err->beforeThrow());
					//} else {
					//	Response::current()->setError($err);
					//}
				}
			}
		}
	}

	/**
	 * 注册 自定义错误处理
	 * set_error_handler() 处理普通错误
	 * register_shutdown_function() 处理致命错误
	 */
	public static function regist()
	{
		set_error_handler([static::class, "handler"]);
		//注册一个 在 exit() 后执行的方法，此方法中获取最后一个错误，如果是 fatal error 则抛出
		register_shutdown_function([static::class, "fatalHandler"]);
	}

	//注册 set_error_handler
	public static function setHandler($callable = null)
	{
		if (is_callable($callable)) {
			set_error_handler($callable);
		} else {
			set_error_handler([static::class, "handler"]);
		}

		//Fatal Error
		//register_shutdown_function([self::class, "fatalHandler"]);
	}



    /**
     * static tools
     */

    /**
     * replace %{n}% in err msg
     * @param String $msg       err msg
     * @param Array $params     replace strs
     * @return String replaced err msg
     */
    public static function replaceErrMsg($msg = "", $params = [])
    {
        if (!Is::nemstr($msg)) return "";
		if (Is::nemarr($params)) {
			foreach ($params as $i => $v){
				$msg = str_replace("%{".($i+1)."}%", $v, $msg);
			}
		}
		return $msg;
    }

    /**
	 * 根据 trigger_error 方法传入的字符串参数，解析查找对应的 错误处理类
	 * @param String $key				like 'foo/bar/jaz'
	 * @return Array [ class fullname, error key (arr path) ]  or  null
	 * foo/bar/jaz 		--> Cgy\error\Foo ->$config[EXPORT_LANG]["bar"]["jaz"]
	 * 					--> Cgy\error\foo\Bar ->$config[EXPORT_LANG]["jaz"]
	 * app/foo/bar/jaz  --> Cgy\App\foo\Error ->$config[EXPORT_LANG]["bar"]["jaz"]
	 * 					--> Cgy\App\foo\error\Bar ->$config[EXPORT_LANG]["jaz"]
	 * module/foo/bar/jaz	--> Cgy\module\foo\Error ->$config[EXPORT_LANG]["bar"]["jaz"]
	 * 						--> Cgy\module\foo\error\Bar ->$config[EXPORT_LANG]["jaz"]
	 */
	public static function cls($key = null)
	{
		if (!Is::nemstr($key)) $key = "base/unknown";
		if (Is::nemstr($key)) {
			$key = str_replace("\\", "/", $key);
			$key = str_replace(".", "/", $key);
			$arr = explode("/", $key);
			if (count($arr)>=3 && in_array(strtolower($arr[0]), ["app","module"])) {
				//app/module custom error cls
				array_splice($arr, 2,0, "error");
				$minidx = 3;
			} else {
				if ("error" != strtolower($arr[0])) {
					array_unshift($arr, "error");
				}
				$minidx = 2;
			}
			$idx = 0;
			for ($i=count($arr); $i>=$minidx; $i--) {
				$subarr = array_slice($arr, 0, $i);
				$subarr[count($subarr)-1] = ucfirst(strtolower(array_slice($subarr, -1)[0]));
				$cls = Cls::find(implode("/",$subarr));
				if (!is_null($cls)) {
					$idx = $i;
					break;
				}
			}
			if ($idx<=0) {
				return [ Cls::find("error/Base"), implode("/", $arr) ];
			} else {
				$arrs = [
					array_slice($arr, 0, $idx),
					array_slice($arr, $idx)
				];
				return [ Cls::find(implode("/", $arrs[0])), implode("/", $arrs[1])];
			}
		}
		return null;
	}
}