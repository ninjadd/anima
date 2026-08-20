<?php

namespace Anima\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class WorkbenchController
{
    /**
     * Display the Anima Workbench SPA.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request): View
    {
        return view('anima::workbench');
    }
}
