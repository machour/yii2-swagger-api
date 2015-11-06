<?php

namespace api\models;

use machour\yii2\swagger\api\ApiModel;

/**
 * Class Pet
 *
 * @package api\models
 */
class Pet extends ApiModel
{
    /**
     * @var integer
     * @format int64
     */
    public $id;

    /**
     * @var Category
     */
    public $category;

    /**
     * @var string
     * @example doggie
     * @required
     */
    public $name;

    /**
     * @var string[]
     * @required
     */
    public $photoUrls;

    /**
     * @var Tag[]
     */
    public $tags;

    /**
     * @var string pet status in the store
     * @enum available pending sold
     */
    public $status;
}