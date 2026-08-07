<?php
use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Database;

return function (App $app) {
    $app->get('/', function (Request $request, Response $response) {
        if (!\App\Services\AuthService::hasGlobalAdminSetup()) {
            return $response->withHeader('Location', BASE_URL . '/admin/init')->withStatus(302);
        }
        return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
    });

    $app->get('/admin/login', \App\Controllers\AdminController::class . ':showLogin');
    $app->post('/admin/login', \App\Controllers\AdminController::class . ':processLogin');
    $app->get('/admin/logout', \App\Controllers\AdminController::class . ':logout');
    $app->get('/admin/init', \App\Controllers\AdminController::class . ':showInit');
    $app->post('/admin/init', \App\Controllers\AdminController::class . ':processInit');

    $app->get('/admin/settings', \App\Controllers\SettingsController::class . ':show');
    $app->post('/admin/settings', \App\Controllers\SettingsController::class . ':update');

    $app->get('/admin/widget-ui', \App\Controllers\SettingsController::class . ':showWidgetUi');
    $app->post('/admin/widget-ui', \App\Controllers\SettingsController::class . ':update');

    $app->get('/admin/reports', \App\Controllers\ReportsController::class . ':show');

    $app->get('/admin/chatlogs', \App\Controllers\SettingsController::class . ':listChatlogs');
    $app->get('/admin/chatlogs/{session_id}', \App\Controllers\SettingsController::class . ':showChatlogDetail');

    $app->get('/admin/api/models', \App\Controllers\ApiController::class . ':listModels');

    $app->get('/admin/text', \App\Controllers\KnowledgeController::class . ':listText');
    $app->post('/admin/text', \App\Controllers\KnowledgeController::class . ':saveText');
    $app->get('/admin/text/delete/{id}', \App\Controllers\KnowledgeController::class . ':deleteText');

    $app->get('/admin/qa', \App\Controllers\KnowledgeController::class . ':listQa');
    $app->post('/admin/qa', \App\Controllers\KnowledgeController::class . ':saveQa');
    $app->get('/admin/qa/delete/{id}', \App\Controllers\KnowledgeController::class . ':deleteQa');

    $app->get('/admin/products', \App\Controllers\KnowledgeController::class . ':listProducts');
    $app->post('/admin/products/sync/prepare', \App\Controllers\KnowledgeController::class . ':prepareSync');
    $app->post('/admin/products/sync/chunk', \App\Controllers\KnowledgeController::class . ':processSyncChunk');
    $app->post('/admin/products/sync/finalize', \App\Controllers\KnowledgeController::class . ':finalizeSync');
    $app->post('/admin/products/delete-all', \App\Controllers\KnowledgeController::class . ':deleteAllProducts');
    $app->post('/admin/products/set-image', \App\Controllers\KnowledgeController::class . ':setProductImage');

    $app->get('/admin/agent-functions', \App\Controllers\AgentFunctionController::class . ':index');
    $app->post('/admin/agent-functions', \App\Controllers\AgentFunctionController::class . ':create');
    $app->delete('/admin/agent-functions/{id}', \App\Controllers\AgentFunctionController::class . ':delete');

    $app->post('/api/chat', \App\Controllers\ChatController::class . ':handleChat');
    $app->post('/api/chat/detect-image', \App\Controllers\ChatController::class . ':detectImage');
    $app->post('/api/chat/visual-search', \App\Controllers\ChatController::class . ':visualSearch');
    $app->post('/api/chat/log', \App\Controllers\ChatController::class . ':handleLogFallback');
    $app->get('/api/chat/history', \App\Controllers\WidgetController::class . ':getHistory');
    $app->get('/api/widget/config', \App\Controllers\WidgetController::class . ':getConfig');
};
