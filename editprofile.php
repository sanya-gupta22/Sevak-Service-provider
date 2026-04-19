<?php
session_start();
require_once '../includes/config.php'; // Database connection

// Check if professional is logged in
if (!isset($_SESSION['professional_id'])) {
    header("Location: ../login.php");
    exit();
}

$professional_id = $_SESSION['professional_id'];

// Verify professional exists
$stmt = $conn->prepare("SELECT id FROM professionals WHERE id = ?");
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $professional_id);
if (!$stmt->execute()) {
    die("Database error: " . $stmt->error);
}
$stmt->store_result();
if ($stmt->num_rows == 0) {
    // Professional doesn't exist
    session_destroy();
    header("Location: ../login.php");
    exit();
}
$stmt->close();

// Function to sanitize input data
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Initialize variables
$errors = [];
$success = "";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic Information Update
    if (isset($_POST['update_basic_info'])) {
        $name = sanitizeInput($_POST['name']);
        $gender = sanitizeInput($_POST['gender']);
        $email = sanitizeInput($_POST['email']);
        $contact = sanitizeInput($_POST['contact']);
        $dob = sanitizeInput($_POST['dob']);
        $city = sanitizeInput($_POST['city']);
        $state = sanitizeInput($_POST['state']);
        $pincode = sanitizeInput($_POST['pincode']);
        $bio = sanitizeInput($_POST['bio']);
        $address = sanitizeInput($_POST['address']);

        // Validate inputs
        if (empty($name)) {
            $errors[] = "Name is required";
        } elseif (strlen($name) > 100) {
            $errors[] = "Name must be less than 100 characters";
        }

        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        } elseif (strlen($email) > 255) {
            $errors[] = "Email must be less than 255 characters";
        }

        if (empty($contact)) {
            $errors[] = "Contact number is required";
        } elseif (!preg_match('/^[0-9]{10,15}$/', $contact)) {
            $errors[] = "Invalid contact number (10 digits only)";
        }

        if (!empty($dob)) {
            $dobDate = new DateTime($dob);
            $today = new DateTime();
            $age = $today->diff($dobDate)->y;
            
            if ($age < 18) {
                $errors[] = "You must be at least 18 years old";
            } elseif ($age > 100) {
                $errors[] = "Please enter a valid date of birth";
            }
        }

        if (!empty($city) && strlen($city) > 100) {
            $errors[] = "City must be less than 100 characters";
        }

        if (!empty($state) && strlen($state) > 100) {
            $errors[] = "State must be less than 100 characters";
        }

        if (!empty($pincode)) {
            if (!preg_match('/^[0-9]{6}$/', $pincode)) {
                $errors[] = "Pincode must be 6 digits";
            }
        }

        if (!empty($bio) && strlen($bio) > 500) {
            $errors[] = "Bio must be less than 500 characters";
        }

        if (!empty($address) && strlen($address) > 255) {
            $errors[] = "Address must be less than 255 characters";
        }

        if (empty($errors)) {
            // Proceed with database update
            $stmt = $conn->prepare("UPDATE professionals SET name=?, gender=?, email=?, contact=?, dob=?, city=?, state=?, pincode=?, bio=?, address=? WHERE id=?");
            if (!$stmt) {
                $errors[] = "Database error: " . $conn->error;
            } else {
                $stmt->bind_param("ssssssssssi", $name, $gender, $email, $contact, $dob, $city, $state, $pincode, $bio, $address, $professional_id);

                if ($stmt->execute()) {
                    // Also update user_db table
                    $stmt_user = $conn->prepare("UPDATE user_db SET name=?, email=?, contact=? WHERE id=? AND user_type='professional'");
                    if ($stmt_user) {
                        $stmt_user->bind_param("sssi", $name, $email, $contact, $professional_id);
                        $stmt_user->execute();
                        $stmt_user->close();
                    }
                    
                    $success = "Basic information updated successfully!";
                } else {
                    $errors[] = "Error updating basic information: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
    
    // Profile Image Upload
        if (isset($_POST['upload_profile_image'])) {
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_image'];
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 2 * 1024 * 1024; // 2MB
                
                if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
                    $upload_dir = '../professional/uploads/profiles/';
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Delete all previous profile images for this user
                    if (!empty($professional['profile_image'])) {
                        $old_files = glob($upload_dir . 'prof_' . $professional_id . '_*');
                        foreach ($old_files as $old_file) {
                            if (is_file($old_file)) {
                                unlink($old_file);
                            }
                        }
                    }
                    
                    // Generate unique filename with client ID and timestamp
                    $imageFileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $file_name = 'prof_' . $professional_id . '_' . time() . '.' . $imageFileType;
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        // Update database with just the filename (not full path)
                        $stmt = $conn->prepare("UPDATE professionals SET profile_image=? WHERE id=?");
                        if ($stmt) {
                            $stmt->bind_param("si", $file_name, $professional_id);
                            
                            if ($stmt->execute()) {
                                $success = "Profile image updated successfully!";
                                // Update the professional array to show the new image immediately
                                $professional['profile_image'] = $file_name;
                            } else {
                                $errors[] = "Error updating profile image in database: " . $stmt->error;
                                // Delete the uploaded file if DB update fails
                                if (file_exists($file_path)) {
                                    unlink($file_path);
                                }
                            }
                            $stmt->close();
                        } else {
                            $errors[] = "Database error: " . $conn->error;
                        }
                    } else {
                        $errors[] = "Error uploading profile image";
                    }
                } else {
                    $errors[] = "Invalid file type or size too large (max 2MB)";
                }
            } else {
                $errors[] = "No file uploaded or upload error";
            }
        }
    
    // Skills & Services Update
    if (isset($_POST['update_services'])) {
        // First delete existing services for this professional
        $stmt = $conn->prepare("DELETE FROM professional_pricing WHERE professional_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $professional_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Get the professional's profession from the professionals table
        $stmt_prof = $conn->prepare("SELECT profession FROM professionals WHERE id = ?");
        $stmt_prof->bind_param("i", $professional_id);
        $stmt_prof->execute();
        $result = $stmt_prof->get_result();
        $profession_data = $result->fetch_assoc();
        $profession = $profession_data['profession'] ?? '';
        $stmt_prof->close();
        
        // Insert new services
        if (isset($_POST['services']) && is_array($_POST['services'])) {
            $stmt = $conn->prepare("INSERT INTO professional_pricing (professional_id, profession, service_name, price, price_unit) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                $errors[] = "Database error: " . $conn->error;
            } else {
                foreach ($_POST['services'] as $service_key => $service) {
                    // Skip if this is the 'enabled' field (from checkbox)
                    if ($service_key === 'enabled') continue;
                    
                    $service_name = sanitizeInput($service['name']);
                    $price = floatval($service['price']);
                    $price_unit = sanitizeInput($service['unit']);
                    
                    $stmt->bind_param("issds", $professional_id, $profession, $service_name, $price, $price_unit);
                    if (!$stmt->execute()) {
                        $errors[] = "Error saving service: " . $stmt->error;
                    }
                }
                $stmt->close();
                
                if (empty($errors)) {
                    $success = "Services updated successfully!";
                }
            }
        }
    }
    
    // Experience Update
    if (isset($_POST['update_experience'])) {
        // Handle deletions first
        if (isset($_POST['delete_experience_id'])) {
            $exp_id = intval($_POST['delete_experience_id']);
            $stmt = $conn->prepare("DELETE FROM professional_experiences WHERE id = ? AND professional_id = ?");
            $stmt->bind_param("ii", $exp_id, $professional_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Experience deleted successfully!";
                header("Location: editprofile.php");
                exit();
            } else {
                $errors[] = "Error deleting experience: " . $stmt->error;
            }
            $stmt->close();
        }
        
        // Handle updates/inserts
        if (isset($_POST['experiences']) && is_array($_POST['experiences'])) {
            $stmt = $conn->prepare("INSERT INTO professional_experiences (id, professional_id, position, company, start_date, end_date, currently_working, description) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE 
                                position = VALUES(position),
                                company = VALUES(company),
                                start_date = VALUES(start_date),
                                end_date = VALUES(end_date),
                                currently_working = VALUES(currently_working),
                                description = VALUES(description)");
            
            if (!$stmt) {
                $errors[] = "Database error: " . $conn->error;
            } else {
                foreach ($_POST['experiences'] as $exp) {
                    $id = !empty($exp['id']) ? intval($exp['id']) : null;
                    $position = sanitizeInput($exp['position']);
                    $company = sanitizeInput($exp['company']);
                    $start_date = sanitizeInput($exp['start_date']);
                    $end_date = isset($exp['end_date']) ? sanitizeInput($exp['end_date']) : null;
                    $currently_working = isset($exp['currently_working']) ? 1 : 0;
                    $description = sanitizeInput($exp['description']);
                    
                    $stmt->bind_param("iissssis", $id, $professional_id, $position, $company, $start_date, $end_date, $currently_working, $description);
                    if (!$stmt->execute()) {
                        $errors[] = "Error saving experience: " . $stmt->error;
                    }
                }
                $stmt->close();
                
                if (empty($errors)) {
                    $_SESSION['success_message'] = "Work experience updated successfully!";
                    header("Location: editprofile.php");
                    exit();
                }
            }
        }
    }
    
    // Certification Update - Fixed version
    if (isset($_POST['update_certifications'])) {
        // First delete existing certifications for this professional
        $stmt = $conn->prepare("DELETE FROM professional_certifications WHERE professional_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $professional_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Insert new certifications if any were submitted
        if (isset($_POST['certifications']) && is_array($_POST['certifications'])) {
            $stmt = $conn->prepare("INSERT INTO professional_certifications 
                                (professional_id, certification_name, issuing_organization, issue_date, expiry_date, credential_id, credential_url) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            if (!$stmt) {
                $errors[] = "Database error: " . $conn->error;
            } else {
                foreach ($_POST['certifications'] as $cert) {
                    // Skip if this is an empty array element
                    if (empty($cert['name'])) continue;
                    
                    $cert_name = sanitizeInput($cert['name']);
                    $issuing_org = sanitizeInput($cert['organization']);
                    $issue_date = sanitizeInput($cert['issue_date']);
                    $expiry_date = !empty($cert['expiry_date']) ? sanitizeInput($cert['expiry_date']) : NULL;
                    $credential_id = !empty($cert['credential_id']) ? sanitizeInput($cert['credential_id']) : NULL;
                    $credential_url = !empty($cert['credential_url']) ? sanitizeInput($cert['credential_url']) : NULL;
                    
                    $stmt->bind_param("issssss", $professional_id, $cert_name, $issuing_org, $issue_date, $expiry_date, $credential_id, $credential_url);
                    if (!$stmt->execute()) {
                        $errors[] = "Error saving certification: " . $stmt->error;
                    }
                }
                $stmt->close();
                
                if (empty($errors)) {
                    $_SESSION['success_message'] = "Certifications updated successfully!";
                    header("Location: editprofile.php");
                    exit();
                }
            }
        } else {
            // No certifications submitted - just clear existing ones (already done above)
            $_SESSION['success_message'] = "Certifications updated successfully!";
            header("Location: editprofile.php");
            exit();
        }
    }
        
    // Document Upload
    if (isset($_POST['upload_document'])) {
        $document_type = sanitizeInput($_POST['document_type']);
        
        // Check if this document type already exists
        $stmt_check = $conn->prepare("SELECT id FROM professional_documents WHERE professional_id = ? AND document_type = ?");
        $stmt_check->bind_param("is", $professional_id, $document_type);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $errors[] = "You already have a document of this type uploaded. Please delete the existing one before uploading a new file.";
        } else {
            if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['document'];
                $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
                    $upload_dir = '../uploads/documents/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $file_name = 'doc_' . $professional_id . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        $stmt = $conn->prepare("INSERT INTO professional_documents (professional_id, document_type, file_name, file_path, file_type, verification_status) VALUES (?, ?, ?, ?, ?, 'pending')");
                        if ($stmt) {
                            $stmt->bind_param("issss", $professional_id, $document_type, $file_name, $file_path, $file['type']);
                            
                            if ($stmt->execute()) {
                                $_SESSION['success_message'] = "Document uploaded successfully! Verification pending.";
                                header("Location: editprofile.php");
                                exit();
                            } else {
                                // Delete the uploaded file if DB insert fails
                                if (file_exists($file_path)) {
                                    unlink($file_path);
                                }
                                $errors[] = "Error saving document information: " . $stmt->error;
                            }
                            $stmt->close();
                        } else {
                            $errors[] = "Database error: " . $conn->error;
                        }
                    } else {
                        $errors[] = "Error uploading document";
                    }
                } else {
                    $errors[] = "Invalid file type or size too large (max 5MB)";
                }
            } else {
                $errors[] = "No file uploaded or upload error";
            }
        }
        $stmt_check->close();
    }

    // Document Deletion
    if (isset($_POST['delete_document'])) {
        $document_id = intval($_POST['document_id']);
        
        // Get document info before deleting
        $stmt = $conn->prepare("SELECT file_path FROM professional_documents WHERE id = ? AND professional_id = ?");
        $stmt->bind_param("ii", $document_id, $professional_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $document = $result->fetch_assoc();
            $file_path = $document['file_path'];
            
            // Delete from database
            $stmt_del = $conn->prepare("DELETE FROM professional_documents WHERE id = ? AND professional_id = ?");
            $stmt_del->bind_param("ii", $document_id, $professional_id);
            
            if ($stmt_del->execute()) {
                // Delete the actual file
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                $_SESSION['success_message'] = "Document deleted successfully!";
                header("Location: editprofile.php");
                exit();
            } else {
                $errors[] = "Error deleting document: " . $stmt_del->error;
            }
            $stmt_del->close();
        } else {
            $errors[] = "Document not found or you don't have permission to delete it";
        }
        $stmt->close();
    }
    
    // Password Update - Fixed version
    if (isset($_POST['update_password'])) {
        $current_password = sanitizeInput($_POST['current_password']);
        $new_password = sanitizeInput($_POST['new_password']);
        $confirm_password = sanitizeInput($_POST['confirm_password']);
        
        // Validate inputs
        if (empty($current_password)) {
            $errors[] = "Current password is required";
        }
        if (empty($new_password)) {
            $errors[] = "New password is required";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        } elseif (!preg_match('/[a-z]/', $new_password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            $errors[] = "Password must contain at least one number";
        } elseif (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
            $errors[] = "Password must contain at least one special character";
        }
        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }

        if (empty($errors)) {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM professionals WHERE id = ?");
            if (!$stmt) {
                $errors[] = "Database error: " . $conn->error;
            } else {
                $stmt->bind_param("i", $professional_id);
                if (!$stmt->execute()) {
                    $errors[] = "Database error: " . $stmt->error;
                } else {
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $professional = $result->fetch_assoc();
                        
                        if (password_verify($current_password, $professional['password'])) {
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            
                            // Start transaction
                            $conn->begin_transaction();
                            
                            try {
                                // Update password in professionals table
                                $stmt_update = $conn->prepare("UPDATE professionals SET password = ? WHERE id = ?");
                                $stmt_update->bind_param("si", $hashed_password, $professional_id);
                                $stmt_update->execute();
                                
                                if ($stmt_update->affected_rows === 0) {
                                    throw new Exception("Failed to update password in professionals table");
                                }
                                
                                // Also update user_db table
                                $stmt_user = $conn->prepare("UPDATE user_db SET password = ? WHERE id = ? AND user_type = 'professional'");
                                $stmt_user->bind_param("si", $hashed_password, $professional_id);
                                $stmt_user->execute();
                                
                                if ($stmt_user->affected_rows === 0) {
                                    throw new Exception("Failed to update password in user_db table");
                                }
                                
                                // Commit transaction
                                $conn->commit();
                                
                                $_SESSION['success_message'] = "Password updated successfully!";
                                header("Location: editprofile.php?password_updated=1");
                                exit();
                            } catch (Exception $e) {
                                // Rollback transaction if any error occurs
                                $conn->rollback();
                                $errors[] = "Failed to update password. Please try again. Error: " . $e->getMessage();
                            }
                            
                            $stmt_update->close();
                            $stmt_user->close();
                        } else {
                            $errors[] = "Current password is incorrect";
                        }
                    } else {
                        $errors[] = "Professional not found";
                    }
                }
                $stmt->close();
            }
        }
    }
    
    // Account Deactivation
    if (isset($_POST['deactivate_account'])) {
        $confirm = isset($_POST['confirm_deactivate']) ? true : false;
        
        if ($confirm) {
            // Update professionals table
            $stmt = $conn->prepare("UPDATE professionals SET account_status='deactivated' WHERE id=?");
            if (!$stmt) {
                $errors[] = "Database error: " . $conn->error;
            } else {
                $stmt->bind_param("i", $professional_id);
                
                if ($stmt->execute()) {
                    // Also update user_db table
                    $stmt_user = $conn->prepare("UPDATE user_db SET account_status='deactivated' WHERE id=? AND user_type='professional'");
                    if ($stmt_user) {
                        $stmt_user->bind_param("i", $professional_id);
                        $stmt_user->execute();
                        $stmt_user->close();
                    }
                    
                    $stmt->close();
                    session_destroy();
                    header("Location: ../login.php?deactivated=1");
                    exit();
                } else {
                    $errors[] = "Error deactivating account: " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $errors[] = "Please confirm account deactivation";
        }
    }
}

// Check for success messages from redirects
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Fetch professional data
$professional = [];
$services = [];
$experiences = [];
$certifications = [];
$documents = [];

// Define profession-specific skills
$profession_skills = [
    'Plumber' => [
        'New plumbing system', 
        'Fixture installation & repair',
        'Pipe installation & repair',
        'Water heater installation & repair',
        'Water filter installation & repair',
        'Emergency plumbing services'
    ],
    'Househelp' => [
        'Cooking',
        'Child care',
        'Grocery shopping and errands',
        'Pet care',
        'Elderly care',
        'Other househelp services'
    ],
    'Garbage Collector' => [
        'Household waste',
        'Biomedical waste',
        'Electronic waste',
        'Industrial waste',
        'Construction waste',
        'Hazardous waste'
    ],
    'Cleaner' => [
        'General house cleaning',
        'Upholstery & carpet cleaning',
        'Window cleaning',
        'Pressure washing',
        'Chimney sweeping',
        'Janitorial cleaning',
        'Medical cleaning'
    ],
    'Painter' => [
        'Wall painting',
        'Ceiling painting',
        'Trim painting',
        'Doors painting',
        'Furniture painting',
        'Deck and fence painting'
    ],
    'Gardener' => [
        'Garden maintenance',
        'Landscaping',
        'Lawn mowing',
        'Weeding',
        'Planting',
        'Pest control'
    ],
    'Laundry' => [
        'Washing',
        'Drying',
        'Ironing',
        'Folding',
        'Stain removing',
        'Dry cleaning',
        'Sofa cleaning',
        'Curtain cleaning',
        'Shoe cleaning',
        'Carpet cleaning'
    ],
    'Electrician' => [
        'Electrical repairs',
        'Wiring installation',
        'Circuit breaker installation',
        'Fan installation',
        'Lighting installation',
        'Switchboard repair',
        'Generator installation'
    ]
];

// Basic info
$stmt = $conn->prepare("SELECT * FROM professionals WHERE id = ?");
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $professional_id);
if (!$stmt->execute()) {
    die("Database error: " . $stmt->error);
}
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $professional = $result->fetch_assoc();
} else {
    die("Professional data not found");
}
$stmt->close();

// Services
$stmt = $conn->prepare("SELECT * FROM professional_pricing WHERE professional_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $professional_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    $stmt->close();
}

// Experiences 
$stmt = $conn->prepare("SELECT *, 
    DATE_FORMAT(start_date, '%b %Y') as formatted_start_date,
    DATE_FORMAT(end_date, '%b %Y') as formatted_end_date
    FROM professional_experiences 
    WHERE professional_id = ? 
    ORDER BY start_date DESC");
if ($stmt) {
    $stmt->bind_param("i", $professional_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $experiences[] = $row;
    }
    $stmt->close();
}

// Certifications
$stmt = $conn->prepare("SELECT * FROM professional_certifications WHERE professional_id = ? ORDER BY issue_date DESC");
if ($stmt) {
    $stmt->bind_param("i", $professional_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $certifications[] = $row;
    }
    $stmt->close();
}

// Documents
$stmt = $conn->prepare("SELECT * FROM professional_documents WHERE professional_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $professional_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sevak - Edit Profile</title>
    <link rel="stylesheet" href="../css/editprofile_prof.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .password-strength {
            display: flex;
            margin-top: 5px;
        }
        .password-strength span {
            height: 5px;
            flex-grow: 1;
            margin-right: 2px;
            background-color: #ddd;
        }
        .password-strength span.active {
            background-color: #4CAF50;
        }
        .password-requirements {
            margin-top: 10px;
            font-size: 0.9em;
            color: #666;
        }
        .password-requirements ul {
            margin: 5px 0 0 15px;
            padding: 0;
        }
        .password-requirements li {
            list-style-type: none;
            position: relative;
            padding-left: 20px;
        }
        .password-requirements li:before {
            content: "•";
            position: absolute;
            left: 0;
        }
        .error-message {
            color: red;
            font-size: 0.8em;
            margin-top: 5px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-danger {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }
        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border-radius: 5px;
            width: 80%;
            max-width: 500px;
        }
        .mt-2 {
            margin-top: 0.5rem;
        }
        .mt-3 {
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <a href="#" class="logo">Sevak</a>
            <ul class="navbar-menu" id="navbarMenu">
                <li><a href="../../html files/service.html" class="navbar-item">Services</a></li>
                <li><a href="../../html files/aboutus.html" class="navbar-item">How It Works</a></li>
                <li><a href="../contactus.php" class="navbar-item">Contact Us</a></li>
                <li><a href="../../html files/help.html" class="navbar-item">Help</a></li>
                <li><a href="../professional/professional-dashboard.php" class="navbar-item">Dashboard</a></li>
                <li><div class="navbar-buttons">
                    <a href="../logout.php" class="btn btn-primary" id="logout-btn">Logout</a>
                    </div></li>
            </ul>
            <div class="navbar-toggle" id="navbarToggle">
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
            </div>
        </div>
    </nav>

    <!-- Edit Profile Section -->
    <section class="edit-profile-section">
        <div class="container">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <p><?php echo $success; ?></p>
                </div>
            <?php endif; ?>
            
            <div class="edit-profile-header">
                <h2>Edit Profile</h2>
                <p>Update your professional information to attract more clients</p>
            </div>

            <div class="edit-profile-container">
                <div class="profile-sidebar">
                    <div class="profile-image-container">
                    <img src="<?php echo !empty($professional['profile_image']) ? '../professional/uploads/profiles/' . htmlspecialchars($professional['profile_image']) : '../professional/uploads/profiles/default_profile.png'; ?>" alt="Profile" class="profile-image" id="profileImage">                        <div class="profile-image-overlay">
                            <form method="post" enctype="multipart/form-data">
                                <label for="profileImageUpload" class="profile-image-edit">
                                    <i class="fas fa-camera"></i>
                                </label>
                                <input type="file" id="profileImageUpload" name="profile_image" accept="image/*" hidden onchange="this.form.submit()">
                                <input type="hidden" name="upload_profile_image" value="1">
                            </form>
                        </div>
                    </div>
                    <div class="profile-navigation">
                        <a href="#basic-info" class="profile-nav-item active">Basic Information</a>
                        <a href="#skills-services" class="profile-nav-item">Skills & Services</a>
                        <a href="#experience" class="profile-nav-item">Experience</a>
                        <a href="#certifications" class="profile-nav-item">Certifications</a>
                        <a href="#documents" class="profile-nav-item">Documents</a>
                        <a href="#settings" class="profile-nav-item">Account Settings</a>
                    </div>
                </div>
                
                <div class="profile-content">
                    <!-- Basic Information Section -->
                    <div class="profile-section active" id="basic-info">
                        <h3 class="section-title">Basic Information</h3>
                        <form class="profile-form" method="post">
                            <div class="form-group">
                                <label for="fullName">Full Name</label>
                                <input type="text" id="fullName" name="name" value="<?php echo htmlspecialchars($professional['name']); ?>" class="form-control" required>
                            </div>
                            <div class="form-row">
                                <!-- Phone Number Field -->
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="contact" 
                                        value="<?php echo htmlspecialchars($professional['contact']); ?>" 
                                        class="form-control" 
                                        pattern="[0-9]{10}" 
                                        maxlength="10"
                                        title="Please enter exactly 10 digits"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($professional['email']); ?>" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="dob">Date of Birth</label>
                                    <input type="date" id="dob" name="dob" value="<?php echo htmlspecialchars($professional['dob']); ?>" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="gender">Gender</label>
                                    <select id="gender" name="gender" class="form-control">
                                        <option value="male" <?php echo ($professional['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo ($professional['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?php echo ($professional['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                                        <option value="prefer-not-to-say" <?php echo ($professional['gender'] == 'prefer-not-to-say') ? 'selected' : ''; ?>>Prefer not to say</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="address">Full Address</label>
                                <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($professional['address']); ?></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="city">City</label>
                                    <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($professional['city']); ?>" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="state">State</label>
                                    <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($professional['state']); ?>" class="form-control">
                                </div>
                                <!-- Pincode Field -->
                                <div class="form-group">
                                    <label for="pincode">Pincode</label>
                                    <input type="text" id="pincode" name="pincode" 
                                        value="<?php echo htmlspecialchars($professional['pincode']); ?>" 
                                        class="form-control" 
                                        pattern="[0-9]{6}" 
                                        maxlength="6"
                                        title="Please enter exactly 6 digits">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="bio">Professional Bio</label>
                                <textarea id="bio" name="bio" class="form-control" rows="4"><?php echo htmlspecialchars($professional['bio']); ?></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="update_basic_info" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Skills & Services Section -->
                    <div class="profile-section" id="skills-services">
                        <h3 class="section-title">Skills & Services</h3>
                        <form class="profile-form" method="post" id="servicesForm">
                            <div class="form-group">
                                <label>Select Your Services</label>
                                <div class="checkbox-group">
                                    <?php 
                                    // Get the professional's profession from the database
                                    $profession = $professional['profession'] ?? '';
                                    
                                    // Determine which skills to show based on profession
                                    $skills_to_show = isset($profession_skills[$profession]) ? $profession_skills[$profession] : [];
                                    
                                    foreach ($skills_to_show as $skill): 
                                        $service_key = strtolower(str_replace(' ', '_', $skill));
                                        $is_checked = false;
                                        $price = '';
                                        $price_unit = 'fixed';
                                        
                                        // Check if service exists in professional's services
                                        foreach ($services as $prof_service) {
                                            if (strtolower($prof_service['service_name']) === strtolower($skill)) {
                                                $is_checked = true;
                                                $price = $prof_service['price'];
                                                $price_unit = $prof_service['price_unit'];
                                                break;
                                            }
                                        }
                                    ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="skill_<?php echo $service_key; ?>" 
                                            name="services[<?php echo $service_key; ?>][enabled]" 
                                            value="1" 
                                            <?php echo $is_checked ? 'checked' : ''; ?>
                                            class="service-checkbox"
                                            data-service="<?php echo $service_key; ?>"
                                            data-service-name="<?php echo htmlspecialchars($skill); ?>"
                                            onchange="toggleServiceDetails(this)">
                                        <label for="skill_<?php echo $service_key; ?>"><?php echo $skill; ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Service Pricing</label>
                                <div class="service-pricing" id="servicePricingContainer">
                                    <?php foreach ($services as $service): 
                                        $service_key = strtolower(str_replace(' ', '_', $service['service_name']));
                                    ?>
                                    <div class="pricing-item" id="pricing_<?php echo $service_key; ?>">
                                        <div class="pricing-header">
                                            <span><?php echo htmlspecialchars($service['service_name']); ?></span>
                                            <div class="pricing-actions">
                                                <button type="button" class="btn-text btn-delete" onclick="removeService('<?php echo $service_key; ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="pricing-body">
                                            <div class="price-form">
                                                <input type="hidden" name="services[<?php echo $service_key; ?>][name]" value="<?php echo htmlspecialchars($service['service_name']); ?>">
                                                <div class="form-row">
                                                    <div class="form-group">
                                                        <label>Starting Price</label>
                                                        <input type="number" name="services[<?php echo $service_key; ?>][price]" 
                                                            value="<?php echo htmlspecialchars($service['price']); ?>" 
                                                            class="form-control" min="0" step="0.01" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Unit</label>
                                                        <select name="services[<?php echo $service_key; ?>][unit]" class="form-control" required>
                                                            <option value="fixed" <?php echo ($service['price_unit'] == 'fixed') ? 'selected' : ''; ?>>Fixed Price</option>
                                                            <option value="hourly" <?php echo ($service['price_unit'] == 'hourly') ? 'selected' : ''; ?>>Per Hour</option>
                                                            <option value="daily" <?php echo ($service['price_unit'] == 'daily') ? 'selected' : ''; ?>>Per Day</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="update_services" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                                        
                    <!-- Experience Section -->
                    <div class="profile-section" id="experience">
                        <h3 class="section-title">Work Experience</h3>
                        <form class="profile-form" method="post" id="experienceForm">
                            <!-- Display saved experiences -->
                            <div class="experience-display" id="experienceDisplay">
                                <?php foreach ($experiences as $exp): ?>
                                    <div class="experience-card" data-id="<?php echo $exp['id']; ?>">
                                        <div class="experience-header">
                                            <h4>Position:  <?php echo htmlspecialchars($exp['position']); ?></h4>
                                            <div class="experience-actions">
                                                <button type="button" class="btn-text btn-edit-exp"><i class="fas fa-edit"></i></button>
                                                <button type="button" class="btn-text btn-delete-exp"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                        <p class="experience-company">Company:  <?php echo htmlspecialchars($exp['company']); ?></p>
                                        <p class="experience-duration">Duration: 
                                            <?php 
                                            echo htmlspecialchars($exp['formatted_start_date']) . ' - ';
                                            echo $exp['currently_working'] ? 'Present' : htmlspecialchars($exp['formatted_end_date']);
                                            ?>
                                            <?php if (!empty($exp['description'])): ?>
                                            <p class="experience-description">Description:  <?php echo htmlspecialchars($exp['description']); ?></p>
                                        <?php endif; ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Experience form (hidden by default) -->
                            <div class="experience-form-container" id="experienceFormContainer" style="display: none;">
                                <div class="experience-form" id="experienceFormFields">
                                    <!-- Form fields will be added here dynamically -->
                                </div>
                                <div class="form-actions">
                                    <button type="button" id="cancelExperience" class="btn btn-outline">Cancel</button>
                                    <button type="submit" name="update_experience" class="btn btn-primary">Save Experience</button>
                                </div>
                            </div>

                            <button type="button" class="btn btn-outline mt-3" id="addNewExperience">
                                <i class="fas fa-plus"></i> Add New Experience
                            </button>
                        </form>
                    </div>

                    <!-- Certification Section - Fixed -->
                    <div class="profile-section" id="certifications">
                        <h3 class="section-title">Education & Certification</h3>
                        <form class="profile-form" method="post" id="certificationForm">
                            <!-- Display saved certifications -->
                            <div class="certification-display" id="certificationDisplay">
                                <?php foreach ($certifications as $cert): ?>
                                    <div class="certification-card" data-id="<?php echo $cert['id']; ?>">
                                        <div class="certification-header">
                                            <h4><?php echo htmlspecialchars($cert['certification_name']); ?></h4>
                                            <div class="certification-actions">
                                                <button type="button" class="btn-text btn-edit-cert"><i class="fas fa-edit"></i></button>
                                                <button type="button" class="btn-text btn-delete-cert"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                        <p class="certification-org">Issued by: <?php echo htmlspecialchars($cert['issuing_organization']); ?></p>
                                        <p class="certification-date">
                                            <?php 
                                            echo 'Issued: ' . date('M Y', strtotime($cert['issue_date']));
                                            if (!empty($cert['expiry_date'])) {
                                                echo ' | Expires: ' . date('M Y', strtotime($cert['expiry_date']));
                                            }
                                            ?>
                                        </p>
                                        <?php if (!empty($cert['credential_id'])): ?>
                                            <p class="certification-id">ID: <?php echo htmlspecialchars($cert['credential_id']); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($cert['credential_url'])): ?>
                                            <p class="certification-url">URL: <a href="<?php echo htmlspecialchars($cert['credential_url']); ?>" target="_blank">View</a></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Certification form (hidden by default) -->
                            <div class="certification-form-container" id="certificationFormContainer" style="display: none;">
                                <div class="certification-form" id="certificationFormFields">
                                    <!-- Form fields will be added here dynamically -->
                                </div>
                                <div class="form-actions">
                                    <button type="button" id="cancelCertification" class="btn btn-outline">Cancel</button>
                                    <button type="submit" name="update_certifications" class="btn btn-primary">Save Certification</button>
                                </div>
                            </div>

                            <button type="button" class="btn btn-outline mt-3" id="addNewCertification">
                                <i class="fas fa-plus"></i> Add New Certification
                            </button>
                        </form>
                    </div>
                    
                    <!-- Documents Section -->
                    <div class="profile-section" id="documents">
                        <h3 class="section-title">Personal Documents</h3>
                        <p class="section-description">Upload your identity and qualification documents for verification. All documents must be clearly visible.</p>
                        
                        <div class="documents-grid">
                            <?php foreach ($documents as $doc): ?>
                                <div class="document-card">
                                    <div class="document-header">
                                        <h4><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $doc['document_type']))); ?></h4>
                                        <span class="badge <?php echo $doc['verification_status']; ?>">
                                            <?php echo ucfirst($doc['verification_status']); ?>
                                        </span>
                                    </div>
                                    <div class="document-preview">
                                        <?php if (strpos($doc['file_type'], 'image') !== false): ?>
                                            <i class="fas fa-file-image"></i>
                                        <?php elseif ($doc['file_type'] == 'application/pdf'): ?>
                                            <i class="fas fa-file-pdf"></i>
                                        <?php else: ?>
                                            <i class="fas fa-file"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="document-footer">
                                        <span class="document-filename"><?php echo htmlspecialchars($doc['file_name']); ?></span>
                                        <div class="document-actions">
                                            <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" download class="btn-text"><i class="fas fa-download"></i></a>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
                                                <button type="submit" name="delete_document" class="btn-text"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="document-upload-card">
                            <form method="post" enctype="multipart/form-data" id="documentUploadForm">
                                <label for="documentUpload" class="document-upload-label">
                                    <div class="upload-icon">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </div>
                                    <p>Upload Document</p>
                                    <span>JPG, PNG or PDF (Max 5MB)</span>
                                </label>
                                <input type="file" id="documentUpload" name="document" accept="image/*,.pdf" hidden>
                                <select name="document_type" class="form-control mt-2" required>
                                    <option value="">Select Document Type</option>
                                    <option value="aadhar_card">Aadhar Card</option>
                                    <option value="pan_card">PAN Card</option>
                                    <option value="driving_license">Driving License</option>
                                    <option value="professional_certificate">Professional Certificate</option>
                                    <option value="other">Other</option>
                                </select>
                                <input type="hidden" name="upload_document" value="1">
                                <button type="submit" class="btn btn-primary mt-2">Upload</button>
                            </form>
                        </div>
                        </div>
                    </div>   

                    
                    <!-- Account Settings Section -->
                    <div class="profile-section" id="settings">
                        <h3 class="section-title">Account Settings</h3>
                        <form class="profile-form" method="post" id="passwordForm">
                            <?php if (isset($_GET['password_updated'])): ?>
                                <div class="alert alert-success">Password updated successfully!</div>
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label for="currentPassword">Current Password</label>
                                <input type="password" id="currentPassword" name="current_password" class="form-control" placeholder="Enter current password" required>
                                <?php if (isset($errors['current_password'])): ?>
                                    <span class="error-message"><?php echo $errors['current_password']; ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="newPassword">New Password</label>
                                    <input type="password" id="newPassword" name="new_password" class="form-control" 
                                        placeholder="Enter new password" required 
                                        oninput="checkPasswordStrength(this.value)">
                                    <div class="password-strength" id="passwordStrength">
                                        <span></span><span></span><span></span><span></span><span></span>
                                    </div>
                                    <div class="password-requirements">
                                        Password must contain:
                                        <ul>
                                            <li id="req-length">At least 8 characters</li>
                                            <li id="req-upper">One uppercase letter</li>
                                            <li id="req-lower">One lowercase letter</li>
                                            <li id="req-number">One number</li>
                                            <li id="req-special">One special character</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="confirmPassword">Confirm New Password</label>
                                    <input type="password" id="confirmPassword" name="confirm_password" class="form-control" 
                                        placeholder="Confirm new password" required
                                        oninput="checkPasswordMatch()">
                                    <span id="passwordMatch" class="error-message"></span>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="update_password" class="btn btn-primary">Update Password</button>
                            </div>
                            
                            <div class="danger-zone">
                                <h4 class="settings-subheading">Danger Zone</h4>
                                <p>Actions performed here cannot be undone. Please proceed with caution.</p>
                                <div class="danger-actions">
                                    <button type="button" class="btn btn-outline" id="deactivateAccountBtn">Deactivate Account</button>
                                </div>
                            </div>
                            
                            <!-- Deactivation Confirmation Modal -->
                            <div id="deactivateModal" class="modal" style="display:none;">
                                <div class="modal-content">
                                    <h3>Confirm Account Deactivation</h3>
                                    <p>Are you sure you want to deactivate your account? You will need to contact support to reactivate it.</p>
                                    <form method="post" id="deactivateForm">
                                        <div class="form-group">
                                            <label>
                                                <input type="checkbox" name="confirm_deactivate" required>
                                                I understand that my account will be deactivated
                                            </label>
                                        </div>
                                        <div class="form-actions">
                                            <button type="button" class="btn btn-outline" id="cancelDeactivate">Cancel</button>
                                            <button type="submit" name="deactivate_account" class="btn btn-danger">Deactivate Account</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Section -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-about">
                    <h3>Sevak</h3>
                    <p>Connecting skilled professionals with customers in need of services. Find reliable help for all your home and office needs.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-links">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Services</a></li>
                        <li><a href="#">How It Works</a></li>
                        <li><a href="#">Professionals</a></li>
                        <li><a href="#">Blog</a></li>
                    </ul>
                </div>
                <div class="footer-links">
                    <h4>Services</h4>
                    <ul>
                        <li><a href="#">Electrician</a></li>
                        <li><a href="#">Plumber</a></li>
                        <li><a href="#">Carpenter</a></li>
                        <li><a href="#">Cleaning</a></li>
                        <li><a href="#">More Services</a></li>
                    </ul>
                </div>
                <div class="footer-contact">
                    <h4>Contact Us</h4>
                    <ul>
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <div>123 Main Street, Delhi, India - 110001</div>
                        </li>
                        <li>
                            <i class="fas fa-phone-alt"></i>
                            <div>+91 98765 43210</div>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <div>info@sevak.com</div>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2023 Sevak. All rights reserved.</p>
                <div class="footer-legal">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Toggle navbar menu on mobile
        document.getElementById('navbarToggle').addEventListener('click', function() {
            document.getElementById('navbarMenu').classList.toggle('active');
        });
        
        // Profile navigation
        const profileNavItems = document.querySelectorAll('.profile-nav-item');
        const profileSections = document.querySelectorAll('.profile-section');
        
        profileNavItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                
                // Update active nav item
                profileNavItems.forEach(navItem => navItem.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding section
                profileSections.forEach(section => {
                    section.classList.remove('active');
                    if (section.id === targetId) {
                        section.classList.add('active');
                    }
                });
            });
        });
        
        // Prevent non-numeric input for phone and pincode
        document.getElementById('phone').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 10) {
                this.value = this.value.slice(0, 10);
            }
        });

        document.getElementById('pincode').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 6) {
                this.value = this.value.slice(0, 6);
            }
        });

        // Experience management
        const experienceFormContainer = document.getElementById('experienceFormContainer');
        const experienceFormFields = document.getElementById('experienceFormFields');
        const experienceDisplay = document.getElementById('experienceDisplay');
        const addNewExperienceBtn = document.getElementById('addNewExperience');
        const cancelExperienceBtn = document.getElementById('cancelExperience');
        let experiencesData = [];

        // Show experience form
        function showExperienceForm(experience = null) {
            experienceFormFields.innerHTML = `
                <input type="hidden" name="experiences[0][id]" value="${experience?.id || ''}">
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" name="experiences[0][position]" value="${experience?.position || ''}" class="form-control" placeholder="Position" required>
                </div>
                <div class="form-group">
                    <label>Company</label>
                    <input type="text" name="experiences[0][company]" value="${experience?.company || ''}" class="form-control" placeholder="Company" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="experiences[0][start_date]" value="${experience?.start_date || ''}" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="experiences[0][end_date]" value="${experience?.end_date || ''}" class="form-control" ${experience?.currently_working ? 'disabled' : ''}>
                    </div>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="experiences[0][currently_working]" ${experience?.currently_working ? 'checked' : ''} onchange="toggleEndDate(this)">
                        Currently working here
                    </label>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="experiences[0][description]" class="form-control" rows="3" placeholder="Description">${experience?.description || ''}</textarea>
                </div>
            `;
            
            experienceFormContainer.style.display = 'block';
            addNewExperienceBtn.style.display = 'none';
        }

        // Hide experience form
        function hideExperienceForm() {
            experienceFormContainer.style.display = 'none';
            addNewExperienceBtn.style.display = 'block';
        }

        // Add new experience
        addNewExperienceBtn.addEventListener('click', function() {
            showExperienceForm();
        });

        // Cancel experience edit/add
        cancelExperienceBtn.addEventListener('click', function() {
            hideExperienceForm();
        });

        // Edit experience
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-edit-exp')) {
                const card = e.target.closest('.experience-card');
                const experienceId = card.dataset.id;
                
                // Find the experience in the PHP array
                const experience = <?php echo json_encode($experiences); ?>.find(exp => exp.id == experienceId);
                if (experience) {
                    showExperienceForm(experience);
                }
            }
            
            if (e.target.closest('.btn-delete-exp')) {
                const card = e.target.closest('.experience-card');
                const experienceId = card.dataset.id;
                
                // Show confirmation dialog
                if (confirm('Are you sure you want to delete this experience?')) {
                    // Create a form to submit the deletion
                    const form = document.createElement('form');
                    form.method = 'post';
                    form.action = ''; // Submit to same page
                    
                    // Add the experience ID to delete
                    const deleteInput = document.createElement('input');
                    deleteInput.type = 'hidden';
                    deleteInput.name = 'delete_experience_id';
                    deleteInput.value = experienceId;
                    form.appendChild(deleteInput);
                    
                    // Add the update_experience flag
                    const updateInput = document.createElement('input');
                    updateInput.type = 'hidden';
                    updateInput.name = 'update_experience';
                    updateInput.value = '1';
                    form.appendChild(updateInput);
                    
                    // Add the form to the document and submit it
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        });

        // Toggle end date field based on currently working checkbox
        function toggleEndDate(checkbox) {
            const endDateInput = checkbox.closest('.form-group').previousElementSibling.querySelector('input[name$="[end_date]"]');
            endDateInput.disabled = checkbox.checked;
            if (checkbox.checked) {
                endDateInput.value = '';
            }
        }
        
        // Certification management - Fixed version
        const certificationFormContainer = document.getElementById('certificationFormContainer');
        const certificationFormFields = document.getElementById('certificationFormFields');
        const certificationDisplay = document.getElementById('certificationDisplay');
        const addNewCertificationBtn = document.getElementById('addNewCertification');
        const cancelCertificationBtn = document.getElementById('cancelCertification');

        // Show certification form
        function showCertificationForm(certification = null) {
            certificationFormFields.innerHTML = `
                <div class="form-group">
                    <label>Certification Name</label>
                    <input type="text" name="certifications[0][name]" 
                        value="${certification?.certification_name || ''}" 
                        class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Issuing Organization</label>
                    <input type="text" name="certifications[0][organization]" 
                        value="${certification?.issuing_organization || ''}" 
                        class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Issue Date</label>
                        <input type="date" name="certifications[0][issue_date]" 
                            value="${certification?.issue_date || ''}" 
                            class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Expiry Date (if applicable)</label>
                        <input type="date" name="certifications[0][expiry_date]" 
                            value="${certification?.expiry_date || ''}" 
                            class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Credential ID (if applicable)</label>
                        <input type="text" name="certifications[0][credential_id]" 
                            value="${certification?.credential_id || ''}" 
                            class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Credential URL (if available)</label>
                        <input type="url" name="certifications[0][credential_url]" 
                            value="${certification?.credential_url || ''}" 
                            class="form-control">
                    </div>
                </div>
            `;
            
            certificationFormContainer.style.display = 'block';
            addNewCertificationBtn.style.display = 'none';
        }

        // Hide certification form
        function hideCertificationForm() {
            certificationFormContainer.style.display = 'none';
            addNewCertificationBtn.style.display = 'block';
        }

        // Add new certification
        addNewCertificationBtn.addEventListener('click', function() {
            showCertificationForm();
        });

        // Cancel certification edit/add
        cancelCertificationBtn.addEventListener('click', function() {
            hideCertificationForm();
        });

        // Edit certification
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-edit-cert')) {
                const card = e.target.closest('.certification-card');
                const certId = card.dataset.id;
                
                // Find the certification in the PHP array
                const certification = <?php echo json_encode($certifications); ?>.find(cert => cert.id == certId);
                if (certification) {
                    showCertificationForm(certification);
                }
            }
            
            if (e.target.closest('.btn-delete-cert')) {
                const card = e.target.closest('.certification-card');
                const certId = card.dataset.id;
                
                // Show confirmation dialog
                if (confirm('Are you sure you want to delete this certification?')) {
                    // Create a form to submit the deletion
                    const form = document.createElement('form');
                    form.method = 'post';
                    form.action = '';
                    
                    // Add hidden field for certification ID to delete
                    const deleteInput = document.createElement('input');
                    deleteInput.type = 'hidden';
                    deleteInput.name = 'delete_certification_id';
                    deleteInput.value = certId;
                    form.appendChild(deleteInput);
                    
                    // Add the update_certifications flag
                    const updateInput = document.createElement('input');
                    updateInput.type = 'hidden';
                    updateInput.name = 'update_certifications';
                    updateInput.value = '1';
                    form.appendChild(updateInput);
                    
                    // Add the form to the document and submit it
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        });
                
        // Password Update Form Handling
        document.addEventListener('DOMContentLoaded', function() {
            const passwordForm = document.getElementById('passwordForm');
            
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Clear previous errors
                    clearErrors();
                    
                    // Get form values
                    const currentPassword = document.getElementById('currentPassword').value.trim();
                    const newPassword = document.getElementById('newPassword').value.trim();
                    const confirmPassword = document.getElementById('confirmPassword').value.trim();
                    
                    // Validate form
                    let isValid = true;
                    
                    // Validate current password
                    if (!currentPassword) {
                        showError('currentPassword', 'Current password is required');
                        isValid = false;
                    }
                    
                    // Validate new password
                    if (!newPassword) {
                        showError('newPassword', 'New password is required');
                        isValid = false;
                    } else if (newPassword.length < 8) {
                        showError('newPassword', 'Password must be at least 8 characters');
                        isValid = false;
                    } else if (!/[A-Z]/.test(newPassword)) {
                        showError('newPassword', 'Password must contain at least one uppercase letter');
                        isValid = false;
                    } else if (!/[a-z]/.test(newPassword)) {
                        showError('newPassword', 'Password must contain at least one lowercase letter');
                        isValid = false;
                    } else if (!/[0-9]/.test(newPassword)) {
                        showError('newPassword', 'Password must contain at least one number');
                        isValid = false;
                    } else if (!/[^A-Za-z0-9]/.test(newPassword)) {
                        showError('newPassword', 'Password must contain at least one special character');
                        isValid = false;
                    }
                    
                    // Validate confirm password
                    if (newPassword !== confirmPassword) {
                        showError('confirmPassword', 'Passwords do not match');
                        isValid = false;
                    }
                    
                    // If validation passes, submit the form
                    if (isValid) {
                        // Create a hidden input to trigger the PHP handler
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'update_password';
                        hiddenInput.value = '1';
                        passwordForm.appendChild(hiddenInput);
                        
                        // Submit the form
                        passwordForm.submit();
                    }
                });
                
                // Real-time password strength feedback
                document.getElementById('newPassword').addEventListener('input', function() {
                    updatePasswordStrength(this.value);
                });
                
                // Real-time password match feedback
                document.getElementById('confirmPassword').addEventListener('input', function() {
                    checkPasswordMatch();
                });
            }
            
            function clearErrors() {
                document.querySelectorAll('.error-message').forEach(el => {
                    el.textContent = '';
                });
            }
            
            function showError(fieldId, message) {
                const field = document.getElementById(fieldId);
                if (!field) return;
                
                let errorElement = field.nextElementSibling;
                
                // Create error element if it doesn't exist
                if (!errorElement || !errorElement.classList.contains('error-message')) {
                    errorElement = document.createElement('div');
                    errorElement.className = 'error-message';
                    field.parentNode.insertBefore(errorElement, field.nextSibling);
                }
                
                errorElement.textContent = message;
                errorElement.style.color = 'red';
            }
            
            function updatePasswordStrength(password) {
                const strengthMeter = document.getElementById('passwordStrength');
                const requirements = {
                    length: document.getElementById('req-length'),
                    upper: document.getElementById('req-upper'),
                    lower: document.getElementById('req-lower'),
                    number: document.getElementById('req-number'),
                    special: document.getElementById('req-special')
                };
                
                // Reset all
                strengthMeter.querySelectorAll('span').forEach(span => span.className = '');
                Object.values(requirements).forEach(el => el.style.color = '#666');
                
                let strength = 0;
                
                // Check length
                if (password.length >= 8) {
                    strength++;
                    requirements.length.style.color = 'green';
                }
                
                // Check uppercase
                if (/[A-Z]/.test(password)) {
                    strength++;
                    requirements.upper.style.color = 'green';
                }
                
                // Check lowercase
                if (/[a-z]/.test(password)) {
                    strength++;
                    requirements.lower.style.color = 'green';
                }
                
                // Check number
                if (/[0-9]/.test(password)) {
                    strength++;
                    requirements.number.style.color = 'green';
                }
                
                // Check special character
                if (/[^A-Za-z0-9]/.test(password)) {
                    strength++;
                    requirements.special.style.color = 'green';
                }
                
                // Update strength meter
                const strengthSpans = strengthMeter.querySelectorAll('span');
                for (let i = 0; i < strength; i++) {
                    strengthSpans[i].className = 'active';
                }
            }
            
            function checkPasswordMatch() {
                const newPassword = document.getElementById('newPassword').value;
                const confirmPassword = document.getElementById('confirmPassword').value;
                const matchMessage = document.getElementById('passwordMatch');
                
                if (!newPassword || !confirmPassword) {
                    matchMessage.textContent = '';
                    return;
                }
                
                if (newPassword === confirmPassword) {
                    matchMessage.textContent = 'Passwords match!';
                    matchMessage.style.color = 'green';
                } else {
                    matchMessage.textContent = 'Passwords do not match';
                    matchMessage.style.color = 'red';
                }
            }
        });

        // Deactivate account modal
        const deactivateBtn = document.getElementById('deactivateAccountBtn');
        const deactivateModal = document.getElementById('deactivateModal');
        const cancelDeactivate = document.getElementById('cancelDeactivate');
        
        deactivateBtn.addEventListener('click', function() {
            deactivateModal.style.display = 'block';
        });
        
        cancelDeactivate.addEventListener('click', function() {
            deactivateModal.style.display = 'none';
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === deactivateModal) {
                deactivateModal.style.display = 'none';
            }
        });
        
        // Toggle service details when checkbox is clicked
        function toggleServiceDetails(checkbox) {
            const serviceKey = checkbox.dataset.service;
            const serviceName = checkbox.dataset.serviceName;
            const pricingContainer = document.getElementById('servicePricingContainer');
            
            if (checkbox.checked) {
                // Add new pricing item if it doesn't exist
                if (!document.getElementById(`pricing_${serviceKey}`)) {
                    const newPricingItem = document.createElement('div');
                    newPricingItem.className = 'pricing-item';
                    newPricingItem.id = `pricing_${serviceKey}`;
                    newPricingItem.innerHTML = `
                        <div class="pricing-header">
                            <span>${serviceName}</span>
                            <div class="pricing-actions">
                                <button type="button" class="btn-text btn-delete" onclick="removeService('${serviceKey}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="pricing-body">
                            <div class="price-form">
                                <input type="hidden" name="services[${serviceKey}][name]" value="${serviceName}">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Starting Price</label>
                                        <input type="number" name="services[${serviceKey}][price]" 
                                            class="form-control" min="0" step="0.01" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Unit</label>
                                        <select name="services[${serviceKey}][unit]" class="form-control" required>
                                            <option value="fixed" selected>Fixed Price</option>
                                            <option value="hourly">Per Hour</option>
                                            <option value="daily">Per Day</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    pricingContainer.appendChild(newPricingItem);
                }
            } else {
                // Remove pricing item if it exists
                const pricingItem = document.getElementById(`pricing_${serviceKey}`);
                if (pricingItem) {
                    pricingItem.remove();
                }
            }
        }

        // Remove service and uncheck the checkbox
        function removeService(serviceKey) {
            if (confirm('Are you sure you want to remove this service?')) {
                const pricingItem = document.getElementById(`pricing_${serviceKey}`);
                if (pricingItem) {
                    pricingItem.remove();
                }
                
                const checkbox = document.getElementById(`skill_${serviceKey}`);
                if (checkbox) {
                    checkbox.checked = false;
                }
            }
        }

        // Initialize the checkboxes on page load
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.service-checkbox');
            checkboxes.forEach(checkbox => {
                // Only show pricing for checked checkboxes
                if (checkbox.checked) {
                    toggleServiceDetails(checkbox);
                }
            });
        });

        // Logout confirmation
        document.getElementById('logout-btn').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = this.href;
            }
        });
    </script>
</body>
</html>