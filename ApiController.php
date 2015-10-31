<?php

namespace machour\yii2\swagger\api;

use ReflectionClass;
use ReflectionMethod;
use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\helpers\Json;
use yii\helpers\Url;
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
        if ($action == $this->swaggerDocumentationAction) {
            return $this->getSwaggerDocumentation();
        }
        Yii::$app->response->format = Response::FORMAT_JSON;

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
     * Generates the swagger configuration file
     *
     * This method will inspect the current API controller and generate the
     * configuration based on the doc blocks found.
     *
     * @return array The definition as an array
     */
    public function actionSwagger()
    {
        $ret = [
            'swagger' => $this->swaggerVersion,
            'info' => [],
            'host' => '',
            'basePath' => '',
            'tags' => [],
            'schemes' => [],
            'paths' => [],
            'securityDefinitions' => $this->getSecurityDefinitions(),
            'definitions' => [],
        ];

        $class = new ReflectionClass(static::class);

        $classDoc = $class->getDocComment();
        $tags = ApiDocParser::parseDocCommentTags($classDoc);

        $ret['info']['description'] = ApiDocParser::parseDocCommentDetail($classDoc);

        if (isset($tags['version'])) {
            $ret['info']['version'] = $tags['version'];
        }

        $ret['info']['title'] = ApiDocParser::parseDocCommentSummary($classDoc);

        if (isset($tags['termsOfService'])) {
            $ret['info']['termsOfService'] = $tags['termsOfService'];
        }

        if (isset($tags['email'])) {
            $ret['info']['contact'] = [
                'email' => $tags['email'],
            ];
        }

        if (isset($tags['license'])) {
            $license = $this->tokenize($tags['license'], 2);
            $ret['info']['license'] = [
                'name' => $license[1],
                'url' => $license[0],
            ];
        }

        $tagsExternalDocs = [];
        if (isset($tags['tagExternalDocs'])) {
            $tagExternalDocs = $this->mixedToArray($tags['tagExternalDocs']);
            foreach ($tagExternalDocs as $externalDocs) {
                list($tag, $url, $description) = $this->tokenize($externalDocs, 3);
                $tagsExternalDocs[$tag] = [
                    'description' => $description,
                    'url' => $url,
                ];
            }
        }

        if (isset($tags['tag'])) {
            foreach ($this->mixedToArray($tags['tag']) as $tag) {
                $tagDef = array_combine(['name', 'description'], $this->tokenize($tag, 2));
                if (isset($tagsExternalDocs[$tagDef['name']])) {
                    $tagDef['externalDocs'] = $tagsExternalDocs[$tagDef['name']];
                }
                $ret['tags'][] = $tagDef;

            }
        }

        $ret['basePath'] = isset($tags['basePath']) ? $tags['basePath'] : Url::to('/');
        $ret['host'] = isset($tags['host']) ? $tags['host'] : Url::to('/', 1);

        if (isset($tags['scheme'])) {
            if (is_array($tags['scheme'])) {
                foreach ($tags['scheme'] as $scheme) {
                    $ret['schemes'][] = $scheme;
                }
            } else {
                $ret['schemes'][] = $tags['scheme'];
            }
        }

        $produces = [];
        if (isset($tags['produces'])) {
            $produces = $this->mixedToArray($tags['produces']);
        }

        $ret['paths'] = $this->parseMethods($class, $produces);

        $ret['definitions'] = [];
        if (isset($tags['definition'])) {
            $ret['definitions'] = $this->parseModels($this->mixedToArray($tags['definition']));
        }
        $ret['definitions']['ApiResponse'] = $this->parseModel('machour\\yii2\\swagger\\api\\ApiResponse', false);

        if (isset($tags['externalDocs'])) {
            $externalDocs = $this->tokenize($tags['externalDocs'], 2);
            $ret['externalDocs'] = [
                'description' => $externalDocs[1],
                'url' => $externalDocs[0],
            ];
        }

        Yii::$app->response->format = Response::FORMAT_JSON;
        return $ret;
    }

    /**
     * Returns the models definition
     *
     * @param array $definitions
     * @return array
     * @throws Exception
     */
    private function parseModels($definitions)
    {
        $ret = [];
        foreach ($definitions as $definition)
        {
            $ret[$definition] = $this->parseModel($definition);
        }
        return $ret;
    }

    /**
     * Returns a model definition
     *
     * @param $definition
     * @param bool $xml
     * @return array
     * @throws Exception
     */
    private function parseModel($definition, $xml = true) {
        $model = strpos($definition, '\\') === false ?
            $this->modelsNamespace . '\\' . $definition :
            $definition;
        if (!is_subclass_of($model, ApiModel::class)) {
            throw new Exception("The model definition for $model was not found", 501);
        }

        $class = new ReflectionClass($model);

        $ret = [
            'type' => 'object',
            'properties' => $this->parseProperties($class),
        ];
        if ($xml) {
            $ret['xml'] = ['name' => $definition];
        }

        return $ret;
    }

    /**
     * Returns the class properties
     *
     * Used for models
     *
     * @param ReflectionClass $class
     * @return array
     */
    private function parseProperties($class)
    {
        $ret = [];
        foreach ($class->getProperties() as $property) {
            $tags = ApiDocParser::parseDocCommentTags($property->getDocComment());
            list($type, $description) = $this->tokenize($tags['var'], 2);

            $p = [];
            if (strpos($type, '[]') > 0) {
                $type = str_replace('[]', '', $type);
                $p['type'] = 'array';
                $p['xml'] = ['name' => preg_replace('!s$!', '', $property->name), 'wrapped' => true];
                if (class_exists($this->modelsNamespace . '\\' . $type)) {
                    $p['items'] = ['$ref' => $this->getDefinition($type)];
                } else {
                    $p['items'] = ['type' => $type];
                }
            } elseif (class_exists($this->modelsNamespace . '\\' . $type)) {
                $p['$ref'] = $this->getDefinition($type);
            } else {
                $p['type'] = $type;
                $enums = isset($tags['enum']) ? $this->mixedToArray($tags['enum']) : [];
                foreach ($enums as $enum) {
                    $p['enum'] = $this->tokenize($enum);
                }
            }

            if (isset($tags['format'])) {
                $p['format'] = $tags['format'];
            }

            if (isset($tags['example'])) {
                $p['example'] = $tags['example'];
            }

            if (!empty($description)) {
                $p['description'] = $description;
            }

            $ret[$property->name] = $p;
        }
        return $ret;
    }

    /**
     * Returns the methods definition
     *
     * @param ReflectionClass $class
     * @param array $produces The class level @produces tags
     * @return array
     * @throws Exception
     */
    private function parseMethods($class, $produces)
    {
        $ret = [];
        foreach ($class->getMethods() as $method) {
            $def = [];

            $methodDoc = $method->getDocComment();

            if (static::class !== $method->class) {
                continue;
            }

            $tags = ApiDocParser::parseDocCommentTags($methodDoc);

            if (!isset($tags['path'])) {
                continue;
            }

            if (!in_array($tags['method'], $this->supportedMethods)) {
                throw new Exception("Unknown HTTP method specified in {$method->name} : {$tags['method']}", 501);
            }

            //$params = DocParser::getActionArgsHelp($method);

            $enums = isset($tags['enum']) ? $this->mixedToArray($tags['enum']) : [];
            $availableEnums = [];
            foreach ($enums as $enum) {
                $data = $this->tokenize($enum);
                $availableEnums[str_replace('$', '', array_shift($data))] = $data;
            }

            if (isset($tags['tag'])) {
                $def['tags'] = $this->mixedToArray($tags['tag']);
            }

            $def['summary'] = ApiDocParser::parseDocCommentSummary($methodDoc);
            $def['description'] = ApiDocParser::parseDocCommentDetail($methodDoc);

            $def['operationId'] = $this->getOperationId($method);

            if (isset($tags['consumes'])) {
                $def['consumes'] = $this->mixedToArray($tags['consumes']);
            }

            if (isset($tags['produces'])) {
                $def['produces'] = $this->mixedToArray($tags['produces']);
            } elseif (!empty($produces)) {
                $def['produces'] = $produces;
            }

            $def['parameters'] = $this->parseParameters($method, $tags, $availableEnums);

            $def['responses'] = [];

            if (isset($tags['return'])) {
                list($type, $description) = $this->tokenize($tags['return'], 2);
                $def['responses'][200] = [];
                if (!empty($description)) {
                    $def['responses'][200]['description'] = $description;
                }

                $schema = [];
                if (strpos($type, '[]') > 0) {
                    $schema['type'] = 'array';
                    $type = str_replace('[]', '', $type);
                    if (class_exists($this->modelsNamespace . '\\' . $type)) {
                        $schema['items'] = ['$ref' => $this->getDefinition($type)];
                    }
                } elseif (class_exists($this->modelsNamespace . '\\' . $type)) {
                    $schema = ['$ref' => $this->getDefinition($type)];
                } else {
                    $schema['type'] = $type;
                }
                $def['responses'][200]['schema'] = $schema;

            }
            /*    $def['responses'][200] = [
                    'description' => $tags['return'],
                    'schema' => $tags['return'],
                ];
    */
            if (isset($tags['errors'])) {
                $errors = $this->mixedToArray($tags['errors']);
                foreach ($errors as $error) {
                    if (preg_match('!(\d+)\s+(.*)$!', $error, $matches)) {
                        $def['responses'][$matches[1]] = ['description' => $matches[2]];
                    }
                }
            }

            if (isset($tags['security'])) {
                $security = [];
                $secs = $this->mixedToArray($tags['security']);
                foreach ($secs as $sec) {
                    list($bag, $permission) = $this->tokenize($sec, 2);
                    if (!isset($security[$bag])) {
                        $security[$bag] = [];
                    }
                    if (!empty($permission)) {
                        $security[$bag][] = $permission;
                    }
                }
                foreach ($security as $section => $privileges) {
                    $def['security'][] = [$section => $privileges];
                }
            }

            if (!isset($ret[$tags['path']])) {
                $ret[$tags['path']] = [];
            }
            $ret[$tags['path']][$tags['method']] = $def;
        }
        return $ret;
    }

    /**
     * Gets the swagger operation id from a method
     *
     * @param ReflectionMethod $method
     * @return string
     */
    private function getOperationId($method)
    {
        return strtolower(substr($method->name, 6, 1)) . substr($method->name, 7);
    }

    /**
     * Tells if the given method have the given parameter in its signature
     *
     * @param ReflectionMethod $method The method to inspect
     * @param string $parameter The parameter name
     * @return bool Returns TRUE if the parameter is present in the signature, FALSE otherwise
     */
    private function haveParameter($method, $parameter)
    {
        foreach ($method->getParameters() as $param) {
            if ($param->name == $parameter) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the parameters definition
     *
     * @param ReflectionMethod $method
     * @param array $tags
     * @param array $availableEnums
     * @return array
     */
    private function parseParameters($method, $tags, $availableEnums)
    {
        $ret = [];
        foreach (['parameter' => true, 'optparameter' => false] as $tag => $required) {
            if (isset($tags[$tag])) {
                $parameters = $this->mixedToArray($tags[$tag]);
                foreach ($parameters as $parameter) {
                    list($type, $name, $description) = $this->tokenize($parameter, 3);
                    $name = ltrim($name, '$');
                    $p = [];
                    $p['name'] = $name;

                    $http_method = $tags['method'];

                    $consumes = isset($tags['consumes']) ? $tags['consumes'] : '';
                    switch ($consumes) {
                        case 'multipart/form-data':
                            $p['in'] = $this->haveParameter($method, $name) ? 'path' : 'formData';
                            break;

                        case 'application/x-www-form-urlencoded':
                            $p['in'] = $this->haveParameter($method, $name) ? 'path' : 'formData';
                            break;

                        default:
                            if (in_array($http_method, ['put', 'post'])) {
                                if ($this->isPathParameter($name, $tags['path'])) {
                                    $p['in'] = 'path';
                                } else {
                                    $p['in'] = 'body';
                                }
                            } else {
                                if ($this->isPathParameter($name, $tags['path'])) {
                                    $p['in'] = 'path';
                                } else {
                                    $p['in'] = $this->haveParameter($method, $name) ? 'query' : 'header';
                                }
                            }
                        break;
                    }
                    if (!empty($description)) {
                        $p['description'] = $description;
                    }
                    $p['required'] = $required;
                    if (strpos($type, '[]') > 0) {
                        $p['type'] = 'array';
                        $p['items'] = ['type' => str_replace('[]', '', $type)];
                        if (isset($availableEnums[$name])) {
                            $p['items']['enum'] = $availableEnums[$name];
                            $p['items']['default'] = count($availableEnums[$name]) ? $availableEnums[$name][0] : '';
                        }
                        $p['collectionFormat'] = 'csv';
                    } elseif (class_exists($this->modelsNamespace . '\\' . $type)) {
                        $p['schema'] = ['$ref' => $this->getDefinition($type)];
                    } else {
                        list($type, $format) = $this->getTypeAndFormat($type);
                        $p['type'] = $type;
                        if ($format !== null) {
                            $p['format'] = $format;
                        }
                    }
                    $ret[] = $p;
                }
            }
        }
        return $ret;
    }

    /**
     * Gets the type and format of a parameter
     *
     * @param $type
     * @return array An array with the type as first element, and the format
     *               or null as second element
     */
    private function getTypeAndFormat($type)
    {
        switch ($type) {
            case 'int64': return ['integer', 'int64'];
            case 'int32': return ['integer', 'int32'];
            default: return [$type, null];
        }
    }

    /**
     * Tells if a parameter is part of the swagger path
     *
     * @param string $name The parameter name
     * @param string $path The swagger path
     * @return bool Returns TRUE if the parameter is in the path, FALSE otherwise
     */
    private function isPathParameter($name, $path)
    {
        return strpos($path, '{' . $name . '}') !== false;
    }

    /**
     * Returns the definition path for a type
     *
     * @param string $type The model name
     * @return string
     */
    private function getDefinition($type)
    {
        return '#/definitions/' . $type;
    }

    /**
     * Converts a mixed (array or string) value to an array
     *
     * @param string|array $mixed The mixed value
     * @return array The resulting array
     */
    private function mixedToArray($mixed)
    {
        return is_array($mixed) ? array_values($mixed) : [$mixed];
    }

    /**
     * Tokenize an input string
     *
     * @param string $input The string to be tokenized
     * @param int $limit The number of tokens to generate
     * @return array
     */
    private function tokenize($input, $limit = -1)
    {
        $chunks = preg_split('!\s+!', $input, $limit);
        if ($limit !== -1 && count($chunks) != $limit) {
            $chunks += array_fill(count($chunks), $limit, '');
        }
        return $chunks;
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
                $doc = $this->actionSwagger();
                // Store the credentials to disk.
                if (!is_dir(dirname($path))) {
                    mkdir(dirname($path), 0700, true);
                }
                file_put_contents($path, Json::encode($doc));
            }
        } else {
            $doc = $this->actionSwagger();
        }
        return $doc;
    }
}

