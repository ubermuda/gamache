<?php

declare(strict_types=1);

namespace App\Module\Billing\Command;

use App\Module\Audit\Auditor;

function three_segments_are_reported(Auditor $auditor): void
{
    $auditor->record('billing.webhook.received', 'success');
}

function a_missing_dot_is_reported(Auditor $auditor): void
{
    $auditor->record('billing_comp_granted', 'success');
}

function an_uppercase_segment_is_reported(Auditor $auditor): void
{
    $auditor->record('Billing.compGranted', 'success');
}

function a_leading_digit_is_reported(Auditor $auditor): void
{
    $auditor->record('2fa.enabled', 'success');
}

function a_leading_underscore_is_reported(Auditor $auditor): void
{
    $auditor->record('billing._comp_granted', 'success');
}

// The receiver every real call site uses: an injected property.
final readonly class SyncSubscriptionHandler
{
    public function __construct(private Auditor $auditor)
    {
    }

    public function __invoke(): void
    {
        $this->auditor->record('billing.comp.granted', 'success');
    }
}
