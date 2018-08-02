<?php
namespace KalmoyaElementalCloner;

defined('C5_EXECUTE') or die("Access Denied.");

use stdClass;
use Exception;
use Illuminate\Filesystem\Filesystem;
use Concrete\Core\Http\ResponseFactory;
use Concrete\Core\Controller\Controller;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ThumbnailUploader extends Controller
{
    /**
     * PHP error constants - these match those founds in $_FILES[$field]['error] if it exists.
     */
    const E_PHP_FILE_ERROR_DEFAULT = 0;
    const E_PHP_FILE_EXCEEDS_UPLOAD_MAX_FILESIZE = 1;
    const E_PHP_FILE_EXCEEDS_HTML_MAX_FILE_SIZE = 2;
    const E_PHP_FILE_PARTIAL_UPLOAD = 3;
    const E_PHP_NO_FILE = 4;

    /**
     * concrete5 internal error constants.
     */
    const E_FILE_INVALID_EXTENSION = 10;
    const E_FILE_INVALID = 11; // pointer is invalid file, is a directory, etc...
    const E_FILE_UNABLE_TO_STORE = 12;
    const E_FILE_INVALID_STORAGE_LOCATION = 13;
    const E_FILE_EXCEEDS_POST_MAX_FILE_SIZE = 20;

    /**
     * The Filesystem instance to use.
     *
     * @var Filesystem
     */
    protected $filesystem = null;

    protected $tmpDir = DIR_FILES_UPLOADED_STANDARD . '/tmp/elementalcloner';

    /**
     * Get the Filesystem instance to use.
     *
     * @return Filesystem
     */
    public function getFilesystem()
    {
        if (null === $this->filesystem) {
            $this->filesystem = new Filesystem();
        }

        return $this->filesystem;
    }

    /**
     * Imports a local file into the system. The file must be added to this path
     * somehow. That's what happens in tools/files/importers/.
     * If a $fr (FileRecord) object is passed, we assign the newly imported FileVersion
     * object to that File. If not, we make a new filerecord.
     *
     * @param string $pointer path to file
     * @param string|bool $filename
     * @param File|FileFolder|bool $fr
     *
     * @return number Error Code | \Concrete\Core\EntiFile\Version
     */
    protected function import($pointer, $filename = false)
    {
        $dataInfo = false;
        if (empty($filename)) {
            // determine filename from $pointer
            $filename = basename($pointer);
        }

        $fh = $this->app->make('helper/validation/file');
        $fi = $this->app->make('helper/file');

        $sanitizedFilename = $fi->sanitize($filename);
        // test if file is valid, else return FileImporter::E_FILE_INVALID
        if (!$fh->file($pointer)) {
            throw new Exception($this->getErrorMessage(self::E_FILE_INVALID), 400);
        }

        $extension = strtolower($fi->getExtension($sanitizedFilename));
        $allowedExt = ['png', 'jpg', 'jpeg'];
        if (!in_array($extension, $allowedExt)) {
            throw new Exception($this->getErrorMessage(self::E_FILE_INVALID_EXTENSION), 406);
        }

        try {
            $uploadedFile = new UploadedFile($pointer, $sanitizedFilename);
            $uploadedFile->move($this->tmpDir, $sanitizedFilename);
            $dataInfo = [];
        } catch (Exception $e) {
            $fs = $this->getFilesystem();
            $fs->delete($this->tmpDir . '/' . $sanitizedFilename);
            throw $e;
        }
        $dataInfo['sanitizedFilename'] = $sanitizedFilename;

        return $dataInfo;
    }

    public function uploadThumb()
    {
        /** @var ResponseFactory $responseFactory */
        $responseFactory = $this->app->make(ResponseFactory::class);
        $file = [];
        try {
            if ($post_max_size = $this->app->make('helper/number')->getBytes(ini_get('post_max_size'))) {
                if ($post_max_size < $this->request->server->get('CONTENT_LENGTH')) {
                    throw new Exception($this->getErrorMessage(self::E_FILE_EXCEEDS_POST_MAX_FILE_SIZE), 400);
                }
            }

            if (!$this->app->make('helper/validation/token')->validate('upload_theme_thumb')) {
                throw new Exception($this->app->make('helper/validation/token')->getErrorMessage(), 401);
            }

            $filesArchive = $this->request->files->get('themeThumb');
            if (isset($filesArchive)) {
                $files[] = $this->handleUpload('themeThumb');
            }
        } catch (Exception $e) {
            if ($code = $e->getCode()) {
                return $responseFactory->error($e->getMessage(), $code);
            }
            // This error doesn't have a code, it's likely not what we're wanting.
            throw $e;
            exit;
        }
        $response = ['file' => $files];

        return $responseFactory->json($response);
    }

    protected function handleUpload($property)
    {
        $dataInfo = ['name' => false, 'url' => false];
        $name = $_FILES[$property]['name'];
        $tmp_name = $_FILES[$property]['tmp_name'];

        try {
            if ($_FILES[$property]['error']) {
                throw new Exception($this->getErrorMessage($_FILES[$property]['error']));
            }
            $dataInfo = $this->import($tmp_name, $name);
        } catch (Exception $e) {
            throw $e;
        }

        $file = new stdClass();
        $file->name = $dataInfo['sanitizedFilename']; //$name;
        $file->url = $tmp_name;

        return $file;
    }

    /**
     * Returns a text string explaining the error that was passed.
     *
     * @param int $code
     *
     * @return string
     */
    public function getErrorMessage($code)
    {
        $msg = '';
        switch ($code) {
            case self::E_PHP_NO_FILE:
            case self::E_FILE_INVALID:
                $msg = t('The file is invalid or might not have been uploaded correctly.');
                break;
            case self::E_FILE_INVALID_EXTENSION:
                $msg = t('Invalid file extension. The thumbnail must be a PNG or JPG file.');
                break;
            case self::E_PHP_FILE_PARTIAL_UPLOAD:
                $msg = t('The file was only partially uploaded.');
                break;
            case self::E_FILE_INVALID_STORAGE_LOCATION:
                $msg = t('No default file storage location could be found to store this file.');
                break;
            case self::E_FILE_EXCEEDS_POST_MAX_FILE_SIZE:
                $msg = t('Uploaded file is too large. The current value of post_max_filesize is %s', ini_get('post_max_size'));
                break;
            case self::E_PHP_FILE_EXCEEDS_HTML_MAX_FILE_SIZE:
            case self::E_PHP_FILE_EXCEEDS_UPLOAD_MAX_FILESIZE:
                $msg = t('Uploaded file is too large. The current value of upload_max_filesize is %s', ini_get('upload_max_filesize'));
                break;
            case self::E_FILE_UNABLE_TO_STORE:
                $msg = t('Unable to copy file to storage location. Please check the settings for the storage location.');
                break;
            case self::E_PHP_FILE_ERROR_DEFAULT:
            default:
                $msg = t("An unknown error occurred while uploading the file. Please check that file uploads are enabled, and that your file does not exceed the size of the post_max_size or upload_max_filesize variables.\n\nFile Uploads: %s\nMax Upload File Size: %s\nPost Max Size: %s", ini_get('file_uploads'), ini_get('upload_max_filesize'), ini_get('post_max_size'));
                break;
        }

        return $msg;
    }
}
