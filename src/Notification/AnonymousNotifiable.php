<?php

declare(strict_types=1);

namespace Switch\Foundation\Notification;

use Switch\Foundation\Notification\Facade\Notification as NotificationFacade;

class AnonymousNotifiable
{
    public array $routes = [];

    public function route(string $channel, mixed $route): static
    {
        $this->routes[$channel] = $route;
        return $this;
    }

    public function routeNotificationFor(string $channel): mixed
    {
        return $this->routes[$channel] ?? null;
    }

    public function notify(Notification $notification): void
    {
        NotificationFacade::send($this, $notification, array_keys($this->routes));
    }

    public function notifyNow(Notification $notification): void
    {
        NotificationFacade::sendNow($this, $notification, array_keys($this->routes));
    }
}
