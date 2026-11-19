<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ApproverController extends Controller
{
    /**
     * Display users eligible to receive quotation approval assignments.
     */
    public function index(): JsonResponse
    {
        $approvers = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('roles.is_active', true)
            ->whereIn('roles.name', [
                'Super Admin',
                'Finance Manager',
            ])
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'roles.name as role_name',
            ])
            ->orderBy('users.name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Approvers retrieved successfully.',
            'data' => $approvers,
        ]);
    }
}
