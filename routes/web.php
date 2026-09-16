<?php

use App\Http\Controllers\Admin\AssetController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\KnowledgeArticleController as AdminKnowledgeArticleController;
use App\Http\Controllers\Admin\PriorityMatrixController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\RequestTemplateController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\SlaPolicyController;
use App\Http\Controllers\Admin\UserAccessController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KnowledgeArticleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceStatusController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Fast reporting (section 5)
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket:ticket_number}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket:ticket_number}/comments', [TicketController::class, 'storeComment'])->name('tickets.comments.store');
    Route::post('/tickets/{ticket:ticket_number}/transition', [TicketController::class, 'transition'])->name('tickets.transition');
    Route::post('/tickets/{ticket:ticket_number}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
    Route::post('/tickets/{ticket:ticket_number}/priority', [TicketController::class, 'overridePriority'])->name('tickets.priority');
    Route::post('/tickets/{ticket:ticket_number}/share', [TicketController::class, 'share'])->name('tickets.share');
    Route::post('/tickets/{ticket:ticket_number}/approvals/{approval}', [TicketController::class, 'decideApproval'])->name('tickets.approvals.decide');
    Route::post('/tickets/{ticket:ticket_number}/checklist/{task}', [TicketController::class, 'completeChecklistTask'])->name('tickets.checklist.complete');
    Route::post('/tickets/{ticket:ticket_number}/satisfaction', [TicketController::class, 'submitSatisfaction'])->name('tickets.satisfaction');
    Route::get('/attachments/{attachment}', [TicketAttachmentController::class, 'show'])->name('attachments.show');
    Route::get('/tickets-export.csv', [TicketExportController::class, 'export'])->name('tickets.export');

    // QR-code entry point: identifies the asset/site context, still requires login.
    Route::get('/report/asset/{asset:tag}', [TicketController::class, 'createForAsset'])->name('tickets.create.asset');

    Route::get('/service-status', [ServiceStatusController::class, 'index'])->name('service-status.index');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('/knowledge', [KnowledgeArticleController::class, 'index'])->name('knowledge.index');
    Route::get('/knowledge/{knowledgeArticle:slug}', [KnowledgeArticleController::class, 'show'])->name('knowledge.show');

    Route::middleware('can:manage-admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('companies', CompanyController::class)->except(['show']);
        Route::resource('projects', ProjectController::class)->except(['show']);
        Route::resource('sites', SiteController::class)->except(['show']);
        Route::resource('calendars', CalendarController::class)->except(['show']);
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('sla-policies', SlaPolicyController::class)->except(['show']);
        Route::get('priority-matrix', [PriorityMatrixController::class, 'index'])->name('priority-matrix.index');
        Route::put('priority-matrix', [PriorityMatrixController::class, 'update'])->name('priority-matrix.update');
        Route::resource('vendors', VendorController::class)->except(['show']);
        Route::resource('assets', AssetController::class)->except(['show']);
        Route::resource('users', UserController::class)->except(['destroy', 'show']);
        Route::post('users/{user}/access-grants', [UserAccessController::class, 'store'])->name('users.access-grants.store');
        Route::delete('access-grants/{accessGrant}', [UserAccessController::class, 'revoke'])->name('access-grants.revoke');
        Route::resource('knowledge-articles', AdminKnowledgeArticleController::class)->except(['show']);
        Route::get('request-templates', [RequestTemplateController::class, 'index'])->name('request-templates.index');
        Route::post('request-templates/{key}', [RequestTemplateController::class, 'store'])->name('request-templates.store');
    });
});

require __DIR__.'/auth.php';
