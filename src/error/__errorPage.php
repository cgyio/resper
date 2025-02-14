<?php
//var_dump(WEB_DEBUG);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<title>Resper Error</title>
<link rel="stylesheet" href="/src/resper/main.css?use=error-page"/>
<link rel="icon" href="//io.cgy.design/icon/md-sharp-report-gmailerrorred?fill=fa5151"/>
</head>
<body>

<div class="err_panel">
    <div class="err_title mg-b-m">
        <img src="https://io.cgy.design/icon/md-sharp-report-gmailerrorred?fill=fa5151" alt="">
        <span class="f-xxl f-bold f-d3">Error: <?=$error["title"]?></span>
    </div>
    <div class="err_info f-xl f-d3 mg-b-m">
        <?=$error["msg"]?>
    </div>
    <?php
    if (WEB_DEBUG == true) {
    ?>
    <div class="err_row pd-r-xl">
        <label>文件</label>
        <span><?=$error["file"]?></span>
    </div>
    <div class="err_row pd-r-xl">
        <label>行号</label>
        <span><?=$error["line"]?></span>
    </div>
    <?php
    }
    ?>
    <div class="err_foot">
        <img class="err_logo" src="//io.cgy.design/icon/cgy-light" alt="CgyDesign Logo">
        <span>&copy; 2023~<?php echo date("Y", time()); ?></span>
        <a href="https://github.com/cgyio/resper">
            <span class="info">powered by </span>
            <span class="strong code">Resper.</span>
        </a>
        <span class="err_footgap"></span>
        <button 
            class="cgy-btn btn-vant-arrowleft btn-primary btn-pop mg-r-xs" 
            title="返回上一页" 
            onclick="goBack()"
        >
            <span class="btn-label">返回</span>
        </button>
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