<?php

namespace Growinc\HyRpcLaravel;

use Ramsey\Uuid\Uuid;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use SensioLabs\Consul\ServiceFactory;
use SensioLabs\Consul\Services\Health;
use SensioLabs\Consul\Services\HealthInterface;

/**
 * 
 * $service = RpcClient::service('OTPService')
 *  ->params(1, 2, 3, 4)
 *  ->call('find');
 */
class RpcClient
{
    protected $health;
    protected $params;
    protected $service;
    protected $method;
    protected $client;

    public function __construct(string $serviceName = null) {
        $this->service = $serviceName;
    }

    public static function service(string $serviceName)
    {
        return new self($serviceName);
    }

    public function params(...$args)
    {
        $this->params = $args;
        return $this;
    }

    public function call(string $method)
    {
        $data = [
			'jsonrpc' => '2.0',
			'method' => $this->generate($this->service, $method),
			'params' => $this->params,
			'id' => Uuid::uuid4()->getHex(),
		];
        
        if(is_null($this->client)){
			$this->createClient();
		}

        $message = json_encode($data, JSON_UNESCAPED_UNICODE)."\r\n";
        $data = "";
        
        $i = 0;
		while(true){
			try{
				$res = $this->client->request('POST', '/', ['body' => $message]);
				$data = (string) $res->getBody();
			}catch (\Exception $e){
				$this->createClient();
			}
			if(!empty($data) || $i > 3){
				break;
			}
			$i++;
		}

		if(!empty($data)){
			$dataResult = (array) json_decode($data,true);
            if(array_key_exists('result', $dataResult)){
			    return $this->success('ok', $dataResult['result']);
            }elseif(isset($dataResult['error'])){
                return $this->error($dataResult['error']['code'], $dataResult['error']['message'], $dataResult['error']['data']);
            }
		}

        return $this->error(99, 'Data exception');
    }

    protected function generate(string $service, string $method): string
    {
        $handledNamespace = explode('\\', $service);
        $handledNamespace = Str::replaceArray('\\', ['/'], end($handledNamespace));
        $handledNamespace = Str::replaceLast('Service', '', $handledNamespace);
        $path = Str::snake($handledNamespace);

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        return $path . '/' . $method;
    }

    /**
	 * @param $message
	 * @param $data
	 * @return array
	 */
	protected function success($message, $data = [])
	{
		return ['error' => 0, 'message' => $message, 'data' => $data];
	}

	/**
	 * @param $message
	 * @param $data
	 * @return array
	 */
	protected function error($code, $message, $data = [])
	{
		return ['error' => $code, 'message' => $message, 'data' => $data];
	}

    /**
     * guzzle client
     */
    protected function createClient()
    {
        $node = $this->getNodes($this->service);
        return $this->client = new Client(['base_uri' => $node]);
    }

    
    /**
     * Undocumented function
     *
     * @return void
     */
    protected function getNodes($name)
    {
        $health = $this->createConsulHealth();
        $services = $health->service($name)->json();
        
        $nodes = [];
        $metadata['protocol'] = 'jsonrpc-http';
        foreach ($services as $node) {
            $passing = true;
            $service = $node['Service'] ?? [];
            $checks = $node['Checks'] ?? [];

            if (isset($service['Meta']['Protocol']) && $metadata['protocol'] !== $service['Meta']['Protocol']) {
                // The node is invalid, if the protocol is not equal with the client's protocol.
                continue;
            }

            foreach ($checks as $check) {
                $status = $check['Status'] ?? false;
                if ($status !== 'passing') {
                    $passing = false;
                }
            }

            if ($passing) {
                $address = $service['Address'] ?? '';
                $port = (int) ($service['Port'] ?? 0);
                // @TODO Get and set the weight property.
                $address && $port && $nodes[] = ['host' => $address, 'port' => $port];
            }
        }
        
        if (empty($nodes)) {
            throw new \Exception('No node alive.');
        }
        $key = array_rand($nodes);
        $node = $nodes[$key];

        $uri = $node['host'] . ':' . $node['port'];
        $schema = value(function () use ($node) {
            $schema = 'http';
            if (array_key_exists('schema', $node)) {
                $schema = $node['schema'];
            }
            if (! in_array($schema, ['http', 'https'])) {
                $schema = 'http';
            }
            $schema .= '://';
            return $schema;
        });
        $url = $schema . $uri;
        
        return $url;
    }

    protected function createConsulHealth(): HealthInterface
    {
        if ($this->health instanceof HealthInterface) {
            return $this->health;
        }

        if (! class_exists(Health::class)) {
            throw new \Exception('Component of \'friendsofphp/consul-php-sdk\' is required if you want the client fetch the nodes info from consul.');
        }

        $token = config('rpc-client.consul.token', '');
        $options = [
            'base_uri' => config('rpc-client.consul.host', 'http://localhost:8500'),
        ];

        if (! empty($token)) {
            $options['headers'] = [
                'X-Consul-Token' => $token,
            ];
        }

        return $this->health = (new ServiceFactory($options))->get(HealthInterface::class);
    }
}