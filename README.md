# data-sdk-php
数据资源库系统 laravel框架接口SDK，服务端

安装手册：
composer require shenercloud/data-sdk-php

第二步:注册服务提供者 至/config/app.php

ShenerCloud\DataSdkServiceProvider::class

第三步:创建配置文件
/config/datasdkclient.php
申请接口appid和秘钥 进行配置

第四步：使用
$client = app('datasdkclient');
$client->方法();

接口方法：
//设置操作的用户接口
setUserOpenidAndAppsecret($openid,$appsecret);

//平台用户注册接口
$res = userRegister($account);
if(isset($res->Code) && $res->Code == 100){
    dump($res);
}

//用户登录并获取Token -- 前置必须设置操作用户接口 setUserOpenidAndAppsecret
$res = userLoginAndGetToken();
if(isset($res->Code) && $res->Code == 100){
    dump($res);
}

//用户获取Token --- 前置必须设置操作用户接口 setUserOpenidAndAppsecret
$res = getUserToken();
if(isset($res->Code) && $res->Code == 100){
    dump($res);
}

//刷新用户Token --- 前置必须设置操作用户接口 setUserOpenidAndAppsecret
$res = userTokenRefresh($openid,$token,$refresh_token);
if(isset($res->Code) && $res->Code == 100){
    dump($res);
}

//获取资源集合接口,media_id可默认为空
$res = getSourceItems($media_id='',$page=1,$offset=20);
if(isset($res->Code) && $res->Code == 100){
    dump($res);
}

//获取资源详细信息接口
$res = getSourceInfo($media_id);
if(isset($res->Code) && $res->Code == 100){
    dump($res);
}

//获取资源实际临时请求地址
$res = getSourceActAddr($media_id);
if(isset($res->Code) && $res->Code == 100){
    dump($res);
}
