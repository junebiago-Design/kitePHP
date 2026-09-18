<?php

namespace Apps\Controllers;

use Core\Controller;

class ContactController extends Controller
{
    public function index(): string
    {
        return $this->view('contact', [
            'projectName' => 'Demo',
            'namespace'   => 'Apps\Controllers',
            'viewName'    => 'contact',
        ]);
    }
}