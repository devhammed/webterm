<?php

namespace Devhammed\Webterm;

use InvalidArgumentException;

class TerminalContext
{
    /** @var resource */
    public mixed $process;

    public array $pipes;

    public function __construct(mixed $process, array $pipes)
    {
        if ( ! is_resource($process)) {
            throw new InvalidArgumentException('The process must be a resource.');
        }

        $this->process = $process;

        $this->pipes = $pipes;
    }
}
