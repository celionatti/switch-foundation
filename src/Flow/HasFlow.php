<?php

declare(strict_types=1);

namespace Switch\Foundation\Flow;

trait HasFlow
{
    /**
     * Define the state machine for this model.
     * Override this method in your Model to configure states and transitions.
     */
    public static function flow(): StateMachine
    {
        return StateMachine::define('status');
    }

    /**
     * Get the configured StateMachine instance.
     */
    public function getStateMachine(): StateMachine
    {
        return static::flow();
    }

    /**
     * Get current state of the model.
     */
    public function state(): string
    {
        return $this->getStateMachine()->getCurrentState($this);
    }

    /**
     * Check if a transition or target state is currently allowed.
     */
    public function canTransitionTo(string $targetState, array $context = []): bool
    {
        return $this->getStateMachine()->can($this, $targetState, $context);
    }

    /**
     * Check if a named transition is currently allowed.
     */
    public function canApply(string $transition, array $context = []): bool
    {
        return $this->getStateMachine()->can($this, $transition, $context);
    }

    /**
     * Transition the model to a target state.
     */
    public function transitionTo(string $targetState, array $context = []): static
    {
        $this->getStateMachine()->transitionTo($this, $targetState, $context);
        return $this;
    }

    /**
     * Apply a named transition on the model.
     */
    public function applyFlow(string $transition, array $context = []): static
    {
        $this->getStateMachine()->apply($this, $transition, $context);
        return $this;
    }

    /**
     * Get all currently available transitions for this model.
     *
     * @return array<string, array{name: string, to: string}>
     */
    public function availableTransitions(): array
    {
        return $this->getStateMachine()->getAvailableTransitions($this);
    }
}
