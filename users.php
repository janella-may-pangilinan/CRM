<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch current user role
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_role = $stmt->get_result();
$currentUser = $result_role->fetch_assoc();
$stmt->close();

// Only admins can access
if (!$currentUser || $currentUser['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// ADD USER
if (isset($_POST['add_user'])) {
    $name     = $_POST['name'];
    $email    = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role     = $_POST['role'];

    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $password, $role);
    $stmt->execute();
    $stmt->close();

    header("Location: users.php");
    exit();
}

// UPDATE USER
if (isset($_POST['update_user'])) {
    $id    = intval($_POST['id']);
    $name  = $_POST['name'];
    $email = $_POST['email'];
    $role  = $_POST['role'];

    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, password=?, role=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $email, $password, $role, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
        $stmt->bind_param("sssi", $name, $email, $role, $id);
    }
    $stmt->execute();
    $stmt->close();

    header("Location: users.php");
    exit();
}

// DELETE USER (prevent self-delete)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id !== $_SESSION['user_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: users.php");
    exit();
}

// --- SEARCH FILTER ---
$search = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $stmt = $conn->prepare("SELECT id, name, email, role, created_at FROM users WHERE name LIKE ? OR email LIKE ? ORDER BY id DESC");
    $stmt->bind_param("ss", $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY id DESC");
}

// IF EDIT MODE
$editUser = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Manage Users</title>
    <style>

            :root {
            --primary: #4a6cf7;
            --primary-dark: #3a5ad9;
            --secondary: #6c757d;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --sidebar-width: 230px;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }


        body {
            margin: 0; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            display: flex; 
            height: 100vh; 
            background: #f3f4f6;
        }
       .sidebar {
    width: var(--sidebar-width);
    background: var(--primary);
    color: white;
    display: flex;
    flex-direction: column;
    padding-top: 20px;
    transition: var(--transition);
    z-index: 1000;
}

.sidebar h2 {
    text-align: center;
    margin-bottom: 30px;
    font-size: 22px;
    padding: 0 15px;
}

.sidebar a {
    color: white;
    padding: 12px 20px;
    text-decoration: none;
    display: flex;
    align-items: center;
    transition: var(--transition);
    border-radius: 6px;
    margin: 4px 10px;
}

.sidebar a i {
    margin-right: 10px;
    width: 20px;
    text-align: center;
}

.sidebar a:hover {
    background: var(--primary-dark);
}

.sidebar a.active {
    background: white;
    color: var(--primary);
}
        .main-content { 
            flex: 1; 
            padding: 25px; 
            overflow-y: auto; 
        }
        .header {
            background: white; 
            padding: 18px 25px; 
            border-radius: 12px; 
            box-shadow: 0 2px 6px rgba(0,0,0,0.08); 
            margin-bottom: 25px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
        }
        .header h2 { margin: 0; font-size: 24px; color: #333; }
        .card {
            background: white; 
            padding: 20px; 
            border-radius: 14px; 
            box-shadow: 0 2px 6px rgba(0,0,0,0.08); 
            margin-bottom: 20px;
        }
        .card h3 { 
            margin-top: 0; 
            margin-bottom: 12px; 
            font-size: 20px; 
            color: #444; 
        }
        table {
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px;
        }
        table th, table td {
            border: 1px solid #ddd; 
            padding: 12px; 
            text-align: left;
        }
        th {
            background: #007BFF; 
            color: white; 
            font-size: 14px;
        }
        tr:nth-child(even) { background: #f9f9f9; }

        .btn {
            padding: 6px 12px; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-edit { background: #ffc107; color: white; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-add { 
            background: #28a745; 
            color: white; 
            padding: 10px 15px; 
            margin-top: 10px; 
            border-radius: 8px; 
            display: inline-block; 
            font-weight: bold;
            border: none;
            cursor: pointer;
        }
        input, select, textarea {
            width: 95%; 
            padding: 10px; 
            margin: 5px 0; 
            border: 1px solid #ccc; 
            border-radius: 8px; 
            font-size: 14px;
        }
        .search-bar { 
            margin-bottom: 15px; 
            display: flex; 
            gap: 10px; 
            align-items: center;
        }
        .search-bar input { 
            flex: 1; 
            margin: 0;
        }
        .cancel-btn { 
            margin-left: 10px; 
            color: #555; 
            text-decoration: none; 
            padding: 8px 12px;
            border-radius: 6px;
            background: #f8f9fa;
            border: 1px solid #ddd;
        }
        .cancel-btn:hover {
            background: #e9ecef;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
    <h2>CRM</h2>
     <a href="dashboard.php"><i class="fas fa-chart-line"></i> <span>Dashboard</span></a>
    <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
   
       <a href="users.php" class="active" ><i class="fas fa-user-cog"></i> <span>Users</span></a>
  
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>
    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h2>Manage Users</h2>
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?> 👋</span>
        </div>

        <!-- Add / Edit User Form -->
        <div class="card">
            <?php if ($editUser) { ?>
                <h3>Edit User</h3>
                <form method="POST">
                    <input type="hidden" name="id" value="<?= $editUser['id']; ?>">
                    <input type="text" name="name" value="<?= htmlspecialchars($editUser['name']); ?>" required><br>
                    <input type="email" name="email" value="<?= htmlspecialchars($editUser['email']); ?>" required><br>
                    <input type="password" name="password" placeholder="New Password (leave blank to keep current)"><br>
                    <select name="role" required>
                        <option value="admin" <?= $editUser['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="user" <?= $editUser['role'] === 'user' ? 'selected' : ''; ?>>Manager</option>
                    </select><br>
                    <button type="submit" name="update_user" class="btn-add">Update User</button>
                    <a href="users.php" class="cancel-btn">Cancel</a>
                </form>
            <?php } else { ?>
                <h3>Add New User</h3>
                <form method="POST">
                    <input type="text" name="name" placeholder="Full Name" required><br>
                    <input type="email" name="email" placeholder="Email" required><br>
                    <input type="password" name="password" placeholder="Password" required><br>
                    <select name="role" required>
                        <option value="admin">Admin</option>
                        <option value="user">Manager</option>
                    </select><br>
                    <button type="submit" name="add_user" class="btn-add">+ Add User</button>
                </form>
            <?php } ?>
        </div>

        <!-- Users Table -->
        <div class="card">
            <h3>User List</h3>

            <!-- 🔍 Search bar -->
            <form method="GET" class="search-bar">
                <input type="text" name="search" placeholder="Search by name or email" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                <button type="submit" class="btn-add">Search</button>
                <a href="users.php" class="cancel-btn">Reset</a>
            </form>

            <table>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
                <?php while ($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']); ?></td>
                    <td><?= htmlspecialchars($row['name']); ?></td>
                    <td><?= htmlspecialchars($row['email']); ?></td>
                    <td><?= ucfirst(htmlspecialchars($row['role'])); ?></td>
                    <td><?= htmlspecialchars($row['created_at']); ?></td>
                    <td>
                        <?php if ($row['id'] != $_SESSION['user_id']) { ?>
                            <a href="users.php?edit=<?= $row['id']; ?>" class="btn btn-edit">✏ Edit</a>
                            <a href="users.php?delete=<?= $row['id']; ?>" 
                               class="btn btn-delete" 
                               onclick="return confirm('Are you sure you want to delete this user?');">
                               🗑 Delete
                            </a>
                        <?php } else { ?>
                            <em>(You)</em>
                        <?php } ?>
                    </td>
                </tr>
                <?php } ?>
            </table>
        </div>
    </div>
</body>
</html>