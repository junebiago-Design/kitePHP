<?php

namespace Apps\Controllers;

use Core\Controller;

class DbCodeGeneratorController extends Controller
{
    public function index(): string
    {
        return $this->view('dbcodegenerator', [
            'projectName' => 'Demo',
            'namespace'   => 'Apps\Controllers',
            'viewName'    => 'dbcodegenerator',
        ]);
    }
}