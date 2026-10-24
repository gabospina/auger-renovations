<?php
session_start();

// 1. SECURITY: Redirect to login if not authenticated
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: admin.php");
    exit;
}

// 2. SETUP DIRECTORIES & JSON FILE
$dataDir = __DIR__ . '/data';
$galleryFile = $dataDir . '/gallery.json';
$baseImageDir = __DIR__ . '/../assets/images';

if (!is_dir($dataDir)) { mkdir($dataDir, 0777, true); }
if (!file_exists($galleryFile)) { file_put_contents($galleryFile, json_encode([])); }

$galleryData = json_decode(file_get_contents($galleryFile), true) ?: [];
$message = '';

// 3. HANDLE PHOTO DELETION
if (isset($_GET['delete'])) {
    $idToDelete = $_GET['delete'];
    
    foreach ($galleryData as $index => $photo) {
        if ($photo['id'] === $idToDelete) {
            $filePath = __DIR__ . '/../' . $photo['path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            array_splice($galleryData, $index, 1);
            break;
        }
    }
    
    file_put_contents($galleryFile, json_encode($galleryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    header("Location: gestion-photos.php?msg=deleted");
    exit;
}

// 4. HANDLE NEW PHOTO UPLOADS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photos'])) {
    $category = $_POST['category'] ?? 'salle_de_bains';
    $title = trim($_POST['title'] ?? 'Salle de bain rénovée');
    $description = trim($_POST['description'] ?? 'Réalisation récente par Auger Rénovation.');
    
    $targetDir = $baseImageDir . '/' . $category;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $uploadedCount = 0;
    
    foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
            $fileName = basename($_FILES['photos']['name'][$key]);
            $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($fileType, $allowedTypes)) {
                
                $newFileName = time() . '_' . rand(1000, 9999) . '.' . $fileType;
                $targetFilePath = $targetDir . '/' . $newFileName;
                
                if (move_uploaded_file($tmp_name, $targetFilePath)) {
                    $newPhoto = [
                        'id'          => uniqid('img_'),
                        'category'    => $category,
                        'title'       => $title,
                        'description' => $description,
                        'path'        => 'assets/images/' . $category . '/' . $newFileName,
                        'timestamp'   => time(),
                        'date_added'  => date('Y-m-d H:i:s')
                    ];
                    
                    // Add to the VERY TOP of the array so newest shows first
                    array_unshift($galleryData, $newPhoto);
                    $uploadedCount++;
                }
            }
        }
    }

    if ($uploadedCount > 0) {
        file_put_contents($galleryFile, json_encode($galleryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $message = "$uploadedCount photo(s) uploaded successfully!";
    } else {
        $message = "Error: Invalid file formats or no files selected.";
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = "Photo deleted successfully.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Gestion des Photos | Auger Rénovation</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { font-family: system-ui, -apple-system, sans-serif; background: #f6f8fa; margin: 0; color: #24292e; }
    .topbar { background: #24292e; color: #fff; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
    .topbar a { color: #fff; text-decoration: none; font-weight: bold; background: #444; padding: 8px 12px; border-radius: 4px; }
    .topbar a:hover { background: #d1b06a; }
    .container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
    
    .upload-card { background: #fff; padding: 25px; border-radius: 8px; border: 1px solid #d0d7de; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .upload-card h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 15px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: bold; margin-bottom: 8px; }
    select, input[type="text"], textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; font-size: 14px; }
    textarea { resize: vertical; min-height: 70px; line-height: 1.4; }
    
    .custom-file-upload { display: inline-block; padding: 14px 15px; cursor: pointer; border: 2px dashed #a0aec0; border-radius: 6px; background: #f8fafc; font-weight: bold; color: #4a5568; text-align: center; transition: all 0.2s; width: 100%; box-sizing: border-box; }
    .custom-file-upload:hover { background: #edf2f7; border-color: #d1b06a; color: #24292e; }
    #preview-container { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 15px; }
    .preview-img { width: 100px; height: 100px; object-fit: cover; border-radius: 6px; border: 2px solid #d1b06a; box-shadow: 0 2px 6px rgba(0,0,0,0.12); }
    
    .btn-submit { background: #d1b06a; color: #fff; border: none; padding: 12px 24px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 16px; margin-top: 10px; }
    .btn-submit:hover { background: #c2a15b; }
    
    .success-msg { background: #d4edda; color: #155724; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb; }

    .gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 15px; }
    .gallery-item { background: #fff; border: 1px solid #d0d7de; border-radius: 6px; overflow: hidden; position: relative; }
    .gallery-item img { width: 100%; height: 160px; object-fit: cover; display: block; }
    .gallery-info { padding: 12px; font-size: 13px; color: #555; }
    .gallery-meta { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; padding-top: 8px; border-top: 1px solid #eee; }
    .btn-delete { color: #c92a2a; text-decoration: none; font-weight: bold; }
    .btn-delete:hover { text-decoration: underline; }
  </style>
</head>
<body>

  <div class="topbar">
    <div style="font-size: 18px;"><strong>Auger Rénovation</strong> — Portfolio Manager</div>
    <div style="display: flex; gap: 10px;">
      <a href="../salle_de_bains.html" target="_blank" style="background: #d1b06a;"><i class="fa-solid fa-eye"></i> View Public Page</a>
      <a href="admin.php"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
    </div>
  </div>

  <div class="container">
    
    <?php if ($message): ?>
      <div class="success-msg"><i class="fa-solid fa-check"></i> <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- UPLOAD FORM -->
    <div class="upload-card">
      <h2><i class="fa-solid fa-cloud-arrow-up" style="color: #d1b06a;"></i> Upload New Project Photo(s)</h2>
      <form method="post" enctype="multipart/form-data">
        
        <div class="form-group">
          <label>Category (Where should these show up?)</label>
          <select name="category" required>
            <option value="salle_de_bains">Salle de Bains (Bathrooms)</option>
            <option value="cuisines">Cuisines (Kitchens)</option>
            <option value="sous_sols">Sous-sols (Basements)</option>
          </select>
        </div>

        <div class="form-group">
          <label>Project Title (Displayed under the photo)</label>
          <input type="text" name="title" placeholder="Ex: Salle de bain moderne à Vaudreuil-Dorion" required>
        </div>

        <div class="form-group">
          <label>Short Description (Auto-expands or drag corner to resize)</label>
          <textarea 
            name="description" 
            rows="3" 
            placeholder="Ex: Douche en céramique, vanité flottante et éclairage encastré." 
            oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'" 
            required></textarea>
        </div>

        <div class="form-group">
          <label>Select Photo</label>
          <label for="photo-upload" class="custom-file-upload" id="upload-label">
            <i class="fa-solid fa-camera"></i> Choose Photo
          </label>
          <input type="file" id="photo-upload" name="photos[]" accept="image/png, image/jpeg, image/jpg, image/webp" multiple required style="display: none;">
          
          <!-- Thumbnail Previews appear here -->
          <div id="preview-container"></div>
        </div>

        <button type="submit" class="btn-submit"><i class="fa-solid fa-upload"></i> Upload Photo</button>
      </form>
    </div>

    <!-- DISPLAY EXISTING PHOTOS -->
    <h2><i class="fa-solid fa-images"></i> Manage Uploaded Photos</h2>
    <div class="gallery-grid">
      <?php if (empty($galleryData)): ?>
        <p style="color: #666;">No dynamic photos uploaded yet.</p>
      <?php else: ?>
        <?php foreach ($galleryData as $photo): ?>
          <div class="gallery-item">
            <img src="../<?= htmlspecialchars($photo['path']) ?>" alt="<?= htmlspecialchars($photo['title'] ?? '') ?>">
            <div class="gallery-info">
              <strong style="display: block; color: #24292e; margin-bottom: 4px;"><?= htmlspecialchars($photo['title'] ?? 'Sans titre') ?></strong>
              <span style="font-size: 12px; color: #666; display: block; white-space: pre-wrap;"><?= htmlspecialchars($photo['description'] ?? '') ?></span>
              <div class="gallery-meta">
                <span style="background: #eee; padding: 2px 6px; border-radius: 4px; font-size: 11px;"><?= htmlspecialchars(str_replace('_', ' ', $photo['category'])) ?></span>
                <a href="?delete=<?= $photo['id'] ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this photo?');"><i class="fa-solid fa-trash"></i> Delete</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>

  <!-- JAVASCRIPT FOR THUMBNAIL PREVIEWS -->
  <script>
    document.getElementById('photo-upload').addEventListener('change', function(event) {
      const previewContainer = document.getElementById('preview-container');
      const uploadLabel = document.getElementById('upload-label');
      
      previewContainer.innerHTML = ''; 
      
      const files = event.target.files;
      if (files && files.length > 0) {
        uploadLabel.innerHTML = `<i class="fa-solid fa-check" style="color: #38a169;"></i> ${files.length} Photo(s) Selected — Click to change`;
        
        Array.from(files).forEach(file => {
          if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.classList.add('preview-img');
            previewContainer.appendChild(img);
          }
        });
      } else {
        uploadLabel.innerHTML = `<i class="fa-solid fa-camera"></i> Choose Photo`;
      }
    });
  </script>

</body>
</html>