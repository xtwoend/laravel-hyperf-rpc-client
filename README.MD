# Hyperf RPC Client for Laravel


## Install

```
    composer require growinc/hyrpc-laravel
```


## Usage

```
    use Growinc\HyRpcLaravel\RpcClient;

    $result = RpcClient::service('UserService')->params(1)->call('find');

```

