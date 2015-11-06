<?php

use machour\yii2\swagger\ui\SwaggerUiAsset;

/* @var $this \yii\web\View */
/* @var $content string */

SwaggerUiAsset::register($this);

$this->beginPage();

?><!DOCTYPE html>
<html>
  <head>
    <title><?= Yii::$app->name ?></title>
    <link href="//fonts.googleapis.com/css?family=Droid+Sans:400,700" rel="stylesheet" type="text/css" />
    <?php $this->head() ?>

    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
  </head>
  <body class="swagger-section">
    <?php $this->beginBody() ?>
    <?= $content ?>
    <?php $this->endBody() ?>
  </body>
</html>
<?php $this->endPage() ?>
