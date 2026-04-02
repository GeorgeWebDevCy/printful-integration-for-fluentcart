<?php

namespace PrintfulForFluentCart;

defined('ABSPATH') || exit;

/**
 * Collects all action/filter registrations and applies them in one pass.
 */
class Loader
{
    /** @var array */
    private $actions = [];

    /** @var array */
    private $filters = [];

    /**
     * @param string $hook
     * @param object $component
     * @param string $callback
     * @param int    $priority
     * @param int    $accepted_args
     */
    public function addAction($hook, $component, $callback, $priority = 10, $accepted_args = 1)
    {
        $this->actions[] = compact('hook', 'component', 'callback', 'priority', 'accepted_args');
    }

    /**
     * @param string $hook
     * @param object $component
     * @param string $callback
     * @param int    $priority
     * @param int    $accepted_args
     */
    public function addFilter($hook, $component, $callback, $priority = 10, $accepted_args = 1)
    {
        $this->filters[] = compact('hook', 'component', 'callback', 'priority', 'accepted_args');
    }

    public function run()
    {
        foreach ($this->actions as $a) {
            add_action($a['hook'], [$a['component'], $a['callback']], $a['priority'], $a['accepted_args']);
        }

        foreach ($this->filters as $f) {
            add_filter($f['hook'], [$f['component'], $f['callback']], $f['priority'], $f['accepted_args']);
        }
    }
}
