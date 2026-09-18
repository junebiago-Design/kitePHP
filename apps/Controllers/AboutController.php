<?php

namespace Apps\Controllers;

use Core\Controller;

class AboutController extends Controller
{
    public function index(): string
    {
        return $this->view('about', [
            'projectName' => 'Demo',
            'namespace'   => 'Apps\Controllers',
            'viewName'    => 'about',
        ]);
    }
}