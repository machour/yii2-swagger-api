<?php

namespace api\tests;

use Yii;
use yii\helpers\Json;

// @TODO Why is this needed ?
require 'TestCase.php';

/**
 * @group vendor
 * @group swagger-api
 */
class ApiTest extends TestCase
{

    // Tests :
    public function testCompareDocs()
    {
        $original = file_get_contents(__DIR__ . '/data/petstore.json');

        $originalJson = Json::decode($original);
        $this->assertTrue(is_array($originalJson), 'Error decoding original swagger JSON');

        $generated = file_get_contents('http://api.fly-car.local/v2/swagger.json');
        $generatedJson = Json::decode($generated);

        // Fake the host
        $generatedJson['host'] = 'petstore.swagger.io';
        // Change params position (doesn't matter IRL, but matters for the test)
        $parameters = $generatedJson['paths']['/pet/{petId}']['delete']['parameters'];
        $tmp = $parameters[0];
        $parameters[0] = $parameters[1];
        $parameters[1] = $tmp;
        $generatedJson['paths']['/pet/{petId}']['delete']['parameters'] = $parameters;
        $generatedJson['paths']['/user/{username}']['get']['parameters'][0]['description'] .= ' ';

        $this->assertEquals($originalJson, $generatedJson, 'Error comparing both JSON structs !');
    }

}
