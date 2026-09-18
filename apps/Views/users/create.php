
<?= $this->assets('css'); ?>
<div class="container mt-4">

    <h1 class="mb-4">Create User</h1>

    <form
        method="POST"
        action="<?= htmlspecialchars($baseUrl) ?>/users"
    >

        <div class="mb-3">

            <label
                for="username"
                class="form-label"
            >
                Username
            </label>

            <input
                type="text"
                id="username"
                name="username"
                class="form-control"
                required
            >

        </div>

        <div class="mb-3">

            <label
                for="email"
                class="form-label"
            >
                Email
            </label>

            <input
                type="email"
                id="email"
                name="email"
                class="form-control"
                required
            >

        </div>

        <button
            type="submit"
            class="btn btn-primary"
        >
            Save User
        </button>

        <a
            href="<?= htmlspecialchars($baseUrl) ?>/users"
            class="btn btn-secondary"
        >
            Cancel
        </a>

    </form>

</div>

