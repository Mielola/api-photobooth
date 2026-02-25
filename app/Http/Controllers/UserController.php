<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    // get profile
    public function index(){
        $user = Auth::user();
        return response()->json([
            'status' => 'success',
            'data' => $user
        ]);
    }
}
