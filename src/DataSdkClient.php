<?php
namespace ShenerCloud;

use ShenerCloud\Exception\DataException;
use ShenerCloud\Http\RequestCore;
use ShenerCloud\Http\RequestCore_Exception;
use ShenerCloud\Http\ResponseCore;
class DataSdkClient
{
    private $Appid;
    private $Appsecret;
    private $Path;
    private $time;
    private $useSSL=true;
    private $Hostname = '';
    private $Openid='';
    private $Oappsecret='';
    
    private $timeout=120;
    private $connectTimeout=120;
    private $redirects=0;
    private $maxRetries=5;
    
    public function __construct($appid,$appsecret,$path,$hostname)
    {
        $Appid = trim($appid);
        $Appsecret = trim($appsecret);
        $Path = trim($path);
        $Hostname = trim($hostname);

        if (empty($Appid)) {
            throw new DataException("access key id is empty");
        }
        if (empty($Appsecret)) {
            throw new DataException("access key secret is empty");
        }
        if (empty($Path))
        {
            throw new DataException("Token save path are't set");
        }
        if (empty($Hostname))
            throw new DataException("Host name set error");
        $this->Appid = $Appid;
        $this->Appsecret = $Appsecret;
        $this->Path = $Path;
        $this->Hostname = $Hostname;
        self::checkEnv();
    }

    /**
     * 设置用户
     * @param $openid
     * @param $appsecret
     * @return bool
     * @throws DataException
     */
    public function setUserOpenidAndAppsecret($openid,$appsecret)
    {
        $openid = stripslashes($openid);
        $appsecret = stripslashes($appsecret);
        if (empty($openid) || empty($appsecret))
            throw new DataException("openid and appsecret is empty");
        $this->Openid = $openid;
        $this->Oappsecret = $appsecret;
        return true;
    }

    /**
     * 平台用户注册
     * @param $account
     * @return ResponseCore
     */
    public function userRegister($account)
    {
        $account = trim($account);
        if (empty($account))
            throw new DataException("account is empty");
            $params['request_url'] = $this->Hostname.'/oauth/user/register';
            $this->time = time();
            $params['argement'] = [
                'Account'=>$account,
                'Appid'=>$this->Appid,
                'Sign'=>md5($this->Appid.$this->Appsecret.$this->time),
                'Time'=>date('Y-m-d H:i:s',$this->time)
            ];
            $res = $this->auth($params);

        if (isset($res->Code) && $res->Code == 100){
            $this->setUserOpenidAndAppsecret($res->User->Openid,$res->User->Appsecret);
        }
        return $res;
    }

    /**
     * 本地获取TOken
     * @param $openid
     * @return bool
     * @throws DataException
     */
    private function getAccessToken()
    {
        if (empty($this->Openid))
            return false;
        $file = $this->Path.md5($this->Openid.date('Ymd',time()));
        if (file_exists($file)){
            $data = file_get_contents($file);
            $data = json_decode($data);
            if ($data->expire_in - time() < 30 && $data->expire_in - time() > 0) {
                $res = $this->userTokenRefresh($this->Openid,$data->refresh_token);
                return $this->getAccessToken();
            }else if ($data->expire_in - time() < 0){
                $res = $this->getUserToken();
                if (isset($res->Code) && $res->Code == 100) {
                    return $this->getAccessToken();
                }else {
                    throw new DataException("token expire");
                    return false;
                }
            }
            return $data->token;
        }else{
            $res = $this->userLoginAndGetToken();
            if (isset($res->Code) && $res->Code == 100)
                return $this->getAccessToken();
        }
        throw new DataException("token are't existed");
        return false;
    }

    /**
     * 设置本地Token
     * @param $openid
     * @param $token
     * @param $refresh_token
     * @param $expire
     * @return bool
     */
    private function setAccessToken($openid,$token,$refresh_token,$expire)
    {
        $path = $this->Path;
        $openid = stripslashes($openid);
        $token = stripslashes($token);
        $refresh_token = stripslashes($refresh_token);
        $expire = intval($expire);
        $arr = [
            'token'=>$token,
            'refresh_token'=>$refresh_token,
            'expire_in'=>$expire
        ];
        if (empty($openid) || empty($token) || empty($refresh_token) || empty($expire))
            return false;

        $file = $this->Path.md5($openid.date('Ymd',time()));
        $f = fopen($file, 'w');
        fwrite($f, json_encode($arr));
        fclose($f);
        return true;
    }
    
    /**
     * 用户登录并返回Token
     * @param $openid
     * @return ResponseCore
     * @throws DataException
     */
    public function userLoginAndGetToken()
    {
        if (empty($this->Openid) || empty($this->Oappsecret))
            throw new DataException("openid is empty");

        $this->time = time();
        $params['request_url'] = $this->Hostname.'/oauth/user/login';
        $params['argement'] = [
            'Openid'=>$this->Openid,
            'Sign'=>md5($this->Openid.$this->Oappsecret.$this->time),
            'Time'=>date('Y-m-d H:i:s',$this->time)
        ];
        $res = $this->auth($params);
        if (isset($res->Code) && $res->Code == 100){
            if(!$this->setAccessToken($this->Openid,$res->Token->Token,$res->Token->Refresh_token,$res->Token->Expire_in))
                throw new DataException("write token file failed;");
        }
        return $res;
    }
    
    /**
     * 用户获取Token
     * @param $openid
     * @return ResponseCore
     * @throws DataException
     */
    public function getUserToken()
    {
        if (empty($this->Openid))
            throw new DataException("openid is empty");

        $params['request_url'] = $this->Hostname.'/oauth/user/token';
        $this->time = time();
        $params['argement'] = [
            'Openid'=>$this->Openid,
            'Sign'=>md5($this->Openid.$this->Oappsecret.$this->time),
            'Time'=>date('Y-m-d H:i:s',$this->time)
        ];
        $res = $this->auth($params);
        if (isset($res->Code) && $res->Code == 100){
            $this->setAccessToken($this->Openid,$res->Token->Token,$res->Token->Refresh_token,$res->Token->Expire_in);
        }
        return $res;
    }
    
    /**
     * 刷新用户Token
     * @param $openid
     * @param $token
     * @param $refresh_token
     * @return ResponseCore
     * @throws DataException
     */
    private function userTokenRefresh($openid,$token,$refresh_token)
    {
        $openid = trim($openid);
        $token = trim($token);
        $refresh_token = trim($refresh_token);
        if (empty($openid) || empty($token) || empty($refresh_token))
            throw new DataException("openid or token or refresh_token is empty");
            
        $params['request_url'] = $this->Hostname.'/oauth/user/refresh';
        $params['argement'] = [
            'Refresh_token'=>$refresh_token,
            'Sign'=>md5($openid.$token.$this->time),
            'Time'=>date('Y-m-d H:i:s',$this->time)
        ];
        $res = $this->auth($params);
        if (isset($res->Code) && $res->Code == 100){
            $this->setAccessToken($this->Openid,$res->Token,$res->Refresh_token,$res->Expire_in);
        }
        return $res;
    }
    
    /**
     * 获取资源数据集合
     * @param $media_id
     * @param $page
     * @param $offset
     * @return ResponseCore
     * @throws DataException
     */
    public function getSourceItems($media_id='',$page=1,$offset=20)
    {
        $media_id = trim($media_id);
        $page = intval($page);
        $offset = intval($offset);
        $params['request_url'] = $this->Hostname.'/oauth/source/get';
        $params['argement'] = [
            'Media'=>$media_id,
            'Token'=>$this->getAccessToken(),
            'Page'=>$page,
            'Offset'=>$offset
        ];
        return $this->auth($params);
    }
    
    /**
     * 获取资源详细信息
     * @param $media_id
     * @return ResponseCore
     * @throws DataException
     */
    public function getSourceInfo($media_id)
    {
        $media_id = trim($media_id);
        if (empty($media_id))
            throw new DataException("media id is empty");
            
            $params['request_url'] = $this->Hostname.'/oauth/source/info';
            $params['argement'] = [
                'Media'=>$media_id,
                'Token'=>$this->getAccessToken()
            ];
            return $this->auth($params);
    }
    
    /**
     * 获取资源实际地址
     * @param $media_id
     * @return ResponseCore
     * @throws DataException
     */
    public function getSourceActAddr($media_id)
    {
        $media_id = trim($media_id);
        if (empty($media_id))
            throw new DataException("media id is empty");
            
            $params['request_url'] = $this->Hostname.'/oauth/source/addr';
            $params['argement'] = [
                'Media'=>$media_id,
                'Token'=>$this->getAccessToken()
            ];
            return $this->auth($params);
    }
    
    /**
     * 验证并且执行请求，
     *
     * @param array $params
     * @throws DataException
     * @throws RequestCore_Exception
     */
    private function auth($params)
    {
        $request_url = isset($params['request_url'])?trim($params['request_url']):'';
        $argement = isset($params['argement'])?$params['argement']:array();

        if (empty($request_url)){
            throw new DataException("request url is empty");
        }
        
        //创建请求
        $request = new RequestCore($request_url);
        if ($this->timeout !== 0) {
            $request->timeout = $this->timeout;
        }
        if ($this->connectTimeout !== 0) {
            $request->connect_timeout = $this->connectTimeout;
        }
        $request->ssl_verification = false;
        $request->debug_mode = true;
        $request->set_method('post');
        $request->set_body($argement);

        try {
            $request->send_request();
        } catch (RequestCore_Exception $e) {
            throw(new DataException('RequestCoreException: ' . $e->getMessage()));
        }
        $response_header = $request->get_response_header();
        $data = new ResponseCore($response_header, $request->get_response_body(), $request->get_response_code());

        //如果返回错误那就休眠重新请求几次
        if ((integer)$request->get_response_code() === 500) {
            echo $data->body;
            return false;
        }

        $this->redirects = 0;
        return json_decode($data->body);
    }
    
    /**
     * 用来检查sdk所以来的扩展是否打开
     *
     * @throws OssException
     */
    public static function checkEnv()
    {
        if (function_exists('get_loaded_extensions')) {
            //检测curl扩展
            $enabled_extension = array("curl");
            $extensions = get_loaded_extensions();
            if ($extensions) {
                foreach ($enabled_extension as $item) {
                    if (!in_array($item, $extensions)) {
                        throw new DataException("Extension {" . $item . "} is not installed or not enabled, please check your php env.");
                    }
                }
            } else {
                throw new DataException("function get_loaded_extensions not found.");
            }
        } else {
            throw new DataException('Function get_loaded_extensions has been disabled, please check php config.');
        }
    }
    
    /**
     * 设置最大尝试次数
     *
     * @param int $maxRetries
     * @return void
     */
    public function setMaxTries($maxRetries = 3)
    {
        $this->maxRetries = $maxRetries;
    }
    
    /**
     * 获取最大尝试次数
     *
     * @return int
     */
    public function getMaxRetries()
    {
        return $this->maxRetries;
    }
    
    /**
     * 设置http库的请求超时时间，单位秒
     *
     * @param int $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }
    
    /**
     * 设置http库的连接超时时间，单位秒
     *
     * @param int $connectTimeout
     */
    public function setConnectTimeout($connectTimeout)
    {
        $this->connectTimeout = $connectTimeout;
    }
}
