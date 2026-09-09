<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;

class LandingController extends Controller
{
    /**
     * صفحه فرود عمومی پروژه
     */
    public function index()
    {
        return view('front.landing');
    }
}
