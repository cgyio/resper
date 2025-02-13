<?php
/**
 * 输出通用页面 css
 */

$basePath = __DIR__;    //当前路径作为 scss 解析路径
$glue = "\r\n";
$scss = [];

/**
 * 调用 normalize 库
 */
$scss[] = "@import \"https://io.cgy.design/normalize/@\";";

/**
 * 调用 io.cgy.design/theme/h5/base 主题样式
 */
$scss[] = \Cgy\request\Curl::get("https://io.cgy.design/theme/h5/base/light.scss", "ssl");

/**
 * 调用 与此 css 文件同名的 scss 文件，必须在 $basePath 路径下
 */
$pi = pathinfo(__FILE__);
$fn = $pi["basename"];
$fn = str_replace(".css","", $fn);
$fn = str_replace(".php","", $fn);
$fp = $basePath.DS.$fn.".scss";
if (file_exists($fp)) {
    $scss[] = "@import \"".$fn.".scss\";";
}

/**
 * 调用通过 $_GET["use"] 指定的 其他 scss 文件，必须在 $basePath 路径下
 */
$uses = $_GET["use"] ?? "";
if (!empty($uses)) $uses = \Cgy\util\Arr::mk($uses);
if (!empty($uses)) {
    for ($i=0;$i<count($uses);$i++) {
        $usei = $uses[$i];
        $fi = $basePath.DS.$usei.".scss";
        if (!file_exists($fi)) continue;
        $scss[] = "@import \"".$usei.".scss\";";
    }
}

//合并生成 scss 内容
$scss = implode($glue, $scss);

/**
 * 测试输出 scss
 */
/*\Cgy\module\Mime::setHeaders("scss");
\Cgy\Response::sentHeaders();
echo $scss;
exit;*/

/**
 * 调用 SCSS 解析器，解析 scss，生成 css 内容
 */
$compressed = $_GET["min"] ?? "yes";
$compressed = $compressed !== "no";
$compiler = new \ScssPhp\ScssPhp\Compiler();
$outputStyle = $compressed ? \ScssPhp\ScssPhp\OutputStyle::COMPRESSED : \ScssPhp\ScssPhp\OutputStyle::EXPANDED;
$compiler->setOutputStyle($outputStyle);
$compiler->setImportPaths($basePath);
$cnt = "";
try {
    $cnt = $compiler->compileString($scss)->getCss();
} catch (\Exception $e) {
    var_dump($e);
    trigger_error("custom::Complie SCSS to CSS Error", E_USER_ERROR);
}

/**
 * 输出 css
 */
\Cgy\module\Mime::setHeaders("css");
\Cgy\Response::sentHeaders();
echo $cnt;

exit;
