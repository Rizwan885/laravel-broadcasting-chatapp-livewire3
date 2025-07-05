<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index()
    {
        $users = User::where('id', '!=', Auth::user()->id)->withCount(['unreadMessages'])->get();
        return view('dashboard', compact('users'));
    }

    public function userChat(int $userId)
    {

        return view('user-chat', compact('userId'));
    }
}
