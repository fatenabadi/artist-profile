
<?php
session_start();
require 'config.php';

$logged_in_user_id = $_SESSION['user_id'] ?? null;

// Check if an artist_id is being requested in the URL
if (isset($_GET['artist_id'])) {
    $requested_artist_id = (int)$_GET['artist_id'];

    // Get the artist_id of the logged-in user
    $stmt = $conn->prepare("SELECT artist_id FROM artists WHERE user_id = ?");
    $stmt->execute([$logged_in_user_id]);
    $artist = $stmt->fetch();

    if (!$artist || $requested_artist_id !== (int)$artist['artist_id']) {
        // Load the requested artist's profile for visitors
        $stmt = $conn->prepare("SELECT * FROM artistsinfo WHERE artist_id = ?");
        $stmt->execute([$requested_artist_id]);
        $profile = $stmt->fetch();

        if (!$profile) {
            // Artist not found: redirect to home or error page
            header("Location: home2.php");
            exit();
        }

        // Set the profile data for visitors
        $isVisitor = true; // This indicates that the user is a visitor
    } else {
        // ✅ If code reaches here, it means the artist is viewing their own profile
        $artist_id = $artist['artist_id'];
        // Load existing profile data
        $stmt = $conn->prepare("
            SELECT a.*, b.bio, b.artistic_goals, b.artstyles 
            FROM artistsinfo a 
            LEFT JOIN aboutartists b ON a.artist_id = b.artist_id 
            WHERE a.artist_id = ?
        ");
        $stmt->execute([$artist_id]);
        $profile = $stmt->fetch();
        $isVisitor = false; // This indicates that the user is the artist
    }
} else {
    // No artist_id is in URL: we assume artist is trying to open their own profile
    $stmt = $conn->prepare("SELECT artist_id FROM artists WHERE user_id = ?");
    $stmt->execute([$logged_in_user_id]);
    $artist = $stmt->fetch();

    if (!$artist) {
        // Not an artist: redirect to home or error page
        header("Location: home2.php");
        exit();
    }

    // Redirect the artist to their own profile page
    header("Location: artistprofilepage2.php?artist_id=" . $artist['artist_id']);
    exit();
}

// Initialize variables for profile display
$values = [
    'fullName' => $profile['fullname'] ?? '',
    'birthDate' => $profile['dateofbirth'] ?? '',
    'education' => $profile['education'] ?? '',
    'email' => $profile['email'] ?? '',
    'location' => $profile['location'] ?? '',
    'phone' => $profile['phonenumber'] ?? '',
    'socialLinks' => $profile['sociallinks'] ?? '',
    'shortBio' => $profile['bio'] ?? '',
    'artisticGoals' => $profile['artistic_goals'] ?? '',
    'styles' => !empty($profile['artstyles']) ? explode(',', $profile['artstyles']) : []
];

// Check if user has any artworks
$hasArtworks = false;
$artworks = [];
if (isset($artist_id)) {
    $stmt = $conn->prepare("SELECT * FROM artworks WHERE artist_id = ? ORDER BY upload_date DESC");
    $stmt->execute([$artist_id]);
    $artworks = $stmt->fetchAll();
    $hasArtworks = count($artworks) > 0;
}

// Fetch featured artists for the sidebar or other sections
$featuredArtists = $conn->query("
    SELECT 
        u.username,
        ai.fullname,
        ai.profile_pic,
        aa.bio,
        COUNT(aw.artwork_id) as artwork_count
    FROM artists a
    JOIN users u ON a.user_id = u.user_id
    JOIN artistsinfo ai ON a.artist_id = ai.artist_id
    JOIN aboutartists aa ON a.artist_id = aa.artist_id
    LEFT JOIN artworks aw ON a.artist_id = aw.artist_id
    GROUP BY a.artist_id
    ORDER BY artwork_count DESC
    LIMIT 4
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artist Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { 
            --primary-light: #a4e0dd;
            --primary: #78cac5;
            --primary-dark: #4db8b2;
            --secondary-light: #f2e6b5;
            --secondary: #e7cf9b;
            --secondary-dark: #96833f;
            --light: #EEF9FF;
            --dark: #173836;
        }
        
        body {
            background-color: var(--light);
            font-family: 'Nunito', sans-serif;
        }

        .profile-header {
            height: 300px;
            object-fit: fill;
            background-image: url('img/Teal Gold Dust Motivational Quote Facebook Cover.png');
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            position: relative;
            border-radius: 0% 0% 30% 30%;
            overflow: hidden;
            transition-property: background-image;
            transition-duration: 0.3s;
            transition-timing-function: ease;
        }

        .profile-info-container {
            background-color: #f9f9f9;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .info-card {
            display: flex;
            align-items: center;
            background-color: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .info-card:hover {
            transform: translateY(-5px);
        }

        .info-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-right: 15px;
        }

        .info-content h5 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--dark);
        }

        .info-content p {
            margin: 0;
            color: #555;
        }

        .artworks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
            padding: 2rem;
        }

        .artwork-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
            z-index: 1;
            position: relative;
        }

        .artwork-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .artwork-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .artwork-details {
            position: relative;
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            text-align: center;
            z-index: 1 !important;
        }

        .artwork-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.75rem;
            line-height: 1.3;
        }

        .artwork-price {
            font-weight: bold;
            color: var(--primary-dark);
            font-size: 1.1rem;
            margin-top: auto;
            padding-top: 0.5rem;
        }

        .back-arrow-btn {
            position: fixed;
            top: 50px;
            left: 50px;
            width: 50px;
            height: 50px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .back-arrow-btn:hover {
            background-color: var(--primary-dark);
            transform: scale(1.05);
        }

        .back-arrow-btn i {
            font-size: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- Back Arrow Button -->
    <button id="backArrow" class="back-arrow-btn" title="Go back">
        <i class="fas fa-arrow-left"></i>
    </button>

    <!-- Profile Header -->
    <div class="profile-header"></div>

    <div class="container text-center">
        <div class="profile-image-container" id="profileContainer">
            <img class="img-fluid rounded-circle" 
                 src="img/<?php echo 'artist_profiles/default_artist.jpg'; ?>" 
                 style="width: 190px; height: 150px; object-fit: cover;">
        </div>
        
        <h1 class="d-inline-block mt-3 text-center" id="username"><?php echo htmlspecialchars($values['fullName']); ?></h1>
        <p class="d-inline-block lead text-muted mt-2 text-center" id="role"><?php echo  'Artist' ?></p>  
    </div>

    <!-- Overview and Portfolio -->
    <div class="tabs-container">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#overview">Overview</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#portfolio">Portfolio</a>
            </li>
        </ul>

        <!-- Tabs content -->
        <div class="tab-content">
            <!-- Overview -->
            <div class="tab-pane fade show active" id="overview">
                <div class="profile-info-container" style="display: block;">
                    <h3 class="form-title">Artist Overview</h3>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-card">
                                <div class="info-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="info-content">
                                    <h5>Full Name</h5>
                                    <p><?php echo htmlspecialchars($values['fullName']); ?></p>
                                </div>
                            </div>
                            <div class="info-card">
                                <div class="info-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="info-content">
                                    <h5>Date of Birth</h5>
                                    <p><?php echo htmlspecialchars($values['birthDate']); ?></p>
                                </div>
                            </div>
                            <div class="info-card">
                                <div class="info-icon">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div class="info-content">
                                    <h5>Education</h5>
                                    <p><?php echo htmlspecialchars($values['education']); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-card">
                                <div class="info-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="info-content">
                                    <h5>Email</h5>
                                    <p><?php echo htmlspecialchars($values['email']); ?></p>
                                </div>
                            </div>
                            <div class="info-card">
                                <div class="info-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="info-content">
                                    <h5>Location</h5>
                                    <p><?php echo htmlspecialchars($values['location']); ?></p>
                                </div>
                            </div>
                            <div class="info-card">
                                <div class="info-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="info-content">
                                    <h5>Phone</h5>
                                    <p><?php echo htmlspecialchars($values['phone']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h4>Artistic Goals</h4>
                        <p><?php echo nl2br(htmlspecialchars($values['artisticGoals'])); ?></p>
                    </div>
                    <div class="mt-4">
                        <h4>Art Styles</h4>
                        <p><?php echo implode(', ', array_map('htmlspecialchars', $values['styles'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Portfolio -->
            <div class="tab-pane fade" id="portfolio">
                <h3>My Art Gallery</h3>
                <div class="artworks-container">
                    <?php if (!$hasArtworks): ?>
                        <div class="no-artworks text-center py-5">
                            <h4 class="mb-3" style="color: var(--dark);">This artist has no artworks yet.</h4>
                        </div>
                    <?php else: ?>
                        <div class="artworks-grid">
                            <?php foreach ($artworks as $artwork): ?>
                                <div class="artwork-card">
                                    <img src="<?php echo htmlspecialchars($artwork['image_path']); ?>" class="artwork-image" alt="<?php echo htmlspecialchars($artwork['title']); ?>">
                                    <div class="artwork-details">
                                        <h5 class="artwork-title"><?php echo htmlspecialchars($artwork['title']); ?></h5>
                                        <p class="artwork-price">$<?php echo number_format($artwork['price'], 2); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="artistic-footer bg-dark text-light py-5">
        <div class="container">
            <div class="row g-5">
                <!-- Brand & Social -->
                <div class="col-lg-4">
                    <div class="footer-brand mb-4">
                        <h3 class="mb-3">Artistic</h3>
                        <p class="small">Where creativity meets community</p>
                    </div>
                    <div class="social-grid">
                        <a href="#" class="social-icon">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="social-icon">
                            <i class="fab fa-behance"></i>
                        </a>
                        <a href="#" class="social-icon">
                            <i class="fab fa-dribbble"></i>
                        </a>
                        <a href="#" class="social-icon">
                            <i class="fab fa-artstation"></i>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="col-lg-2 col-6">
                    <h5 class="text-primary mb-4">Create</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-light">Challenges</a></li>
                        <li class="mb-2"><a href="#" class="text-light">Tutorials</a></li>
                        <li class="mb-2"><a href="#" class="text-light">Resources</a></li>
                        <li class="mb-2"><a href="#" class="text-light">Workshops</a></li>
                    </ul>
                </div>

                <!-- Community -->
                <div class="col-lg-2 col-6">
                    <h5 class="text-primary mb-4">Community</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-light">Gallery</a></li>
                        <li class="mb-2"><a href="#" class="text-light">Forum</a></li>
                        <li class="mb-2"><a href="#" class="text-light">Events</a></li>
                        <li class="mb-2"><a href="#" class="text-light">Blog</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div class="col-lg-4 col-6">
                    <h5 class="text-primary mb-4">Contact</h5>
                    <div class="mb-3">
                        <p class="small mb-1"><i class="fas fa-map-marker-alt me-2"></i>123 Art Street, Creative City</p>
                        <p class="small mb-1"><i class="fas fa-envelope me-2"></i>contact@arthub.com</p>
                        <p class="small"><i class="fas fa-phone me-2"></i>+1 (555) ART-HUB</p>
                    </div>
                    <div class="art-gallery">
                        <div class="row g-2">
                            <div class="col-4"><img src="./img/pexels-pixabay-159862.jpg" class="img-fluid rounded" alt="Artwork"></div>
                            <div class="col-4"><img src="./img/pexels-tiana-18128-2956376.jpg" class="img-fluid rounded" alt="Artwork"></div>
                            <div class="col-4"><img src="./img/pexels-andrew-2123337.jpg" class="img-fluid rounded" alt="Artwork"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Copyright -->
            <div class="border-top pt-4 mt-5 text-center">
                <p class="small mb-0 text-muted">
                    &copy 24.3.2025- <?php echo date("d.m.Y")?> ArtHub. All rights reserved. 
                    <a href="#" class="text-muted">Privacy</a> | 
                    <a href="#" class="text-muted">Terms</a> | 
                    <a href="#" class="text-muted">FAQs</a>
                </p>
            </div>
        </div>
    </footer>
      

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
