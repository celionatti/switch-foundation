<?php

declare(strict_types=1);

namespace Switch\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Foundation\Data\DataManager;
use Switch\Foundation\Data\Facade\Data as DataFacade;
use Switch\Foundation\Data\MockGenerator;

class DataTest extends TestCase
{
    private string $tempDataDir;

    protected function setUp(): void
    {
        DataFacade::clear();
        $this->tempDataDir = sys_get_temp_dir() . '/switch_data_test_' . uniqid();
        @mkdir($this->tempDataDir, 0777, true);
        DataFacade::addPath($this->tempDataDir);
    }

    protected function tearDown(): void
    {
        // Cleanup temp files
        if (is_dir($this->tempDataDir)) {
            $files = glob($this->tempDataDir . '/*');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->tempDataDir);
        }
    }

    public function testMockGeneratorProducesValidData(): void
    {
        $faker = new MockGenerator();

        $this->assertNotEmpty($faker->name());
        $this->assertStringContainsString('@', $faker->email());
        $this->assertTrue(str_starts_with($faker->avatar(), 'http'));
        $this->assertNotEmpty($faker->title());
        $this->assertTrue($faker->price(10, 100) >= 10.0);
        $this->assertTrue((bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $faker->date()));
        $this->assertIsArray($faker->address());
        $this->assertNotEmpty($faker->company());
        $this->assertTrue((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $faker->uuid()));
    }

    public function testMockBlueprintsAndGeneration(): void
    {
        $manager = new DataManager();

        // Custom blueprint
        $manager->define('member', function ($i, $f) {
            return [
                'id' => $i,
                'name' => $f->name(),
                'points' => 100 * $i,
            ];
        });

        $members = $manager->mock('member', 3, ['status' => 'active']);

        $this->assertCount(3, $members);
        $this->assertEquals(1, $members[0]['id']);
        $this->assertEquals(100, $members[0]['points']);
        $this->assertEquals('active', $members[0]['status']);
        $this->assertEquals(300, $members[2]['points']);
    }

    public function testBuiltinEntityMocks(): void
    {
        $users = DataFacade::mock('user', 2);
        $this->assertCount(2, $users);
        $this->assertArrayHasKey('name', $users[0]);
        $this->assertArrayHasKey('email', $users[0]);
        $this->assertEquals('admin', $users[0]['role']);

        $products = DataFacade::mock('product', 2);
        $this->assertCount(2, $products);
        $this->assertArrayHasKey('title', $products[0]);
        $this->assertArrayHasKey('price', $products[0]);
    }

    public function testStaticDataLoadingFromPhpAndJson(): void
    {
        // 1. Create a mock PHP dataset
        file_put_contents($this->tempDataDir . '/countries.php', '<?php return ["US" => ["name" => "United States", "code" => "US"], "FR" => ["name" => "France", "code" => "FR"]];');

        // 2. Create a mock JSON dataset
        file_put_contents($this->tempDataDir . '/plans.json', json_encode([
            ['id' => 'free', 'name' => 'Free Tier', 'price' => 0],
            ['id' => 'pro', 'name' => 'Pro Plan', 'price' => 29]
        ]));

        $this->assertEquals('United States', DataFacade::get('countries.US.name'));
        $this->assertEquals('FR', DataFacade::get('countries.FR.code'));

        // Querying helpers
        $proPlan = DataFacade::find('plans', 'pro');
        $this->assertNotNull($proPlan);
        $this->assertEquals('Pro Plan', $proPlan['name']);

        $names = DataFacade::pluck('plans', 'name');
        $this->assertEquals(['Free Tier', 'Pro Plan'], $names);
    }

    public function testGlobalHelpers(): void
    {
        // Fake helper
        $email = fake('email');
        $this->assertStringContainsString('@', $email);

        // Mock helper
        $posts = mock('post', 2);
        $this->assertCount(2, $posts);
        $this->assertArrayHasKey('slug', $posts[0]);

        // In-memory data
        DataFacade::set('settings', ['site_name' => 'Switch Studio']);
        $this->assertEquals('Switch Studio', data('settings.site_name'));
    }
}
