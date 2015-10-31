<?php

namespace machour\yii2\swagger\api;

/**
 * Class ApiResponse
 */
class ApiResponse extends ApiModel
{
    /**
     * @var integer
     * @format int32
     */
    public $code;

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $message;
}