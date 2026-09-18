<?php

$users =$user['id'];
?>

<?= $this->assets('css'); ?>


<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h1>Users</h1>

        <a
            href="<?= htmlspecialchars($baseUrl) ?>/users/create"
            class="btn btn-primary"
        >
            Add User
        </a>

    </div>


        <div class="alert alert-info">
            No users found.
        </div>

   

        <div class="table-responsive">

            <table class="table table-bordered table-hover">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Created</th>
                        <th width="180">Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                (string) $user['id']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $user['username']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $user['email']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $user['created_at'] ?? ''
                            ) ?>
                        </td>

                        <td>

                            <a
                                href="<?= htmlspecialchars($baseUrl) ?>/users/edit/<?= (int) $user['id'] ?>"
                                class="btn btn-sm btn-warning"
                            >
                                Edit
                            </a>

                            <form
                                method="POST"
                                action="<?= htmlspecialchars($baseUrl) ?>/users/delete/<?= (int) $user['id'] ?>"
                                style="display:inline"
                                onsubmit="return confirm('Delete this user?');"
                            >

                                <button
                                    type="submit"
                                    class="btn btn-sm btn-danger"
                                >
                                    Delete
                                </button>

                            </form>

                        </td>

                    </tr>

             

                </tbody>

            </table>

        </div>


</div>