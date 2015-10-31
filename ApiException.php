<?php

namespace machour\yii2\swagger\api;

use yii\base\Exception;

/**
 * This class represents an Api exception, thrown from the Api controller
 */
class ApiException extends Exception
{
    /**
     * @var int Default error code
     */
    public $code = 501;

    /**
     * @var string Default error message
     */
    public $message = 'Api Error';

    /**
     * Transforms a non ApiException to an ApiException
     *
     * @param Exception $e The exception to be transformed
     * @return ApiException
     */
    public static function fromException($e)
    {
        if (is_subclass_of($e, self::class)) {
            return $e;
        }
        return new self([
            'code' => $e->code,
            'message' => $e->message
        ]);
    }
}