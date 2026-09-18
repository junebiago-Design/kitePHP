<?php

namespace Apps\Controllers;

use Core\Controller;

class TestController extends Controller
{
    public function index(): string
    {
        return $this->view('test', [
            'projectName' => 'Demo',
            'namespace'   => 'Apps\Controllers',
            'viewName'    => 'test',
        ]);
    }
}