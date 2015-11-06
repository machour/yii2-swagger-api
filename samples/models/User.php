<?php

namespace api\models;

use machour\yii2\swagger\api\ApiModel;

/**
 * Class User
 *
 * @package api\models
 */
class User extends ApiModel
{
    /**
     * @var integer
     * @format int64
     */
    public $id;

    /**
     * @var string
     */
    public $username;

    /**
     * @var string
     */
    public $firstName;

    /**
     * @var string
     */
    public $lastName;

    /**
     * @var string
     */
    public $email;

    /**
     * @var string
     */
    public $password;

    /**
     * @var string
     */
    public $phone;

    /**
     * @var integer User Status
     * @format int32
     */
    public $userStatus;

}