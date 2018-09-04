<?php
namespace KalmoyaElementalCloner;

defined('C5_EXECUTE') or die("Access Denied.");

use Exception;
use League\Url\Url;
use Concrete\Core\File\File;
use League\Flysystem\Filesystem;
use Concrete\Core\Page\Theme\Theme;
use League\Flysystem\Adapter\Local;
use League\Flysystem\AdapterInterface;
use Concrete\Core\Application\Application;
use Concrete\Theme\Elemental\PageTheme as Elemental;

class ElementalClonerTool
{
    protected $sourceDir;
    protected $destDir;
    protected $pkgDir;
    protected $themeHandle;
    protected $pkgHandle;
    protected $pkgAppVersion;
    protected $pkgVersion;
    protected $pkgDescription;
    protected $pkgName;
    protected $pkgIcon;
    protected $buildPackage = false;
    protected $token;
    protected $action;
    protected $fs;
    protected $app;
    protected $fh;
    protected $th;
    protected $vals;
    protected $valt;
    protected $themeNewName;
    protected $themeNewDesc;
    protected $themeDirCreated = false;

    public function __construct(Application $app, $settings = [])
    {
        extract($settings);

        $this->app = $app;
        $this->fs = $this->getFlysystemClass();
        $this->fh = $app->make('helper/file');
        $this->th = $app->make('helper/text');
        $this->vals = $app->make('helper/validation/strings');
        $this->valt = $app->make('token');

        $this->themeHandle = $handle;
        $this->sourceDir = DIR_FILES_THEMES_CORE . '/elemental';

        $this->token = $token;
        $this->action = $action;
        $name = empty($name) ? $this->th->unhandle($handle) : $name;
        $this->themeNewName = $name;
        $this->themeNewDesc = $description;
        $this->thumbSource = $thumbSource;
        $this->googleFonts = $googleFonts;
        $this->fID = $fID;
        $this->filename = $filename;
        $buildPackage = isset($buildPackage) ? $buildPackage : false;
        if ($buildPackage && $this->vals->notEmpty($pkgHandle)) {
            $this->pkgHandle = $pkgHandle;
            $this->pkgIcon = $pkgIcon;
            $this->pkgfID = $pkgfID;
            $this->pkgThumbSource = $pkgThumbSource;
            if ('package' === $action) {
                $this->pkgAppVersion = empty($pkgAppVersion) ? "8.0.0" : $pkgAppVersion;
                $this->pkgVersion = empty($pkgVersion) ? "0.9" : $pkgVersion;
                $this->pkgDescription = empty($pkgDescription) ? t("Install theme %s", $this->themeNewName) : $pkgDescription;
                $this->pkgName = empty($this->pkgName) ? $this->th->unhandle($pkgHandle) : $this->pkgName;
            }

            $this->buildPackage = $buildPackage;
            $this->pkgDir = DIRNAME_PACKAGES . '/' . $pkgHandle;
            $this->destDir = $this->pkgDir . '/' . DIRNAME_THEMES . '/' . $handle;
        } else {
            $this->destDir = DIRNAME_APPLICATION . '/' . DIRNAME_THEMES . '/' . $handle;
        }
    }

    protected function getFlysystemClass()
    {
        $local = new Local(DIR_BASE);
        $fs = new Filesystem($local);

        return $fs;
    }

    public function themeDirectoryExists($fragment = '')
    {
        $fragment = !empty($fragment) ? '/' . trim($fragment, '/') : $fragment;

        return $this->fs->has($this->destDir . $fragment);
    }

    public function packageDirectoryExists($fragment = '')
    {
        $fragment = !empty($fragment) ? '/' . trim($fragment, '/') : $fragment;

        return $this->fs->has($this->pkgDir . $fragment);
    }

    public function process($doCleanup = false)
    {
        if (empty($this->action)) {
            return false;
        }

        try {
            switch ($this->action) {
                case 'package':
                    $ret = $this->buildPackage();
                    break;
                case 'clone':
                    $ret = $this->cloneTheme();
                    break;
                case 'customize':
                    $ret = $this->customizeTheme();
                    break;
                case 'googleFonts':
                    $ret = $this->manageGoogleFonts();
                    break;
                case 'thumb':
                    $ret = $this->customizeThumb();
                    break;
                default:
                    $ret = false;
                    break;
            }
        } catch (Exception $e) {
            if (($this->packageDirCreated || $this->themeDirCreated) && $this->packageDirectoryExists()) {
                $this->fh->removeAll($this->pkgDir, true);
            } elseif ($this->themeDirCreated && $this->themeDirectoryExists()) {
                $this->fh->removeAll($this->destDir, true);
            }

            throw $e;
        }

        try {
            if ($ret && $doCleanup) {
                $this->cleanup();
            }
        } catch (Exception $e) {
            throw new Exception(
                t("There was a problem cleaning up the images you uploaded to the server but your theme was built succesfully so nothing to worry about")
            );
        }

        return $ret;
    }

    protected function buildPackage()
    {
        if (empty($this->token) || !$this->valt->validate('create_elemental_clone', $this->token)) {
            throw new Exception(
                t("This is embarassing! Could you reload the page and try again please?")
            );
        }

        if (!$this->buildPackage || !$this->vals->notEmpty($this->pkgHandle)) {
            return false;
        }

        if ($this->packageDirectoryExists()) {
            throw new Exception(
                t(
                    'A package with handle %s already exists in the packages directory. Please use a different handle.',
                    '&ldquo;' . $this->pkgHandle . '&rdquo;'
                )
            );
        }

        $dirCreated = $this->fs->createDir(
            $this->pkgDir . '/themes',
            ['visibility' => AdapterInterface::VISIBILITY_PUBLIC]
        );
        $this->packageDirCreated = true;
        if (!$dirCreated) {
            throw new Exception(
                t(
                    "Unable to create the package directory %s . Permissions might be set incorrectly at the server's level?",
                    '&ldquo;' . $this->pkgDir . '&rdquo;'
                )
            );
        }

        $pkgControllerIsSaved = false;

        $pkgController = $this->fs->read(
            DIRNAME_PACKAGES . '/' . ELEMENTAL_CLONER_PACKAGE_HANDLE . '/template/controller.php.tmpl'
        );
        $search = [
            "{{package_camel_handle}}",
            "{{package_handle}}",
            "{{package_app_version_required}}",
            "{{package_version}}",
            "{{package_description}}",
            "{{package_name}}",
            "{{theme_handle}}",
        ];
        $replace = [
            camelcase($this->pkgHandle),
            $this->pkgHandle,
            $this->pkgAppVersion,
            $this->pkgVersion,
            $this->pkgDescription,
            $this->pkgName,
            $this->themeHandle,
        ];
        $pkgController = str_replace($search, $replace, $pkgController);

        $pkgControllerIsSaved = $this->fs->write(
            $this->pkgDir . '/controller.php',
            $pkgController,
            ['visibility' => AdapterInterface::VISIBILITY_PUBLIC]
        );

        // Checking that the directory got created correctly
        // if (!$this->packageDirectoryExists()) {
        //     throw new Exception(
        //         t(
        //             "Unable to create the package's controller in %s . Permissions might be set incorrectly at the server's level?",
        //             '&ldquo;' . $this->pkgDir . '&rdquo;'
        //         )
        //     );
        // }

        return $pkgControllerIsSaved;
    }

    protected function cloneTheme()
    {
        if (empty($this->token) || !$this->valt->validate('create_elemental_clone', $this->token)) {
            throw new Exception(
                t("This is embarassing! Could you reload the page and try again please?")
            );
        }
        if (!$this->buildPackage || !$this->vals->notEmpty($this->pkgHandle)) {
            if ($this->themeDirectoryExists()) {
                throw new Exception(
                    t(
                        'A theme with handle %s already exists in the application/themes directory. Please use a different handle.',
                        '&ldquo;' . $this->destDir . '&rdquo;'
                    )
                );
            }
        }

        // Themes can be in application/themes so the check above suffices
        // They can be in packages and should technically be installed
        // They can be core and are uninstallabe so they are always installed
        // $availableThemes = Theme::getAvailableThemes(true);

        $installedThemesHandles = Theme::getInstalledHandles();

        if (in_array($this->themeHandle, $installedThemesHandles)) {
            throw new Exception(
                t(
                    'A theme with handle %s is already installed. Please use a different handle.',
                    '&ldquo;' . $this->themeHandle . '&rdquo;'
                )
            );
        }

        $this->fh->copyAll($this->sourceDir, $this->destDir);
        $this->themeDirCreated = true;
        // Checking that the directory got copied correctly
        if (!$this->themeDirectoryExists()) {
            throw new Exception(
                t(
                    "Unable to copy directory %s to %s. Permissions might be set incorrectly at the server's level?",
                    '&ldquo;' . $this->sourceDir . '&rdquo;',
                    '&ldquo;' . $this->destDir . '&rdquo;'
                )
            );
        }

        return true;
    }

    protected function customizeTheme()
    {
        if (empty($this->token) || !$this->valt->validate('create_elemental_clone', $this->token)) {
            throw new Exception(
                t("This is embarassing! Could you reload the page and try again please?")
            );
        }

        $pageThemeIsSaved = false;

        if (!$this->themeDirectoryExists()) {
            throw new Exception(
                t("Your new theme is nowhere to be found and cannot be customized. Please try again.")
            );
        } else {
            $elemental = new Elemental();

            $elementalName = '';
            $elementalDesc = '';

            if (method_exists($elemental, 'getThemeName')) {
                $elementalName = $elemental->getThemeName();
            }
            if (method_exists($elemental, 'getThemeDescription')) {
                $elementalDesc = $elemental->getThemeDescription();
            }

            $pageTheme = $this->fs->read($this->destDir . '/page_theme.php');

            if (!$pageTheme) {
                throw new Exception(
                    t("Your new theme's page_theme.php file is nowhere to be found and cannot be customized. Please try again.")
                );
            }
            $pageTheme = $this->lines($pageTheme);

            $namespaceIsReplaced = false;
            $nameIsReplaced = false;
            $descriptionIsReplaced = false;
            $nameFunction = false;
            $descFunction = false;

            if ($this->buildPackage && $this->vals->notEmpty($this->pkgHandle)) {
                $namespace = 'Concrete\Package\\' . camelcase($this->pkgHandle) . '\Theme\\' . camelcase($this->themeHandle);
            } else {
                $namespace = 'Application\Theme\\' . camelcase($this->themeHandle);
            }

            foreach ($pageTheme as $index => $line) {
                if (!$namespaceIsReplaced
                    && $this->contains('namespace', $line, false)
                    && $this->contains('Concrete\Theme\Elemental', $line, true)
                ) {
                    $namespaceIsReplaced = true;
                    $pageTheme[$index] = str_replace('Concrete\Theme\Elemental', $namespace, $line);
                    continue;
                }

                if (!empty($elementalName) && !$nameIsReplaced) {
                    if (!$nameFunction
                        && $this->containsAll(['public', 'function'], $line, false)
                        && $this->contains('getThemeName', $line, true)
                    ) {
                        $nameFunction = true;
                        continue;
                    } elseif ($nameFunction
                        && $this->containsAll(['return', $elementalName], $line, false)
                    ) {
                        $nameIsReplaced = true;
                        $pageTheme[$index] = $this->replaceReturnText($this->themeNewName, $line);
                        continue;
                    }
                }

                if (!empty($elementalDesc) && !empty($this->themeNewDesc) && !$descriptionIsReplaced) {
                    if (!$descFunction
                        && $this->containsAll(['public', 'function'], $line, false)
                        && $this->contains('getThemeDescription', $line, true)
                    ) {
                        $descFunction = true;
                        continue;
                    } elseif ($descFunction
                        && $this->containsAll(['return', $elementalDesc], $line, false)
                    ) {
                        $descriptionIsReplaced = true;
                        $pageTheme[$index] = $this->replaceReturnText($this->themeNewDesc, $line);
                        continue;
                    }
                }

                if ($descriptionIsReplaced && $nameIsReplaced && $namespaceIsReplaced) {
                    break;
                }
            }

            $pageTheme = implode('', $pageTheme);

            $pageThemeIsSaved = $this->fs->update(
                $this->destDir . '/page_theme.php',
                $pageTheme,
                ['visibility' => AdapterInterface::VISIBILITY_PUBLIC]
            );

            $pkgExtraFolder = '';
            if ($this->buildPackage && $this->vals->notEmpty($this->pkgHandle)) {
                $pkgExtraFolder = '../';
            }

            // Update main.less to load core LESS file properly
            // new "../../../../concrete/css/build/core/include/mixins.less";
            // old "../../../css/build/core/include/mixins.less";
            $mainLess = $this->fs->read($this->destDir . '/css/main.less');

            // Whether it's in application or in packages the new path to the core is the same
            $mainLess = str_replace(
                "../../../css/build/core/include/mixins.less",
                $pkgExtraFolder . "../../../../concrete/css/build/core/include/mixins.less",
                $mainLess
            );

            $mainLessIsSaved = $this->fs->update(
                $this->destDir . '/css/main.less',
                $mainLess,
                ['visibility' => AdapterInterface::VISIBILITY_PUBLIC]
            );
        }

        return $pageThemeIsSaved && $mainLessIsSaved;
    }

    protected function customizeThumb()
    {
        $data = [];
        $data['theme'] = [
            'source' => $this->thumbSource,
            'file' => $this->filename,
            'fID' => $this->fID,
            'width' => '360',
            'height' => '270',
            'destination' => $this->destDir . '/thumbnail.png',
        ];

        if ($this->buildPackage && $this->vals->notEmpty($this->pkgHandle)) {
            $data['package'] = [
                'source' => $this->pkgThumbSource,
                'file' => $this->pkgIcon,
                'fID' => $this->pkgfID,
                'width' => '97',
                'height' => '97',
                'destination' => $this->pkgDir . '/icon.png',
            ];
        }

        $im = $this->app->make('helper/image');
        $ret = false;
        $func = false;
        $delete = [];
        foreach ($data as $key => $value) {
            if ('upload' === $value['source'] && $this->vals->notEmpty($value['file'])) {
                $image = $this->fh->getTemporaryDirectory() . '/elementalcloner/' . $value['file'];
                $func = 'rename';
            } elseif ('manager' === $value['source'] && (int) $value['fID'] > 0) {
                $image = File::getByID($value['fID']);
                $func = 'copy';
            }

            if (!$func) {
                continue;
            }

            $thumb = $im->getThumbnail($image, $value['width'], $value['height'], true);
            if ('theme' === $key) {
                $this->fs->delete($value['destination']);
            }

            $thumbSrc = ltrim((string) Url::createFromUrl($thumb->src)->getPath(), '/');
            $relPath = trim($this->app->make('app_relative_path'), '/');
            $thumbSrc = str_replace($relPath, '', $thumbSrc);
            $ret = $this->fs->$func($thumbSrc, $value['destination']);

            if (!$ret) {
                $subject = 'theme' === $key ? t("your new theme's thumbnail") : t("your new package's icon");
                throw new Exception(
                    t(
                        "There was a problem setting up %s. Let's give it another try shall we?",
                        $subject
                    )
                );
            }
        }

        return $ret;
    }

    protected function manageGoogleFonts()
    {
        $cssThemes = ['blue-sky', 'default', 'night-road', 'royal'];
        $pkgPath = DIR_PACKAGES . '/' . ELEMENTAL_CLONER_PACKAGE_HANDLE;

        $fontsCssFilesPathFragment = '/css/build/fonts/';
        $fontsCssFilesPath = $pkgPath . $fontsCssFilesPathFragment;

        $fontsFilesPathFragment = '/fonts';
        $fontsFilesPath = $pkgPath . $fontsFilesPathFragment;

        if ('google' === $this->googleFonts) {
            return true;
        } elseif ('local' === $this->googleFonts) {
            // copy the font files
            $this->fh->copyAll($fontsFilesPath, $this->destDir . $fontsFilesPathFragment);
            if (!$this->themeDirectoryExists($fontsFilesPathFragment)) {
                throw new Exception(
                    t(
                        "Unable to copy the fonts over to your new theme. Permissions might be set incorrectly at the server's level?"
                    )
                );
            }

            // delete the existing font LESS files
            $this->fh->removeAll($this->destDir . $fontsCssFilesPathFragment, true);

            // copy the new font LESS files
            $this->fh->copyAll($fontsCssFilesPath, $this->destDir . $fontsCssFilesPathFragment);
            if (!$this->themeDirectoryExists($fontsCssFilesPathFragment)) {
                throw new Exception(
                    t(
                        "Unable to copy the fonts LESS files over to your new theme. Permissions might be set incorrectly at the server's level?"
                    )
                );
            }
        }

        return true;
    }

    protected function cleanup()
    {
        $data = [];

        if ($this->vals->notEmpty($this->filename)) {
            $data[] = $this->filename;
        }

        if ($this->vals->notEmpty($this->pkgIcon)) {
            $data[] = $this->pkgIcon;
        }

        if (count($data) > 0) {
            foreach ($data as $file) {
                $image = REL_DIR_FILES_UPLOADED_STANDARD . '/tmp/elementalcloner/' . $file;
                $relPath = trim($this->app->make('app_relative_path'), '/');
                $image = str_replace($relPath, '', $image);
                if ($this->fs->has($image)) {
                    $this->fs->delete($image);
                }
            }
        }
    }

    protected function replaceReturnText($newText, $haystack)
    {
        $pattern = "/return(.*)[^;]/";

        return preg_replace($pattern, 'return t("' . $newText . '")', $haystack);
    }

    /**
     * Splits on newlines and carriage returns, returning an array of Stringy
     * objects corresponding to the lines in the string.
     *
     * @return static[] An array of Stringy objects
     */
    protected function lines($text)
    {
        $pattern = '[\r\n]{1,2}';

        $array = preg_split("/($pattern)/", $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        return $array;
    }

    /**
     * Returns true if the string contains all $needles, false otherwise. By
     * default the comparison is case-sensitive, but can be made insensitive by
     * setting $caseSensitive to false.
     *
     * @param  string[] $needles       Substrings to look for
     * @param  bool     $caseSensitive Whether or not to enforce case-sensitivity
     *
     * @return bool     Whether or not $str contains $needle
     */
    public function containsAll($needles, $haystack, $caseSensitive = true)
    {
        if (empty($needles)) {
            return false;
        }
        foreach ($needles as $needle) {
            if (!$this->contains($needle, $haystack, $caseSensitive)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns true if the string contains $needle, false otherwise. By default
     * the comparison is case-sensitive, but can be made insensitive by setting
     * $caseSensitive to false.
     *
     * @param  string $needle        Substring to look for
     * @param  bool   $caseSensitive Whether or not to enforce case-sensitivity
     *
     * @return bool   Whether or not $str contains $needle
     */
    public function contains($needle, $haystack, $caseSensitive = true)
    {
        if ($caseSensitive) {
            return false !== \mb_strpos($haystack, $needle, 0);
        }

        return false !== \mb_stripos($haystack, $needle, 0);
    }
}
