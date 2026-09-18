<?php

namespace Apps\Controllers;

use Core\Controller;

class HomeController extends Controller
{
    public function index(): string
    {
        return $this->view('welcome', [
            'projectName' => 'Demo',
            'namespace'   => 'Apps',
        ]);
    }

     public function shit(): string
    {
        return $this->view('database', [
            'projectName' => 'Database | DEMO',
            
        ]);
    }
}