<?php

namespace app\admin\middleware;

use app\admin\service\annotation\ControllerAnnotation;
use app\admin\service\annotation\MiddlewareAnnotation;
use app\admin\service\annotation\NodeAnnotation;
use app\admin\service\SystemLogService;
use app\common\traits\JumpTrait;
use app\Request;
use ReflectionClass;
use Closure;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\DocParser;
use ReflectionException;

class SystemLog
{
    use JumpTrait;

    /**
     * 敏感信息字段，日志记录时需要加密
     * @var array
     */
    protected array $sensitiveParams = [
        'password',
        'password_again',
        'phone',
        'mobile',
    ];

    /**
     * @throws ReflectionException
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (!env('APP_ADMIN_SYSTEM_LOG', true)) return $response;
        $params = $request->param();
        if (isset($params['s'])) unset($params['s']);
        foreach ($params as $key => $val) {
            in_array($key, $this->sensitiveParams) && $params[$key] = "***********";
        }
        $method = strtolower($request->method());
        $url    = $request->url();

        if (env('APP_DEBUG')) {
            trace(['url' => $url, 'method' => $method, 'params' => $params,], 'requestDebugInfo');
        }
        if ($request->isAjax()) {
            if (in_array($method, ['post', 'put', 'delete'])) {

                $title = '';
                try {
                    $pathInfo    = $request->pathinfo();
                    $pathInfoExp = explode('/', $pathInfo);
                    $_action     = end($pathInfoExp) ?? '';
                    $pathInfoExp = explode('.', $pathInfoExp[0] ?? '');
                    $_name       = $pathInfoExp[0] ?? '';
                    $_controller = ucfirst($pathInfoExp[1] ?? '');
                    $className   = $_controller ? "app\admin\controller\\{$_name}\\{$_controller}" : "app\admin\controller\\{$_name}";
                    if ($_name && $_action) {
                        $reflectionMethod = new \ReflectionMethod($className, $_action);
                        $attributes       = $reflectionMethod->getAttributes(MiddlewareAnnotation::class);
                        foreach ($attributes as $attribute) {
                            $annotation = $attribute->newInstance();
                            $_ignore    = (array)$annotation->ignore;
                            if (in_array('log', array_map('strtolower', $_ignore))) return $response;
                        }

                        // 支持旧写法
                        $reflectionClass = new \ReflectionClass($className);
                        $properties      = $reflectionClass->getDefaultProperties();
                        $ignoreLog       = $properties['ignoreLog'] ?? [];
                        if (in_array($_action, $ignoreLog)) return $response;
                        $parser = new DocParser();
                        $parser->setIgnoreNotImportedAnnotations(true);
                        $reader               = new AnnotationReader($parser);
                        $controllerAnnotation = $reader->getClassAnnotation($reflectionClass, ControllerAnnotation::class);
                        $reflectionAction     = $reflectionClass->getMethod($_action);
                        $nodeAnnotation       = $reader->getMethodAnnotation($reflectionAction, NodeAnnotation::class);
                        $title                = $controllerAnnotation->title . ' - ' . $nodeAnnotation->title;
                    }
                }catch (\Throwable $exception) {
                }

                $ip = $request->ip();
                // 限制记录的响应内容，避免过大
                $_response = json_encode($response->getData(), JSON_UNESCAPED_UNICODE);
                $_response = mb_substr($_response, 0, 3000, 'utf-8');

                $data = [
                    'admin_id'    => session('admin.id'),
                    'title'       => $title,
                    'url'         => $url,
                    'method'      => $method,
                    'ip'          => $ip,
                    'content'     => json_encode($params, JSON_UNESCAPED_UNICODE),
                    'response'    => $_response,
                    'useragent'   => $request->server('HTTP_USER_AGENT'),
                    'create_time' => time(),
                ];
                SystemLogService::instance()->save($data);
            }
        }
        return $response;
    }
}