<?php

namespace machour\yii2\swagger\api;


use ReflectionClass;
use ReflectionMethod;
use Yii;
use yii\base\Configurable;
use yii\base\Exception;
use yii\captcha\CaptchaValidator;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\helpers\VarDumper;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

class ApiGenerator implements Configurable
{

    /**
     * Controller level model definition
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#definitionsObject
     */
    const T_DEFINITION = 'definition';

    /**
     * Api version number
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#infoObject
     */
    const T_VERSION = 'version';

    /**
     * Terms of service
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#infoObject
     */
    const T_TOS = 'termsOfService';

    /**
     * Contact email
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#contactObject
     */
    const T_CONTACT_EMAIL = 'contactEmail';

    /**
     * Contact name
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#contactObject
     */
    const T_CONTACT_NAME = 'contactName';

    /**
     * Contact url
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#contactObject
     */
    const T_CONTACT_URL = 'contactUrl';

    /**
     * Api License
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#licenseObject
     */
    const T_LICENSE = 'license';

    /**
     * External Documentation Object for a tag
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#externalDocumentationObject
     */
    const T_TAG_EXTERNAL_DOC = 'tagExternalDocs';

    /**
     * Tag for API documentation control
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#operation-object
     */
    const T_TAG = 'tag';

    /**
     * The host (name or IP) serving the API
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#fixed-fields
     */
    const T_HOST = 'host';

    /**
     * The transfer protocol of the API
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#fixed-fields
     */
    const T_SCHEME = 'scheme';

    /**
     * The HTTP method for the endpoint
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#pathItemObject
     */
    const T_METHOD = 'method';

    /**
     * Mime type that this API can produce
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#fixed-fields
     */
    const T_PRODUCES = 'produces';

    /**
     * Mime type that the endpoint can consume
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#fixed-fields
     */
    const T_CONSUMES = 'consumes';

    /**
     * Security requirement for the endpoint
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#securityRequirementObject
     */
    const T_SECURITY = 'security';

    /**
     * External Documentation Object for the API
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#externalDocumentationObject
     */
    const T_EXTERNAL_DOC = 'externalDocs';

    /**
     * An error returned by the endpoint
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#responseObject
     */
    const T_ERRORS = 'errors';

    /**
     * A valid response returned by the endpoint
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#responseObject
     */
    const T_RETURN = 'return';

    /**
     * An default response returned by the endpoint
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#responseObject
     */
    const T_DEFAULT = 'default';

    /**
     * Property type and description for a model
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#schemaObject
     */
    const T_VAR = 'var';

    /**
     * Enumeration of possible values for a property or parameter
     * @todo Merge in T_CONSTRAINT
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#parameter-object
     */
    const T_ENUM = 'enum';

    /**
     * Example of value
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#schema-object
     */
    const T_EXAMPLE = 'example';

    /**
     * Type format
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#format
     */
    const T_FORMAT = 'format';

    /**
     * Property or parameter required state
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#fixed-fields-7
     */
    const T_REQUIRED = 'required';

    /**
     * A relative path to an individual endpoint
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#pathItemObject
     */
    const T_PATH = 'path';

    /**
     * A constraint for a parameter
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#items-object
     */
    const T_CONSTRAINT = 'constraint';

    /**
     * A header emitted by the endpoint
     * @link https://github.com/swagger-api/swagger-spec/blob/master/versions/2.0.md#headers-object
     */
    const T_EMITS_HEADER = 'emitsHeader';

    /**
     * @var string The models namespace. Must be defined by the daughter class
     */
    public $modelsNamespace;

    /**
     * Swagger current version
     * @var string
     */
    public $swaggerVersion;

    /**
     * Swagger documentation action
     * @var string
     */
    public $swaggerDocumentationAction;

    /**
     * Swagger security definitions
     * @var array
     */
    public $securityDefinitions;

    /**
     * @var
     */
    public $controller;

    /**
     * @var array List of supported HTTP methods
     */
    public $supportedMethods;

    /**
     * Holds all definitions
     *
     * @var array
     */
    private $definitions = [];

    public function __construct($config)
    {
        if (!empty($config)) {
            Yii::configure($this, $config);
        }
    }

    /**
     * Checks if the given $type is an ApiModel
     *
     * @param $type
     * @return bool Returns TRUE if it's a model class, FALSE otherwise
     */
    private function isDefinedType($type)
    {
        return in_array($type, $this->definitions);
    }

    /**
     * @param ReflectionClass|ReflectionMethod $reflection
     * @param ApiController $parent
     * @return string
     */
    public function fetchDocComment($reflection, $parent = null)
    {
//        echo $reflection . "\n";
//        echo $parent . "\n";

        $found = false;


        while (!$found) {

            $doc = $reflection->getDocComment();

            if ($doc) {
                return $doc;
            } else {
                if ($reflection instanceof ReflectionMethod) {
                    $parentClass = $reflection->getDeclaringClass()->getParentClass();
					$reflection = $parentClass->getMethod($reflection->getName());
                } else {
                    $reflection = $reflection->getParentClass();
                }
            }
        }
    }

    /**
     * Generates the swagger configuration file
     *
     * This method will inspect the current API controller and generate the
     * configuration based on the doc blocks found.
     *
     * @param string $version The version number (the module id)
     * @return array The definition as an array
     * @throws Exception
     */
    public function getJson($version, $ret = [])
    {
		if(empty($ret)){
			$ret = [
				'swagger' => $this->swaggerVersion,
				'info' => [
					'contact' => [],
				],
				'host' => '',
				'basePath' => '/' . $version,
				'tags' => [],
				'schemes' => [],
				'paths' => [],
				'securityDefinitions' => (Object)$this->securityDefinitions,
				'definitions' => [],
			];
		}


        $class = new ReflectionClass($this->controller);

        $classDoc = $this->fetchDocComment($class, ApiController::class);
        //$classDoc = $class->getDocComment();
        $tags = self::parseDocCommentTags($classDoc);

		if (!empty($tags['basePath'])) {
			$ret['basePath'] = $tags['basePath'];
		}

        $this->definitions[] = 'ApiResponse';
        $ret['definitions']['ApiResponse'] = $this->parseModel('machour\\yii2\\swagger\\api\\ApiResponse', false);
        if (isset($tags[self::T_DEFINITION])) {
            $this->definitions = array_merge($this->definitions, $this->mixedToArray($tags[self::T_DEFINITION]));
            $ret['definitions'] = array_merge($ret['definitions'], $this->parseModels($this->mixedToArray($tags[self::T_DEFINITION])));
        }

        $ret['info']['description'] = self::parseDocCommentDetail($classDoc);

        if (isset($tags[self::T_VERSION])) {
            $ret['info']['version'] = $tags[self::T_VERSION];
        }

        $ret['info']['title'] = self::parseDocCommentSummary($classDoc);

        if (isset($tags[self::T_TOS])) {
            $ret['info']['termsOfService'] = $tags[self::T_TOS];
        }

        if (isset($tags[self::T_CONTACT_EMAIL])) {
            $ret['info']['contact']['email'] = $tags[self::T_CONTACT_EMAIL];
        }
        if (isset($tags[self::T_CONTACT_NAME])) {
            $ret['info']['contact']['name'] = $tags[self::T_CONTACT_NAME];
        }
        if (isset($tags[self::T_CONTACT_URL])) {
            $ret['info']['contact']['url'] = $tags[self::T_CONTACT_URL];
        }

        if (isset($tags[self::T_LICENSE])) {
            $license = $this->tokenize($tags[self::T_LICENSE], 2);
            $ret['info']['license'] = [
                'name' => $license[1],
                'url' => $license[0],
            ];
        }

        $ret['host'] = isset($tags[self::T_HOST]) ?
                            $tags[self::T_HOST] :
                            (isset($_SERVER['HTTP_HOST']) ?
                                $_SERVER['HTTP_HOST'] :
                                $_SERVER['SERVER_NAME']);

        if (isset($tags[self::T_SCHEME])) {
            $schemes = $this->mixedToArray($tags[self::T_SCHEME]);
            foreach ($schemes as $scheme) {
                $ret['schemes'][] = $scheme;
            }
        }

        $produces = [];
        if (isset($tags[self::T_PRODUCES])) {
            $produces = $this->mixedToArray($tags[self::T_PRODUCES]);
        }

        $tagsExternalDocs = [];
        if (isset($tags[self::T_TAG_EXTERNAL_DOC])) {
            $tagExternalDocs = $this->mixedToArray($tags[self::T_TAG_EXTERNAL_DOC]);
            foreach ($tagExternalDocs as $externalDocs) {
                list($tag, $url, $description) = $this->tokenize($externalDocs, 3);
                $tagsExternalDocs[$tag] = [
                    'description' => $description,
                    'url' => $url,
                ];
            }
        }

        if (isset($tags[self::T_TAG])) {
            foreach ($this->mixedToArray($tags[self::T_TAG]) as $tag) {
                $tagDef = array_combine(['name', 'description'], $this->tokenize($tag, 2));
                if (isset($tagsExternalDocs[$tagDef['name']])) {
                    $tagDef['externalDocs'] = $tagsExternalDocs[$tagDef['name']];
                }

                if (!in_array($tagDef, $ret['tags'], true)) {
                	$ret['tags'][] = $tagDef;
				}
            }
        }

		$ret['paths'] = array_merge($ret['paths'], $this->parseMethods($class, $produces));

        if (isset($tags[self::T_EXTERNAL_DOC])) {
            $externalDocs = $this->tokenize($tags[self::T_EXTERNAL_DOC], 2);
            $ret['externalDocs'] = [
                'description' => $externalDocs[1],
                'url' => $externalDocs[0],
            ];
        }

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

//        if (!is_subclass_of($model, ApiModel::class)) { // TODO FIXME
		if (!class_exists($model)) {
            throw new Exception("The model definition for $model was not found", 501);
		}
//        }

        $ret = [
            'type' => 'object',
        ];

        $class = new ReflectionClass($model);

        $properties =  $this->parseProperties($class);
        foreach ($properties as $name => &$property) {
            if (isset($property['required'])) {
                unset($property['required']);
                if (!isset($ret['required'])) {
                    $ret['required'] = [];
                }
                $ret['required'][] = $name;
            }
        }
        $ret['properties'] = $properties;

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

        $defaults = $class->getDefaultProperties();

//		if ($class->name === 'eo\models\database\RecreationObject') {
//			var_dump($class->getProperties());die();
//		}

		$commentPropertyTags	= self::parseDocComment($class->getDocComment());

		$propertyTags	= [];
        foreach ($class->getProperties() as $property) {
            $propertyTags[$property->name] = self::parseDocCommentTags($property->getDocComment());
		}

		foreach (ArrayHelper::merge($commentPropertyTags, $propertyTags) as $propertyname => $tags) {
			$p = [];
			// TODO VAR voorvullen bij parseDocComment
			if (!empty($tags[self::T_VAR]) && (!isset($tags['ignore']))) {
				list($type, $description) = $this->tokenize($tags[self::T_VAR], 2);
				if (strpos($type, '[]') > 0) {
					$type = str_replace('[]', '', $type);
					$p['type'] = 'array';
					$p['xml'] = ['name' => preg_replace('!s$!', '', $propertyname), 'wrapped' => true];
					if ($this->isDefinedType($type)) {
						$p['items'] = ['$ref' => $this->getDefinition($type)];
					} else {
						$p['items'] = ['type' => $type];
					}
				} elseif ($this->isDefinedType($type)) {
					$p['$ref'] = $this->getDefinition($type);
				} else {
					$p['type'] = $type;
					$enums = isset($tags[self::T_ENUM]) ? $this->mixedToArray($tags[self::T_ENUM]) : [];
					foreach ($enums as $enum) {
						$p['enum'] = $this->tokenize($enum);
					}
				}

				if (isset($tags[self::T_FORMAT])) {
					$p['format'] = $tags[self::T_FORMAT];
				}

				if (isset($tags[self::T_EXAMPLE])) {
					$p['example'] = $tags[self::T_EXAMPLE];
				}

				if (isset($tags[self::T_REQUIRED])) {
					$p['required'] = true;
				}

				if (!empty($description)) {
					$p['description'] = $description;
				}

				if (isset($defaults[$propertyname]) && !is_null($defaults[$propertyname])) { // TODO isset toegevoegd, ok?
					$p['default'] = $defaults[$propertyname];
				}

				$name	= $propertyname;
				if ($propertyname[0] === '_') {
					$propertyname = substr($propertyname, 1);
				}
				$ret[$name] = $p;
			}
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
    public function parseMethods($class, $produces)
    {
        $ret = [];
		if($class->getName() != '\machour\yii2\swagger\api\ApiGenerator'){
			foreach ($class->getMethods() as $method) {

				$def = $this->parseMethod($method, $produces);

				if ($def) {
					$methodDoc = $this->fetchDocComment($method);
					$tags = self::parseDocCommentTags($methodDoc);

					if (!isset($tags[self::T_PATH])) {
						continue;
					}

					if (!isset($ret[$tags[self::T_PATH]])) {
						$ret[$tags[self::T_PATH]] = [];
					}
					$ret[$tags[self::T_PATH]][$tags[self::T_METHOD]] = $def;
				}
			}
		}
		return $ret;
    }

    /**
     * @param ReflectionMethod $method
     * @param bool $produces
     * @return array
     * @throws Exception
     */
    public function parseMethod($method, $produces = false)
    {
        $def = [];

		if ($method->name == 'indexDataProvider') return null; // TODO FIXME TODO
        $methodDoc = $this->fetchDocComment($method);

        if ($this->controller !== $method->class && !is_subclass_of($this->controller, $method->class)) {
            return false;
        }

        $tags = self::parseDocCommentTags($methodDoc);

        if (!isset($tags[self::T_PATH])) {
            return false;
        }

        if (!in_array($tags[self::T_METHOD], $this->supportedMethods)) {
            throw new Exception("Unknown HTTP method specified in {$method->name} : {$tags[self::T_METHOD]}", 501);
        }

        $enums = isset($tags[self::T_ENUM]) ? $this->mixedToArray($tags[self::T_ENUM]) : [];
        $availableEnums = [];
        foreach ($enums as $enum) {
            $data = $this->tokenize($enum);
            $availableEnums[str_replace('$', '', array_shift($data))] = $data;
        }

        if (isset($tags[self::T_TAG])) {
            $def['tags'] = $this->mixedToArray($tags[self::T_TAG]);
        }

        $def['summary'] = self::parseDocCommentSummary($methodDoc);
        $def['description'] = self::parseDocCommentDetail($methodDoc);

        $def['operationId'] = $this->getOperationId($method);

        if (isset($tags[self::T_CONSUMES])) {
            $def['consumes'] = $this->mixedToArray($tags[self::T_CONSUMES]);
        }

        if (isset($tags[self::T_PRODUCES])) {
            $def['produces'] = $this->mixedToArray($tags[self::T_PRODUCES]);
        } elseif (!empty($produces)) {
            $def['produces'] = $produces;
        }

        $def['parameters'] = $this->parseParameters($method, $tags, $availableEnums);

        $def['responses'] = [];

        if (isset($tags[self::T_DEFAULT])) {
            $def['responses']['default'] = [
                'description' => $tags[self::T_DEFAULT]
            ];
        }

        if (isset($tags[self::T_RETURN])) {
        	if (is_array($tags[self::T_RETURN])) {
        		throw new ServerErrorHttpException('Swagger; '.$method->name.' kan maximaal 1 return hebben');
			}
            list($type, $description) = $this->tokenize($tags[self::T_RETURN], 2);
            $def['responses'][200] = [];
            if (!empty($description)) {
                $def['responses'][200]['description'] = $description;
            }

            $schema = [];
            if (strpos($type, '[]') > 0) {
                $schema['type'] = 'array';
                $type = str_replace('[]', '', $type);
                if ($this->isDefinedType($type)) {
                    $schema['items'] = ['$ref' => $this->getDefinition($type)];
                }
            } elseif ($this->isDefinedType($type)) {
                $schema = ['$ref' => $this->getDefinition($type)];
            } elseif (preg_match('!^Map\((.*)\)$!', $type, $matches)) {
                // Swaggers Map Primitive
                $schema['type'] = 'object';
                list($type, $format) = $this->getTypeAndFormat($matches[1]);
                $schema['additionalProperties'] = ['type' => $type];
                if (!is_null($format)) {
                    $schema['additionalProperties']['format'] = $format;
                }
            } else {
                $schema['type'] = $type;
            }
            $def['responses'][200]['schema'] = $schema;
            if (isset($tags[self::T_EMITS_HEADER])) {
                $def['responses'][200]['headers'] = [];
                $headers = $this->mixedToArray($tags[self::T_EMITS_HEADER]);
                foreach ($headers as $header) {
                    list($type, $name, $description) = $this->tokenize($header, 3);
                    list($type, $format) = $this->getTypeAndFormat($type);
                    $def['responses'][200]['headers'][$name] = [
                        'type' => $type,
                        'format' => $format,
                        'description' => $description
                    ];
                }
            }
        }

        if (isset($tags[self::T_ERRORS])) {
            $errors = $this->mixedToArray($tags[self::T_ERRORS]);
            foreach ($errors as $error) {
                if (preg_match('!(\d+)\s+(.*)$!', $error, $matches)) {
                    $def['responses'][$matches[1]] = ['description' => $matches[2]];
                }
            }
        }

        if (isset($tags[self::T_SECURITY])) {
            $security = [];
            $secs = $this->mixedToArray($tags[self::T_SECURITY]);
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

        return $def;
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
    public function parseParameters($method, $tags, $availableEnums)
    {
        $ret = [];
        $constraints = [];
        if (isset($tags[self::T_CONSTRAINT])) {
            foreach ($this->mixedToArray($tags[self::T_CONSTRAINT]) as $constraint) {
                list($type, $parameter, $value) = $this->tokenize($constraint, 3);
                $parameter = ltrim($parameter, '$');
                if (!isset($constraints[$parameter])) {
                    $constraints[$parameter] = [];
                }
                $constraints[$parameter][$type] = $value;
            }
        }
        foreach (['parameter' => true, 'optparameter' => false] as $tag => $required) {
            if (isset($tags[$tag])) {
                $parameters = $this->mixedToArray($tags[$tag]);
                foreach ($parameters as $parameter) {
                    list($type, $name, $description) = $this->tokenize($parameter, 3);
                    $name = ltrim($name, '$');
                    $p = [];
                    $p['name'] = $name;

                    $http_method = $tags[self::T_METHOD];

                    $consumes = isset($tags[self::T_CONSUMES]) ? $tags[self::T_CONSUMES] : '';
                    switch ($consumes) {
                        case 'multipart/form-data':
                            $p['in'] = $this->haveParameter($method, $name) ? 'path' : 'formData';
                            break;

                        case 'application/x-www-form-urlencoded':
                            $p['in'] = $this->haveParameter($method, $name) ? 'path' : 'formData';
                            break;

                        default:
                            if (in_array($http_method, ['put', 'post'])) {
                                if ($this->isPathParameter($name, $tags[self::T_PATH])) {
                                    $p['in'] = 'path';
                                } else {
                                    $p['in'] = 'body';
                                }
                            } else {
                                if ($this->isPathParameter($name, $tags[self::T_PATH])) {
                                    $p['in'] = 'path';
                                } else {
                                    $p['in'] = $this->haveParameter($method, $name) ? 'query' : 'header';
                                }
                            }
                            break;
                    }
                    if (isset($constraints[$name])) {
                        foreach ($constraints[$name] as $constraint => $value) {
                            $p[$constraint] = $value;
                        }
                    }
                    if (!empty($description)) {
                        $p['description'] = $description;
                    }
                    $p['required'] = $required;
                    if (strpos($type, '[]') > 0) {
                        $type = str_replace('[]', '', $type);
                        if ($this->isDefinedType($type)) {
                            $p['schema'] = [
                                'type' => 'array',
                                'items' => [
                                    '$ref' => $this->getDefinition($type),
                                ]
                            ];
                        } else {
                            $p['type'] = 'array';
                            $p['items'] = ['type' => $type];
                            if (isset($availableEnums[$name])) {
                                $p['items']['enum'] = $availableEnums[$name];
                                $p['items']['default'] = count($availableEnums[$name]) ? $availableEnums[$name][0] : '';
                            }
                            $p['collectionFormat'] = 'csv';
                        }
                    } elseif ($this->isDefinedType($type)) {
                        $p['schema'] = ['$ref' => $this->getDefinition($type)];
                    } else {
                        list($type, $format) = $this->getTypeAndFormat($type);
                        $p['type'] = $type;
                        if ($format !== null) {
                            $p['format'] = $format;
                        }
                        if (isset($availableEnums[$name])) {
                            $p['enum'] = $availableEnums[$name];
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
            case 'int64':
            case 'int32': return ['integer', $type];
            case 'date-time': return ['string', $type];
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
     * Returns the first line of doc block.
     *
     * @param string $block
     * @return string
     */
    public static function parseDocCommentSummary($block)
    {
        $docLines = preg_split('~\R~u', $block);
        if (isset($docLines[1])) {
            return trim($docLines[1], "\t *");
        }
        return '';
    }

    /**
     * Returns full description from the doc block.
     *
     * @param string $block
     * @return string
     */
    public static function parseDocCommentDetail($block)
    {
        $comment = strtr(trim(preg_replace('/^\s*\**( |\t)?/m', '', trim($block, '/'))), "\r", '');
        if (preg_match('/^\s*@\w+/m', $comment, $matches, PREG_OFFSET_CAPTURE)) {
            $comment = trim(substr($comment, 0, $matches[0][1]));
        }

        if (empty($comment)) {
            return '';
        }

        $summary = self::parseDocCommentSummary($block);
        $pos = strpos($comment, $summary);
        if ($pos !== false) {
            $comment = trim(substr_replace($comment, '', $pos, strlen($summary)));
        }

        return $comment;
    }

    /**
     * Parses the comment block into tags.
     *
     * @param string $comment the comment block
     * @return array the parsed tags
     */
    public static function parseDocCommentTags($comment)
    {
        $comment = "@description \n" . strtr(trim(preg_replace('/^\s*\**( |\t)?/m', '', trim($comment, '/'))), "\r", '');
        $parts = preg_split('/^\s*@/m', $comment, -1, PREG_SPLIT_NO_EMPTY);
        $tags = [];
        foreach ($parts as $part) {
            if (preg_match('/^(\w+)(.*)/ms', trim($part), $matches)) {
                $name = $matches[1];
                if (!isset($tags[$name])) {
                    $tags[$name] = trim($matches[2]);
                } elseif (is_array($tags[$name])) {
                    $tags[$name][] = trim($matches[2]);
                } else {
                    $tags[$name] = [$tags[$name], trim($matches[2])];
                }
            }
        }
        return $tags;
    }

    /**
     * Parses the comment block.
     *
     * @param string $comment the comment block
     * @return array the parsed tags
     */
    public static function parseDocComment($comment)
    {
		$allowedProps	= ['type', 'xml', 'type', 'format', 'example', 'required', 'description', 'default', 'ignore'];
        $comment = "@description \n" . strtr(trim(preg_replace('/^\s*\**( |\t)?/m', '', trim($comment, '/'))), "\r", '');
        $parts = preg_split('/^\s*@/m', $comment, -1, PREG_SPLIT_NO_EMPTY);
        $tags = [];
        foreach ($parts as $part) {
            if (preg_match('/^(\w+)(.*)/m', trim($part), $matches)) {
                $name = $matches[1];
				$tags[] = [$name => trim($matches[2])];
            }
        }

        $output	= [];
        foreach ($tags as $index => $tag) {
        	if (isset($tag['property'])) {
        		$properties = [];
				$propIndex = $index;
        		while($propIndex > 0 && !empty($tags[$propIndex--])) {
        			$keys = array_keys($tags[$propIndex]);
        			if (!empty($keys) && in_array($keys[0], $allowedProps, true)) {
						$properties	= $tags[$propIndex];
					} else {
						break;
					}
				}
				$properties[self::T_VAR] = $tag['property'];

				list ($type, $name) = explode(' ', $tag['property']);
				$output[substr($name, 1)] = $properties;
			}
		}

        return $output;
    }

}
