<?php

namespace machour\yii2\swagger\api;

use ReflectionClass;
use Yii;
use yii\base\Action;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\helpers\Json;
use yii\rest\ActiveController as BaseController;
use yii\web\Response;

/**
 * Class ApiController
 */
abstract class ApiController extends BaseController
{
    /**
     * @inheritdoc
     */
    public $enableCsrfValidation = false;

    /**
     * @var string The models namespace. Must be defined by the daughter class
     */
    public $modelsNamespace;

    /**
     * Swagger current version
     * @var string
     */
    public $swaggerVersion = '2.0';

    /**
     * The exception code when the requested action is not found
     * @var int
     */
    public $apiNotFoundCode = 404;

    /**
     * The exception message when the requested action is not found
     * @var string
     */
    public $apiNotFoundMessage = 'API action not found';

    /**
     * Whether to cache the generated api docs
     * @var bool
     */
    public $apiDocsCacheEnabled = YII_ENV_PROD;

    /**
     * The directory where the generated api docs will be cached
     * @var string
     */
    public $apiDocsCacheDirectory = '@runtime/swagger-api/';

    /**
     * @var array List of supported HTTP methods
     */
    public $supportedMethods = [
        'get',
        'post',
        'put',
        'delete',
        'head',
        'options',
        'patch'
    ];

    /**
     * @var string
     */
    public $versionsDirectory = 'versions/';

    /**
     * @var boolean
     */
    public $shutdown = false;

    /**
     * @var string
     */
    public $shutdownMessage = 'API disabled';

    /**
     * @var string
     */
    public $shutdownCode = 403;

    /**
     * @var ApiGenerator
     */
    public $generator;

    /**
     * @var string The response format
     */
    public $format = Response::FORMAT_JSON;

    /**
     * Gets the security definitions for the API
     *
     * @abstract
     * @return array
     */
    abstract public function getSecurityDefinitions();

    protected $version;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if ($this->modelsNamespace == null) {
            throw new InvalidConfigException('The $modelsNamespace property must be set by your controller');
        }

        $this->version = $this->module->id;

        parent::init();

        $this->generator = new ApiGenerator([
            'modelsNamespace' => $this->modelsNamespace,
            'swaggerVersion' => '2.0',
            'swaggerDocumentationAction' => 'swagger.json',
            'securityDefinitions' => $this->getSecurityDefinitions(),
            'controller' => static::class,
            'supportedMethods' => $this->supportedMethods,
        ]);
    }

    /**
     * Generates the swagger frontend
     *
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('@vendor/views/api/index');
    }

    /**
     * @param Action $action
     * @return bool
     */
    public function beforeAction($action)
    {
        if ($action->id == 'call') {

            if (Yii::$app->request->headers->has('accept')) {
                switch (Yii::$app->request->headers->get('accept')) {
                    case 'application/xml':
                        $this->format = Response::FORMAT_XML;
                        break;

                    case 'application/json':
                        $this->format = Response::FORMAT_JSON;
                        break;
                }
            }
            Yii::$app->response->format = $this->format;
        }
        return parent::beforeAction($action);
    }

    /**
     * Catch all action
     *
     * All calls made to the API, including swagger json configuration calls
     * are forwarded to this action, which analyze the url, and forwards the
     * call accordingly, selecting the correct API version folder
     *
     * @param string $path The requested path
     * @param string $action The requested action
     * @return object Returns the JSON or XML response
     * @throws Exception
     * @throws \Exception Regular exceptions will be show if YII_DEBUG is true
     */
    public function actionCall($path = null, $action = null)
    {

        if ($this->shutdown) {
            return $this->sendFailure(new ApiException($this->shutdownMessage, $this->shutdownCode));
        }

        if ($this->module->id != $this->version) {
            throw new Exception("Version number mismatch. (check your routes)");
        }

        $class = new ReflectionClass(static::class);

        foreach ($class->getMethods() as $method) {

            if ($method->class !== static::class && !is_subclass_of($method->class, self::class)) {
                continue;
            }

            $tags = ApiGenerator::parseDocCommentTags($this->generator->fetchDocComment($method));

            if (!isset($tags['path'])) {
                continue;
            }

            if (!in_array($tags['method'], $this->supportedMethods)) {
                throw new Exception("Unknown HTTP method specified in {$method->name} : {$tags['method']}", 501);
            }

            if ($tags['path'] == rtrim('/' . $path . '/' . $action, '/') && $tags['method'] == $this->getRequestMethod()) {
                try {
                    $get = Yii::$app->request->get();
                    unset($get['version'], $get['path'], $get['action']);

                    $a = $this->generator->parseMethod($method);

                    $args = [];

                    $rParams = $method->getParameters();

                    foreach ($a['parameters'] as $i => $parameter) {
                        // Test GET parameters
                        if ($parameter['in'] == 'query') {
                            if ($parameter['required'] && !isset($get[$parameter['name']])) {
                                return $this->sendFailure(new ApiException("Missing required parameter: " . $parameter['name'], 400));
                            } else {
                                switch ($parameter['type']) {
                                    case 'boolean':
                                        // "false" => false
                                        if (isset($get[$parameter['name']]) && $get[$parameter['name']] === 'false') {
                                            $get[$parameter['name']] = false;
                                        }
                                        break;
                                    case 'string':
                                        if (isset($get[$parameter['name']]) && is_array($get[$parameter['name']])) {
                                            return $this->sendFailure(new ApiException(sprintf("Wrong parameter type for %s, string required but array seen", $parameter['name']), 400));
                                        }
                                        break;
                                }
                            }
                            if ($parameter['required'] || isset($get[$parameter['name']])) {
                                $args[$parameter['name']] = $get[$parameter['name']];
                            } else {
                                $args[$parameter['name']] = $rParams[$i]->getDefaultValue();
                            }
                        }

                    }

                    return $this->sendSuccess($method->invokeArgs($this, $args));
                } catch (ApiException $e) {
                    return $this->sendFailure($e);
                } catch (\Exception $e) {
                    if (YII_DEBUG) {
                        // We are in development mode, use Yii built in error page
                        Yii::$app->response->format = Response::FORMAT_HTML;
                        throw $e;
                    } else {
                        return $this->sendFailure(new ApiException("Something went wrong", 501));
                    }
                }
            }
        }

        return $this->sendFailure(new ApiException($this->apiNotFoundMessage, $this->apiNotFoundCode));

    }

    /**
     * Ask the controller not to run the API call, but to raise
     * an ApiException instead
     *
     * @param string $message
     * @param int $code
     * @return bool Returns TRUE
     */
    public function shutdown($message = null, $code = null)
    {
        if (!is_null($message)) {
            $this->shutdownMessage = $message;
        }
        if (!is_null($code)) {
            $this->shutdownCode = $code;
        }
        $this->shutdown = true;
        return true;
    }

    /**
     * @param mixed $response The response
     * @return array
     */
    private function sendSuccess($response) {
        return $this->sendResponse(200, 'success', $response);
    }

    /**
     * @param ApiException $exception
     * @return array
     */
    private function sendFailure($exception) {
        return $this->sendResponse($exception->code, $exception->message);
    }

    /**
     * Formats an API response
     *
     * @param int $code The response code
     * @param string $message The response human readable message
     * @param object $response The responded object
     * @return array
     */
    private function sendResponse($code, $message, $response = null) {
        $ret = [
            'code' => $code,
            'message' => $message,
        ];
        if ($response) {
            $ret['response'] = $response;
        }
        return $ret;
    }

    /**
     * Gets the swagger documentation
     *
     * This will fetch/write into the cache if $apiDocsCacheEnabled is set to true.
     *
     * @todo Automatically refresh the cache by checking controller and models mtimes
     * @return array The swagger documentation structure
     */
    public function actionDocumentation()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if ($this->apiDocsCacheEnabled) {
            $filename = str_replace('\\', '-', static::class);
            $path = Yii::getAlias($this->apiDocsCacheDirectory) . $filename . '.json';

            if (file_exists($path)) {
                $doc = file_get_contents($path);
            } else {
                $doc = $this->generator->getJson($this->version);
                // Store the credentials to disk.
                if (!is_dir(dirname($path))) {
                    mkdir(dirname($path), 0700, true);
                }
                file_put_contents($path, Json::encode($doc));
            }
        } else {
            $doc = $this->generator->getJson($this->version);
        }
        return $doc;
    }

    /**
     * Translate the Yii HTTP notation to Swagger notation
     *
     * @return string
     */
    private function getRequestMethod()
    {
        switch (Yii::$app->request->method) {
            case 'GET': return 'get';
            case 'PUT': return 'put';
            case 'POST': return 'post';
            case 'DELETE': return 'delete';
            case 'PATCH': return 'patch';
            case 'OPTIONS': return 'options';
            case 'HEAD': return 'head';
        }
        throw new \Exception("Unknown HTTP Method", 400);
    }
}

