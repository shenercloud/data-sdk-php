<?php
namespace ShenerCloud\tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Exception;
use ShenerCloud\DataSdkClient;

class DataSdkTest extends TestCase
{
    private $appid = '6582017n0911073620g';
    private $appsecret = '6c30731ae73f0370890d4549953c25bf852244ad';
    private $path = 'tmp_path';
    private $hostname = 'https://data.shenercloud.com';

    public function testUserLoginAndGetToken()
    {
        $client = new DataSdkClient($this->appid, $this->appsecret, $this->path, $this->hostname);
        $res = $client->userLoginAndGetToken("openid");
        $this->assertEmpty($res);
        throw new Exception($res);
    }
}