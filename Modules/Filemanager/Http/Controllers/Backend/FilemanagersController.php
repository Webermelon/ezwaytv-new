<?php
namespace Modules\Filemanager\Http\Controllers\Backend;

use App\Authorizable;
use App\Http\Controllers\Controller;
use Modules\Filemanager\Models\Filemanager;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Modules\Filemanager\Http\Requests\FilemanagerRequest;
use App\Traits\ModuleTrait;
use App\Models\Setting;
use App\Models\MediaCompressorJob;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessFileUpload;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Entertainment\Models\Entertainment;
use Illuminate\Support\Facades\Log;

class FilemanagersController extends Controller
{
    protected string $exportClass = '\App\Exports\FilemanagerExport';

    use ModuleTrait {
        initializeModuleTrait as private traitInitializeModuleTrait;
    }

    public function __construct()
    {
        $this->traitInitializeModuleTrait(
            'filemanager.title', // module title
            'media', // module name
            'fa-solid fa-clipboard-list' // module icon
        );
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $module_action = 'List';
        $searchQuery = $request->get('query');
        $perPage = 31;
        $page = $request->get('page', 1);

        $result = getMediaUrls($searchQuery, $perPage, $page);
        $mediaUrls = $result['mediaUrls'];
        $hasMore = $result['hasMore'];

        if ($request->ajax()) {
            return response()->json([
                'html' => view('filemanager::backend.filemanager.partial', compact('mediaUrls'))->render(),
                'hasMore' => $hasMore,
            ]);
        }

        return view('filemanager::backend.filemanager.index', compact('module_action', 'mediaUrls', 'hasMore'));
    }



    public function getMediaStore(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = 31; // Number of items per page

        $searchQuery = $request->get('query');
        $result = getMediaUrls($searchQuery, $perPage, $page);


        $mediaUrls = $result['mediaUrls'];
        $hasMore = $result['hasMore'];

        $html = view('filemanager::backend.filemanager.partial', compact('mediaUrls'))->render();

            return response()->json([
                'html' => $html,
                'hasMore' => $hasMore,
            ]);
    }


    public function store(FilemanagerRequest $request)
  {

    $page_type = $request->input('page_type');
        $normalizedPageType = $page_type;
        $lastUploadedFileName = null;
        if ($normalizedPageType === 'season') {
            $normalizedPageType = 'tvshow/season';
        } elseif ($normalizedPageType === 'episode') {
            $normalizedPageType = 'tvshow/episode';
        }

    $jobs = [];
        $syncProcessedCount = 0;
        $redirectFolder = null;
        $uploadedFilesForResponse = [];

    // Mode A: direct file post (fallback)
    if ($request->hasFile('file_url')) {
        foreach ($request->file('file_url') as $file) {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileType = $this->getFileType($extension);
            $baseName = pathinfo($originalName, PATHINFO_FILENAME);
            $sanitizedBaseName = $this->sanitizeUploadBaseName($baseName);
            $uniqueFileName = $sanitizedBaseName . '_' . uniqid() . '.' . $extension;
            $temporaryPath = $file->storeAs('temp/uploads', $uniqueFileName);
            // If chunk-assembled temp (original name) exists, remove to avoid duplicate
            $assembledTempPath = storage_path('app/temp/uploads/' . $originalName);
            if (file_exists($assembledTempPath)) {
                @unlink($assembledTempPath);
            }
            $filemanagerData = [
                'file_url' => $temporaryPath,
                'file_name' => $uniqueFileName,
            ];
            if (Schema::hasColumn('filemanagers', 'status')) {
                $filemanagerData['status'] = $fileType === 'video' ? 'processing' : 'ready';
            }
            $filemanager = Filemanager::create($filemanagerData);
            $lastUploadedFileName = $uniqueFileName;
            if ($redirectFolder === null) {
                $targetType = in_array($fileType, ['image', 'video'], true) ? $fileType : 'other';
                $redirectFolder = trim($normalizedPageType . '/' . $targetType, '/');
            }
            $diskType = config('filesystems.active', env('ACTIVE_STORAGE', 'local'));
            Log::info('file uploaded', ['file' => $uniqueFileName]);
            if ($fileType === 'image') {
                // Keep images immediate so previews are available right away.
                ProcessFileUpload::dispatchSync($filemanager, $temporaryPath, $diskType, $originalName, $page_type, $fileType);
                $syncProcessedCount++;
            } else {
                $job = new ProcessFileUpload($filemanager, $temporaryPath, $diskType, $originalName, $page_type, $fileType);
                $jobs[] = $job;
            }

            $filemanager->refresh();
            $uploadedFilesForResponse[] = [
                'original_name' => $originalName,
                'file_name' => $filemanager->file_name,
                'file_type' => $fileType,
                'status' => $filemanager->status ?? 'ready',
                'remote_job_id' => $filemanager->remote_job_id ?? null,
            ];
        }
    }
    // Mode B: chunk upload already assembled; receive only file names
    elseif ($request->filled('file_names')) {
        foreach ((array) $request->input('file_names', []) as $originalName) {
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $fileType = $this->getFileType($extension);
            $baseName = pathinfo($originalName, PATHINFO_FILENAME);
            $sanitizedBaseName = $this->sanitizeUploadBaseName($baseName);
            $uniqueFileName = $sanitizedBaseName . '_' . uniqid() . '.' . $extension;
            // Source path is the assembled temp file produced by /upload
            $temporaryPath = 'temp/uploads/' . $originalName;
            $filemanagerData = [
                'file_url' => $temporaryPath,
                'file_name' => $uniqueFileName,
            ];
            if (Schema::hasColumn('filemanagers', 'status')) {
                $filemanagerData['status'] = $fileType === 'video' ? 'processing' : 'ready';
            }
            $filemanager = Filemanager::create($filemanagerData);
            $lastUploadedFileName = $uniqueFileName;
            if ($redirectFolder === null) {
                $targetType = in_array($fileType, ['image', 'video'], true) ? $fileType : 'other';
                $redirectFolder = trim($normalizedPageType . '/' . $targetType, '/');
            }
            $diskType = config('filesystems.active', env('ACTIVE_STORAGE', 'local'));
            Log::info('queued assembled temp', ['file' => $originalName]);
            if ($fileType === 'image') {
                // Keep images immediate so previews are available right away.
                ProcessFileUpload::dispatchSync($filemanager, $temporaryPath, $diskType, $originalName, $page_type, $fileType);
                $syncProcessedCount++;
            } else {
                $job = new ProcessFileUpload($filemanager, $temporaryPath, $diskType, $originalName, $page_type, $fileType);
                $jobs[] = $job;
            }

            $filemanager->refresh();
            $uploadedFilesForResponse[] = [
                'original_name' => $originalName,
                'file_name' => $filemanager->file_name,
                'file_type' => $fileType,
                'status' => $filemanager->status ?? 'ready',
                'remote_job_id' => $filemanager->remote_job_id ?? null,
            ];
        }
    }

    if (!empty($jobs)) {

        Bus::batch($jobs)->dispatch();
        Log::info('batch dispatched', ['count' => count($jobs)]);

        // foreach ($jobs as $job) {
        //      ProcessFileUpload::dispatchSync(
        //         $job->filemanager,
        //         $job->filePath,
        //         $job->diskType,
        //         $job->originalName,
        //         $job->page_type,
        //         $job->fileType
        //     );
        // }
        // Log::info('jobs dispatched synchronously', ['count' => count($jobs)]);

    } elseif ($syncProcessedCount === 0) {
        Log::warning('no jobs queued for upload');
    }
    $message = trans('filemanager.file_added');
    $redirectParams = [];
    if (!empty($redirectFolder)) {
        $redirectParams['open_folder'] = $redirectFolder;
    }

    if ($request->ajax() || $request->wantsJson()) {
        // Clean temp files older than 1 hour to avoid accumulating local storage
        try {
            $tempDir = storage_path('app/temp');
            if (is_dir($tempDir)) {
                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tempDir));
                $now = time();
                foreach ($files as $file) {
                    if ($file->isFile()) {
                        $fileMTime = $file->getMTime();
                        // delete files older than 1 hour (3600 seconds)
                        if ($now - $fileMTime > 3600) {
                            @unlink($file->getPathname());
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('temp cleanup failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'file_name' => $lastUploadedFileName,
            'files' => $uploadedFilesForResponse,
            'redirect_folder' => $redirectFolder,
            'processing_count' => count($jobs),
        ]);
    }

    return redirect()->route('backend.media-library.index', $redirectParams)->with('success', $message);
}


private function getFileType($extension)
{
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'ico', 'tiff', 'tif'];
    $videoExtensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', '3gp', 'm4v', 'mpg', 'mpeg'];

    $extension = strtolower($extension);

    if (in_array($extension, $imageExtensions)) {
        return 'image';
    } elseif (in_array($extension, $videoExtensions)) {
        return 'video';
    } else {
        return 'other';
    }
}

private function sanitizeUploadBaseName(string $baseName): string
{
    $sanitized = preg_replace('/[^A-Za-z0-9_]+/', '_', rawurldecode($baseName)) ?: '';
    $sanitized = trim($sanitized, '_');

    return $sanitized !== '' ? $sanitized : 'media';
}


//old file upload code


// public function upload(Request $request)
// {
//     $fileChunk = $request->file('file_chunk');
//     $fileName = $request->input('file_name');           // original name or server-generated token
//     $index = (int) $request->input('index');            // 0-based index
//     $totalChunks = (int) $request->input('total_chunks');
//     $temporaryDirectory = storage_path('app/temp/uploads/');
//     if (! is_dir($temporaryDirectory)) {
//         mkdir($temporaryDirectory, 0775, true);
//     }
//     $partPath = $temporaryDirectory . $fileName . '.part' . $index;
//     $fileChunk->move($temporaryDirectory, $fileName . '.part' . $index);
//     // If last chunk, merge all parts
//     if ($index + 1 === $totalChunks) {
//         $outputFilePath = $temporaryDirectory . $fileName;  // or final destination path
//         $output = fopen($outputFilePath, 'wb');             // overwrite, not append
//         for ($i = 0; $i < $totalChunks; $i++) {
//             $chunkPath = $temporaryDirectory . $fileName . '.part' . $i;
//             $in = fopen($chunkPath, 'rb');
//             stream_copy_to_stream($in, $output);
//             fclose($in);
//             unlink($chunkPath);
//         }
//         fclose($output);
//     }
//     return response()->json(['success' => true]);
// }


    public function upload(Request $request)
{
    $fileChunk = $request->file('file_chunk');
    $fileName = $request->input('file_name');           // unique name/token for the whole file
    $index = (int) $request->input('index');            // 0-based index
    $totalChunks = (int) $request->input('total_chunks');

    $temporaryDirectory = storage_path('app/temp/uploads/');
    if (! is_dir($temporaryDirectory)) {
        mkdir($temporaryDirectory, 0775, true);
    }

    // Append this chunk directly to a single temp file
    $outputFilePath = $temporaryDirectory . $fileName;

    // First chunk: start fresh
    if ($index === 0 && file_exists($outputFilePath)) {
        @unlink($outputFilePath);
    }

    // Stream-append current chunk
    $in = fopen($fileChunk->getRealPath(), 'rb');
    $out = fopen($outputFilePath, $index === 0 ? 'wb' : 'ab');
    if ($out !== false) {
        // exclusive lock to avoid concurrent writes
        @flock($out, LOCK_EX);
        stream_copy_to_stream($in, $out);
        @flock($out, LOCK_UN);
        fclose($out);
    }
    fclose($in);

    // If last chunk, finalize: move to final storage and remove temp
    // if ($index + 1 === $totalChunks) {
    //     $activeDisk = env('ACTIVE_STORAGE', 'local');
    //     if ($activeDisk === 'local') {
    //         $targetPath = 'public/streamit-laravel/' . $fileName;
    //         \Illuminate\Support\Facades\Storage::disk('local')->put($targetPath, file_get_contents($outputFilePath));
    //     } else {
    //         $targetPath = 'streamit-laravel/' . $fileName;
    //         \Illuminate\Support\Facades\Storage::disk($activeDisk)->put($targetPath, file_get_contents($outputFilePath));
    //     }
    //     @unlink($outputFilePath);
    // }

    return response()->json(['success' => true]);
}
    //delete function chnage while old is repating url with public/storage/
    public function destroy(Request $request)
    {


        $url = (string) $request->input('url', '');
        $requestPath = (string) $request->input('path', '');

        $activeDisk = config('filesystems.active', env('ACTIVE_STORAGE', 'local'));
        $pathsToDelete = $this->storageDeleteCandidates($requestPath, $url, $activeDisk);
        $relativePath = $pathsToDelete[0] ?? '';
        $fileName = basename($relativePath ?: mediaStoragePathFromUrl($url));

        deleteBunnyStreamVideoByFile($fileName);

        $disk = Storage::disk($activeDisk);
        $matchingFilemanagers = $this->matchingFilemanagersForDelete($fileName, $pathsToDelete);

        foreach ($matchingFilemanagers as $filemanager) {
            $storedPath = $this->normalizeDeletePath((string) $filemanager->getRawOriginal('file_url'), $activeDisk);
            if ($storedPath !== '') {
                $pathsToDelete[] = $storedPath;
            }
        }

        if ($activeDisk !== 'local') {
            foreach ($pathsToDelete as $candidate) {
                if (!pathinfo($candidate, PATHINFO_EXTENSION)) {
                    foreach ($disk->files(dirname($candidate) === '.' ? '' : dirname($candidate)) as $candidatePath) {
                        if (str_starts_with(basename($candidatePath), $fileName)) {
                            $pathsToDelete[] = $candidatePath;
                        }
                    }
                }
            }
        }

        $pathsToDelete = array_values(array_unique(array_filter($pathsToDelete)));
        $deletedPaths = [];

        foreach ($pathsToDelete as $pathToDelete) {
            if ($disk->exists($pathToDelete) && $disk->delete($pathToDelete)) {
                $deletedPaths[] = $pathToDelete;
            }
        }

        $deleted = count($deletedPaths) > 0;

        if ($deleted || $matchingFilemanagers->isNotEmpty()) {
            if ($matchingFilemanagers->isEmpty()) {
                $matchingFilemanagers = $this->matchingFilemanagersForDelete($fileName, $deletedPaths);
            }

            $filemanagerIds = $matchingFilemanagers->pluck('id')->all();
            if (!empty($filemanagerIds)) {
                MediaCompressorJob::query()
                    ->where('owner_type', Filemanager::class)
                    ->whereIn('owner_id', $filemanagerIds)
                    ->delete();
            }

            $matchingFilemanagers->each(fn ($filemanager) => $filemanager->forceDelete());

            return response()->json(['success' => true, 'deleted_paths' => $deletedPaths]);
        }

        return response()->json([
            'success' => false,
            'path' => $relativePath,
            'message' => 'File was not found on the active storage disk.',
        ], 404);
    }

    private function storageDeleteCandidates(string $requestPath, string $url, string $activeDisk): array
    {
        $candidates = [];

        foreach ([$requestPath, $url] as $source) {
            $path = $this->normalizeDeletePath($source, $activeDisk);
            if ($path !== '') {
                $candidates[] = $path;
                $candidates = array_merge($candidates, $this->mediaTypePathVariants($path));
            }
        }

        return array_values(array_unique($candidates));
    }

    private function mediaTypePathVariants(string $path): array
    {
        $type = $this->getFileType(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($type, ['image', 'video'], true)) {
            return [];
        }

        $segments = explode('/', trim($path, '/'));
        $fileName = array_pop($segments);
        $mediaSegmentIndex = array_search('image', $segments, true);

        if ($mediaSegmentIndex === false) {
            $mediaSegmentIndex = array_search('video', $segments, true);
        }

        if ($mediaSegmentIndex !== false) {
            $segments[$mediaSegmentIndex] = $type;
            return [implode('/', array_merge($segments, [$fileName]))];
        }

        if (!empty($segments)) {
            $segments[] = $type;
            $segments[] = $fileName;
            return [implode('/', $segments)];
        }

        return [];
    }

    private function normalizeDeletePath(string $pathOrUrl, string $activeDisk): string
    {
        if ($pathOrUrl === '') {
            return '';
        }

        $path = mediaStoragePathFromUrl($pathOrUrl);
        $path = ltrim($path, '/');

        $bucket = (string) config("filesystems.disks.{$activeDisk}.bucket");
        if ($bucket !== '' && str_starts_with($path, $bucket.'/')) {
            $path = substr($path, strlen($bucket) + 1);
        }

        if ($activeDisk === 'local') {
            $storagePos = strpos($path, 'storage/');
            if ($storagePos !== false) {
                $path = 'public/'.ltrim(substr($path, $storagePos + strlen('storage/')), '/');
            } elseif (! str_starts_with($path, 'public/')) {
                $path = 'public/'.$path;
            }

            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return str_starts_with($path, 'public/') ? substr($path, strlen('public/')) : $path;
    }

    private function matchingFilemanagersForDelete(string $fileName, array $paths)
    {
        $paths = collect($paths)
            ->filter()
            ->flatMap(fn ($path) => [$path, $this->normalizeFilemanagerPath((string) $path), 'public/'.$this->normalizeFilemanagerPath((string) $path)])
            ->unique()
            ->values()
            ->all();

        if ($fileName === '' && empty($paths)) {
            return collect();
        }

        return Filemanager::query()
            ->where(function ($query) use ($fileName, $paths) {
                if ($fileName !== '') {
                    $query->where('file_name', $fileName);
                }

                if (!empty($paths)) {
                    $query->orWhereIn('file_url', $paths);
                    foreach ($paths as $path) {
                        $query->orWhere('file_name', basename($path));
                    }
                }
            })
            ->get();
    }

   public function SearchMedia(Request $request){
        $search = $request->input('search', '');
        $storagePath = storage_path('app/public');
        $results = [];

        if (empty($search)) {
            return response()->json([
                'success' => true,
                'results' => []
            ]);
        }

        // Search through all directories recursively
        $this->searchMediaRecursively($storagePath, $search, $results);

        return response()->json([
            'success' => true,
            'results' => $results
        ]);
   }

   private function searchMediaRecursively($path, $search, &$results, $currentFolder = '') {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            $relativePath = $currentFolder ? $currentFolder . '/' . $item : $item;

            if (is_dir($itemPath)) {
                // Recursively search subdirectories
                $this->searchMediaRecursively($itemPath, $search, $results, $relativePath);
            } else {
                // Check if it's an image or video file
                $isVideo = preg_match('/\.(mp4|webm|avi|mov)$/i', $item);
                $isImage = preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $item);

                if (($isVideo || $isImage) && stripos($item, $search) !== false) {
                    // Derive page_type from folder structure
                    $pageType = 'default';
                    $folderSegments = explode('/', $currentFolder);
                    $imageIndex = array_search('image', $folderSegments, true);
                    $videoIndex = array_search('video', $folderSegments, true);

                    if ($imageIndex !== false && $imageIndex > 0) {
                        $pageType = $folderSegments[$imageIndex - 1];
                    } elseif ($videoIndex !== false && $videoIndex > 0) {
                        $pageType = $folderSegments[$videoIndex - 1];
                    } elseif (!empty($folderSegments)) {
                        $pageType = end($folderSegments);
                    }

                    $mediaUrl = '';
                    if ($isVideo || $isImage) {
                        $type = $isVideo ? 'video' : 'image';
                        $mediaUrl = setBaseUrlWithFileName($item, $type, $pageType);
                    }

                    $results[] = [
                        'name' => $item,
                        'path' => $relativePath,
                        'is_dir' => false,
                        'size' => filesize($itemPath),
                        'modified' => filemtime($itemPath),
                        'media_url' => $mediaUrl,
                        'is_video' => $isVideo,
                        'is_image' => $isImage,
                        'folder' => $currentFolder
                    ];
                }
            }
        }
    }

    /**
     * Get folder contents via AJAX with pagination support
     */
    public function getFolderContents(Request $request)
    {
        $folder = $request->get('folder', '');
        $limit = (int) $request->get('limit', 60);
        $offset = (int) $request->get('offset', 0);
        $activeDisk = env('ACTIVE_STORAGE', 'local');
        $contents = [];
        $allItems = [];

        try {
            if ($activeDisk === 'local') {
                // Local storage
                $storagePath = storage_path('app/public');
                $fullPath = $folder ? $storagePath . '/' . $folder : $storagePath;

                if (is_dir($fullPath)) {
                    $items = array_diff(scandir($fullPath), ['.', '..']);
                    foreach ($items as $item) {
                        $itemAbsolutePath = $fullPath . '/' . $item;
                        $isDir = is_dir($itemAbsolutePath);
                        // Build the relative path that the frontend can feed back to us
                        $relativePath = ltrim(($folder ? $folder . '/' : '') . $item, '/');
                        $allItems[] = $this->formatItem($item, $itemAbsolutePath, $folder, 'local', $isDir, $relativePath);
                    }
                }
            } else {
                // Remote storage (Bunny, S3, DO Spaces, etc.)
                $disk = Storage::disk($activeDisk);
                $directories = $disk->directories($folder);

                foreach ($directories as $dir) {
                    $allItems[] = $this->formatItem(basename($dir), $dir, $folder, $activeDisk, true, trim($dir, '/'));
                }

                // Use listContents to fetch file metadata (including lastModified) in one request
                try {
                    $fsDriver = $disk->getDriver();
                    foreach ($fsDriver->listContents($folder ?: '', false) as $fsItem) {
                        if ($fsItem instanceof \League\Flysystem\FileAttributes) {
                            $filePath = $fsItem->path();
                            $modified = $fsItem->lastModified() ?? 0;
                            $size = $fsItem->fileSize() ?? 0;
                            $name = basename($filePath);
                            $relativePath = trim($filePath, '/');
                            $isVideo = (bool) preg_match('/\.(mp4|webm|avi|mov)$/i', $name);
                            $isImage = (bool) preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $name);
                            $pageType = 'default';
                            if (!empty($folder)) {
                                $segments = explode('/', $folder);
                                $videoIndex = array_search('video', $segments, true);
                                $imageIndex = array_search('image', $segments, true);
                                if ($imageIndex !== false && $imageIndex > 0) {
                                    $pageType = $segments[$imageIndex - 1];
                                } elseif ($videoIndex !== false && $videoIndex > 0) {
                                    $pageType = $segments[$videoIndex - 1];
                                } else {
                                    $pageType = end($segments) ?: 'default';
                                }
                            }
                            $mediaUrl = ($isVideo || $isImage) ? setBaseUrlWithFileName($relativePath, $isVideo ? 'video' : 'image', $pageType) : '';
                            $allItems[] = [
                                'name' => $name,
                                'path' => $relativePath,
                                'is_dir' => false,
                                'size' => $size,
                                'modified' => $modified,
                                'uploaded_at' => null,
                                'media_url' => $mediaUrl,
                                'preview_url' => $this->previewUrl($relativePath, $isImage),
                                'is_video' => $isVideo,
                                'is_image' => $isImage,
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    // Fallback: individual file listing
                    $files = $disk->files($folder);
                    foreach ($files as $file) {
                        $allItems[] = $this->formatItem(basename($file), $file, $folder, $activeDisk, false, trim($file, '/'));
                    }
                }
            }

            $allItems = $this->appendPendingFilemanagerItems($allItems, $folder);

            // Sorting: support sort param from frontend
            $sort = $request->get('sort', 'modified_desc');
            if ($sort === 'modified_desc') {
                usort($allItems, function($a, $b) {
                    $timeA = $a['modified'] ?? 0;
                    $timeB = $b['modified'] ?? 0;
                    if ($timeA === $timeB) {
                        // tie-break: directories first, then name (reverse for desc)
                        if (($a['is_dir'] ?? false) !== ($b['is_dir'] ?? false)) {
                            return ($a['is_dir'] ?? false) ? -1 : 1;
                        }
                        return strcasecmp($b['name'] ?? '', $a['name'] ?? '');
                    }
                    return $timeB <=> $timeA;
                });
            } elseif ($sort === 'modified_asc') {
                usort($allItems, function($a, $b) {
                    $timeA = $a['modified'] ?? 0;
                    $timeB = $b['modified'] ?? 0;
                    if ($timeA === $timeB) {
                        // tie-break: directories first, then name (normal for asc)
                        if (($a['is_dir'] ?? false) !== ($b['is_dir'] ?? false)) {
                            return ($a['is_dir'] ?? false) ? -1 : 1;
                        }
                        return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
                    }
                    return $timeA <=> $timeB;
                });
            } elseif ($sort === 'name_asc') {
                usort($allItems, function($a, $b) {
                    // directories first, then name A->Z
                    if (($a['is_dir'] ?? false) !== ($b['is_dir'] ?? false)) {
                        return ($a['is_dir'] ?? false) ? -1 : 1;
                    }
                    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
                });
            } elseif ($sort === 'name_desc') {
                usort($allItems, function($a, $b) {
                    if (($a['is_dir'] ?? false) !== ($b['is_dir'] ?? false)) {
                        return ($a['is_dir'] ?? false) ? -1 : 1;
                    }
                    return strcasecmp($b['name'] ?? '', $a['name'] ?? '');
                });
            } else {
                // fallback to newest first with deterministic tie-break
                usort($allItems, function($a, $b) {
                    $timeA = $a['modified'] ?? 0;
                    $timeB = $b['modified'] ?? 0;
                    if ($timeA === $timeB) {
                        if (($a['is_dir'] ?? false) !== ($b['is_dir'] ?? false)) {
                            return ($a['is_dir'] ?? false) ? -1 : 1;
                        }
                        return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
                    }
                    return $timeB <=> $timeA;
                });
            }

            // Filter out files with no recognised media extension (keep dirs always)
            $allItems = array_values(array_filter($allItems, function ($item) {
                if ($item['is_dir'] ?? false) return true;
                return ($item['is_video'] ?? false) || ($item['is_image'] ?? false);
            }));

            $allItems = $this->attachUploadTimestamps($allItems);

            // Apply pagination
            $totalItems = count($allItems);
            $contents = array_slice($allItems, $offset, $limit);
            $nextOffset = ($offset + $limit) < $totalItems ? ($offset + $limit) : null;

        } catch (\Exception $e) {
            Log::error('Error getting folder contents: ' . $e->getMessage());
        }


        return response()->json([
            'success' => true,
            'contents' => $contents,
            'pagination' => [
                'next_offset' => $nextOffset,
                'total_items' => $totalItems ?? 0,
                'current_offset' => $offset,
                'limit' => $limit,
                'has_more' => $nextOffset !== null
            ]
        ]);
    }

    /**
     * Format a file or directory item for response.
     */
    private function formatItem($name, $absolutePath, $folder, $disk = 'local', $isDir = false, $relativePath = null)
    {
        $isVideo = preg_match('/\.(mp4|webm|avi|mov)$/i', $name);
        $isImage = preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $name);

        // Derive page type
        $pageType = 'default';
        if (!empty($folder)) {
            $segments = explode('/', $folder);
            $imageIndex = array_search('image', $segments, true);
            $videoIndex = array_search('video', $segments, true);

            if ($imageIndex !== false && $imageIndex > 0) {
                $pageType = $segments[$imageIndex - 1];
            } elseif ($videoIndex !== false && $videoIndex > 0) {
                $pageType = $segments[$videoIndex - 1];
            } else {
                $pageType = end($segments) ?: 'default';
            }
        }

        $mediaUrl = '';
        if (!$isDir && ($isVideo || $isImage)) {
            $type = $isVideo ? 'video' : 'image';
            $mediaUrl = setBaseUrlWithFileName($relativePath !== null ? $relativePath : $name, $type, $pageType);
        }

        // When local, compute size/mtime using absolute path; expose relative path to the client
        $size = $disk === 'local' && !$isDir && is_file($absolutePath) ? filesize($absolutePath) : 0;
        if ($disk === 'local') {
            $modified = file_exists($absolutePath) ? filemtime($absolutePath) : 0;
        } else {
            // Remote disk: use Storage::disk()->lastModified() so files sort by real mtime
            try {
                $modified = \Illuminate\Support\Facades\Storage::disk($disk)->lastModified($absolutePath);
            } catch (\Exception $e) {
                $modified = 0;
            }
        }
        return [
            'name' => $name,
            'path' => $relativePath !== null ? $relativePath : $absolutePath,
            'is_dir' => $isDir,
            'size' => $size,
            'modified' => $modified,
            'uploaded_at' => null,
            'status' => 'ready',
            'remote_job_id' => null,
            'media_url' => $mediaUrl,
            'preview_url' => $this->previewUrl($relativePath !== null ? $relativePath : $absolutePath, (bool) $isImage),
            'is_video' => $isVideo,
            'is_image' => $isImage,
        ];
    }

    private function appendPendingFilemanagerItems(array $items, string $folder): array
    {
        if (! Schema::hasColumn('filemanagers', 'status')) {
            return $items;
        }

        $existingPaths = collect($items)
            ->filter(fn ($item) => !($item['is_dir'] ?? false))
            ->pluck('path')
            ->map(fn ($path) => $this->normalizeFilemanagerPath((string) $path))
            ->all();

        $query = Filemanager::query()
            ->whereIn('status', ['queued', 'processing', 'failed']);

        $normalizedFolder = trim($folder, '/');

        if ($normalizedFolder !== '') {
            $query->where(function ($builder) use ($normalizedFolder) {
                $builder->where('file_url', 'like', 'public/'.$normalizedFolder.'/%')
                    ->orWhere('file_url', 'like', $normalizedFolder.'/%');
            });
        }

        foreach ($query->latest('created_at')->limit(100)->get() as $filemanager) {
            $path = $this->normalizeFilemanagerPath((string) $filemanager->getRawOriginal('file_url'));
            if ($path === '' || in_array($path, $existingPaths, true)) {
                continue;
            }

            if ($normalizedFolder !== '') {
                if (! str_starts_with($path, $normalizedFolder.'/')) {
                    continue;
                }

                $folderRelativePath = substr($path, strlen($normalizedFolder) + 1);
                if ($folderRelativePath === '' || str_contains($folderRelativePath, '/')) {
                    continue;
                }
            } elseif (str_contains($path, '/')) {
                continue;
            }

            $name = $filemanager->file_name ?: basename($path);
            $isVideo = (bool) preg_match('/\.(mp4|webm|avi|mov|wmv|flv|mkv|3gp|m4v|mpg|mpeg)$/i', $name);
            $isImage = (bool) preg_match('/\.(jpg|jpeg|png|gif|webp|svg|bmp|ico|tiff|tif)$/i', $name);
            $type = $isVideo ? 'video' : ($isImage ? 'image' : 'file');
            $pageType = $this->pageTypeFromPath($path);

            $items[] = [
                'name' => $name,
                'path' => $path,
                'is_dir' => false,
                'size' => 0,
                'modified' => optional($filemanager->created_at)->timestamp ?? time(),
                'uploaded_at' => optional($filemanager->created_at)->timestamp,
                'status' => $filemanager->status,
                'remote_job_id' => $filemanager->remote_job_id ?? null,
                'media_url' => ($isVideo || $isImage) ? setBaseUrlWithFileName($path, $type, $pageType) : '',
                'preview_url' => null,
                'is_video' => $isVideo,
                'is_image' => $isImage,
            ];
        }

        return $items;
    }

    private function normalizeFilemanagerPath(string $path): string
    {
        $path = ltrim($path, '/');

        return str_starts_with($path, 'public/') ? substr($path, strlen('public/')) : $path;
    }

    private function pageTypeFromPath(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        $imageIndex = array_search('image', $segments, true);
        $videoIndex = array_search('video', $segments, true);

        if ($imageIndex !== false && $imageIndex > 0) {
            return $segments[$imageIndex - 1];
        }

        if ($videoIndex !== false && $videoIndex > 0) {
            return $segments[$videoIndex - 1];
        }

        return $segments[0] ?? 'default';
    }

    private function previewUrl(?string $path, bool $isImage): ?string
    {
        $path = ltrim((string) $path, '/');

        if (!$isImage || $path === '' || !str_starts_with($path, 'ads/image/')) {
            return null;
        }

        $key = rtrim(strtr(base64_encode($path), '+/', '-_'), '=');

        return route('backend.media-library.preview', ['key' => $key]);
    }

    public function preview(Request $request)
    {
        $key = (string) $request->query('key');
        $path = $key !== ''
            ? (string) base64_decode(strtr($key, '-_', '+/'), true)
            : (string) $request->query('path');
        $path = ltrim($path, '/');
        abort_if($path === '' || str_contains($path, '..') || !str_starts_with($path, 'ads/image/'), 404);

        $activeDisk = env('ACTIVE_STORAGE', 'local');

        if ($activeDisk !== 'local' && Storage::disk($activeDisk)->exists($path)) {
            $stream = Storage::disk($activeDisk)->readStream($path);
            abort_if($stream === false, 404);

            return response()->stream(function () use ($stream) {
                fpassthru($stream);
                fclose($stream);
            }, 200, [
                'Content-Type' => Storage::disk($activeDisk)->mimeType($path) ?: 'image/jpeg',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        $localPath = storage_path('app/public/' . $path);
        abort_unless(is_file($localPath), 404);

        return response()->file($localPath, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function attachUploadTimestamps(array $items): array
    {
        $fileNames = collect($items)
            ->filter(fn ($item) => !($item['is_dir'] ?? false) && !empty($item['name']))
            ->pluck('name')
            ->unique()
            ->values();

        if ($fileNames->isEmpty()) {
            return $items;
        }

        $filemanagerByName = Filemanager::query()
            ->whereIn('file_name', $fileNames)
            ->latest('created_at')
            ->get(['file_name', 'created_at', 'status', 'remote_job_id'])
            ->unique('file_name')
            ->keyBy('file_name');

        foreach ($items as &$item) {
            if ($item['is_dir'] ?? false) {
                continue;
            }

            $filemanager = $filemanagerByName[$item['name']] ?? null;
            $item['uploaded_at'] = $filemanager ? optional($filemanager->created_at)->timestamp : ($item['modified'] ?? null);
            $item['status'] = $filemanager->status ?? ($item['status'] ?? 'ready');
            $item['remote_job_id'] = $filemanager->remote_job_id ?? ($item['remote_job_id'] ?? null);
        }

        unset($item);

        return $items;
    }

    /**
     * Get media URL using helper function
     */
    public function getMediaUrl(Request $request)
    {
        $fileName = $request->get('file');
        $type = $request->get('type', 'image');
        $pageType = $request->get('page_type', 'default');

        // Call the helper function
        $url = setBaseUrlWithFileName($fileName, $type, $pageType);

        return response()->json([
            'success' => true,
            'url' => $url
        ]);
    }

    /**
     * Poll upload processing status for a file by file_name.
     * Returns: { status: 'pending'|'processing'|'ready'|'failed' }
     */
    public function getFileStatus(Request $request)
    {
        $fileName = $request->get('file_name');
        if (!$fileName) {
            return response()->json(['status' => 'ready']);
        }

        $record = \Modules\Filemanager\Models\Filemanager::where('file_name', $fileName)
            ->latest()
            ->first();

        if (!$record) {
            return response()->json(['status' => 'ready']);
        }

        $rawPath = (string) $record->getRawOriginal('file_url');
        $path = $this->normalizeFilemanagerPath($rawPath);
        $name = (string) ($record->file_name ?: basename($path));
        $isVideo = (bool) preg_match('/\.(mp4|webm|avi|mov|wmv|flv|mkv|3gp|m4v|mpg|mpeg)$/i', $name);
        $isImage = (bool) preg_match('/\.(jpg|jpeg|png|gif|webp|svg|bmp|ico|tiff|tif)$/i', $name);
        $type = $isVideo ? 'video' : ($isImage ? 'image' : 'file');
        $pageType = $this->pageTypeFromPath($path);
        $status = $record->status ?? 'ready';
        $job = null;

        if (!empty($record->remote_job_id)) {
            $job = MediaCompressorJob::where('remote_job_id', $record->remote_job_id)->latest()->first();
        }

        return response()->json([
            'status' => $status,
            'file_name' => $record->file_name,
            'remote_job_id' => $record->remote_job_id ?? null,
            'error_message' => $job?->error_message,
            'media_url' => $status === 'ready' && ($isVideo || $isImage)
                ? setBaseUrlWithFileName($path, $type, $pageType)
                : null,
            'updated_at' => optional($record->updated_at)->timestamp,
        ]);
    }

}
