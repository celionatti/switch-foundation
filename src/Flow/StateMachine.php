<?php

declare(strict_types=1);

namespace Switch\Foundation\Flow;

class StateMachine
{
    /**
     * @var array<string>
     */
    private array $states = [];
    private ?string $initialState = null;

    /**
     * @var array<string, array{name: string, from: array<string>, to: string, guard: callable|null}>
     */
    private array $transitions = [];

    /**
     * @var array<string, array<callable>>
     */
    private array $beforeCallbacks = [];

    /**
     * @var array<string, array<callable>>
     */
    private array $afterCallbacks = [];

    public function __construct(public readonly string $field = 'status')
    {
    }

    public static function define(string $field = 'status'): self
    {
        return new self($field);
    }

    /**
     * Define the possible state names.
     *
     * @param array<string> $states
     */
    public function states(array $states): self
    {
        $this->states = $states;
        if ($this->initialState === null && !empty($states)) {
            $this->initialState = $states[0];
        }
        return $this;
    }

    /**
     * Set the initial default state.
     */
    public function initial(string $state): self
    {
        $this->initialState = $state;
        return $this;
    }

    public function getInitialState(): ?string
    {
        return $this->initialState;
    }

    /**
     * Allow a transition from one or more states to a target state.
     *
     * @param string $name Transition name (e.g. 'pay', 'ship', 'cancel')
     * @param string|array<string> $from Source state(s)
     * @param string $to Target state
     * @param callable|null $guard fn($model, $context): bool
     */
    public function allow(string $name, string|array $from, string $to, ?callable $guard = null): self
    {
        $this->transitions[$name] = [
            'name' => $name,
            'from' => (array) $from,
            'to' => $to,
            'guard' => $guard,
        ];
        return $this;
    }

    /**
     * Register a callback to execute before or on transition.
     */
    public function beforeTransition(string $transition, callable $callback): self
    {
        $this->beforeCallbacks[$transition][] = $callback;
        return $this;
    }

    public function afterTransition(string $transition, callable $callback): self
    {
        $this->afterCallbacks[$transition][] = $callback;
        return $this;
    }

    public function onTransition(string $transition, callable $callback): self
    {
        return $this->afterTransition($transition, $callback);
    }

    /**
     * Check if a transition or target state can be transitioned to.
     */
    public function can(object $model, string $transitionOrTargetState, array $context = []): bool
    {
        $currentState = $this->getCurrentState($model);

        // Check if transition name matches
        if (isset($this->transitions[$transitionOrTargetState])) {
            $t = $this->transitions[$transitionOrTargetState];
            if (!in_array($currentState, $t['from'], true)) {
                return false;
            }
            if ($t['guard'] !== null && !$t['guard']($model, $context)) {
                return false;
            }
            return true;
        }

        // Check if target state matches any allowed transition from current state
        foreach ($this->transitions as $t) {
            if ($t['to'] === $transitionOrTargetState && in_array($currentState, $t['from'], true)) {
                if ($t['guard'] === null || $t['guard']($model, $context)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Apply a named transition on the model.
     */
    public function apply(object $model, string $transition, array $context = []): object
    {
        if (!isset($this->transitions[$transition])) {
            throw new TransitionDeniedException("Undefined transition '{$transition}'.", $this->getCurrentState($model), '', $transition);
        }

        $t = $this->transitions[$transition];
        $currentState = $this->getCurrentState($model);

        if (!in_array($currentState, $t['from'], true)) {
            throw new TransitionDeniedException(
                "Cannot apply transition '{$transition}' from current state '{$currentState}'. Allowed from: " . implode(', ', $t['from']),
                $currentState,
                $t['to'],
                $transition
            );
        }

        if ($t['guard'] !== null && !$t['guard']($model, $context)) {
            throw new TransitionDeniedException(
                "Guard rejected transition '{$transition}' on model.",
                $currentState,
                $t['to'],
                $transition
            );
        }

        // Run before callbacks
        foreach ($this->beforeCallbacks[$transition] ?? [] as $cb) {
            $cb($model, $context, $currentState, $t['to']);
        }

        // Apply state change
        $this->setCurrentState($model, $t['to']);

        // Auto-save if ORM model
        if (method_exists($model, 'save')) {
            $model->save();
        }

        // Record audit trail if model uses HasAuditTrail
        if (method_exists($model, 'recordAudit')) {
            $model->recordAudit('state_transition', [
                'transition' => $transition,
                'from' => $currentState,
                'to' => $t['to'],
                'context' => $context,
            ]);
        }

        // Run after callbacks
        foreach ($this->afterCallbacks[$transition] ?? [] as $cb) {
            $cb($model, $context, $currentState, $t['to']);
        }

        return $model;
    }

    /**
     * Transition model to a target state by automatically finding the matching allowed transition.
     */
    public function transitionTo(object $model, string $targetState, array $context = []): object
    {
        $currentState = $this->getCurrentState($model);

        foreach ($this->transitions as $name => $t) {
            if ($t['to'] === $targetState && in_array($currentState, $t['from'], true)) {
                return $this->apply($model, $name, $context);
            }
        }

        throw new TransitionDeniedException(
            "No allowed transition from '{$currentState}' to '{$targetState}'.",
            $currentState,
            $targetState,
            ''
        );
    }

    /**
     * Get all currently available transitions for model.
     *
     * @return array<string, array{to: string, name: string}>
     */
    public function getAvailableTransitions(object $model): array
    {
        $currentState = $this->getCurrentState($model);
        $available = [];

        foreach ($this->transitions as $name => $t) {
            if (in_array($currentState, $t['from'], true) && ($t['guard'] === null || $t['guard']($model, []))) {
                $available[$name] = [
                    'name' => $name,
                    'to' => $t['to'],
                ];
            }
        }

        return $available;
    }

    public function getCurrentState(object $model): string
    {
        $field = $this->field;
        $val = $model->{$field} ?? ($model->getAttribute($field) ?? $this->initialState);
        return (string) ($val ?: $this->initialState);
    }

    private function setCurrentState(object $model, string $state): void
    {
        $field = $this->field;
        $model->{$field} = $state;
    }
}
