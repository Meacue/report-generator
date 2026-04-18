<?php

declare(strict_types=1);

namespace App\Domain\Bitrix24\Enums;

/**
 * Role of the current Bitrix24 user in a task.
 *
 * Values use snake_case to match the Bitrix24 REST API conventions and are
 * persisted on {@see \App\Domain\Bitrix24\Models\Task::$participation_roles}.
 */
enum ParticipationRole: string
{
    case Creator = 'creator';
    case Responsible = 'responsible';
    case Accomplice = 'accomplice';
    case Auditor = 'auditor';
    case External = 'external';
}
