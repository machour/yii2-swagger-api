<?php

namespace api\models;

use machour\yii2\swagger\api\ApiModel;

/**
 * Class Order
 *
 * @package api\models
 */
class Order extends ApiModel
{
    /**
     * @var integer
     * @format int64
     */
    public $id;

    /**
     * @var integer
     * @format int64
     */
    public $petId;

    /**
     * @var integer
     * @format int32
     */
    public $quantity;

    /**
     * @var string
     * @format date-time
     */
    public $shipDate;

    /**
     * @var string Order Status
     * @enum placed approved delivered
     */
    public $status;

    /**
     * @var boolean
     */
    public $complete = false;
}