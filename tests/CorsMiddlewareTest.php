<?php

/*

Copyright (c) 2016-2022 Mika Tuupola

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

*/

/**
 * @see       https://github.com/zestic/cors-middleware
 * @see       https://github.com/neomerx/cors-psr7
 * @see       https://www.w3.org/TR/cors/
 * @license   https://www.opensource.org/licenses/mit-license.php
 */

namespace Zestic\Middleware;

use Equip\Dispatch\MiddlewareCollection;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use Tuupola\Http\Factory\ResponseFactory;
use Tuupola\Http\Factory\ServerRequestFactory;

class CorsMiddlewareTest extends TestCase
{
    public function testShouldBeTrue(): void
    {
        $this->assertTrue(true);
    }

    public function testShouldReturn200ByDefault(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("GET", "https://example.com/api");

        $response = (new ResponseFactory())->createResponse();
        $cors = new CorsMiddleware();

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Foo");
            return $response;
        };

        $response = $cors($request, $response, $next);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testShouldAcceptWildcardSettings(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("POST", "https://example.com/api")
            ->withHeader("Origin", "https://subdomain.example.com");

        $response = (new ResponseFactory())->createResponse();
        $cors = new CorsMiddleware([
            "origin" => [
                "*.example.com",
            ],
            "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
            "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since"],
            "headers.expose" => ["Authorization", "Etag"],
            "credentials" => true,
            "cache" => 86400,
        ]);

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Foo");
            return $response;
        };

        $response = $cors($request, $response, $next);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame("https://subdomain.example.com", $response->getHeaderLine("Access-Control-Allow-Origin"));
        $this->assertSame("true", $response->getHeaderLine("Access-Control-Allow-Credentials"));
        $this->assertSame("Origin", $response->getHeaderLine("Vary"));
        $this->assertSame("Authorization,Etag", $response->getHeaderLine("Access-Control-Expose-Headers"));
    }

    public function testShouldHaveCorsHeaders(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("GET", "https://example.com/api")
            ->withHeader("Origin", "http://www.example.com");

        $response = (new ResponseFactory())->createResponse();
        $cors = new CorsMiddleware([
            "origin" => "*",
            "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
            "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since"],
            "headers.expose" => ["Authorization", "Etag"],
            "credentials" => true,
            "cache" => 86400,
        ]);

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Foo");
            return $response;
        };

        $response = $cors($request, $response, $next);
        $this->assertSame("http://www.example.com", $response->getHeaderLine("Access-Control-Allow-Origin"));
        $this->assertSame("true", $response->getHeaderLine("Access-Control-Allow-Credentials"));
        $this->assertSame("Origin", $response->getHeaderLine("Vary"));
        $this->assertSame("Authorization,Etag", $response->getHeaderLine("Access-Control-Expose-Headers"));
    }

    public function testShouldReturn401WithWrongOrigin(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("GET", "https://example.com/api")
            ->withHeader("Origin", "http://www.foo.com");

        $response = (new ResponseFactory())->createResponse();
        $cors = new CorsMiddleware([
            "origin" => ["http://www.example.com"],
            "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
            "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since"],
            "headers.expose" => ["Authorization", "Etag"],
            "credentials" => true,
            "cache" => 86400,
        ]);

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Foo");
            return $response;
        };

        $response = $cors($request, $response, $next);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testShouldReturn200WithCorrectOrigin(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("GET", "https://example.com/api")
            ->withHeader("Origin", "http://mobile.example.com");

        $response = (new ResponseFactory())->createResponse();
        $cors = new CorsMiddleware([
            "origin" => ["http://www.example.com", "http://mobile.example.com"],
            "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
            "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since"],
            "headers.expose" => ["Authorization", "Etag"],
            "credentials" => true,
            "cache" => 86400,
        ]);

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Foo");
            return $response;
        };

        $response = $cors($request, $response, $next);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testShouldReturn401WithWrongMethod(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("OPTIONS", "https://example.com/api")
            ->withHeader("Origin", "http://www.example.com")
            ->withHeader("Access-Control-Request-Headers", "Authorization")
            ->withHeader("Access-Control-Request-Method", "PUT");

        $response = (new ResponseFactory())->createResponse();
        $cors = new CorsMiddleware([
            "origin" => "*",
            "methods" => ["GET", "POST", "DELETE"],
            "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since"],
            "headers.expose" => ["Authorization", "Etag"],
            "credentials" => true,
            "cache" => 86400,
        ]);

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Foo");
            return $response;
        };

        $response = $cors($request, $response, $next);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testShouldReturn401WithWrongMethodFromFunction(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("OPTIONS", "https://example.com/api")
            ->withHeader("Origin", "http://www.example.com")
            ->withHeader("Access-Control-Request-Headers", "Authorization")
            ->withHeader("Access-Control-Request-Method", "PUT");

        $response = (new ResponseFactory())->createResponse();
        $cors = new CorsMiddleware([
            "origin" => "*",
            "methods" => fn($request) => ["GET", "POST", "DELETE"],
            "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since"],
            "headers.expose" => ["Authorization", "Etag"],
            "credentials" => true,
            "cache" => 86400,
        ]);

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Foo");
            return $response;
        };

        $response = $cors($request, $response, $next);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testShouldReturn401WithWrongMethodFromInvokableClass(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("OPTIONS", "https://example.com/api")
            ->withHeader("Origin", "http://www.example.com")
            ->withHeader("Access-Control-Request-Headers", "Authorization")
            ->withHeader("Access-Control-Request-Method", "PUT");

        $response = (new ResponseFactory())->createResponse();
        $cors = new CorsMiddleware([
            "origin" => "*",
            "methods" => new TestMethodsHandler(),
            "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since"],
            "headers.expose" => ["Authorization", "Etag"],
            "credentials" => true,
            "cache" => 86400,
        ]);

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Foo");
            return $response;
        };

        $response = $cors($request, $response, $next);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testShouldReturn200WithCorrectMethodFromFunction(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("OPTIONS", "https://example.com/api")
            ->withHeader("Origin", "http://www.example.com")
            ->withHeader("Access-Control-Request-Headers", "Authorization")
            ->withHeader("Access-Control-Request-Method", "DELETE");

        $response = (new ResponseFactory())->createResponse();
        $cors = new CorsMiddleware([
            "origin" => ["*"],
            "methods" => fn($request) => ["GET", "POST", "DELETE"],
            "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since"],
            "headers.expose" => ["Authorization", "Etag"],
            "credentials" => true,
            "cache" => 86400,
        ]);

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Foo");
            return $response;
        };

        $response = $cors($request, $response, $next);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testShouldReturn200WithCorrectMethodFromInvokableClass(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("OPTIONS", "https://example.com/api")
            ->withHeader("Origin", "http://www.example.com")
            ->withHeader("Access-Control-Request-Headers", "Authorization")
            ->withHeader("Access-Control-Request-Method", "DELETE");

        $response = (new ResponseFactory())->createResponse();
        $cors = new CorsMiddleware([
            "origin" => ["*"],
            "methods" => new TestMethodsHandler(),
            "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since"],
            "headers.expose" => ["Authorization", "Etag"],
            "credentials" => true,
            "cache" => 86400,
        ]);

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Foo");
            return $response;
        };

        $response = $cors($request, $response, $next);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testShouldReturn200WithCorrectMethodUsingArrayNotation(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("OPTIONS", "https://example.com/api")
            ->withHeader("Origin", "http://www.example.com")
            ->withHeader("Access-Control-Request-Headers", "Authorization")
            ->withHeader("Access-Control-Request-Method", "DELETE");

        $response = (new ResponseFactory())->createResponse();
        $cors = new CorsMiddleware([
            "origin" => ["*"],
            "methods" => fn(ServerRequestInterface $request) => TestMethodsHandler::methods($request),
            "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since"],
            "headers.expose" => ["Authorization", "Etag"],
            "credentials" => true,
            "cache" => 86400,
        ]);

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Foo");
            return $response;
        };

        $response = $cors($request, $response, $next);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testShouldReturn401WithWrongHeader(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("OPTIONS", "https://example.com/api")
            ->withHeader("Origin", "http://www.example.com")
            ->withHeader("Access-Control-Request-Headers", "X-Nosuch")
            ->withHeader("Access-Control-Request-Method", "PUT");

        $response = (new ResponseFactory())->createResponse();
        $cors = new CorsMiddleware([
            "origin" => ["*"],
            "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
            "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since"],
            "headers.expose" => ["Authorization", "Etag"],
            "credentials" => true,
            "cache" => 86400,
            "error" => fn($request, $response, $arguments) => "ignored",
        ]);

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Foo");
            return $response;
        };

        $response = $cors($request, $response, $next);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testShouldReturn200WithProperPreflightRequest(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("OPTIONS", "https://example.com/api")
            ->withHeader("Origin", "http://www.example.com")
            ->withHeader("Access-Control-Request-Headers", "Authorization")
            ->withHeader("Access-Control-Request-Method", "PUT");

        $response = (new ResponseFactory())->createResponse();
        $cors = new CorsMiddleware([
            "origin" => ["*"],
            "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
            "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since"],
            "headers.expose" => ["Authorization", "Etag"],
            "credentials" => true,
            "cache" => 86400,
        ]);

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Foo");
            return $response;
        };

        $response = $cors($request, $response, $next);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testShouldReturn200WithNoCorsHeaders(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("GET", "https://example.com/api")
            ->withHeader("Origin", "https://example.com");

        $response = (new ResponseFactory())->createResponse();
        $cors = new CorsMiddleware([
            "origin" => [],
            "origin.server" => "https://example.com",
        ]);

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Foo");
            return $response;
        };

        $response = $cors($request, $response, $next);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertEmpty($response->getHeaderLine("Access-Control-Allow-Origin"));
    }

    public function testAnonymousMethodsFunctionBindsToMiddlewareInstance(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("OPTIONS", "https://example.com/api")
            ->withHeader("Origin", "http://www.example.com")
            ->withHeader("Access-Control-Request-Headers", "X-Nosuch")
            ->withHeader("Access-Control-Request-Method", "PUT");

        $interceptedClassName = "";

        $response = (new ResponseFactory())->createResponse();
        $logger = new NullLogger();
        $cors = new CorsMiddleware([
            "logger" => $logger,
            "origin" => ["*"],
            "methods" => function () use (&$interceptedClassName) {
                $interceptedClassName = static::class;

                return ["GET"];
            },
            "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since"],
            "headers.expose" => ["Authorization", "Etag"],
            "credentials" => true,
            "cache" => 86400,
        ]);

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Foo");
            return $response;
        };

        $response = $cors($request, $response, $next);

        $this->assertSame(
            CorsMiddleware::class,
            $interceptedClassName
        );
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testShouldCallAnonymousErrorFunction(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("OPTIONS", "https://example.com/api")
            ->withHeader("Origin", "http://www.example.com")
            ->withHeader("Access-Control-Request-Headers", "X-Nosuch")
            ->withHeader("Access-Control-Request-Method", "PUT");

        $response = (new ResponseFactory())->createResponse();
        $logger = new NullLogger();
        $cors = new CorsMiddleware([
            "logger" => $logger,
            "origin" => ["*"],
            "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
            "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since"],
            "headers.expose" => ["Authorization", "Etag"],
            "credentials" => true,
            "cache" => 86400,
            "error" => function ($request, $response, $arguments) {
                $response->getBody()->write(static::class);
                return $response;
            },
        ]);

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Foo");
            return $response;
        };

        $response = $cors($request, $response, $next);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertInstanceOf($response->getBody(), $cors);
    }

    public function testShouldCallInvokableErrorClass(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("OPTIONS", "https://example.com/api")
            ->withHeader("Origin", "http://www.example.com")
            ->withHeader("Access-Control-Request-Headers", "X-Nosuch")
            ->withHeader("Access-Control-Request-Method", "PUT");

        $response = (new ResponseFactory())->createResponse();
        $logger = new NullLogger();
        $cors = new CorsMiddleware([
            "logger" => $logger,
            "origin" => ["*"],
            "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
            "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since"],
            "headers.expose" => ["Authorization", "Etag"],
            "credentials" => true,
            "cache" => 86400,
            "error" => new TestErrorHandler(),
        ]);

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Foo");
            return $response;
        };

        $response = $cors($request, $response, $next);
        $this->assertSame(402, $response->getStatusCode());
        $this->assertSame(TestErrorHandler::class, (string) $response->getBody());
    }

    public function testShouldCallArrayNotationError(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("OPTIONS", "https://example.com/api")
            ->withHeader("Origin", "http://www.example.com")
            ->withHeader("Access-Control-Request-Headers", "X-Nosuch")
            ->withHeader("Access-Control-Request-Method", "PUT");

        $response = (new ResponseFactory())->createResponse();
        $logger = new NullLogger();
        $cors = new CorsMiddleware([
            "logger" => $logger,
            "origin" => ["*"],
            "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
            "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since"],
            "headers.expose" => ["Authorization", "Etag"],
            "credentials" => true,
            "cache" => 86400,
            "error" => fn(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface => TestErrorHandler::error($request, $response, $arguments),
        ]);

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Foo");
            return $response;
        };

        $response = $cors($request, $response, $next);
        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame(TestErrorHandler::class, (string) $response->getBody());
    }

    public function testShouldHandlePsr15(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest("GET", "https://example.com/api")
            ->withHeader("Origin", "http://www.example.com");

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write("Success");
            return $response;
        };

        $collection = new MiddlewareCollection([
            new CorsMiddleware([
                "origin" => ["*"],
                "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
                "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since"],
                "headers.expose" => ["Authorization", "Etag"],
                "credentials" => true,
                "cache" => 86400,
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        $this->assertSame("http://www.example.com", $response->getHeaderLine("Access-Control-Allow-Origin"));
        $this->assertSame("true", $response->getHeaderLine("Access-Control-Allow-Credentials"));
        $this->assertSame("Origin", $response->getHeaderLine("Vary"));
        $this->assertSame("Authorization,Etag", $response->getHeaderLine("Access-Control-Expose-Headers"));
        $this->assertSame("Success", (string) $response->getBody());
    }
}
