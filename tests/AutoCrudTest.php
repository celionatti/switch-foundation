<?php

declare(strict_types=1);

namespace Switch\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Database\Connection\Connection;
use Switch\Database\Connection\ConnectionConfig;
use Switch\Database\Connection\ConnectionManager;
use Switch\Database\ORM\Model;
use Switch\Database\Schema\Blueprint;
use Switch\Database\Schema\SchemaBuilder;
use Switch\Foundation\Api\AutoCrud\AutoCrudController;
use Switch\Foundation\Api\AutoCrud\QueryFilter;
use Switch\Http\ServerRequest;
use Switch\Http\Uri;
use Switch\Router\Router;

// Sample Product Model for Auto-CRUD test
class TestProduct extends Model
{
    protected string $table = 'products';
    protected array $fillable = ['title', 'description', 'price', 'category', 'stock'];
    public array $searchable = ['title', 'description'];
}

class AutoCrudTest extends TestCase
{
    protected Connection $connection;

    protected function setUp(): void
    {
        $config = ConnectionConfig::fromArray([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $manager = ConnectionManager::getInstance();
        $manager->addConnection('default', $config);
        $manager->setDefaultConnection('default');
        $this->connection = $manager->connection('default');
        Model::setConnection($this->connection);

        // Create products table
        $schema = new SchemaBuilder($this->connection);
        $schema->dropIfExists('products');
        $schema->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->float('price');
            $table->string('category')->default('general');
            $table->integer('stock')->default(0);
            $table->timestamps();
        });

        // Seed initial products
        TestProduct::create(['title' => 'MacBook Pro', 'description' => 'Apple M3 chip', 'price' => 1999.00, 'category' => 'laptops', 'stock' => 10]);
        TestProduct::create(['title' => 'Dell XPS 15', 'description' => 'Intel Core i9', 'price' => 1499.00, 'category' => 'laptops', 'stock' => 5]);
        TestProduct::create(['title' => 'iPhone 15 Pro', 'description' => 'Titanium design', 'price' => 999.00, 'category' => 'phones', 'stock' => 20]);
    }

    public function testQueryFilterAppliesFiltersAndPagination(): void
    {
        $request = new ServerRequest(
            method: 'GET',
            uri: new Uri('http://localhost/api/products?filter[category]=laptops&filter[price][gte]=1500&sort=-price&page=1&per_page=10')
        );

        $filter = QueryFilter::for(TestProduct::query(), $request);
        $result = $filter->paginate();

        $this->assertCount(1, $result['data']);
        $this->assertEquals('MacBook Pro', $result['data'][0]['title']);
        $this->assertEquals(1, $result['meta']['total']);
    }

    public function testAutoCrudControllerIndexAndShow(): void
    {
        $controller = new AutoCrudController(TestProduct::class);

        // Index
        $request = new ServerRequest(method: 'GET', uri: new Uri('http://localhost/api/products?sort=price'));
        $response = $controller->index($request);
        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertCount(3, $body['data']);
        $this->assertEquals('iPhone 15 Pro', $body['data'][0]['title']); // lowest price first

        // Show
        $showResponse = $controller->show($request, ['id' => 1]);
        $this->assertEquals(200, $showResponse->getStatusCode());
        $showBody = json_decode((string) $showResponse->getBody(), true);
        $this->assertEquals('MacBook Pro', $showBody['data']['title']);
    }

    public function testAutoCrudControllerStoreUpdateDestroy(): void
    {
        $controller = new AutoCrudController(TestProduct::class, [
            'rules' => ['title' => 'required', 'price' => 'required|numeric'],
        ]);

        // Store
        $storeReq = (new ServerRequest(
            method: 'POST',
            uri: new Uri('http://localhost/api/products')
        ))->withParsedBody(['title' => 'AirPods Max', 'price' => 549.00, 'category' => 'audio']);

        $storeRes = $controller->store($storeReq);
        $this->assertEquals(201, $storeRes->getStatusCode());
        $storeBody = json_decode((string) $storeRes->getBody(), true);
        $newId = $storeBody['data']['id'];

        // Update
        $updateReq = (new ServerRequest(
            method: 'PUT',
            uri: new Uri("http://localhost/api/products/{$newId}")
        ))->withParsedBody(['price' => 499.00]);

        $updateRes = $controller->update($updateReq, ['id' => $newId]);
        $this->assertEquals(200, $updateRes->getStatusCode());
        $updateBody = json_decode((string) $updateRes->getBody(), true);
        $this->assertEquals(499.00, $updateBody['data']['price']);

        // Destroy
        $destroyRes = $controller->destroy(new ServerRequest('DELETE', new Uri("http://localhost/api/products/{$newId}")), ['id' => $newId]);
        $this->assertEquals(200, $destroyRes->getStatusCode());
        $this->assertNull(TestProduct::find($newId));
    }

    public function testRouterApiResourceRegistersEndpoints(): void
    {
        $router = new Router();
        $router->apiResource('api/products', TestProduct::class);

        $indexMatch = $router->match('GET', '/api/products');
        $this->assertNotNull($indexMatch);
        $this->assertEquals('api.products.index', $indexMatch->getRoute()->getName());

        $showMatch = $router->match('GET', '/api/products/123');
        $this->assertEquals('123', $showMatch->getParameters()['id']);

        $deleteMatch = $router->match('DELETE', '/api/products/123');
        $this->assertEquals('api.products.destroy', $deleteMatch->getRoute()->getName());
    }
}
