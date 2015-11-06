<?php

namespace api\versions\v2\controllers;

class ApiController extends \api\controllers\ApiController
{

    /**
     * @var string The models namespace
     */
    public $modelsNamespace = '\api\versions\v2\models';

    public function actionMehdi($toto)
    {
        return "ok";
    }


}