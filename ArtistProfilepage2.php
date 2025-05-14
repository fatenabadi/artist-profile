
<?php
require 'config.php';

// Check if artist_id is provided
if (!isset($_GET['artist_id'])) {
    header("Location: index.php");
    exit();
}

$artist_id = (int)$_GET['artist_id'];

// Get artist information
$stmt = $conn->prepare("
    SELECT 
        u.username,
        u.email,
        ai.fullname,
        ai.dateofbirth,
        ai.education,
        ai.location,
        ai.phonenumber,
        ai.sociallinks,
        aa.bio,
        aa.artistic_goals,
        aa.artstyles
    FROM artists a
    JOIN users u ON a.user_id = u.user_id
    LEFT JOIN artistsinfo ai ON a.artist_id = ai.artist_id
    LEFT JOIN aboutartists aa ON a.artist_id = aa.artist_id
    WHERE a.artist_id = ?
");
$stmt->execute([$artist_id]);
$artist = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$artist) {
    header("Location: index.php");
    exit();
}

// Get artist's artworks
$stmt = $conn->prepare("
    SELECT * FROM artworks 
    WHERE artist_id = ? 
    AND is_available = 1 
    ORDER BY upload_date DESC
");
$stmt->execute([$artist_id]);
$artworks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format birth date if exists
$birthDate = '';
if (!empty($artist['dateofbirth'])) {
    $date = new DateTime($artist['dateofbirth']);
    $birthDate = $date->format('F j, Y');
}

// Split art styles
$artStyles = !empty($artist['artstyles']) ? explode(',', $artist['artstyles']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($artist['fullname'] ?? $artist['username']) ?> - Artist Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-header {
            height: 200px;
            background: linear-gradient(45deg, #4db8b2, #78cac5);
        }
        .profile-image {
            width: 150px;
            height: 150px;
            border: 4px solid white;
            margin-top: -75px;
        }
        .artwork-card img {
            height: 200px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="profile-header"></div>
    
    <div class="container text-center mb-5">
        <img src="img/artist_profiles/default_artist.jpg" 
             class="profile-image rounded-circle"
             alt="<?= htmlspecialchars($artist['fullname'] ?? $artist['username']) ?>">
        
        <h1 class="mt-3"><?= htmlspecialchars($artist['fullname'] ?? $artist['username']) ?></h1>
        
        <?php if (!empty($artist['location'])): ?>
            <p class="text-muted">
                <i class="fas fa-map-marker-alt"></i>
                <?= htmlspecialchars($artist['location']) ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="container">
        <div class="row">
            <!-- About Section -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body">
                        <?php if (!empty($artist['bio'])): ?>
                            <h3 class="card-title">About</h3>
                            <p><?= nl2br(htmlspecialchars($artist['bio'])) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($artStyles)): ?>
                            <h4>Art Styles</h4>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($artStyles as $style): ?>
                                    <span class="badge bg-primary"><?= htmlspecialchars($style) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Artworks Section -->
            <div class="col-md-8">
                <h3 class="mb-4">Artworks</h3>
                <div class="row row-cols-1 row-cols-md-2 g-4">
                    <?php foreach ($artworks as $artwork): ?>
                        <div class="col">
                            <div class="card h-100">
                                <img src="<?= htmlspecialchars($artwork['image_path']) ?>" 
                                     class="card-img-top"
                                     alt="<?= htmlspecialchars($artwork['title']) ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($artwork['title']) ?></h5>
                                    <?php if ($artwork['price'] > 0): ?>
                                        <p class="text-success fw-bold">
                                            $<?= number_format($artwork['price'], 2) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>