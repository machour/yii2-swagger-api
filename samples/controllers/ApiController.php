<?php

namespace api\controllers;

use machour\yii2\swagger\api\ApiController as BaseApiController;
use api\models\Category;
use api\models\Order;
use api\models\Pet;
use api\models\User;
use machour\yii2\swagger\api\ApiException;
use Yii;

/**
 * Swagger Petstore
 *
 * This is a sample server Petstore server.  You can find out more about Swagger at [http://swagger.io](http://swagger.io) or on [irc.freenode.net, #swagger](http://swagger.io/irc/).  For this sample, you can use the api key `special-key` to test the authorization filters.
 *
 * @basePath /v2
 * @host api.fly-car.local
 * @scheme http
 *
 * @termsOfService http://swagger.io/terms/
 * @license http://www.apache.org/licenses/LICENSE-2.0.html Apache 2.0
 * @externalDocs http://swagger.io Find out more about Swagger
 *
 * @tag pet Everything about your Pets
 * @tagExternalDocs pet http://swagger.io Find out more
 * @tag store Access to Petstore orders
 * @tag user Operations about user
 * @tagExternalDocs user http://swagger.io Find out more about our store
 *
 * @definition User
 * @definition Order
 * @definition Category
 * @definition Tag
 * @definition Pet
 *
 * @produces application/xml
 * @produces application/json
 *
 * @contactEmail apiteam@swagger.io
 * @version 1.0.0
 */
class ApiController extends BaseApiController
{

    /**
     * @var string The models namespace
     */
    public $modelsNamespace = '\api\models';

    /**
     * @inheritdoc
     */
    public $apiDocsCacheEnabled = false;

    /**
     * Gets the security definitions for the API
     *
     * @return array
     */
    function getSecurityDefinitions()
    {
        return [
            'petstore_auth' => [
                'type' => 'oauth2',
                'authorizationUrl' => 'http://petstore.swagger.io/api/oauth/dialog',
                'flow' => 'implicit',
                'scopes' => [
                    'write:pets' => 'modify pets in your account',
                    'read:pets' => 'read your pets',
                ],
            ],
            'api_key' => [
                'type' => 'apiKey',
                'name' => 'api_key',
                'in' => 'header',
            ]
        ];
    }

    /**
     * Add a new pet to the store
     *
     * @path /pet
     * @method post
     * @tag pet
     * @consumes application/json
     * @consumes application/xml
     * @parameter Pet $body Pet object that needs to be added to the store
     * @throws ApiException
     * @errors 405 Invalid input
     * @enum $status available pending sold
     * @security petstore_auth write:pets
     * @security petstore_auth read:pets
     */
    public function actionAddPet()
    {
        $body = file_get_contents('php://input');
        if (!$body) {
            throw new ApiException('Invalid input', 405);
        }
    }


    /**
     * This is a test for mehdi
     *
     * @path /mehdi
     * @method get
     * @tag misc
     * @parameter string $toto Description of toto
     * @errors 404 Mehdi not found
     */
    public function actionMehdi($toto)
    {
        return "foo";
    }

    /**
     * Update an existing pet
     *
     * @path /pet
     * @method put
     * @tag pet
     * @consumes application/json
     * @consumes application/xml
     * @parameter Pet $body Pet object that needs to be added to the store
     * @errors 400 Invalid ID supplied
     * @errors 404 Pet not found
     * @errors 405 Validation exception
     * @throws ApiException
     * @security petstore_auth write:pets
     * @security petstore_auth read:pets
     */
    public function actionUpdatePet()
    {
        $body = Yii::$app->request->rawBody;

        if (!$body) {
            throw new ApiException('Invalid ID supplied', 400);
        }
    }

    /**
     * Finds Pets by status
     *
     * Multiple status values can be provided with comma seperated strings
     *
     * @path /pet/findByStatus
     * @method get
     * @tag pet
     * @parameter string[] $status Status values that need to be considered for filter
     * @param string[] $status
     * @return Pet[] successful operation
     * @throws ApiException
     * @enum $status available pending sold
     * @errors 400 Invalid status value
     * @security petstore_auth write:pets
     * @security petstore_auth read:pets
     */
    public function actionFindPetsByStatus($status)
    {
        if (!$status) {
            throw new ApiException('Invalid status value', 400);
        }

        return [new Pet(['id' => 9, 'category' => new Category(['id' => 3, 'name' => 'Cat']), 'name' => 'Pussycat'])];
    }

    /**
     * Finds Pets by tags
     *
     * Muliple tags can be provided with comma seperated strings. Use tag1, tag2, tag3 for testing.
     *
     * @path /pet/findByTags
     * @method get
     * @tag pet
     * @parameter string[] $tags Tags to filter by
     * @param string[] $tags
     * @return Pet[] successful operation
     * @throws ApiException
     * @errors 400 Invalid tag value
     * @security petstore_auth write:pets
     * @security petstore_auth read:pets
     */
    public function actionFindPetsByTags($tags)
    {
        if (!$tags) {
            throw new ApiException('Invalid tag value', 400);
        }

        return [new Pet(['id' => 9, 'category' => new Category(['id' => 3, 'name' => 'Cat']), 'name' => 'Pussycat'])];
    }

    /**
     * Find pet by ID
     *
     * Returns a single pet
     *
     * @path /pet/{petId}
     * @tag pet
     * @method get
     * @param integer $petId
     * @parameter int64 $petId ID of pet to return
     * @return Pet successful operation
     * @errors 400 Invalid ID supplied
     * @errors 404 Pet not found
     * @security api_key
     */
    public function actionGetPetById($petId)
    {
        return new Pet(['id' => $petId, 'category' => new Category(['id' => 3, 'name' => 'Cat']), 'name' => 'Pussycat']);
    }

    /**
     * Updates a pet in the store with form data
     *
     * @path /pet/{petId}
     * @tag pet
     * @method post
     * @param integer $petId
     * @consumes application/x-www-form-urlencoded
     * @parameter int64 $petId ID of pet that needs to be updated
     * @optparameter string $name Updated name of the pet
     * @optparameter string $status Updated status of the pet
     * @errors 405 Invalid input
     * @security petstore_auth write:pets
     * @security petstore_auth read:pets
     */
    public function actionUpdatePetWithForm($petId)
    {
    }

    /**
     * Deletes a pet
     *
     * @path /pet/{petId}
     * @tag pet
     * @method delete
     * @param integer $petId
     * @parameter int64 $petId Pet id to delete
     * @optparameter string $api_key
     * @errors 400 Invalid pet value
     * @security petstore_auth write:pets
     * @security petstore_auth read:pets
     */
    public function actionDeletePet($petId)
    {
    }

    /**
     * uploads an image
     *
     * @path /pet/{petId}/uploadImage
     * @tag pet
     * @method post
     * @param integer $petId
     * @consumes multipart/form-data
     * @produces application/json
     * @parameter int64 $petId ID of pet to update
     * @optparameter string $additionalMetadata Additional data to pass to server
     * @optparameter file $file file to upload
     * @security petstore_auth write:pets
     * @security petstore_auth read:pets
     * @return ApiResponse successful operation
     */
    public function actionUploadFile($petId)
    {
        return [];
    }

    /**
     * Returns pet inventories by status
     *
     * Returns a map of status codes to quantities
     *
     * @path /store/inventory
     * @tag store
     * @method get
     *
     * @produces application/json
     *
     * @return Map(int32) successful operation
     *
     * @security api_key
     */
    public function actionGetInventory()
    {
        return ['key' => 3, 'otherkey' => 1];
    }

    /**
     * Place an order for a pet
     *
     * @path /store/order
     * @tag store
     * @method post
     *
     * @parameter Order $body order placed for purchasing the pet
     *
     * @return Order successful operation
     * @errors 400 Invalid Order
     */
    public function actionPlaceOrder()
    {
        return new Order(['id' => 666]);
    }

    /**
     * Find purchase order by ID
     *
     * For valid response try integer IDs with value <= 5 or > 10. Other values will generated exceptions
     *
     * @path /store/order/{orderId}
     * @method get
     * @tag store
     *
     * @parameter int64 $orderId ID of pet that needs to be fetched
     * @constraint minimum $orderId 1.0
     * @constraint maximum $orderId 5.0
     * @param int $orderId
     * @return Order successful operation
     * @errors 400 Invalid ID supplied
     * @errors 404 Order not found
     * @throws ApiException
     */
    public function actionGetOrderById($orderId)
    {
        if ($orderId > 5 and $orderId <= 10) {
            throw new ApiException("Order not found", 404);
        }
        return new Order(['id' => $orderId]);
    }

    /**
     * Delete purchase order by ID
     *
     * For valid response try integer IDs with value < 1000. Anything above 1000 or nonintegers will generate API errors
     *
     * @path /store/order/{orderId}
     * @method delete
     * @tag store
     *
     * @parameter string $orderId ID of the order that needs to be deleted
     * @param string $orderId
     * @constraint minimum $orderId 1.0
     * @errors 400 Invalid ID supplied
     * @errors 404 Order not found
     * @throws ApiException
     */
    public function actionDeleteOrder($orderId)
    {
        if ($orderId >= 1000) {
            throw new ApiException("Invalid ID supplied", 400);
        }
    }

    /**
     * Create user
     *
     * This can only be done by the logged in user.
     *
     * @path /user
     * @method post
     * @tag user
     *
     * @parameter User $body Created user object
     * @default successful operation
     */
    public function actionCreateUser()
    {
        return;
    }

    /**
     * Creates list of users with given input array
     *
     * @path /user/createWithArray
     * @method post
     * @tag user
     *
     * @parameter User[] $body List of user object
     * @default successful operation
     */
    public function actionCreateUsersWithArrayInput()
    {
        return;
    }

    /**
     * Creates list of users with given input array
     *
     * @path /user/createWithList
     * @method post
     * @tag user
     *
     * @parameter User[] $body List of user object
     * @default successful operation
     */
    public function actionCreateUsersWithListInput()
    {
        return;
    }

    /**
     * Logs user into the system
     *
     * @path /user/login
     * @method get
     * @tag user
     *
     * @parameter string $username The user name for login
     * @parameter string $password The password for login in clear text
     * @param $username
     * @param $password
     * @return string successful operation
     * @emitsHeader int32 X-Rate-Limit calls per hour allowed by the user
     * @emitsHeader date-time X-Expires-After date in UTC when toekn expires
     * @errors 400 Invalid username/password supplied
     */
    public function actionLoginUser($username, $password)
    {
        return 'foo';
    }

    /**
     * Logs out current logged in user session
     *
     * @path /user/logout
     * @method get
     * @tag user
     *
     * @default successful operation
     */
    public function actionLogoutUser()
    {
        return 'foo';
    }

    /**
     * Get user by user name
     *
     * @path /user/{username}
     * @method get
     * @tag user
     *
     * @parameter string $username The name that needs to be fetched. Use user1 for testing.
     * @errors 400 Invalid username supplied
     * @errors 404 User not found
     * @return User successful operation
     */
    public function actionGetUserByName()
    {
        return new User(['id' => 300]);
    }

    /**
     * Updated user
     *
     * This can only be done by the logged in user.
     *
     * @path /user/{username}
     * @method put
     * @tag user
     *
     * @parameter string $username name that need to be deleted
     * @parameter User $body Updated user object
     * @errors 400 Invalid user supplied
     * @errors 404 User not found
     */
    public function actionUpdateUser()
    {

    }

    /**
     * Delete user
     *
     * This can only be done by the logged in user.
     *
     * @path /user/{username}
     * @method delete
     * @tag user
     *
     * @parameter string $username The name that needs to be deleted
     * @errors 400 Invalid username supplied
     * @errors 404 User not found
     */
    public function actionDeleteUser()
    {

    }

}

