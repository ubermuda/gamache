<?php

declare(strict_types=1);

enum ValidStatus: string
{
    case Pending = 'pending';
    case InReview = 'in-review';
    case Done = 'done';
    case InProgress123 = 'in-progress-123';
}
