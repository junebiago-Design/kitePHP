<?php

namespace Apps\Controllers;

use Core\Controller;
use Apps\Models\User;

class UserController extends Controller
{
    protected User $user;

    public function __construct(string $baseUrl = '')
    {
        parent::__construct($baseUrl);

        $this->user = new User();
    }

    /**
     * GET /users
     *
     * Display all users.
     */
    public function index(): string
    {
        $users = $this->user->all();

        return $this->view('users/index', [
            'users' => $users
        ]);
    }



    /**
     * GET /users/create
     *
     * Display create form.
     */
    public function create(): string
    {
        return $this->view('users/create');
    }

    /**
     * POST /users
     *
     * Insert a new user.
     */
    public function store(): void
    {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if ($username === '' || $email === '') {
            header('Location: ' . $this->baseUrl . '/users/create');
            exit;
        }

        $this->user->create(
            $username,
            $email
        );

        header('Location: ' . $this->baseUrl . '/users');
        exit;
    }

    /**
     * GET /users/edit/{id}
     *
     * Display edit form.
     */
    public function edit(int $id): string
    {
        $user = $this->user->find($id);

        if (!$user) {
            http_response_code(404);

            return $this->view('users/not-found');
        }

        return $this->view('users/edit', [
            'user' => $user
        ]);
    }

    
    public function find(int $id): string
    {
        $user = $this->user->find($id);

        if (!$user) {
            http_response_code(404);

            return $this->view('users/not-found');
        }

        return $this->view('users/find', [
            'user' => $user
        ]);
    }


    /**
     * POST /users/update/{id}
     *
     * Update user.
     */
    public function update(int $id): void
    {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if ($username === '' || $email === '') {
            header(
                'Location: ' .
                $this->baseUrl .
                '/users/edit/' .
                $id
            );

            exit;
        }

        $this->user->update(
            $id,
            $username,
            $email
        );

        header('Location: ' . $this->baseUrl . '/users');
        exit;
    }

    /**
     * POST /users/delete/{id}
     *
     * Delete user.
     */
    public function delete(int $id): void
    {
        $this->user->delete($id);

        header('Location: ' . $this->baseUrl . '/users');
        exit;
    }
}