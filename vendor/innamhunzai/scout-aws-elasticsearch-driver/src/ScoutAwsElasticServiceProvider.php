<?php

namespace InnamHunzai\ScoutAwsElastic;

use Exception;
use Monolog\Logger;
use GuzzleHttp\Psr7\Uri;
use Elasticsearch\Client;
use Illuminate\Support\Arr;
use GuzzleHttp\Psr7\Request;
use Psr\Log\LoggerInterface;
use Aws\Signature\SignatureV4;
use Elasticsearch\ClientBuilder;
use Aws\Credentials\Credentials;
use Monolog\Handler\StreamHandler;
use function Aws\default_http_handler;
use Illuminate\Support\Facades\Config;
use Psr\Http\Message\ResponseInterface;
use ScoutElastic\ScoutElasticServiceProvider;
use GuzzleHttp\Ring\Future\CompletedFutureArray;

class ScoutAwsElasticServiceProvider extends ScoutElasticServiceProvider
{
    /**
     * Map configuration array keys with ES ClientBuilder setters
     *
     * @var array
     */
    protected $configMappings = [
        'sslVerification'    => 'setSSLVerification',
        'sniffOnStart'       => 'setSniffOnStart',
        'retries'            => 'setRetries',
        'httpHandler'        => 'setHandler',
        'connectionPool'     => 'setConnectionPool',
        'connectionSelector' => 'setSelector',
        'serializer'         => 'setSerializer',
        'connectionFactory'  => 'setConnectionFactory',
        'endpoint'           => 'setEndpoint',
        'namespaces'         => 'registerNamespace',
    ];
    
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        if (config('scout_aws_elasticsearch_driver.aws_enabled') === true) {
            $this->awsConnection();
        } else {
            $this->standardConnection();
        }
    }
    
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
        
        $this->publishes([
            __DIR__ . '/../Config/scout_aws_elasticsearch_driver.php' => config_path('scout_aws_elasticsearch_driver.php'),
        ]);
    }
    
    /**
     * @return void
     */
    protected function awsConnection(): void
    {
        $this
            ->app
            ->singleton('scout_elastic.client', function () {
                $config = Config::get('scout_aws_elasticsearch_driver.connections.default');
                
                return $this->buildClient($config);
            });
    }
    
    /**
     * @return void
     */
    protected function standardConnection(): void
    {
        $this
            ->app
            ->singleton('scout_elastic.client', function () {
                $config['hosts'] = collect(Config::get('scout_aws_elasticsearch_driver.connections.default.hosts'))
                    ->map(function ($host) {
                        return ["{$host['host']}:{$host['port']}"];
                    })
                    ->flatten()
                    ->toArray();
                
                return ClientBuilder::fromConfig($config);
            });
    }
    
    
    /**
     * Build and configure an Elasticsearch client.
     *
     * Modified from cviebrock/laravel-elasticsearch
     *
     * @see https://github.com/cviebrock/laravel-elasticsearch/blob/master/src/Factory.php
     *
     * @param array $config
     * @return Client
     * @throws Exception
     */
    protected function buildClient(array $config): Client
    {
        $clientBuilder = ClientBuilder::create();
        
        // Configure hosts
        $clientBuilder->setHosts($config['hosts']);
        
        // Configure logging
        if (Arr::get($config, 'logging')) {
            $logObject = Arr::get($config, 'logObject');
            $logPath = Arr::get($config, 'logPath');
            $logLevel = Arr::get($config, 'logLevel');
            if ($logObject && $logObject instanceof LoggerInterface) {
                $clientBuilder->setLogger($logObject);
            } elseif ($logPath && $logLevel) {
                $handler = new StreamHandler($logPath, $logLevel);
                $logObject = new Logger('log');
                $logObject->pushHandler($handler);
                $clientBuilder->setLogger($logObject);
            }
        }
        // Set additional client configuration
        foreach ($this->configMappings as $key => $method) {
            $value = Arr::get($config, $key);
            if (is_array($value)) {
                foreach ($value as $vItem) {
                    $clientBuilder->$method($vItem);
                }
            } elseif ($value !== null) {
                $clientBuilder->$method($value);
            }
        }
        // Configure handlers for any AWS hosts
        foreach ($config['hosts'] as $host) {
            if (isset($host['aws']) && $host['aws']) {
                $clientBuilder->setHandler(function (array $request) use ($host) {
                    $psr7Handler = default_http_handler();
                    $signer = new SignatureV4('es', $host['aws_region']);
                    $request['headers']['Host'][0] = parse_url($request['headers']['Host'][0])['host'];
                    // Create a PSR-7 request from the array passed to the handler
                    $psr7Request = new Request(
                        $request['http_method'],
                        (new Uri($request['uri']))
                            ->withScheme($request['scheme'])
                            ->withHost($request['headers']['Host'][0]),
                        $request['headers'],
                        $request['body']
                    );
                    // Sign the PSR-7 request with credentials from the environment
                    $signedRequest = $signer->signRequest(
                        $psr7Request,
                        new Credentials($host['aws_key'], $host['aws_secret'])
                    );
                    
                    // Send the signed request to Amazon ES
                    /** @var ResponseInterface $response */
                    $response = $psr7Handler($signedRequest)
                        ->then(function (ResponseInterface $response) {
                            return $response;
                        }, function ($error) {
                            return $error['response'];
                        })
                        ->wait();
                    
                    // Convert the PSR-7 response to a RingPHP response
                    return new CompletedFutureArray([
                        'status'         => $response->getStatusCode(),
                        'headers'        => $response->getHeaders(),
                        'body'           => $response->getBody()
                            ->detach(),
                        'transfer_stats' => ['total_time' => 0],
                        'effective_url'  => (string)$psr7Request->getUri(),
                    ]);
                });
            }
        }
        
        // Build and return the client
        return $clientBuilder->build();
    }
}
