# Scout AWS Elasticsearch Driver 

This package is a wrapper around [babenkoivan/scout-elasticsearch-driver](https://packagist.org/packages/babenkoivan/scout-elasticsearch-driver), adding AWS support.

## Requirements
The package has been tested with the following configuration:

* PHP version 7.4.*
* Laravel Framework version 7.*
* Elasticsearch version 7.9

## Upgrading
If you are upgrading from before version 1.2.4, version 2.0.0 requires the `scout_elastic_aws` config file to be re-published or add `aws_enabled` key.

```php
'aws_enabled' => env('SCOUT_AWS_ELASTIC_ENABLED', true);
```

## Installation
Use composer to install package
```sh
composer require innamhunzai/scout-aws-elastic
```

## Configuration
To configure the package you need to publish the following settings:
```sh
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
php artisan vendor:publish --provider="ScoutElastic\ScoutElasticServiceProvider"
php artisan vendor:publish --provider="InnamHunzai\ScoutAwsElastic\ScoutAwsElasticServiceProvider"
```
After publishing the configuration files, you can configure the connection to your Elasticsearch cluster with the following `.env` variables (Replace values with your own values):
```ini
SCOUT_DRIVER=elastic

SCOUT_ELASTIC_HOST=localhost
SCOUT_ELASTIC_SCHEME=https
SCOUT_ELASTIC_PORT=443
SCOUT_ELASTIC_USER=
SCOUT_ELASTIC_PASS=
SCOUT_AWS_ELASTIC_ENABLED=true
SCOUT_AWS_ELASTIC_REGION=us-east-2
SCOUT_AWS_ELASTIC_ACCESS_KEY=<your_aws_access_id>
SCOUT_AWS_ELASTIC_ACCESS_SECRET=<your_secret_aws_key>
```
## Usage
Because this package is a wrapper for babenkoivan/scout-elasticsearch-driver, all usage documentation can be found [here](https://github.com/babenkoivan/scout-elasticsearch-driver/blob/master/README.md) 