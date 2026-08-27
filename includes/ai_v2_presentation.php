<?php

/**
 * Plain-language presentation for deterministic AI V2 results.
 * These helpers never calculate or alter scores or classifications.
 */

function getAiRecencyExplanation(?string $recency): string
{
    return match ($recency) {
        'Recent' => 'This happened recently and may still be relevant to the Asset\'s current condition.',
        'Intermediate' => 'This happened a few months ago and is worth considering together with newer records.',
        'Earlier' => 'This is an older record, so it provides background rather than an immediate concern.',
        'Historical context' => 'This happened more than a year ago and mainly helps explain the Asset\'s longer history.',
        default => '',
    };
}

function getAiMaintenanceExplanation(array $pattern): string
{
    return match ((string) ($pattern['classification'] ?? '')) {
        'Recurring and recent' =>
            'This Asset has needed corrective maintenance more than once recently. The repeated cases deserve a closer look.',
        'Recurring' =>
            'This Asset has needed corrective maintenance more than once within the past year. It may be worth checking whether the same problem keeps returning.',
        'Isolated' =>
            'Only one corrective maintenance case was found, so this appears to be a one-time issue so far.',
        default =>
            'No corrective maintenance pattern is currently shown in the available records.',
    };
}

function getAiAuditTrendExplanation(array $trend): string
{
    $count = (int) ($trend['audits_considered'] ?? 0);

    return match ((string) ($trend['classification'] ?? '')) {
        'Worsening' =>
            'The latest completed Audits show that the Asset\'s recorded condition has become more concerning over time.',
        'Improving' =>
            'Recent completed Audits show that the Asset\'s recorded condition has been improving.',
        'Stable' =>
            'Recent completed Audits show about the same condition, with no clear improvement or decline.',
        'Mixed' =>
            'Recent Audit results vary, so there is no clear direction yet. Reviewing the individual findings may explain the changes.',
        default => $count === 1
            ? 'There is only one completed Audit for this Asset. More completed Audits are needed before PCMS can identify a trend.'
            : 'There are not enough completed Audits yet to tell whether the Asset\'s condition is improving or getting worse.',
    };
}

function getAiCoveragePresentation(array $coverage): array
{
    return match ((string) ($coverage['label'] ?? 'Limited')) {
        'Strong' => [
            'label' => 'Good amount of information',
            'explanation' => 'PCMS found records from all of the main sources used in this review, giving a fuller picture of the Asset\'s history.',
        ],
        'Moderate' => [
            'label' => 'Some useful information',
            'explanation' => 'PCMS found several useful records for this Asset, but some parts of its history are still missing.',
        ],
        default => [
            'label' => 'Only a small amount of history',
            'explanation' => 'The available history is limited. This should be treated as an early indication that may become clearer as more records are added.',
        ],
    };
}

function getAiConcernPresentation(string $concern, array $v1): array
{
    $conditionCode = (string) ($v1['evidence_summary']['condition_code'] ?? 'normal');

    if ($concern === 'Audit / Condition') {
        $explanation = match ($conditionCode) {
            'completed_missing' => 'A completed Audit reported that the Asset is missing and needs prompt follow-up.',
            'completed_damaged' => 'The latest completed Audit recorded damage that may need follow-up.',
            'completed_investigation' => 'The latest completed Audit contains a finding that still needs review.',
            default => 'The available Audit or condition record may need follow-up.',
        };

        return ['label' => 'Asset condition', 'explanation' => $explanation];
    }

    return match ($concern) {
        'Recurring Maintenance' => [
            'label' => 'Repeated maintenance needs',
            'explanation' => 'Corrective maintenance has happened more than once and may need a closer review.',
        ],
        'Maintenance' => [
            'label' => 'Maintenance history',
            'explanation' => 'Maintenance activity is the main reason this Asset currently needs attention.',
        ],
        'Asset Status' => [
            'label' => 'Current Asset status',
            'explanation' => 'The Asset\'s current status may require accountability or service follow-up.',
        ],
        'Asset Age' => [
            'label' => 'Asset age',
            'explanation' => 'The Asset\'s age is the main reason it appears in this attention review.',
        ],
        'Multiple Concerns' => [
            'label' => 'Several issues need attention',
            'explanation' => 'More than one part of the Asset\'s recorded history may need review.',
        ],
        default => [
            'label' => 'No major concern found',
            'explanation' => 'No major concern was found in the available records. Continue normal monitoring.',
        ],
    };
}

function getAiScoreComponentExplanation(string $component, int $points): string
{
    return match ($component) {
        'asset_age' => sprintf('+%d  The Asset\'s age contributes to its attention score.', $points),
        'corrective_maintenance' => sprintf('+%d  The Asset has corrective maintenance in its recent history.', $points),
        'audit_condition' => sprintf('+%d  Its Audit history or current condition needs attention.', $points),
        'recurring_attention' => sprintf('+%d  The Asset has entered an attention status more than once.', $points),
        default => sprintf('+%d  Recorded information contributes to the score.', $points),
    };
}

function buildAiV2Presentation(
    array $v1,
    array $maintenancePattern,
    array $auditTrend,
    array $coverage,
    string $primaryConcern
): array {
    $componentExplanations = [];

    foreach ($v1['components'] ?? [] as $component => $points) {
        if ((int) $points > 0) {
            $componentExplanations[] = getAiScoreComponentExplanation(
                (string) $component,
                (int) $points
            );
        }
    }

    if (!$componentExplanations) {
        $componentExplanations[] =
            'No major concern currently adds points to this Asset\'s attention score.';
    }

    return [
        'maintenance_label' => match ($maintenancePattern['classification']) {
            'Recurring and recent' => 'Repeated maintenance needs',
            'Recurring' => 'Maintenance has happened more than once',
            'Isolated' => 'One corrective case found',
            default => 'No repeated corrective maintenance',
        },
        'maintenance_explanation' => getAiMaintenanceExplanation($maintenancePattern),
        'audit_label' => match ($auditTrend['classification']) {
            'Worsening' => 'The recorded condition is becoming more concerning',
            'Improving' => 'The recorded condition is improving',
            'Stable' => 'The recorded condition is staying about the same',
            'Mixed' => 'The recent results vary',
            default => 'Not enough Audit history yet',
        },
        'audit_explanation' => getAiAuditTrendExplanation($auditTrend),
        'coverage' => getAiCoveragePresentation($coverage),
        'concern' => getAiConcernPresentation($primaryConcern, $v1),
        'score_explanations' => $componentExplanations,
        'score_summary' => sprintf(
            'Together, these records produce an attention score of %d out of 100.',
            (int) ($v1['score'] ?? 0)
        ),
    ];
}
