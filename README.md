# ElasticSearch Datasource for CakePHP
[![License](https://poser.pugx.org/cakephp/elastic-search/license.svg)](https://packagist.org/packages/cakephp/elastic-search)

This is a pre-alpha version of an alternative ORM for CakePHP 3.0 using [Elastic Search](http://www.elasticsearch.org/)
as its backend. It is currently under development and is only being used to test the
interfaces exposed in CakePHP 3.0.

## Installing ElasticSearch via composer

You can install ElasticSearch into your project using
[composer](http://getcomposer.org). For existing applications you can add the
following to your `composer.json` file:

	"require": {
		"cakephp/elastic-search": "dev-master"
	}

And run `php composer.phar update`

## Running tests

Assuming you have PHPUnit installed system wide using one of the methods stated
[here](http://phpunit.de/manual/current/en/installation.html), you can run the
tests for CakePHP by doing the following:

1. Copy `phpunit.xml.dist` to `phpunit.xml`
2. Run `phpunit`
