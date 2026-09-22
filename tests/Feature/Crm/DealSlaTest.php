<?php

declare(strict_types=1);

use App\Enums\DealStage;
use App\Support\BusinessHours;
use App\Support\BusinessTime;
use App\Support\Config\Documents\BusinessRulesDocument;
use App\Support\Crm\DealSla;
use Carbon\CarbonImmutable;

function pipelineRules(): BusinessRulesDocument
{
    return BusinessRulesDocument::fromArray(BusinessRulesDocument::initial());
}

test('a new lead opened on Friday afternoon is due Monday noon', function (): void {
    $rules = pipelineRules();
    $hours = BusinessHours::fromDocument($rules);
    $entered = CarbonImmutable::parse('2026-09-18 17:00:00', BusinessTime::zone());

    $deadline = DealSla::deadline(
        DealStage::NewLead,
        $entered,
        $hours,
        $rules->crm->pipeline,
        $rules->sla->responseHours,
    );

    expect($deadline->setTimezone(BusinessTime::zone())->format('Y-m-d H:i'))->toBe('2026-09-21 12:00');

    expect(DealSla::assess($stage = DealStage::NewLead, $entered, $entered->addMinute(), $rules))->toBe('ok');

    $span = (int) $entered->diffInSeconds($deadline);
    $warnAt = $entered->addSeconds((int) floor($span * 0.75));
    expect(DealSla::assess($stage, $entered, $warnAt, $rules))->toBe('warn');
    expect(DealSla::assess($stage, $entered, $deadline, $rules))->toBe('bad');
    expect(DealSla::assess($stage, $entered, $deadline->addMinute(), $rules))->toBe('bad');
});

test('a Monday holiday pushes the new-lead deadline to Tuesday', function (): void {
    $document = BusinessRulesDocument::initial();
    $document['holds']['holidays'] = ['2026-09-21'];
    $rules = BusinessRulesDocument::fromArray($document);
    $entered = CarbonImmutable::parse('2026-09-18 17:00:00', BusinessTime::zone());

    $deadline = DealSla::deadline(
        DealStage::NewLead,
        $entered,
        BusinessHours::fromDocument($rules),
        $rules->crm->pipeline,
        $rules->sla->responseHours,
    );

    expect($deadline->setTimezone(BusinessTime::zone())->format('Y-m-d H:i'))->toBe('2026-09-22 12:00');
});

test('qualifying counts five business days after the entry day', function (): void {
    $rules = pipelineRules();
    $entered = CarbonImmutable::parse('2026-09-18 17:00:00', BusinessTime::zone());

    $deadline = DealSla::deadline(
        DealStage::Qualifying,
        $entered,
        BusinessHours::fromDocument($rules),
        $rules->crm->pipeline,
        $rules->sla->responseHours,
    );

    expect($deadline->setTimezone(BusinessTime::zone())->format('Y-m-d H:i'))->toBe('2026-09-25 18:00');
});
