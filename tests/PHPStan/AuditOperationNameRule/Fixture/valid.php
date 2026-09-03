<?php

declare(strict_types=1);

namespace App\Module\Billing\Command;

use App\Module\Audit\Auditor;

function two_segments_pass(Auditor $auditor): void
{
    $auditor->record('billing.comp_granted', 'success');
    $auditor->record('account.api_token_authentication_failed', 'refused');
    $auditor->record('project.mcp_token_minted', 'success', ['projectId' => 'p-1']);
}

// Another object's record() is a different method.
function another_receiver_passes(Recorder $recorder): void
{
    $recorder->record('billing.webhook.received', 'success');
}

// The name arrives as a variable, so the rule cannot read it.
function variable_argument_passes(Auditor $auditor, string $operation): void
{
    $auditor->record($operation, 'success');
}

// The name is a literal one hop away, at this helper's own call sites.
final readonly class GrantCompHandler
{
    public function __construct(private Auditor $auditor)
    {
    }

    public function __invoke(): void
    {
        $this->record('billing.comp_granted');
        $this->record('billing.account.reenabled');
    }

    private function record(string $operation): void
    {
        $this->auditor->record($operation, 'success');
    }
}

final readonly class Recorder
{
    public function record(string $operation, string $outcome): void
    {
    }
}
