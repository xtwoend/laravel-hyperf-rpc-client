<?php

namespace Growinc\HyRpcLaravel;

use Illuminate\Support\ServiceProvider;

class RpcServiceProvider extends ServiceProvider
{
    public function register()
    {
        # code...
    }

    public function boot()
	{
		$this->publishes([
			__DIR__.'/../config/rpc-client.php' => config_path('rpc-client.php'),
		], 'config');
	}
}