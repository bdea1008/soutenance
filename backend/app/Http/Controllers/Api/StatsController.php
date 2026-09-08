<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Contribution;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Statistiques générales de la plateforme, affichées sur la page d'accueil
 * publique (§2, contenus de mise en confiance).
 */
class StatsController extends Controller
{
    public function index(): JsonResponse
    {
        $publicStatuses = array_map(fn (ProjectStatus $s) => $s->value, ProjectStatus::publicStatuses());

        return response()->json([
            'projects_total' => Project::whereIn('status', $publicStatuses)->count(),
            'projects_funded' => Project::whereIn('status', [
                ProjectStatus::Funded->value,
                ProjectStatus::InProgress->value,
                ProjectStatus::Completed->value,
            ])->count(),
            'total_raised' => (int) Project::whereIn('status', $publicStatuses)->sum('amount_raised'),
            'investors_count' => User::where('role', UserRole::Investor->value)->count(),
            'promoters_count' => User::where('role', UserRole::Promoter->value)->count(),
            'contributions_count' => Contribution::count(),
            'currency' => 'XOF',
        ]);
    }
}
