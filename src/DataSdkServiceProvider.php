<?php
namespace ShenerCloud;

use Illuminate\Support\ServiceProvider;
use ShenerCloud\DataSdkClient;

class DataSdkServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../Config/config.php' => config_path('datasdkclient.php')
        ]);
    }
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $appid = config('datasdk.appid');
        $appsecret = config('datasdk.appsecret');
        $path = config('datasdk.path');
        $hostname = config('datasdk.hostname');
        $datasdkclient =  new DataSdkClient($appid, $appsecret,$path,$hostname);
        
        $this->app->singleton('datasdkclient', function($app) use ($datasdkclient) {
            return $datasdkclient;
        });
    }
}
