<?php

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="../assets/cgy-dark-ico.svg"/>
<title>Error</title>
<style>
* {
    --color-bg: #faf9f7;
    --color-bd: #f0efed;
    --color-bd-err: #f0afad;
    --color-fc: #636260;
    --color-fc-dark: #333230;
    --color-fc-light: #a3a2a0;

    margin:0; padding:0; font-family:monospace; font-size:14px;
}
body {
    width: 100vw; height: 100vh; overflow: hidden;
    display: flex; align-items: flex-start; justify-content: center;
    background-color: var(--color-bg);
    color: var(--color-fc-light);
}
.err_panel {
    width: 40vw; min-width: 480px; height: auto; padding: 32px; margin-top: 15vh;
    display: flex; flex-direction: column;
    box-sizing: border-box;
    border: var(--color-bd-err) solid 1px; border-radius: 16px;
    background-color: #fff;
    box-shadow: 4px 6px 16px rgba(0,0,0,.1);
}
.err_panel > div {
    width: 100%;
    box-sizing: border-box;
    display: flex; align-items: center; justify-content: flex-start;
}
.err_panel > .err_title {
    margin-bottom: 16px; min-height: 48px;
}
.err_panel > .err_title > img {
    height: 48px; width: auto; margin-right: 32px;
}
.err_panel > .err_title > .err_title_text {
    font-size: 22px; font-weight: bold; color: var(--color-fc-dark);
}
.err_panel > .err_info {
    padding-left: 80px; margin-bottom: 16px;
    width: 100%; 
    box-sizing: border-box;
    line-height: 150%;
    font-size: 18px;
    color: var(--color-fc-dark);
}
.err_panel > .err_row {
    min-height: 24px; cursor: default;
}
.err_panel > .err_row:hover {
    background-color: var(--color-bg);
    color: var(--color-fc-dark);
}
.err_panel > .err_row > label {
    width: 64px; margin-left: 80px; margin-right: 8px;
    font-weight: bold; color: var(--color-fc);
}
</style>
</head>
<body>

<div class="err_panel">
    <div class="err_title">
        <img src="https://io.cgy.design/icon/vant-close-circle-fill?fill=ff3300" alt="">
        <span class="err_title_text">Error: <?=$error["title"]?></span>
    </div>
    <div class="err_info">
        <?=$error["msg"]?>
    </div>
    <div class="err_row">
        <label>文件</label>
        <span><?=$error["file"]?></span>
    </div>
    <div class="err_row">
        <label>行号</label>
        <span><?=$error["line"]?></span>
    </div>
</div>

</body>
</html>