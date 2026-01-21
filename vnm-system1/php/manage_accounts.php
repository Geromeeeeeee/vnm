<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Path Logic
    if (file_exists('db.php')) { include 'db.php'; } 
    elseif (file_exists('../db.php')) { include '../db.php'; }

    // Logic Handlers
    if(isset($_GET['archive_id'])){
        $id = intval($_GET['archive_id']);
        mysqli_query($conn, "UPDATE users SET is_archived = 1 WHERE user_id = $id");
        header("Location: manage_accounts.php");
        exit();
    }
    if(isset($_GET['restore_id'])){
        $id = intval($_GET['restore_id']);
        mysqli_query($conn, "UPDATE users SET is_archived = 0 WHERE user_id = $id");
        header("Location: manage_accounts.php");
        exit();
    }
    if(isset($_GET['delete_id'])){
        $id = intval($_GET['delete_id']);
        
        // Delete the user directly - rental data will be preserved in the database
        // The rental_requests will still exist and show in rental_summary for historical sales data
        mysqli_query($conn, "DELETE FROM users WHERE user_id = $id");
        
        header("Location: manage_accounts.php");
        exit();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts | VNM Admin</title>
    <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    
    <style>
        main { padding: 40px; width: 100%; box-sizing: border-box; }
        
        .section-container { 
            background: white; 
            padding: 30px; 
            border-radius: 15px; 
            box-shadow: 0px 5px 20px rgba(0,0,0,0.05); 
            margin-bottom: 40px; 
        }

        .full-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        
        .full-table th { 
            background: #f8f9fa; 
            padding: 18px 15px; 
            text-align: left; 
            border-bottom: 2px solid #eee; 
            font-size: 13px; 
            color: #111;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .full-table td { 
            padding: 20px 15px; 
            border-bottom: 1px solid #f0f0f0; 
            font-size: 15px;
            color: #444;
        }

        .active-header { border-left: 5px solid #2ecc71; padding-left: 15px; font-weight: bold; }
        .archive-header { border-left: 5px solid #f39c12; padding-left: 15px; color: #666; font-weight: bold; }

        .btn-group { display: flex; gap: 8px; justify-content: flex-end; }
        
        .action-btn { 
            width: 38px; 
            height: 38px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border-radius: 8px; 
            cursor: pointer; 
            border: 1px solid #ddd; 
            background: white; 
            transition: 0.3s ease;
            text-decoration: none;
            color: #555;
        }

        .edit-btn:hover { background: #3498db; color: white; border-color: #3498db; }
        .archive-btn:hover { background: #f39c12; color: white; border-color: #f39c12; }
        .restore-btn:hover { background: #2ecc71; color: white; border-color: #2ecc71; }
        .delete-btn:hover { background: #e74c3c; color: white; border-color: #e74c3c; }

        .license-box {
            background: #fff5f5;
            color: #d63031;
            padding: 4px 10px;
            border-radius: 5px;
            font-family: monospace;
            font-weight: bold;
            font-size: 14px;
        }

        #searchActive {
            padding: 12px 20px;
            border-radius: 25px;
            border: 1px solid #ddd;
            width: 300px;
            outline: none;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">

 <aside class="main-sidebar sidebar-light-primary elevation-4 layout-fixed">
  <a href="/vnm-system1/php/adminindex.php" class="brand-link">
    <img src="/vnm-system1/photos/VNM logo.png" 
         alt="VNM Logo" 
         class="brand-image img-square "
         style="opacity: .8">
    <span class="brand-text font-weight-light">VNM Admin</span>
  </a>
  <div class="sidebar">
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" 
          data-widget="treeview" role="menu" data-accordion="false">
        <li class="nav-item">
          <a href="/vnm-system1/php/adminindex.php" class="nav-link">
            <p>Dashboard</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="/vnm-system1/php/cars/cars.php" class="nav-link">
            <p>Cars</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="/vnm-system1/php/rentals.php" class="nav-link">
            <p>Rentals</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="/vnm-system1/php/car_lifecycle.php" class="nav-link">
            <p>Car Status</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="/vnm-system1/php/manage_accounts.php" class="nav-link bg-gray">
            <p>Accounts</p>
          </a>
        </li>
      </ul>
    </nav>
  </div>
</aside>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Manage Accounts</h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">Active Customer Accounts</h3>
                    <input type="text" id="searchActive" placeholder="Search by name, email, or license..." class="form-control" style="width: 300px;">
                </div>
                <div class="card-body table-responsive p-0" style="max-height: 500px;">
                    <table class="table table-hover table-bordered text-center">
                        <thead>
                            <tr>
                                <th>UID</th>
                                <th>Full Name</th>
                                <th>Contact Info</th>
                                <th>Home Address</th>
                                <th>License</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="activeBody">
                            <?php
                            $res = mysqli_query($conn, "SELECT * FROM users WHERE is_archived = 0 ORDER BY user_id DESC");
                            while($row = mysqli_fetch_assoc($res)):
                            ?>
                            <tr>
                                <td>#<?php echo $row['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                <td>
                                    <div><i class="fas fa-envelope"></i> <?php echo $row['email']; ?></div>
                                    <div><i class="fas fa-phone"></i> <?php echo $row['phone']; ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($row['address']); ?></td>
                                <td><span class="license-box"><?php echo htmlspecialchars($row['license']); ?></span></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="action-btn edit-btn" onclick='openEditModal(<?php echo json_encode($row); ?>)' title="Edit Profile"><i class="fas fa-user-edit"></i></button>
                                        <a href="manage_accounts.php?archive_id=<?php echo $row['user_id']; ?>" class="action-btn archive-btn" title="Archive"><i class="fas fa-archive"></i></a>
                                        <a href="manage_accounts.php?delete_id=<?php echo $row['user_id']; ?>" class="action-btn delete-btn" onclick="return confirm('Delete this user permanently?')" title="Delete"><i class="fas fa-trash-alt"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="card-title">Archived Accounts</h3>
                </div>
                <div class="card-body table-responsive p-0" style="max-height: 500px;">
                    <table class="table table-hover table-bordered text-center">
                        <thead>
                            <tr>
                                <th>UID</th>
                                <th>Full Name</th>
                                <th>Contact Info</th>
                                <th>License</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $res = mysqli_query($conn, "SELECT * FROM users WHERE is_archived = 1 ORDER BY user_id DESC");
                            if(mysqli_num_rows($res) == 0) echo "<tr><td colspan='5' class='text-center py-5 text-muted'>No archived records.</td></tr>";
                            while($row = mysqli_fetch_assoc($res)):
                            ?>
                            <tr style="opacity: 0.7;">
                                <td>#<?php echo $row['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                <td><?php echo $row['email']; ?></td>
                                <td><span class="license-box"><?php echo htmlspecialchars($row['license']); ?></span></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="action-btn edit-btn" onclick='openEditModal(<?php echo json_encode($row); ?>)'><i class="fas fa-edit"></i></button>
                                        <a href="manage_accounts.php?restore_id=<?php echo $row['user_id']; ?>" class="action-btn restore-btn" title="Restore"><i class="fas fa-undo"></i></a>
                                        <a href="manage_accounts.php?delete_id=<?php echo $row['user_id']; ?>" class="action-btn delete-btn" onclick="return confirm('Delete permanently?')"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</div>

<div id="editUserModal" class="modal" style="display:none; position:fixed; top:0; left:0; z-index: 9999; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(3px); justify-content:center; align-items:center;">
    <div class="modal-content" style="background: white; padding: 40px; width: 40vw; max-height:85vh; overflow:auto; scrollbar-width:thin; border-radius: 20px; box-shadow: 0 15px 50px rgba(0,0,0,0.3);">
        <h3 style="margin-top: 0; font-family: var(--primary-font);">Update Customer Information</h3>
        <p style="font-size: 13px; color: #888; margin-bottom: 25px;">Ensure all details are accurate before saving.</p>
        
        <form action="update_user.php" method="POST">
            <input type="hidden" name="user_id" id="modal_user_id">
            
            <div style="margin-bottom: 20px;">
                <label style="font-size: 11px; font-weight: bold; color: #999; display: block; margin-bottom: 8px;">FULL NAME</label>
                <input type="text" name="fullname" id="modal_fullname" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 10px; box-sizing: border-box;" required>
            </div>

            <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                <div style="flex: 1;">
                    <label style="font-size: 11px; font-weight: bold; color: #999; display: block; margin-bottom: 8px;">EMAIL ADDRESS</label>
                    <input type="email" name="email" id="modal_email" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 10px; box-sizing: border-box;" required>
                </div>
                <div style="flex: 1;">
                    <label style="font-size: 11px; font-weight: bold; color: #999; display: block; margin-bottom: 8px;">PHONE NUMBER</label>
                    <input type="text" name="phone" id="modal_phone" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 10px; box-sizing: border-box;" required>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="font-size: 11px; font-weight: bold; color: #999; display: block; margin-bottom: 8px;">DRIVER'S LICENSE NO.</label>
                <input type="text" name="license" id="modal_license" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 10px; box-sizing: border-box;" required>
            </div>

            <div style="margin-bottom: 30px;">
                <label style="font-size: 11px; font-weight: bold; color: #999; display: block; margin-bottom: 8px;">HOME ADDRESS</label>
                <textarea name="address" id="modal_address" rows="3" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 10px; box-sizing: border-box;"></textarea>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="button" onclick="closeModal()" style="flex: 1; padding: 14px; border-radius: 10px; border: 1px solid #ddd; background: #f9f9f9; cursor: pointer; font-weight: bold;">Cancel</button>
                <button type="submit" style="flex: 2; padding: 14px; border-radius: 10px; border: none; background: #111; color: white; cursor: pointer; font-weight: bold;">UPDATE ACCOUNT</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Search function for the active table
    document.getElementById('searchActive').addEventListener('keyup', function() {
        let val = this.value.toLowerCase();
        let rows = document.querySelectorAll('#activeBody tr');
        rows.forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(val) ? '' : 'none';
        });
    });

    // OPEN MODAL FUNCTION (Fixed for reliability)
    function openEditModal(userData) {
        document.getElementById('modal_user_id').value = userData.user_id;
        document.getElementById('modal_fullname').value = userData.fullname;
        document.getElementById('modal_email').value = userData.email;
        document.getElementById('modal_phone').value = userData.phone;
        document.getElementById('modal_license').value = userData.license;
        document.getElementById('modal_address').value = userData.address;
        
        document.getElementById('editUserModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('editUserModal').style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == document.getElementById('editUserModal')) {
            closeModal();
        }
    }
</script>

</body>
</html>