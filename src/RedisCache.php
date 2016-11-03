<?php
/*
	RedisCache.php - Redis cache middleware for Slim framework
	Copyright 2015 abouvier <abouvier@student.42.fr>

	Licensed under the Apache License, Version 2.0 (the "License");
	you may not use this file except in compliance with the License.
	You may obtain a copy of the License at

		http://www.apache.org/licenses/LICENSE-2.0

	Unless required by applicable law or agreed to in writing, software
	distributed under the License is distributed on an "AS IS" BASIS,
	WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
	See the License for the specific language governing permissions and
	limitations under the License.
*/

namespace Slim\Middleware;

use \Predis\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RedisCache 
{
	protected $client;
	protected $timeout;

	public function __construct(ClientInterface $client, $timeout = 0)
	{
		$this->client = $client;
		$this->timeout = $timeout;
	}

	public function __invoke(RequestInterface $request, ResponseInterface $response, callable $next)
    {

		$route = $request->getAttribute('route');
	    $name = $route->getName();
	    $groups = $route->getGroups();
	    $methods = $route->getMethods();
	    $arguments = $route->getArguments();
	    $identifier =  $route->getIdentifier();

	    $uri = $request->getUri();
		$path = $uri->getPath();

		$key = $name . '_' . implode("_", $methods) . '_' . implode("_",$groups) . '_' . implode("_",$arguments) . '_' . $identifier . '_' . $uri . '_' . $path;

		if ($this->client->exists($key)) {
			$cache_value = $this->client->get($key);
			$value = explode("|",$cache_value);
			$headers = explode("-#-",$value[0]);
			foreach ($headers as $item) {
				$header = explode("-!@!-",$item);
				$response = $response->withHeader($header[0],$header[1]);
			}

			$response->getBody()->write($value[1]);

	        return $response;
		}

        $response = $next($request, $response);
		
		if ($response->isSuccessful()) {
			$headers = array();
			foreach ($response->getHeaders() as $name => $values) {
				foreach ($values as $value) {
					array_push($headers, $name .'-!@!-'.$value);
	            }
            }
			$value = array('header' => implode("-#-",$headers), 'body' => $response->getBody());
			$this->client->set($key, implode("|",$value));
			$this->client->expire($key, $this->timeout);
		}

        return $response;
    }

}