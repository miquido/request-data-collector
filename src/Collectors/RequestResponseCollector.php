<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Collectors;

use Illuminate\Http\Request;
use Miquido\RequestDataCollector\Collectors\Contracts\ConfigurableInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\UsesResponseInterface;
use Miquido\RequestDataCollector\RequestDataCollector;
use Miquido\RequestDataCollector\Traits\ConfigurableTrait;
use Symfony\Component\HttpFoundation\Response;

class RequestResponseCollector implements DataCollectorInterface, ConfigurableInterface, UsesResponseInterface
{
    use ConfigurableTrait {
        ConfigurableTrait::setConfig as setConfigTrait;
    }

    public const REQUEST_INFO_FORMAT = 'format';

    public const REQUEST_INFO_URI = 'uri';

    public const REQUEST_INFO_ROOT = 'root';

    public const REQUEST_INFO_BASE_URL = 'base_url';

    public const REQUEST_INFO_METHOD = 'method';

    public const REQUEST_INFO_REAL_METHOD = 'real_method';

    public const REQUEST_INFO_PATH_INFO = 'path_info';

    public const REQUEST_INFO_QUERY_STRING = 'query_string';

    public const REQUEST_INFO_USER_AGENT = 'user_agent';

    public const REQUEST_INFO_IS_SECURE = 'is_secure';

    public const REQUEST_INFO_IP = 'ip';

    public const REQUEST_INFO_IPS = 'ips';

    public const REQUEST_INFO_ROUTE = 'route'; // Works only with raw=false setting

    public const RESPONSE_INFO_HTTP_STATUS_CODE = 'http_status_code';

    public const RESPONSE_INFO_CONTENT = 'content';

    public const RESPONSE_INFO_HEADERS = 'headers';

    public const RESPONSE_INFO_COOKIES = 'cookies';

    public const VARIABLE_GET = '_GET';

    public const VARIABLE_POST = '_POST';

    public const VARIABLE_SERVER = '_SERVER';

    public const VARIABLE_COOKIE = '_COOKIE';

    public const VARIABLE_SESSION = '_SESSION';

    public const VARIABLE_ENV = '_ENV';

    /**
     * @var \Illuminate\Http\Request
     */
    private $request;

    /**
     * @var \Symfony\Component\HttpFoundation\Response|null
     */
    private $response;

    /**
     * @param \Illuminate\Http\Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @inheritdoc
     */
    public function setConfig(array $config): void
    {
        $this->setConfigTrait($config);

        if ($config['raw'] ?? false) {
            $this->request = clone $this->request;
        }
    }

    /**
     * @inheritdoc
     */
    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }

    /**
     * @inheritdoc
     */
    public function collect(): array
    {
        return [
            'request'   => $this->collectRequest(),
            'response'  => $this->collectResponse(),
            'variables' => $this->collectVariables(),
            'times'     => $this->collectTimes(),
        ];
    }

    /**
     * @return array
     */
    private function collectRequest(): array
    {
        $data = [];

        if ($this->shouldCollectRequestInfo(self::REQUEST_INFO_FORMAT)) {
            $data[self::REQUEST_INFO_FORMAT] = $this->request->getRequestFormat();
        }

        if ($this->shouldCollectRequestInfo(self::REQUEST_INFO_URI)) {
            $data[self::REQUEST_INFO_URI] = $this->request->getUri();
        }

        if ($this->shouldCollectRequestInfo(self::REQUEST_INFO_ROOT)) {
            $data[self::REQUEST_INFO_ROOT] = $this->request->root();
        }

        if ($this->shouldCollectRequestInfo(self::REQUEST_INFO_BASE_URL)) {
            $data[self::REQUEST_INFO_BASE_URL] = $this->request->getBaseUrl();
        }

        if ($this->shouldCollectRequestInfo(self::REQUEST_INFO_METHOD)) {
            $data[self::REQUEST_INFO_METHOD] = $this->request->getMethod();
        }

        if ($this->shouldCollectRequestInfo(self::REQUEST_INFO_REAL_METHOD)) {
            $data[self::REQUEST_INFO_REAL_METHOD] = $this->request->getRealMethod();
        }

        if ($this->shouldCollectRequestInfo(self::REQUEST_INFO_PATH_INFO)) {
            $data[self::REQUEST_INFO_PATH_INFO] = $this->request->getPathInfo();
        }

        if ($this->shouldCollectRequestInfo(self::REQUEST_INFO_QUERY_STRING)) {
            $data[self::REQUEST_INFO_QUERY_STRING] = $this->request->getQueryString();
        }

        if ($this->shouldCollectRequestInfo(self::REQUEST_INFO_USER_AGENT)) {
            $data[self::REQUEST_INFO_USER_AGENT] = $this->request->userAgent();
        }

        if ($this->shouldCollectRequestInfo(self::REQUEST_INFO_IS_SECURE)) {
            $data[self::REQUEST_INFO_IS_SECURE] = $this->request->isSecure();
        }

        if ($this->shouldCollectRequestInfo(self::REQUEST_INFO_IP)) {
            $data[self::REQUEST_INFO_IP] = $this->request->ip();
        }

        if ($this->shouldCollectRequestInfo(self::REQUEST_INFO_IPS)) {
            $data[self::REQUEST_INFO_IPS] = $this->request->ips();
        }

        if ($this->shouldCollectRequestInfo(self::REQUEST_INFO_ROUTE)) {
            $route = $this->request->route();

            $data[self::REQUEST_INFO_ROUTE] = null;

            if (null !== $route) {
                $data[self::REQUEST_INFO_ROUTE] = [
                    'uri'                => $route->uri,
                    'methods'            => $route->methods,
                    'action'             => $route->action,
                    'isFallback'         => $route->isFallback,
                    'controller'         => \get_class($route->getController()),
                    'defaults'           => $route->defaults,
                    'wheres'             => $route->wheres,
                    'parameters'         => $route->parameters,
                    'parameterNames'     => $route->parameterNames,
                    'computedMiddleware' => $route->computedMiddleware,
                ];
            }
        }

        return $data;
    }

    /**
     * @return array
     */
    private function collectResponse(): array
    {
        $data = [];

        if ($this->shouldCollectResponseInfo(self::RESPONSE_INFO_HTTP_STATUS_CODE)) {
            $data[self::RESPONSE_INFO_HTTP_STATUS_CODE] = $this->response->getStatusCode();
        }

        if ($this->shouldCollectResponseInfo(self::RESPONSE_INFO_CONTENT)) {
            $data[self::RESPONSE_INFO_CONTENT] = $this->response->getContent();
        }

        if ($this->shouldCollectResponseInfo(self::RESPONSE_INFO_HEADERS)) {
            $data[self::RESPONSE_INFO_HEADERS] = $this->response->headers->allPreserveCaseWithoutCookies();
        }

        if ($this->shouldCollectResponseInfo(self::RESPONSE_INFO_COOKIES)) {
            $data[self::RESPONSE_INFO_COOKIES] = [];

            foreach ($this->response->headers->getCookies($this->response->headers::COOKIES_FLAT) as $cookie) {
                $data[self::RESPONSE_INFO_COOKIES][] = [
                    'name'         => $cookie->getName(),
                    'value'        => $cookie->getValue(),
                    'expires_time' => $cookie->getExpiresTime(),
                    'path'         => $cookie->getPath(),
                    'domain'       => $cookie->getDomain(),
                    'secure'       => $cookie->isSecure(),
                    'http_only'    => $cookie->isHttpOnly(),
                ];
            }
        }

        return $data;
    }

    /**
     * @return array
     */
    private function collectVariables(): array
    {
        $data = [];

        if ($this->shouldCollectVariable('_GET')) {
            $data['_GET'] = $this->applyExcludeAndIncludeLogic('_GET', $this->request->query->all());
        }

        if ($this->shouldCollectVariable('_POST')) {
            $data['_POST'] = $this->applyExcludeAndIncludeLogic('_POST', $this->request->request->all());
        }

        if ($this->shouldCollectVariable('_SERVER')) {
            $data['_SERVER'] = $this->applyExcludeAndIncludeLogic('_SERVER', $this->request->server->all());
        }

        if ($this->shouldCollectVariable('_COOKIE')) {
            $data['_COOKIE'] = $this->applyExcludeAndIncludeLogic('_COOKIE', $this->request->cookies->all());
        }

        if ($this->shouldCollectVariable('_SESSION')) {
            global $_SESSION;

            $data['_SESSION'] = $this->applyExcludeAndIncludeLogic('_SESSION', $_SESSION ?? []);
        }

        if ($this->shouldCollectVariable('_ENV')) {
            global $_ENV;

            $data['_ENV'] = $this->applyExcludeAndIncludeLogic('_ENV', $_ENV ?? []);
        }

        return $data;
    }

    /**
     * @return array
     */
    private function collectTimes(): array
    {
        return [
            'laravel_started_at' => \defined('LARAVEL_START') ? LARAVEL_START : -1.0,
            'rdc_started_at'     => RequestDataCollector::getStartedAt(),
            'collected_at'       => \microtime(true),
        ];
    }

    /**
     * @param string $infoName
     *
     * @return bool
     */
    private function shouldCollectRequestInfo(string $infoName): bool
    {
        return \in_array($infoName, $this->config['request_info'] ?? [], true);
    }

    /**
     * @param string $infoName
     *
     * @return bool
     */
    private function shouldCollectResponseInfo(string $infoName): bool
    {
        return \in_array($infoName, $this->config['response_info'] ?? [], true);
    }

    /**
     * @param string $variableName
     *
     * @return bool
     */
    private function shouldCollectVariable(string $variableName): bool
    {
        return isset($this->config['variables'][$variableName]) || \in_array($variableName, $this->config['variables'] ?? [], true);
    }

    /**
     * @param string $variableName
     *
     * @return array
     */
    private function getVariableConfig(string $variableName): array
    {
        return $this->config['variables'][$variableName] ?? [];
    }

    /**
     * @param string $key
     * @param array  $fullDataArray
     *
     * @return array
     */
    private function applyExcludeAndIncludeLogic(string $key, array $fullDataArray): array
    {
        $config = $this->getVariableConfig($key);
        $includes = $config['includes'] ?? null;
        $excludes = $config['excludes'] ?? null;

        if (null !== $includes) {
            return \array_intersect_key($fullDataArray, \array_flip($includes));
        }

        if (null !== $excludes) {
            return \array_diff_key($fullDataArray, \array_flip($excludes));
        }

        return $fullDataArray;
    }
}
