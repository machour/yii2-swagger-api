<?php
$params = array_merge(
    require(__DIR__ . '/../../common/config/params.php'),
    require(__DIR__ . '/../../common/config/params-local.php'),
    require(__DIR__ . '/params.php'),
    require(__DIR__ . '/params-local.php')
);

return [
    'id' => 'app-api',
    'name' => 'Fly Car',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'api\versions',
    'defaultRoute' => 'api',
    'bootstrap' => [
        'log',
    ],
    'modules' => [
        'v1' => [
            'class' => 'api\versions\v1\Api',
        ],
        'v2' => [
            'class' => 'api\versions\v2\Api',
        ],
    ],
    'components' => [
        'user' => [
            'identityClass' => 'common\models\Employee',
            'enableAutoLogin' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName'  => false,
            'rules' => [
                '' => 'v2/api/index',
                'v2/<path>/<action>' => 'v2/api/call',
                'v2/swagger.json' => 'v2/api/documentation',
            ],
        ],
        'i18n' => [
            'translations' => [
                'api' => [
                    'class' => 'yii\i18n\PhpMessageSource',
                    'basePath' => '@api/messages',
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'api/error',
        ],
    ],
    'params' => $params,
];
