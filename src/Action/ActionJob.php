<?php

declare(strict_types=1);

namespace Switch\Foundation\Action;

use Switch\Foundation\Queue\Job;

class ActionJob extends Job
{
    public function __construct(
        public string $actionClass,
        public array $parameters = []
    ) {
    }

    public function handle(): void
    {
        if (class_exists($this->actionClass)) {
            $action = new $this->actionClass();
            if (method_exists($action, 'handle')) {
                $action->handle(...$this->parameters);
            }
        }
    }
}
