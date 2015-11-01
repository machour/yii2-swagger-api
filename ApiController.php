<?php

namespace machour\yii2\swagger\api;

use ReflectionClass;
use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\helpers\Json;
use yii\web\Controller as BaseController;
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
     * Swagger documentation action
     * @var string
     */
    public $swaggerDocumentationAction = 'swagger.json';

    /**
     * The exception code when the requested action is not found
     * @var int
     */
    public $apiNotFoundCode = 404;

    /**
     * The exception message when the requested action is not found
     * @var string
     */
    public $apiNotFoundMessage = 'Action not found';

    /**
     * Whether to cache the generated api docs
     * @var bool
     */
    public $apiDocsCacheEnabled = true;

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
     * @var ApiGenerator
     */
    private $generator;

    /**
     * Gets the security definitions for the API
     *
     * @abstract
     * @return array
     */
    abstract function getSecurityDefinitions();

    /**
     * @inheritdoc
     */
    public function init()
    {
        if ($this->modelsNamespace == null) {
            throw new InvalidConfigException('The $modelsNamespace property must be set by your controller');
        }

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
        return $this->render('index');
    }

    /**
     * Catch all action
     *
     * All calls made to the API, including swagger json configuration calls
     * are forwarded to this action, which analyze the url, and forwards the
     * call accordingly, selecting the correct API version folder
     *
     * @todo Add XML support
     *
     * @param string $version The api version number
     * @param string $path The requested path
     * @param string $action The requested action
     * @return object Returns the JSON or XML response
     * @throws Exception
     */
    public function actionCall($version, $path, $action = null)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if ($action == $this->swaggerDocumentationAction) {
            return $this->getSwaggerDocumentation();
        }

        $class = new ReflectionClass(static::class);

        foreach ($class->getMethods() as $method) {

            if ($method->class !== static::class) {
                continue;
            }

            $tags = ApiDocParser::parseDocCommentTags($method->getDocComment());

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
                    return $this->sendSuccess($method->invokeArgs($this, $get));
                } catch (\Exception $e) {
                    return $this->sendFailure(ApiException::fromException($e));
                }
            }
        }

        return $this->sendFailure(new ApiException($this->apiNotFoundMessage, $this->apiNotFoundCode));

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
    private function getSwaggerDocumentation()
    {
        if ($this->apiDocsCacheEnabled) {
            $filename = str_replace('\\', '-', static::class);
            $path = Yii::getAlias($this->apiDocsCacheDirectory) . $filename . '.json';

            if (file_exists($path)) {
                $doc = file_get_contents($path);
            } else {
                $doc = $this->generator->getJson();
                // Store the credentials to disk.
                if (!is_dir(dirname($path))) {
                    mkdir(dirname($path), 0700, true);
                }
                file_put_contents($path, Json::encode($doc));
            }
        } else {
            $doc = $this->generator->getJson();
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
    }
}

