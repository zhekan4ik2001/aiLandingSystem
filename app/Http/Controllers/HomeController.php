<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    /**
     * Show homepage with map.
     */
    public function index()
    {
        return view('home');
    }
}
