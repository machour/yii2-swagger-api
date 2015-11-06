<?php

namespace api\models;

use machour\yii2\swagger\api\ApiModel;

/**
 * Class Tag
 *
 * @package api\models
 */
class Tag extends ApiModel
{
    /**
     * @var integer
     * @format int64
     */
    public $id;
    /**
     * @var string
     */
    public $name;
}