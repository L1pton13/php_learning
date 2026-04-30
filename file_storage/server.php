<?php
/**
 * Удалённое файловое хранилище — сервер
 * Запуск: php -S 0.0.0.0:8080 server.php
 *
 * Поддерживаемые методы:
 *   GET    /files/{path}  — читать файл
 *   PUT    /files/{path}  — перезаписать файл
 *   POST   /files/{path}  — добавить в конец файла
 *   DELETE /files/{path}  — удалить файл
 *   COPY   /files/{path}  — скопировать (заголовок Destination)
 *   MOVE   /files/{path}  — переместить (заголовок Destination)
 *
 * GET /list/{path}        — список файлов/папок в директории
 * POST /mkdir/{path}      — создать папку
 */

// Настройки
define('STORAGE_ROOT', realpath(__DIR__) . DIRECTORY_SEPARATOR . 'storage');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, COPY, MOVE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Destination, X-Action');


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS'){
    http_response_code(204);
    exit;
}

if (!is_dir(STORAGE_ROOT)){
    mkdir(STORAGE_ROOT, 0755, true);
}

// Разбор URL
$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Отдаём index.html при запросе корня или самого файла
if ($path === '/' || $path === '/index.html' || $path === '') {
    header('Content-Type: text/html; charset=utf-8');
    $file = __DIR__ . '/index.html';
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

// GET /list/{dir} — список содержимого папки
if ($method === 'GET' && preg_match('#^/list(/.*)?$#', $path, $m)) {
    $dir = isset($m[1]) ? ltrim($m[1], '/') : '';
    listDirectory($dir);
    exit;
}

// POST /mkdir/{dir} — создать папку
if ($method === 'POST' && preg_match('#^/mkdir(/.*)?$#', $path, $m)) {
    $dir = isset($m[1]) ? ltrim($m[1], '/') : '';
    makeDirectory($dir);
    exit;
}

if (preg_match('#^/files(/.*)?$#', $path, $m)) {
    $relativePath = isset($m[1]) ? ltrim($m[1], '/') : '';

    switch ($method) {
        case 'GET': handleGet($relativePath);       break;
        case 'PUT': handlePut($relativePath);       break;
        case 'POST': handlePost($relativePath);     break;
        case 'DELETE': handleDelete($relativePath); break;
        case 'COPY': handleCopy($relativePath);     break;
        case 'MOVE': handleMove($relativePath);     break;
        default:    jsonError(405, "Метод не поддерживается");
    }
    exit;
}

//jsonError(404, 'Маршрут не найден');

// GET — прочитать и вернуть файл
function handleGet(string $relativePath): void
{
    $fullPath = safePath($relativePath);
 
    if (!file_exists($fullPath)) {
        jsonError(404, "Файл не найден: $relativePath");
        return;
    }
    if (is_dir($fullPath)) {
        jsonError(400, "Указан путь к папке, а не файлу");
        return;
    }

    $mime = getMimeType($fullPath);
    $size = filesize($fullPath);
 
    header("Content-Type: $mime");
    header("Content-Length: $size");
    header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
    http_response_code(200);
    readfile($fullPath);
}

// PUT — перезаписать файл (создаёт если нет)
function handlePut (string $relativePath): void
{
    if (empty($relativePath)) {
        jsonError(400, "Укажите путь к файлу");
        return;
    }
 
    $fullPath = safePath($relativePath);
    $dir = dirname($fullPath);
 
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Читаем тело запроса (содержимое файла)
    $body = file_get_contents('php://input');
 
    if (file_put_contents($fullPath, $body) === false) {
        jsonError(500, "Не удалось записать файл");
        return;
    }
 
    jsonSuccess(200, "Файл записан", [
        'path' => $relativePath,
        'size' => strlen($body)
    ]);
}

// POST — добавить данные в конец файла
function handlePost(string $relativePath): void 
{
    if (empty($relativePath)) {
        jsonError(400, "Укажите путь к файлу");
        return;
    }
 
    $fullPath = safePath($relativePath);
    $dir = dirname($fullPath);
 
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
 
    $body = file_get_contents('php://input');
 
    // FILE_APPEND — дописываем в конец, не перезаписываем
    if (file_put_contents($fullPath, $body, FILE_APPEND) === false) {
        jsonError(500, "Не удалось дописать файл");
        return;
    }
 
    jsonSuccess(200, "Данные добавлены в конец файла", [
        'path'       => $relativePath,
        'appended'   => strlen($body),
        'total_size' => filesize($fullPath)
    ]);
}

// DELETE — удалить файл
function handleDelete(string $relativePath): void 
{
    $fullPath = safePath($relativePath);
 
    if (!file_exists($fullPath)) {
        jsonError(404, "Файл не найден: $relativePath");
        return;
    }
    if (is_dir($fullPath)) {
        jsonError(400, "Нельзя удалить папку этим методом");
        return;
    }
 
    if (!unlink($fullPath)) {
        jsonError(500, "Не удалось удалить файл");
        return;
    }
 
    jsonSuccess(200, "Файл удалён", ['path' => $relativePath]);
}

/*
    COPY — скопировать файл
    Путь назначения берётся из заголовка Destination
 */
function handleCopy(string $relativePath): void 
{
    $destRelative = getDestinationHeader();
    if ($destRelative === null) return;
 
    $srcPath  = safePath($relativePath);
    $destPath = safePath($destRelative);
 
    if (!file_exists($srcPath)) {
        jsonError(404, "Исходный файл не найден: $relativePath");
        return;
    }
 
    $destDir = dirname($destPath);
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
 
    if (!copy($srcPath, $destPath)) {
        jsonError(500, "Не удалось скопировать файл");
        return;
    }
 
    jsonSuccess(200, "Файл скопирован", [
        'from' => $relativePath,
        'to'   => $destRelative
    ]);
}

/*
    MOVE — переместить файл
    Путь назначения берётся из заголовка Destination
 */
function handleMove(string $relativePath): void 
{
    $destRelative = getDestinationHeader();
    if ($destRelative === null) return;
 
    $srcPath  = safePath($relativePath);
    $destPath = safePath($destRelative);
 
    if (!file_exists($srcPath)) {
        jsonError(404, "Исходный файл не найден: $relativePath");
        return;
    }
 
    $destDir = dirname($destPath);
    if (!is_dir($destDir)) {
        // Создаём новые папки по пути назначения если нужно
        mkdir($destDir, 0755, true);
    }
 
    if (!rename($srcPath, $destPath)) {
        jsonError(500, "Не удалось переместить файл");
        return;
    }
 
    jsonSuccess(200, "Файл перемещён", [
        'from' => $relativePath,
        'to'   => $destRelative
    ]);
}

// LIST — вернуть содержимое папки в виде JSON
function listDirectory(string $relativePath): void 
{
    $fullPath = safePath($relativePath);
 
    if (!is_dir($fullPath)) {
        jsonError(404, "Папка не найдена: $relativePath");
        return;
    }
 
    $items = [];
    $entries = scandir($fullPath);
 
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
 
        $entryPath = $fullPath . DIRECTORY_SEPARATOR . $entry;
        $relEntry  = $relativePath ? "$relativePath/$entry" : $entry;
 
        $items[] = [
            'name'     => $entry,
            'path'     => $relEntry,
            'type'     => is_dir($entryPath) ? 'dir' : 'file',
            'size'     => is_file($entryPath) ? filesize($entryPath) : null,
            'modified' => date('Y-m-d H:i', filemtime($entryPath)),
            'mime'     => is_file($entryPath) ? getMimeType($entryPath) : null,
        ];
    }
 
    // Сортируем: сначала папки, потом файлы
    usort($items, fn($a, $b) =>
        ($a['type'] === 'dir' ? 0 : 1) - ($b['type'] === 'dir' ? 0 : 1)
        ?: strcasecmp($a['name'], $b['name'])
    );
 
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode([
        'ok'   => true,
        'path' => $relativePath,
        'items'=> $items
    ], JSON_UNESCAPED_UNICODE);
}

// MKDIR — создать папку
function makeDirectory(string $relativePath): void 
{
    if (empty($relativePath)) {
        jsonError(400, "Укажите имя папки");
        return;
    }
 
    $fullPath = safePath($relativePath);
 
    if (is_dir($fullPath)) {
        jsonSuccess(200, "Папка уже существует", ['path' => $relativePath]);
        return;
    }
 
    if (!mkdir($fullPath, 0755, true)) {
        jsonError(500, "Не удалось создать папку");
        return;
    }
 
    jsonSuccess(201, "Папка создана", ['path' => $relativePath]);
}

// Вспомогательные функции
 
/*
    Проверяет безопасность пути — нельзя выйти за пределы STORAGE_ROOT
    Защита от path traversal: ../../etc/passwd и т.п.
 */
function safePath(string $relativePath): string {
    // Убираем опасные символы и нормализуем разделители
    $relativePath = str_replace('\\', '/', $relativePath);
    $relativePath = trim($relativePath, '/');
 
    // realpath не работает для несуществующих путей,
    // поэтому нормализуем вручную через explode
    $parts = [];
    foreach (explode('/', $relativePath) as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') {
            array_pop($parts); // убираем последний компонент
        } else {
            $parts[] = $part;
        }
    }
 
    $safeParts = implode(DIRECTORY_SEPARATOR, $parts);
    $fullPath  = STORAGE_ROOT . ($safeParts ? DIRECTORY_SEPARATOR . $safeParts : '');
 
    // Дополнительная проверка: если путь существует, используем realpath
    if (file_exists($fullPath)) {
        $real = realpath($fullPath);
        $root = realpath(STORAGE_ROOT) ?: STORAGE_ROOT;
        if ($real && strpos($real, $root) !== 0) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Доступ запрещён']);
            exit;
        }
        return $real ?: $fullPath;
    }
 
    return $fullPath;
}
 
// Извлекает относительный путь назначения из заголовка Destination
function getDestinationHeader(): ?string {
    // PHP кладёт заголовки в $_SERVER с префиксом HTTP_
    // Но Destination — нестандартный, Apache/Nginx может добавить HTTP_ 
    $dest = $_SERVER['HTTP_DESTINATION']
         ?? getallheaders()['Destination']
         ?? null;
 
    if (empty($dest)) {
        jsonError(400, "Отсутствует заголовок Destination");
        return null;
    }
 
    // Извлекаем только путь: http://192.168.1.5:8080/files/sub/file.txt → sub/file.txt
    $parsed = parse_url($dest, PHP_URL_PATH);
    $parsed = ltrim($parsed, '/');
 
    if (str_starts_with($parsed, 'files/')) {
        $parsed = substr($parsed, 6);
    } elseif ($parsed === 'files') {
        $parsed = '';
    }
 
    return $parsed;
}
 
// Определяет MIME-тип файла по расширению
function getMimeType(string $path): string {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $map = [
        'txt'  => 'text/plain',
        'html' => 'text/html',
        'htm'  => 'text/html',
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'xml'  => 'application/xml',
        'csv'  => 'text/csv',
        'md'   => 'text/markdown',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'pdf'  => 'application/pdf',
        'zip'  => 'application/zip',
        'mp3'  => 'audio/mpeg',
        'wav'  => 'audio/wav',
        'ogg'  => 'audio/ogg',
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'avi'  => 'video/x-msvideo',
    ];
    return $map[$ext] ?? 'application/octet-stream';
}
 
// Отправляет JSON-ответ об успехе
function jsonSuccess(int $code, string $message, array $data = []): void {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode(
        array_merge(['ok' => true, 'message' => $message], $data),
        JSON_UNESCAPED_UNICODE
    );
}
 
// Отправляет JSON-ответ об ошибке
function jsonError(int $code, string $error): void {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
}