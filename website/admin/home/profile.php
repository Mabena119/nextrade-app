<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
// Legacy ?success= / ?error= links → one-time flash + clean URL (banner disappears on refresh)
if ((!empty($_GET['success']) && is_string($_GET['success'])) || (!empty($_GET['error']) && is_string($_GET['error']))) {
    $legacyErr = isset($_GET['error']) && $_GET['error'] !== '';
    $legacyMsg = $legacyErr ? rawurldecode($_GET['error']) : rawurldecode($_GET['success']);
    $_SESSION['profile_flash'] = [
        'type' => $legacyErr ? 'error' : 'success',
        'message' => $legacyMsg,
    ];
    header('Location: profile.php');
    exit;
}

require_once __DIR__ . '/../php-includes/functions.php';

$uploadsFs = realpath(__DIR__ . '/../uploads');
if ($uploadsFs === false) {
    $uploadsFs = dirname(__DIR__) . '/uploads';
}

$imgFile = get_admin($_SESSION['username'], 'image');
$avatarSrc = nextrade_admin_avatar_src($imgFile, $uploadsFs);
$GLOBALS['nextrade_admin_avatar_src'] = $avatarSrc;

include('include/header.php');

$flash = null;
if (!empty($_SESSION['profile_flash']) && is_array($_SESSION['profile_flash'])) {
    $flash = $_SESSION['profile_flash'];
    unset($_SESSION['profile_flash']);
}

// Same folder as the photo: admin/uploads/{basename}.{ext} → admin/uploads/{basename}.mp4
$videoFile = '';
$hasProfileVideo = false;
if (!empty($imgFile) && !nextrade_admin_is_placeholder_image($imgFile)) {
    $videoCandidate = pathinfo($imgFile, PATHINFO_FILENAME) . '.mp4';
    if (is_file($uploadsFs . '/' . $videoCandidate)) {
        $videoFile = $videoCandidate;
        $hasProfileVideo = true;
    }
}
$profileVideoSrc = '';
if ($videoFile !== '') {
    $profileVideoSrc = '../uploads/' . htmlspecialchars($videoFile, ENT_QUOTES, 'UTF-8');
    $vm = @filemtime($uploadsFs . '/' . $videoFile);
    if ($vm !== false) {
        $profileVideoSrc .= '?v=' . (int) $vm;
    }
}
?>

<style>
.profile-page { animation: acSoftIn .55s cubic-bezier(.22,1,.36,1) both;

    padding: 0 4px 32px;
    max-width: 1100px;
}
/* Facebook-style cover banner */
.profile-fb {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 28px;
    background: linear-gradient(145deg, #0f1419 0%, #151c28 55%, #0d1520 100%);
    border: 1px solid rgba(255,255,255,0.08);
    box-shadow: 0 24px 48px rgba(0,0,0,0.35);
}
.profile-fb-cover {
    position: relative;
    height: clamp(220px, 42vw, 360px);
    background: #080c12;
}
.profile-fb-cover-video {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center top;
    pointer-events: none;
}
.profile-fb-cover-video::-webkit-media-controls,
.profile-fb-cover-video::-webkit-media-controls-enclosure {
    display: none !important;
}
.profile-fb-cover-fallback {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
    filter: blur(28px) saturate(1.15) brightness(0.42);
    transform: scale(1.08);
}
.profile-fb-cover-gradient {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg,
        rgba(8,12,18,0.05) 0%,
        rgba(8,12,18,0.45) 50%,
        rgba(13,17,24,0.97) 100%);
    pointer-events: none;
}
.profile-fb-cover-badge {
    position: absolute;
    top: 14px;
    right: 14px;
    z-index: 4;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #fff;
    background: rgba(0,0,0,0.5);
    border: 1px solid rgba(255,255,255,0.14);
    backdrop-filter: blur(10px);
}
.profile-fb-bar {
    position: relative;
    z-index: 5;
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 22px;
    padding: 0 26px 26px;
    margin-top: -72px;
}
.profile-fb-avatar-ring {
    flex-shrink: 0;
    width: 148px;
    height: 148px;
    border-radius: 50%;
    padding: 4px;
    background: linear-gradient(135deg, #00a8ff, #5ef6ff, #0077cc);
    box-shadow: 0 16px 44px rgba(0,0,0,0.55), 0 0 0 7px rgba(21,28,40,0.96);
}
.profile-fb-avatar {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    background: #1a1a1a;
    border: 4px solid #151c28;
}
.profile-fb-meta {
    flex: 1;
    min-width: 200px;
    padding-bottom: 6px;
}
.profile-fb-meta h1 {
    font-size: clamp(1.35rem, 3vw, 1.85rem);
    font-weight: 800;
    margin: 0 0 6px;
    color: #fff;
    letter-spacing: -0.03em;
    text-shadow: 0 2px 18px rgba(0,0,0,0.45);
}
.profile-fb-meta p {
    margin: 0;
    color: rgba(255,255,255,0.55);
    font-size: 0.95rem;
}
.profile-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 14px;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    background: rgba(27, 197, 189, 0.15);
    color: #1bc5bd;
    border: 1px solid rgba(27, 197, 189, 0.35);
}
.profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}
@media (max-width: 991px) {
    .profile-grid { grid-template-columns: 1fr; }
    .profile-fb-bar { padding: 0 18px 22px; margin-top: -56px; }
    .profile-fb-avatar-ring { width: 118px; height: 118px; }
}
.profile-card {
    background: rgba(42, 46, 56, 0.65);
    border-radius: 16px;
    border: 1px solid rgba(255,255,255,0.07);
    padding: 28px;
    backdrop-filter: blur(8px);
}
.profile-card-head {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.profile-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}
.profile-card-icon.photo {
    background: linear-gradient(135deg, rgba(54, 153, 255, 0.2), rgba(54, 153, 255, 0.05));
    color: #00a8ff;
}
.profile-card-icon.details {
    background: linear-gradient(135deg, rgba(0, 229, 255, 0.2), rgba(0, 184, 212, 0.05));
    color: #67e8f9;
}
.profile-card-head h2 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 600;
    color: rgba(255,255,255,0.95);
}
.profile-card-head span {
    display: block;
    margin-top: 4px;
    font-size: 13px;
    color: rgba(255,255,255,0.45);
    line-height: 1.4;
}
.profile-field {
    margin-bottom: 20px;
}
.profile-field:last-child { margin-bottom: 0; }
.profile-field label {
    display: block;
    margin-bottom: 8px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgba(255,255,255,0.5);
}
.profile-field input[type="text"],
.profile-field input[type="email"],
.profile-field input[type="tel"],
.profile-field input[type="password"],
.profile-field input[type="file"] {
    width: 100%;
    padding: 12px 16px;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(0,0,0,0.25);
    color: #fff;
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.profile-field input:focus {
    outline: none;
    border-color: rgba(54, 153, 255, 0.55);
    box-shadow: 0 0 0 3px rgba(54, 153, 255, 0.12);
}
.profile-field input:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
.profile-field-hint {
    margin-top: 6px;
    font-size: 12px;
    color: rgba(255,255,255,0.35);
}
.upload-zone {
    border: 2px dashed rgba(255,255,255,0.15);
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    background: rgba(0,0,0,0.15);
    transition: border-color 0.2s, background 0.2s;
}
.upload-zone:hover {
    border-color: rgba(54, 153, 255, 0.35);
    background: rgba(54, 153, 255, 0.04);
}
.upload-zone input[type="file"] {
    margin-top: 12px;
    padding: 10px;
    cursor: pointer;
}
.btn-remove-bg {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    color: #fff;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.12);
    transition: background 0.15s, border-color 0.15s, transform 0.15s;
    white-space: nowrap;
}
.btn-remove-bg:hover {
    background: rgba(255,255,255,0.14);
    border-color: rgba(255,255,255,0.2);
    color: #fff;
    transform: translateY(-1px);
}
.btn-profile-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    color: #fff;
    background: linear-gradient(135deg, #00a8ff 0%, #0066cc 100%);
    box-shadow: 0 4px 16px rgba(54, 153, 255, 0.35);
    transition: transform 0.15s, box-shadow 0.15s;
}
.btn-profile-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(54, 153, 255, 0.45);
    color: #fff;
}
.btn-profile-secondary {
    background: rgba(255,255,255,0.08);
    box-shadow: none;
    border: 1px solid rgba(255,255,255,0.12);
}
.btn-profile-secondary:hover {
    background: rgba(255,255,255,0.12);
    box-shadow: none;
    color: #fff;
}
.profile-alert {
    position: relative;
    border-radius: 12px;
    padding: 14px 42px 14px 18px;
    margin-bottom: 24px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: profileToastIn 0.45s ease-out;
}
@keyframes profileToastIn {
    from { opacity: 0; transform: translateY(-12px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes profileToastOut {
    to {
        opacity: 0;
        transform: translateY(-12px);
        max-height: 0;
        margin-bottom: 0;
        padding-top: 0;
        padding-bottom: 0;
        overflow: hidden;
        border-width: 0;
    }
}
.profile-alert-dismiss {
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    color: inherit;
    opacity: 0.7;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
    padding: 4px 10px;
    border-radius: 8px;
}
.profile-alert-dismiss:hover {
    opacity: 1;
    background: rgba(255,255,255,0.08);
}
.profile-alert.success {
    background: rgba(27, 197, 189, 0.12);
    border: 1px solid rgba(27, 197, 189, 0.35);
    color: #1bc5bd;
}
.profile-alert.error {
    background: rgba(246, 78, 96, 0.12);
    border: 1px solid rgba(246, 78, 96, 0.35);
    color: #f64e60;
}
.profile-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 8px;
}
.password-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
@media (max-width: 576px) {
    .password-grid { grid-template-columns: 1fr; }
}
</style>

<div class="aura-console-page">
    <header class="aura-console-head">
        <div>
            <p class="aura-kicker">Account</p>
            <h1>Settings</h1>
            <p>Update your photo, profile details, and password.</p>
        </div>
    </header>

    <?php if (is_array($flash) && isset($flash['message']) && (string) $flash['message'] !== ''): ?>
        <div id="profileFlashBanner" role="alert" class="profile-alert <?php echo (($flash['type'] ?? '') === 'error') ? 'error' : 'success'; ?>">
            <i class="ti <?php echo (($flash['type'] ?? '') === 'error') ? 'ti-alert-triangle' : 'ti-check'; ?>"></i>
            <span><?php echo htmlspecialchars((string) $flash['message'], ENT_QUOTES, 'UTF-8'); ?></span>
            <button type="button" class="profile-alert-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php endif; ?>

    <div class="profile-fb">
        <div class="profile-fb-cover">
            <?php if ($profileVideoSrc !== ''): ?>
                <video
                    class="profile-fb-cover-video"
                    src="<?php echo htmlspecialchars($profileVideoSrc, ENT_QUOTES, 'UTF-8'); ?>"
                    autoplay
                    muted
                    loop
                    playsinline
                    disablepictureinpicture
                    controlslist="nodownload noplaybackrate noremoteplayback nofullscreen"
                    poster="<?php echo htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8'); ?>"
                ></video>
                <span class="profile-fb-cover-badge"><i class="ti ti-sparkles"></i> Animated cover</span>
            <?php else: ?>
                <div class="profile-fb-cover-fallback" style="background-image: url('<?php echo htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8'); ?>')"></div>
            <?php endif; ?>
            <div class="profile-fb-cover-gradient"></div>
        </div>
        <div class="profile-fb-bar">
            <div class="profile-fb-avatar-ring">
                <img class="profile-fb-avatar" src="<?php echo htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile" onerror="this.onerror=null;this.src='../assets/sitelogo.png'">
            </div>
            <div class="profile-fb-meta">
                <h1><?php echo htmlspecialchars(get_admin($_SESSION['username'], 'displayname'), ENT_QUOTES, 'UTF-8'); ?></h1>
                <p><?php echo htmlspecialchars(get_admin($_SESSION['username'], 'email'), ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if (get_admin($_SESSION['username'], 'status') === 'active'): ?>
                    <span class="profile-badge"><i class="ti ti-circle-check"></i> Active account</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="profile-grid">
        <div class="profile-card">
            <div class="profile-card-head">
                <div class="profile-card-icon photo"><i class="ti ti-camera"></i></div>
                <div>
                    <h2>Profile photo</h2>
                    <span>Shown in the top bar and across your admin experience. JPG or PNG, max 1&nbsp;MB.</span>
                </div>
            </div>
            <form action="update_profile.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="save_photo" value="1">
                <div class="upload-zone">
                    <i class="ti ti-upload" style="font-size: 1.5rem; color: rgba(255,255,255,0.35);"></i>
                    <p style="margin: 12px 0 0; color: rgba(255,255,255,0.45); font-size: 13px;">Choose a square image for best results</p>
                    <div class="profile-field" style="margin-bottom: 0;">
                        <input type="file" name="logo" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
                    </div>
                </div>
                <div class="profile-actions" style="margin-top: 20px;">
                    <button type="submit" class="btn-profile-primary"><i class="ti ti-photo"></i> Update photo</button>
                    <a href="https://www.remove.bg" target="_blank" rel="noopener noreferrer" class="btn-remove-bg"><i class="ti ti-cut"></i> Remove image background</a>
                </div>
            </form>
        </div>

        <div class="profile-card">
            <div class="profile-card-head">
                <div class="profile-card-icon details"><i class="ti ti-user"></i></div>
                <div>
                    <h2>Personal details</h2>
                    <span>Your display name appears on the dashboard. Email is your login and cannot be changed here.</span>
                </div>
            </div>
            <form action="update_profile.php" method="post">
                <input type="hidden" name="save_profile" value="1">
                <div class="profile-field">
                    <label for="fullname">Legal / full name</label>
                    <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars(get_admin($_SESSION['username'], 'fullname'), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="profile-field">
                    <label for="displayname">Display name</label>
                    <input type="text" id="displayname" name="displayname" value="<?php echo htmlspecialchars(get_admin($_SESSION['username'], 'displayname'), ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="profile-field">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars(get_admin($_SESSION['username'], 'phone'), ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="profile-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars(get_admin($_SESSION['username'], 'email'), ENT_QUOTES, 'UTF-8'); ?>" disabled>
                    <p class="profile-field-hint">Contact support to change your login email.</p>
                </div>

                <div style="margin: 28px 0 16px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.06);">
                    <label style="margin-bottom: 12px; font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.7);">Change password <span style="font-weight: 400; color: rgba(255,255,255,0.35);">(optional)</span></label>
                    <div class="password-grid">
                        <div class="profile-field" style="margin-bottom: 0;">
                            <label for="new_password">New password</label>
                            <input type="password" id="new_password" name="new_password" autocomplete="new-password" placeholder="Leave blank to keep current">
                        </div>
                        <div class="profile-field" style="margin-bottom: 0;">
                            <label for="confirm_password">Confirm</label>
                            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" placeholder="Repeat new password">
                        </div>
                    </div>
                    <p class="profile-field-hint">Minimum 6 characters if you choose to update.</p>
                </div>

                <div class="profile-actions">
                    <button type="submit" class="btn-profile-primary"><i class="ti ti-device-floppy"></i> Save changes</button>
                    <a href="index.php" class="btn-profile-primary btn-profile-secondary" style="text-decoration: none;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var el = document.getElementById('profileFlashBanner');
    if (!el) return;
    var hide = function () {
        el.style.animation = 'profileToastOut 0.38s ease forwards';
        window.setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 400);
    };
    var btn = el.querySelector('.profile-alert-dismiss');
    if (btn) btn.addEventListener('click', hide);
    window.setTimeout(hide, 9000);
})();
</script>

<?php include('include/footer.php'); ?>
