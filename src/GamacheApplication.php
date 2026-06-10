<?php

declare(strict_types=1);

namespace Gamache;

use Symfony\Component\Console\Application;

final class GamacheApplication extends Application
{
    public function __construct(string $projectRoot)
    {
        parent::__construct('Gamache', '1.0.0');
        $this->addCommand(new RunCommand($projectRoot));
        $this->setDefaultCommand('run', true);
    }
}
