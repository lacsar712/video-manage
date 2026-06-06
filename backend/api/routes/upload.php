<?php
function uploadImage($subDir) {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        error('请选择要上传的文件');
    }

    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];

    $maxSize = 5 * 1024 * 1024;
    if ($fileSize > $maxSize) {
        error('文件大小不能超过 5MB');
    }

    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $fileTmpName);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        error('只支持 JPG、PNG、GIF、WebP 格式的图片');
    }

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        error('不支持的文件扩展名');
    }

    $newFileName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

    $uploadDir = __DIR__ . '/../../uploads/' . $subDir . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destination = $uploadDir . $newFileName;
    if (!move_uploaded_file($fileTmpName, $destination)) {
        error('文件上传失败');
    }

    $url = '/uploads/' . $subDir . '/' . $newFileName;

    success(['url' => $url], '上传成功');
}

function uploadCover() {
    uploadImage('covers');
}

function uploadBanner() {
    uploadImage('banners');
}

function handleUploadRequest($path, $method) {
    if ($method === 'POST' && $path === 'upload/cover') {
        uploadCover();
    } elseif ($method === 'POST' && $path === 'upload/banner') {
        uploadBanner();
    } else {
        error('接口不存在', 404);
    }
}
