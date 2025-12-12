<?php

namespace Devhammed\Webterm;

use InvalidArgumentException;
use React\EventLoop\TimerInterface;

class TerminalContext
{
    /** @var resource */
    public mixed $process;

    public array $pipes;

    public TimerInterface $timer;

    public function __construct(mixed $process, array $pipes, TimerInterface $timer)
    {
        if ( ! is_resource($process)) {
            throw new InvalidArgumentException('The process must be a resource.');
        }

        $this->process = $process;

        $this->pipes = $pipes;

        $this->timer = $timer;
    }
}
