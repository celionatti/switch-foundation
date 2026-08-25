<?php

declare(strict_types=1);

namespace Switch\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Foundation\Action\Action;
use Switch\Foundation\Action\ActionJob;
use Switch\Foundation\Queue\Driver\ArrayDriver;
use Switch\Foundation\Queue\Facade\Queue;
use Switch\Foundation\Queue\QueueManager;
use Switch\Http\ServerRequest;
use Switch\Http\Uri;

// Sample Test Action
class CreatePostAction extends Action
{
    public static array $executedJobs = [];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required',
            'email' => 'required|email',
        ];
    }

    public function handle(array $data): array
    {
        self::$executedJobs[] = $data;
        return [
            'id' => 101,
            'title' => $data['title'],
            'email' => $data['email'],
            'created' => true,
        ];
    }
}

class ForbiddenAction extends Action
{
    public function authorize(): bool
    {
        return false;
    }

    public function handle(array $data): string
    {
        return 'secret_data';
    }
}

class ActionTest extends TestCase
{
    protected function setUp(): void
    {
        CreatePostAction::$executedJobs = [];
        $manager = new QueueManager([
            'default' => 'array',
            'connections' => ['array' => ['driver' => 'array']],
        ]);
        QueueManager::setInstance($manager);
    }

    public function testActionDirectServiceExecution(): void
    {
        $result = CreatePostAction::run([
            'title' => 'Introducing Switch Actions',
            'email' => 'dev@switch.test',
        ]);

        $this->assertTrue($result['created']);
        $this->assertEquals('Introducing Switch Actions', $result['title']);
    }

    public function testActionAsHttpControllerSuccess(): void
    {
        $action = new CreatePostAction();

        $request = (new ServerRequest(
            method: 'POST',
            uri: new Uri('http://localhost/api/posts'),
            headers: ['Content-Type' => 'application/json']
        ))->withParsedBody([
            'title' => 'My First Post',
            'email' => 'author@example.com',
        ]);

        $response = $action->asController($request);

        $this->assertEquals(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertTrue($payload['success']);
        $this->assertEquals('My First Post', $payload['data']['title']);
    }

    public function testActionValidationFailure(): void
    {
        $action = new CreatePostAction();

        $request = (new ServerRequest(
            method: 'POST',
            uri: new Uri('http://localhost/api/posts'),
            headers: ['Content-Type' => 'application/json']
        ))->withParsedBody([
            'title' => '', // Missing title
            'email' => 'invalid-email-address',
        ]);

        $response = $action->asController($request);

        $this->assertEquals(422, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertFalse($payload['success']);
        $this->assertArrayHasKey('title', $payload['errors']);
        $this->assertArrayHasKey('email', $payload['errors']);
    }

    public function testActionAuthorizationFailure(): void
    {
        $action = new ForbiddenAction();

        $request = new ServerRequest(
            method: 'GET',
            uri: new Uri('http://localhost/api/secret')
        );

        $response = $action->asController($request);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testActionAsyncQueueDispatch(): void
    {
        $job = CreatePostAction::dispatch([
            'title' => 'Queued Post',
            'email' => 'queued@example.com',
        ]);

        $this->assertInstanceOf(ActionJob::class, $job);
        $this->assertEquals('default', $job->queue);

        // Run worker on queue
        $popped = Queue::pop('default');
        $this->assertNotNull($popped);
        $popped->handle();

        $this->assertCount(1, CreatePostAction::$executedJobs);
        $this->assertEquals('Queued Post', CreatePostAction::$executedJobs[0]['title']);
    }
}
