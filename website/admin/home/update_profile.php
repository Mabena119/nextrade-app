<?php
require dirname(__DIR__) . '/php-includes/security-bridge.php';
auraai_sec_bootstrap();
auraai_sec_session_start();
require dirname(__DIR__) . '/php-includes/connect.php';
require dirname(__DIR__) . '/php-includes/functions.php';

function profile_redirect(string $type, string $message): void
{
    $_SESSION['profile_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
    header('Location: profile.php');
    exit;
}

if (!isset($_SESSION['id'], $_SESSION['username'])) {
    header('Location: ../index.php');
    exit;
}

auraai_sec_require_method('POST');
auraai_sec_require_same_origin();

$adminId = (int) get_admin($_SESSION['username'], 'id');
if ($adminId <= 0) {
    profile_redirect('error', 'Could not resolve your account.');
}

if (isset($_POST['save_photo'])) {
    if (!isset($_FILES['logo']) || (int) ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        profile_redirect('error', 'Please choose an image to upload.');
    }

    $uploadsDir = realpath(__DIR__ . '/../uploads');
    if ($uploadsDir === false) {
        $uploadsDir = dirname(__DIR__) . '/uploads';
    }
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    $upload = auraai_sec_validate_upload($_FILES['logo'], $uploadsDir, ['jpg', 'jpeg', 'png'], 1048576);
    if (empty($upload['ok'])) {
        profile_redirect('error', (string) ($upload['error'] ?? 'Upload failed.'));
    }

    $storedPath = (string) ($upload['path'] ?? '');
    $basename = basename($storedPath);
    if ($basename === '' || !is_file($uploadsDir . '/' . $basename)) {
        profile_redirect('error', 'Upload could not be saved.');
    }

    $mime = auraai_sec_detect_mime($uploadsDir . '/' . $basename);
    if (!in_array($mime, ['image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/x-png'], true)) {
        @unlink($uploadsDir . '/' . $basename);
        profile_redirect('error', 'Please upload a JPG or PNG image.');
    }

    if (function_exists('nextrade_normalize_profile_upload')) {
        if (!nextrade_normalize_profile_upload($uploadsDir . '/' . $basename, $mime)) {
            @unlink($uploadsDir . '/' . $basename);
            profile_redirect('error', 'Could not process that image. Try a different JPG or PNG.');
        }
    } else {
        @chmod($uploadsDir . '/' . $basename, 0644);
    }

    $oldImage = (string) (get_admin($_SESSION['username'], 'image') ?? '');
    if ($oldImage !== '' && $oldImage !== 'default.png') {
        $oldBase = basename($oldImage);
        $oldPath = $uploadsDir . '/' . $oldBase;
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
        $oldVideo = $uploadsDir . '/' . pathinfo($oldBase, PATHINFO_FILENAME) . '.mp4';
        if (is_file($oldVideo)) {
            @unlink($oldVideo);
        }
    }

    $stmt = $con->prepare('UPDATE admin SET image = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        profile_redirect('error', 'Could not save profile photo.');
    }
    $stmt->bind_param('si', $basename, $adminId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        profile_redirect('error', 'Could not save profile photo.');
    }

    if (function_exists('clear_admin_row_cache')) {
        clear_admin_row_cache($_SESSION['username']);
    }

    profile_redirect('success', 'Profile photo updated successfully.');
}

if (isset($_POST['save_profile'])) {
    $fullname = trim((string) ($_POST['fullname'] ?? ''));
    $displayname = trim((string) ($_POST['displayname'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($displayname === '' || $phone === '') {
        profile_redirect('error', 'Display name and phone are required.');
    }

    if ($newPassword !== '' || $confirmPassword !== '') {
        if (strlen($newPassword) < 6) {
            profile_redirect('error', 'New password must be at least 6 characters.');
        }
        if ($newPassword !== $confirmPassword) {
            profile_redirect('error', 'New passwords do not match.');
        }

        $stmt = $con->prepare(
            'UPDATE admin SET fullname = ?, displayname = ?, phone = ?, password = ? WHERE id = ? LIMIT 1'
        );
        if (!$stmt) {
            profile_redirect('error', 'Could not save your profile.');
        }
        $stmt->bind_param('ssssi', $fullname, $displayname, $phone, $newPassword, $adminId);
    } else {
        $stmt = $con->prepare(
            'UPDATE admin SET fullname = ?, displayname = ?, phone = ? WHERE id = ? LIMIT 1'
        );
        if (!$stmt) {
            profile_redirect('error', 'Could not save your profile.');
        }
        $stmt->bind_param('sssi', $fullname, $displayname, $phone, $adminId);
    }

    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        profile_redirect('error', 'Could not save your profile.');
    }

    $_SESSION['displayname'] = $displayname;
    profile_redirect('success', 'Profile updated successfully.');
}

profile_redirect('error', 'No changes were submitted.');
