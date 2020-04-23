<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Tests\Collectors;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Miquido\RequestDataCollector\Collectors\RequestResponseCollector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * @coversDefaultClass \Miquido\RequestDataCollector\Collectors\RequestResponseCollector
 */
class RequestResponseCollectorTest extends TestCase
{
    private $response = [
        '_GET'     => ['g' => 'g'],
        '_POST'    => ['p' => 'p'],
        '_SERVER'  => ['v' => 'v'],
        '_COOKIE'  => ['c' => 'c'],
        '_SESSION' => ['s' => 's'],
        '_ENV'     => ['e' => 'e'],
    ];

    private $config = [
        'raw'       => false,
        'variables' => [
            '_GET',
            '_POST',
            '_SERVER',
            '_COOKIE',
            '_SESSION',
            '_ENV',
        ],

    ];

    /**
     * @var \Illuminate\Http\Request&\Symfony\Component\HttpFoundation\Request&\Prophecy\Prophecy\ObjectProphecy
     */
    private $requestProphecy;

    /**
     * @var \Illuminate\Http\Response&\Symfony\Component\HttpFoundation\Response&\Prophecy\Prophecy\ObjectProphecy
     */
    private $responseProphecy;

    /**
     * @var \Miquido\RequestDataCollector\Collectors\RequestResponseCollector
     */
    private $requestResponseCollector;

    /**
     * @var \Symfony\Component\HttpFoundation\ParameterBag&\Prophecy\Prophecy\ObjectProphecy
     */
    private $parameterBagProphecy;

    protected function setUp(): void
    {
        if (!\defined('LARAVEL_START')) {
            \define('LARAVEL_START', \microtime(true));
        }

        $this->requestProphecy = $this->prophesize(Request::class);
        $this->responseProphecy = $this->prophesize(Response::class);
        $this->parameterBagProphecy = $this->prophesize(ParameterBag::class);

        /**
         * @var \Illuminate\Http\Request $requestMock
         */
        $requestMock = $this->requestProphecy->reveal();

        $this->requestResponseCollector = new RequestResponseCollector($requestMock);
    }

    public function testSetConfigForRawSetting(): void
    {
        $get = [
            'changeable'   => 'a',
            'unchangeable' => 'b',
        ];

        $this->config['raw'] = true;
        $request = new Request($get);

        $requestResponseCollector = new RequestResponseCollector($request);
        $requestResponseCollector->setConfig($this->config);
        $request['changeable'] = 'new value';

        $this->assertEquals($get, $requestResponseCollector->collect()['variables']['_GET']);
    }

    public function testSetConfigForNotRawSetting(): void
    {
        $get = [
            'changeable'   => 'a',
            'unchangeable' => 'b',
        ];
        $newValue = 'c';

        $request = new Request($get);

        $requestResponseCollector = new RequestResponseCollector($request);
        $requestResponseCollector->setConfig($this->config);

        $get['changeable'] = $request['changeable'] = $newValue;

        $this->assertEquals($get, $requestResponseCollector->collect()['variables']['_GET']);
    }

    public function testSimpleCollectMethod(): void
    {
        /**
         * @var \Symfony\Component\HttpFoundation\ParameterBag&\Prophecy\Prophecy\ObjectProphecy $parameterBagProphecy_get
         */
        $parameterBagProphecy_get = $this->prophesize(ParameterBag::class);

        /**
         * @var \Symfony\Component\HttpFoundation\ParameterBag&\Prophecy\Prophecy\ObjectProphecy $parameterBagProphecy_post
         */
        $parameterBagProphecy_post = $this->prophesize(ParameterBag::class);

        /**
         * @var \Symfony\Component\HttpFoundation\ParameterBag&\Prophecy\Prophecy\ObjectProphecy $parameterBagProphecy_server
         */
        $parameterBagProphecy_server = $this->prophesize(ParameterBag::class);

        /**
         * @var \Symfony\Component\HttpFoundation\ParameterBag&\Prophecy\Prophecy\ObjectProphecy $parameterBagProphecy_cookie
         */
        $parameterBagProphecy_cookie = $this->prophesize(ParameterBag::class);

        $parameterBagProphecy_get->all()->shouldBeCalled()->willReturn($this->response['_GET']);
        $parameterBagProphecy_post->all()->shouldBeCalled()->willReturn($this->response['_POST']);
        $parameterBagProphecy_server->all()->shouldBeCalled()->willReturn($this->response['_SERVER']);
        $parameterBagProphecy_cookie->all()->shouldBeCalled()->willReturn($this->response['_COOKIE']);

        $this->requestProphecy->query = $parameterBagProphecy_get->reveal();
        $this->requestProphecy->request = $parameterBagProphecy_post->reveal();
        $this->requestProphecy->server = $parameterBagProphecy_server->reveal();
        $this->requestProphecy->cookies = $parameterBagProphecy_cookie->reveal();

        $this->requestResponseCollector->setConfig($this->config);
        $_ENV = $this->response['_ENV'];
        $_SESSION = $this->response['_SESSION'];

        $collectedVariables = $this->requestResponseCollector->collect()['variables'];
        $this->assertEquals($this->response['_GET'], $collectedVariables['_GET']);
        $this->assertEquals($this->response['_POST'], $collectedVariables['_POST']);
        $this->assertEquals($this->response['_SERVER'], $collectedVariables['_SERVER']);
        $this->assertEquals($this->response['_COOKIE'], $collectedVariables['_COOKIE']);
        $this->assertEquals($this->response['_ENV'], $collectedVariables['_ENV']);
        $this->assertEquals($this->response['_SESSION'], $collectedVariables['_SESSION']);
    }

    public function testCollectMethodWithIncludes(): void
    {
        $get_correct = [
            'correct' => 'a',
        ];
        $get_incorrect = [
            'incorrect1' => 'b',
            'incorrect2' => 'c',
        ];
        $config = [
            'variables' => [
                '_GET' => [
                    'includes' => ['correct'],
                ],
            ],
        ];

        $this->parameterBagProphecy->all()
            ->shouldBeCalled()
            ->willReturn(\array_merge($get_correct, $get_incorrect));

        $this->requestProphecy->query = $this->parameterBagProphecy->reveal();

        $this->requestResponseCollector->setConfig($config);

        $this->assertEquals($get_correct, $this->requestResponseCollector->collect()['variables']['_GET']);
    }

    public function testCollectMethodWithExcludes(): void
    {
        $get_correct = [
            'correct1' => 'a',
            'correct2' => 'b',
        ];
        $get_incorrect = [
            'incorrect' => 'c',
        ];
        $config = [
            'variables' => [
                '_GET' => [
                    'excludes' => ['incorrect'],
                ],
            ],
        ];

        $this->parameterBagProphecy->all()
            ->shouldBeCalled()
            ->willReturn(\array_merge($get_correct, $get_incorrect));

        $this->requestProphecy->query = $this->parameterBagProphecy->reveal();

        $this->requestResponseCollector->setConfig($config);

        $this->assertEquals($get_correct, $this->requestResponseCollector->collect()['variables']['_GET']);
    }

    public function testRequestInfo(): void
    {
        $this->requestResponseCollector->setConfig([
            'request_info' => [
                $this->requestResponseCollector::REQUEST_INFO_BASE_URL,
                $this->requestResponseCollector::REQUEST_INFO_ROOT,
                $this->requestResponseCollector::REQUEST_INFO_URI,
                $this->requestResponseCollector::REQUEST_INFO_METHOD,
                $this->requestResponseCollector::REQUEST_INFO_REAL_METHOD,
                $this->requestResponseCollector::REQUEST_INFO_PATH_INFO,
                $this->requestResponseCollector::REQUEST_INFO_QUERY_STRING,
                $this->requestResponseCollector::REQUEST_INFO_USER_AGENT,
                $this->requestResponseCollector::REQUEST_INFO_FORMAT,
                $this->requestResponseCollector::REQUEST_INFO_IS_SECURE,
                $this->requestResponseCollector::REQUEST_INFO_IP,
                $this->requestResponseCollector::REQUEST_INFO_IPS,
                $this->requestResponseCollector::REQUEST_INFO_ROUTE,
            ],

            'response_info' => [],
        ]);

        $this->requestProphecy->getBaseUrl()->shouldBeCalled()->willReturn('testValue1');
        $this->requestProphecy->root()->shouldBeCalled()->willReturn('testValue2');
        $this->requestProphecy->getUri()->shouldBeCalled()->willReturn('testValue3');
        $this->requestProphecy->getMethod()->shouldBeCalled()->willReturn('testValue4');
        $this->requestProphecy->getRealMethod()->shouldBeCalled()->willReturn('testValue5');
        $this->requestProphecy->getPathInfo()->shouldBeCalled()->willReturn('testValue6');
        $this->requestProphecy->getQueryString()->shouldBeCalled()->willReturn('testValue7');
        $this->requestProphecy->userAgent()->shouldBeCalled()->willReturn('testValue8');
        $this->requestProphecy->getRequestFormat()->shouldBeCalled()->willReturn('testValue9');
        $this->requestProphecy->isSecure()->shouldBeCalled()->willReturn('testValue10');
        $this->requestProphecy->ip()->shouldBeCalled()->willReturn('testValue11');
        $this->requestProphecy->ips()->shouldBeCalled()->willReturn('testValue12');

        /** @var \Illuminate\Routing\Route&\Symfony\Component\HttpFoundation\Request&\Prophecy\Prophecy\ObjectProphecy $route */
        $route = $this->prophesize(Route::class);

        $routeValues = [
            'uri'                => 't1',
            'methods'            => 't2',
            'action'             => 't3',
            'isFallback'         => 't4',
            'controller'         => __CLASS__,
            'defaults'           => 't5',
            'wheres'             => 't6',
            'parameters'         => 't7',
            'parameterNames'     => 't8',
            'computedMiddleware' => 't9',
        ];

        $route->uri = $routeValues['uri'];
        $route->methods = $routeValues['methods'];
        $route->action = $routeValues['action'];
        $route->isFallback = $routeValues['isFallback'];
        $route->getController()->shouldBeCalled()->willReturn(new self());
        $route->defaults = $routeValues['defaults'];
        $route->wheres = $routeValues['wheres'];
        $route->parameters = $routeValues['parameters'];
        $route->parameterNames = $routeValues['parameterNames'];
        $route->computedMiddleware = $routeValues['computedMiddleware'];

        $this->requestProphecy->route()->shouldBeCalled()->willReturn($route->reveal());

        $collectedRequest = $this->requestResponseCollector->collect()['request'];
        $this->assertEquals('testValue1', $collectedRequest[$this->requestResponseCollector::REQUEST_INFO_BASE_URL]);
        $this->assertEquals('testValue2', $collectedRequest[$this->requestResponseCollector::REQUEST_INFO_ROOT]);
        $this->assertEquals('testValue3', $collectedRequest[$this->requestResponseCollector::REQUEST_INFO_URI]);
        $this->assertEquals('testValue4', $collectedRequest[$this->requestResponseCollector::REQUEST_INFO_METHOD]);
        $this->assertEquals('testValue5', $collectedRequest[$this->requestResponseCollector::REQUEST_INFO_REAL_METHOD]);
        $this->assertEquals('testValue6', $collectedRequest[$this->requestResponseCollector::REQUEST_INFO_PATH_INFO]);
        $this->assertEquals('testValue7', $collectedRequest[$this->requestResponseCollector::REQUEST_INFO_QUERY_STRING]);
        $this->assertEquals('testValue8', $collectedRequest[$this->requestResponseCollector::REQUEST_INFO_USER_AGENT]);
        $this->assertEquals('testValue9', $collectedRequest[$this->requestResponseCollector::REQUEST_INFO_FORMAT]);
        $this->assertEquals('testValue10', $collectedRequest[$this->requestResponseCollector::REQUEST_INFO_IS_SECURE]);
        $this->assertEquals('testValue11', $collectedRequest[$this->requestResponseCollector::REQUEST_INFO_IP]);
        $this->assertEquals('testValue12', $collectedRequest[$this->requestResponseCollector::REQUEST_INFO_IPS]);

        $this->assertEquals($routeValues, $collectedRequest[$this->requestResponseCollector::REQUEST_INFO_ROUTE]);
    }

    public function testResponseInfo(): void
    {
        $httpStatusCode = 200;
        $content = 'It works!';
        $dateHeader = Carbon::now()->format(Carbon::RFC7231_FORMAT);

        $this->requestResponseCollector->setConfig([
            'request_info' => [],

            'response_info' => [
                $this->requestResponseCollector::RESPONSE_INFO_HTTP_STATUS_CODE,
                $this->requestResponseCollector::RESPONSE_INFO_CONTENT,
                $this->requestResponseCollector::RESPONSE_INFO_HEADERS,
                $this->requestResponseCollector::RESPONSE_INFO_COOKIES,
            ],
        ]);

        /**
         * @var \Illuminate\Http\Response $responseMock
         */
        $responseMock = $this->responseProphecy->reveal();

        $responseMock->headers = new ResponseHeaderBag();

        $responseMock->headers->set('Content-Type', 'application/json');
        $responseMock->headers->set('Date', $dateHeader);
        $responseMock->headers->setCookie(new Cookie('name', 'value', 10));

        $this->requestResponseCollector->setResponse($responseMock);

        $this->responseProphecy->getStatusCode()->shouldBeCalledOnce()->willReturn($httpStatusCode);
        $this->responseProphecy->getContent()->shouldBeCalledOnce()->willReturn($content);

        $collectedRequest = $this->requestResponseCollector->collect();

        self::assertEquals([
            $this->requestResponseCollector::RESPONSE_INFO_HTTP_STATUS_CODE => $httpStatusCode,
            $this->requestResponseCollector::RESPONSE_INFO_CONTENT          => $content,

            $this->requestResponseCollector::RESPONSE_INFO_HEADERS => [
                'Content-Type'  => ['application/json'],
                'Cache-Control' => ['no-cache, private'],
                'Date'          => [$dateHeader],
            ],

            $this->requestResponseCollector::RESPONSE_INFO_COOKIES => [
                [
                    'name'         => 'name',
                    'value'        => 'value',
                    'expires_time' => 10,
                    'path'         => '/',
                    'domain'       => null,
                    'secure'       => false,
                    'http_only'    => true,
                ],
            ],
        ], $collectedRequest['response']);
    }
}
