<?php

declare(strict_types=1);

use App\Http\Controllers\Crm\AutomationController;
use App\Http\Controllers\Crm\B2bPartnerController;
use App\Http\Controllers\Crm\CampaignController;
use App\Http\Controllers\Crm\ContactConsentController;
use App\Http\Controllers\Crm\ContactController;
use App\Http\Controllers\Crm\ContactMergeController;
use App\Http\Controllers\Crm\ContactTimelineController;
use App\Http\Controllers\Crm\ConversationController;
use App\Http\Controllers\Crm\DealController;
use App\Http\Controllers\Crm\DeliveryController;
use App\Http\Controllers\Crm\EngineActivityController;
use App\Http\Controllers\Crm\JourneyController;
use App\Http\Controllers\Crm\SegmentController;
use App\Http\Controllers\Crm\SyncController;
use App\Http\Controllers\Crm\TaskController;
use App\Http\Controllers\Crm\TemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['ok' => true]));

Route::get('tasks', [TaskController::class, 'index']);
Route::post('tasks', [TaskController::class, 'store']);
Route::patch('tasks/{task}', [TaskController::class, 'update']);
Route::post('tasks/{task}/complete', [TaskController::class, 'complete']);
Route::post('tasks/{task}/cancel', [TaskController::class, 'cancel']);
Route::post('contacts/{contact}/activities', [TaskController::class, 'storeActivity']);

Route::get('templates', [TemplateController::class, 'index']);
Route::get('templates/{template}/versions/{version}', [TemplateController::class, 'show'])->whereNumber('version');
Route::post('templates/{template}/drafts', [TemplateController::class, 'storeDraft']);
Route::post('templates/{template}/versions/{version}/publish', [TemplateController::class, 'publish'])->whereNumber('version');
Route::post('templates/{template}/preview', [TemplateController::class, 'preview']);
Route::post('templates/{template}/test-send', [TemplateController::class, 'testSend']);

Route::get('journeys', [JourneyController::class, 'index']);
Route::get('journeys/{journey}/enrolments', [JourneyController::class, 'enrolments']);
Route::patch('journeys/{journey}', [JourneyController::class, 'update']);
Route::get('contacts/{contact}/journeys', [JourneyController::class, 'forContact']);

Route::get('b2b-partners', [B2bPartnerController::class, 'index']);
Route::get('b2b-partners/{agency}', [B2bPartnerController::class, 'show'])->whereNumber('agency');

Route::get('automations', [AutomationController::class, 'index']);
Route::patch('automations/{key}', [AutomationController::class, 'update'])->where('key', '[A-Za-z0-9:_-]+');

Route::get('segments', [SegmentController::class, 'index']);
Route::get('segments/vocabulary', [SegmentController::class, 'vocabulary']);
Route::post('segments', [SegmentController::class, 'store']);
Route::get('segments/{segment}/contacts', [SegmentController::class, 'contacts']);
Route::patch('segments/{segment}', [SegmentController::class, 'update']);

Route::get('campaigns', [CampaignController::class, 'index']);
Route::get('campaigns/offers-without-campaign', [CampaignController::class, 'offersWithoutCampaign']);
Route::get('campaigns/attribution-model', [CampaignController::class, 'attributionModel']);
Route::post('campaigns', [CampaignController::class, 'store']);
Route::patch('campaigns/{campaign}', [CampaignController::class, 'update']);
Route::post('campaigns/{campaign}/archive', [CampaignController::class, 'archive']);
Route::get('campaigns/{campaign}/bookings', [CampaignController::class, 'bookings']);

Route::get('deliveries', [DeliveryController::class, 'index']);

Route::get('conversations', [ConversationController::class, 'index']);
Route::get('conversations/{conversation}', [ConversationController::class, 'show']);
Route::post('conversations/{conversation}/reply', [ConversationController::class, 'reply']);
Route::patch('conversations/{conversation}', [ConversationController::class, 'update']);
Route::post('conversations/{conversation}/link-contact', [ConversationController::class, 'linkContact']);

Route::get('pipeline', [DealController::class, 'pipeline']);
Route::get('pipeline/stage-map', [DealController::class, 'stageMap']);
Route::post('deals', [DealController::class, 'store']);
Route::get('deals/{deal}', [DealController::class, 'show']);
Route::post('deals/{deal}/assign', [DealController::class, 'assign']);
Route::post('deals/{deal}/bind', [DealController::class, 'bind']);
Route::patch('deals/{deal}/stage', [DealController::class, 'stage']);

Route::get('consents/register', [ContactConsentController::class, 'register']);
Route::get('consents/data-map', [ContactConsentController::class, 'dataMap']);

Route::get('contacts', [ContactController::class, 'index']);
Route::get('contacts/duplicates', [ContactController::class, 'duplicates']);
Route::get('contacts/{contact}', [ContactController::class, 'show']);
Route::get('contacts/{contact}/timeline', ContactTimelineController::class);
Route::get('contacts/{contact}/consents', [ContactConsentController::class, 'show']);
Route::post('contacts/{contact}/consents', [ContactConsentController::class, 'store']);
Route::patch('contacts/{contact}', [ContactController::class, 'update']);
Route::post('contacts/{contact}/merge', [ContactController::class, 'merge']);

Route::get('contact-merges', [ContactMergeController::class, 'index']);
Route::post('contact-merges/{merge}/undo', [ContactMergeController::class, 'undo']);

Route::get('activity', EngineActivityController::class);

Route::get('sync/ownership', [SyncController::class, 'ownership']);
Route::get('sync/jobs', [SyncController::class, 'jobs']);
Route::get('sync/failures', [SyncController::class, 'failures']);
Route::post('sync/failures/{id}/retry', [SyncController::class, 'retry'])->where('id', 'job:[A-Za-z0-9-]+|delivery:[0-9]+');
Route::get('sync/identity', [SyncController::class, 'identity']);
Route::get('sync/events', [SyncController::class, 'events']);
