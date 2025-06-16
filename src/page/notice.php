<?php

//var_dump($type);
//var_dump($color);
//var_dump($icon);
//var_dump($title);
//var_dump($msg);

$panel_width = "";
if (strpos(strtolower($title), "fatal error") !== false) {
    $panel_width = "max-width: 960px;";
}

//$msg = str_replace(VENDOR_PATH, "", $msg);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<title>Resper - <?=ucfirst($type)?></title>
<link rel="stylesheet" href="/src/resper/main.css?use=notice-page"/>
<link rel="icon" href="<?=$icon?>"/>
</head>
<body>

<div class="notice_panel <?=$type?>-notice" style="<?=$panel_width?>">
    <div class="notice_title mg-b-m">
        <img src="<?=$icon?>" alt="">
        <?php
        $tstr = [
            "error" => "Error",
            "warn" => "请注意"
        ];
        if (isset($tstr[$type])) {
        ?>
        <span class="f-xxl f-bold mg-r-l" style="color: <?=$color?>;"><?=$tstr[$type]?> :</span>
        <?php
        }
        ?>
        <span class="f-xxl f-bold f-d3"><?=$title?></span>
    </div>
    <div class="notice_info f-l f-d3 mg-b-m">
        <?=$msg?>
    </div>
    <?php
    if ($type == "error" && WEB_DEBUG == true) {
    ?>
    <div class="notice_row pd-r-xl">
        <label>文件</label>
        <span><?=$file?></span>
    </div>
    <div class="notice_row pd-r-xl">
        <label>行号</label>
        <span><?=$line?></span>
    </div>
    <?php
    }
    ?>
    <div class="notice_foot">
        <img class="notice_logo" src="//io.cgy.design/icon/cgy-light" alt="CgyDesign Logo">
        <span>&copy; 2023~<?php echo date("Y", time()); ?></span>
        <a href="https://github.com/cgyio/resper">
            <span class="info">powered by </span>
            <span class="strong code">Resper.</span>
        </a>
        <span class="notice_footgap"></span>
        <button 
            class="cgy-btn btn-vant-arrowleft btn-primary btn-pop mg-r-xs" 
            title="返回上一页" 
            onclick="goBack()"
        ></button>
        <button 
            class="cgy-btn btn-vant-sync btn-primary btn-pop mg-r-xs" 
            title="刷新页面" 
            onclick="window.location.reload()"
        ></button>
        <button 
            class="cgy-btn btn-vant-home btn-primary btn-pop"
            title="返回首页" 
            onclick="toIdx()"
        ></button>
    </div>
</div>
<script>
function toIdx() {
    window.location.href = window.location.href.split('/').slice(0,3).join('/');
}
function goBack() {
    window.history.go(-1);
}
</script>

</body>
</html>