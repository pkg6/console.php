<?php

use Pkg6\Console\PB;

require 'vendor/autoload.php';

$count = 100;
//创建进度条
$progress = PB::create($count);
//设置进度条宽度 单位 一格
//$progress->setBarWidth(80);
//简洁模式
//$progress->setFormatType(PB::FORMAT_TYPE_NORMAL);
//自定义内容
//$progress->setCustomFormat('%title% %current%/%max% [%bar%] %percent:3s%%  时间:%elapsed%/%estimated% 速度:%speed% 内存:%memory:6s%');
//$progress->setMessage('title','console');
for ($i = 0; $i < $count; $i++) {
    usleep(1000);
    //下一步
//    $progress->next(2);
    $progress->next();
}
//结束
$progress->finish();