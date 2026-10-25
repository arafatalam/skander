<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\GetDashboard;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(GetDashboard $action): Response
    {
        return Inertia::render('Dashboard', ['overview' => $action->get()]);
    }
}
