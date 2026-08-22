<?php

declare(strict_types=1);

namespace Switch\Foundation\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Switch\Foundation\Cache\CacheManager;
use Switch\Foundation\Mailer\Facade\Mail;
use Switch\Foundation\Mailer\Mailable;
use Switch\Foundation\Mailer\MailManager;
use Switch\Foundation\Mailer\Transport\ArrayTransport;
use Switch\Foundation\Notification\AnonymousNotifiable;
use Switch\Foundation\Notification\Channel\BroadcastChannel;
use Switch\Foundation\Notification\Channel\DatabaseChannel;
use Switch\Foundation\Notification\DatabaseNotification;
use Switch\Foundation\Notification\Facade\Notification as NotificationFacade;
use Switch\Foundation\Notification\NotifiableTrait;
use Switch\Foundation\Notification\Notification;
use Switch\Foundation\Notification\NotificationManager;
use Switch\Foundation\Notification\Realtime\NotificationStream;
use Switch\Foundation\Notification\ShouldQueue as NotificationShouldQueue;
use Switch\Foundation\Queue\Driver\ArrayDriver;
use Switch\Foundation\Queue\Facade\Queue;
use Switch\Foundation\Queue\QueueManager;
use Switch\Foundation\Queue\ShouldQueue as MailShouldQueue;
use Switch\Foundation\Queue\Worker;

// Mock Notifiable User
class NotifiableUser
{
    use NotifiableTrait;

    public int $id;
    public string $name;
    public string $email;
    public ?PDO $pdo = null;

    public function __construct(int $id, string $name, string $email, ?PDO $pdo = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->pdo = $pdo;
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }
}

// Mock Order Shipped Notification
class OrderShippedNotification extends Notification
{
    public string $orderNumber;

    public function __construct(string $orderNumber = 'ORD-12345')
    {
        parent::__construct();
        $this->orderNumber = $orderNumber;
    }

    public function via(mixed $notifiable): array
    {
        return ['database', 'mail', 'broadcast', 'sse'];
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'order_number' => $this->orderNumber,
            'message' => "Order #{$this->orderNumber} has shipped!",
            'title' => 'Order Shipped',
        ];
    }
}

// Mock Queued Notification
class QueuedInvoiceNotification extends Notification implements NotificationShouldQueue
{
    public int $invoiceId;

    public function __construct(int $invoiceId)
    {
        parent::__construct();
        $this->invoiceId = $invoiceId;
    }

    public function via(mixed $notifiable): array
    {
        return ['broadcast'];
    }

    public function toArray(mixed $notifiable): array
    {
        return ['invoice_id' => $this->invoiceId];
    }
}

// Mock Queued Mailable
class QueuedWelcomeMailable extends Mailable implements MailShouldQueue
{
    public function __construct()
    {
        $this->subject('Queued Welcome')->text('Welcome to the platform!');
    }
}

class NotificationTest extends TestCase
{
    private PDO $pdo;
    private ArrayTransport $mailTransport;
    private CacheManager $cache;
    private QueueManager $queueManager;

    protected function setUp(): void
    {
        // 1. Mail Manager
        $this->mailTransport = new ArrayTransport();
        $mailManager = new MailManager([
            'default' => 'array',
            'mailers' => ['array' => ['transport' => 'array']],
        ]);
        $mailManager->setTransport('array', $this->mailTransport);
        MailManager::setInstance($mailManager);

        // 2. Queue Manager
        $this->queueManager = new QueueManager([
            'default' => 'array',
            'connections' => ['array' => ['driver' => 'array']],
        ]);
        QueueManager::setInstance($this->queueManager);

        // 3. Cache Manager
        $this->cache = new CacheManager([
            'default' => 'array',
            'stores' => ['array' => ['driver' => 'array']],
        ]);
        CacheManager::setInstance($this->cache);

        BroadcastChannel::flush();

        // 4. In-memory SQLite PDO for notification storage tests
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("CREATE TABLE notifications (
            id VARCHAR(64) PRIMARY KEY,
            type VARCHAR(255) NOT NULL,
            notifiable_type VARCHAR(255) NOT NULL,
            notifiable_id VARCHAR(64) NOT NULL,
            data TEXT NOT NULL,
            read_at DATETIME NULL,
            created_at DATETIME NOT NULL
        )");

        // 5. Notification Manager
        $notifManager = new NotificationManager();
        $notifManager->setChannel('database', new DatabaseChannel($this->pdo));
        NotificationManager::setInstance($notifManager);
    }

    public function testNotificationCreationAndUuid(): void
    {
        $notification = new OrderShippedNotification('ORD-999');
        $this->assertNotEmpty($notification->id);
        $this->assertStringContainsString('-', $notification->id);
        $this->assertEquals(['database', 'mail', 'broadcast', 'sse'], $notification->via(null));
    }

    public function testDatabaseNotificationStorageAndReadStatus(): void
    {
        $user = new NotifiableUser(1, 'Celio', 'celio@example.com');
        $notification = new OrderShippedNotification('ORD-1001');

        $channel = new DatabaseChannel($this->pdo);
        $channel->send($user, $notification);

        // Fetch row from DB
        $stmt = $this->pdo->query("SELECT * FROM notifications WHERE notifiable_id = '1'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($row);
        $this->assertEquals(OrderShippedNotification::class, $row['type']);
        $this->assertNull($row['read_at']);

        $dbNotification = new DatabaseNotification($row);
        $this->assertTrue($dbNotification->unread());
        $this->assertFalse($dbNotification->read());
        $this->assertEquals('ORD-1001', $dbNotification->data['order_number']);
    }

    public function testNotifiableTraitAndNotificationManager(): void
    {
        $user = new NotifiableUser(5, 'Jane Doe', 'jane@example.com', $this->pdo);
        $notification = new OrderShippedNotification('ORD-5555');

        $user->notifyNow($notification);

        // Verify Mail Channel sent email to user's address
        $messages = $this->mailTransport->messages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('jane@example.com', $messages[0]->renderRaw());

        // Verify Broadcast Channel captured event
        $events = BroadcastChannel::events();
        $this->assertCount(1, $events);
        $this->assertEquals('Order #ORD-5555 has shipped!', $events[0]['payload']['message']);

        // Verify SSE Channel cached payload for real-time streaming
        $sseKey = 'sse_stream:5';
        $sseEvents = $this->cache->get($sseKey);
        $this->assertNotEmpty($sseEvents);
        $this->assertEquals('Order #ORD-5555 has shipped!', $sseEvents[0]['message']);
    }

    public function testAnonymousNotifiableOnDemandRouting(): void
    {
        $notification = new OrderShippedNotification('ORD-777');

        NotificationFacade::route('mail', 'accounting@company.com')
            ->notifyNow($notification);

        $messages = $this->mailTransport->messages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('accounting@company.com', $messages[0]->renderRaw());
    }

    public function testQueuedNotificationDispatchingAndWorker(): void
    {
        $user = new NotifiableUser(10, 'Admin', 'admin@example.com');
        $notification = new QueuedInvoiceNotification(8842);

        // Sending queued notification pushes to queue automatically
        $user->notify($notification);

        $this->assertEquals(1, Queue::size());
        $this->assertCount(0, BroadcastChannel::events());

        // Execute background queue worker
        $worker = new Worker($this->queueManager);
        $processed = $worker->processNextJob('default');

        $this->assertTrue($processed);
        $this->assertEquals(0, Queue::size());
        $this->assertCount(1, BroadcastChannel::events());
        $this->assertEquals(8842, BroadcastChannel::events()[0]['payload']['invoice_id']);
    }

    public function testAutomaticQueuedMailable(): void
    {
        $mailable = new QueuedWelcomeMailable();

        // Mail::to()->send() automatically routes through queue because mailable implements ShouldQueue!
        Mail::to('newuser@example.com')->send($mailable);

        $this->assertEquals(1, Queue::size());
        $this->assertCount(0, $this->mailTransport->messages());

        // Run worker
        $worker = new Worker($this->queueManager);
        $worker->processNextJob('default');

        $this->assertEquals(0, Queue::size());
        $this->assertCount(1, $this->mailTransport->messages());
        $this->assertStringContainsString('newuser@example.com', $this->mailTransport->messages()[0]->renderRaw());
    }

    public function testNotificationStreamScriptRendering(): void
    {
        $script = NotificationStream::renderScript('/api/notifications/stream');
        $this->assertStringContainsString("new EventSource('/api/notifications/stream')", $script);
        $this->assertStringContainsString("switch:notification", $script);
    }
}
