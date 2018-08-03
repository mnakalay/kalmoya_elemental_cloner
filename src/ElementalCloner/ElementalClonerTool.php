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
    protected $handle;
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

        $this->handle = $handle;
        $this->destDir = DIRNAME_APPLICATION . '/' . DIRNAME_THEMES . '/' . $handle;
        $this->sourceDir = DIR_FILES_THEMES_CORE . '/elemental';
        $this->fs = $this->getFlysystemClass();
        $this->fh = $app->make('helper/file');
        $this->th = $app->make('helper/text');
        $this->vals = $app->make('helper/validation/strings');
        $this->valt = $app->make('token');

        $this->token = $token;
        $this->action = $action;
        $name = empty($name) ? $this->th->unhandle($handle) : $name;
        $this->themeNewName = $name;
        $this->themeNewDesc = $description;
        $this->thumbSource = $thumbSource;
        $this->googleFonts = $googleFonts;
        $this->fID = $fID;
        $this->filename = $filename;
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

    public function process($doCleanup = false)
    {
        if (empty($this->action)) {
            return false;
        }

        try {
            switch ($this->action) {
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
            if ('clone' !== $this->action || ('clone' === $this->action && $this->themeDirCreated)) {
                $this->fh->removeAll($this->destDir, true);
            }
            throw $e;
        }

        if ($ret && $doCleanup) {
            $this->cleanup();
        }

        return $ret;
    }

    protected function cloneTheme()
    {
        if (empty($this->token) || !$this->valt->validate('create_elemental_clone', $this->token)) {
            throw new Exception(
                t("This is embarassing! Could you reload the page and try again please?")
            );
        }

        if ($this->themeDirectoryExists()) {
            throw new Exception(
                t(
                    'A theme with handle %s already exists in the application/themes directory. Please use a different handle.',
                    '&ldquo;' . $this->destDir . '&rdquo;'
                )
            );
        }

        // Themes can be in application/themes so the check above suffices
        // They can be in packages and should technically be installed
        // They can be core and are uninstallabe so they are always installed
        // $availableThemes = Theme::getAvailableThemes(true);

        $installedThemesHandles = Theme::getInstalledHandles();

        if (in_array($this->handle, $installedThemesHandles)) {
            throw new Exception(
                t(
                    'A theme with handle %s is already installed. Please use a different handle.',
                    '&ldquo;' . $this->handle . '&rdquo;'
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

            foreach ($pageTheme as $index => $line) {
                if (!$namespaceIsReplaced
                    && $this->contains('namespace', $line, false)
                    && $this->contains('Concrete\Theme\Elemental', $line, true)
                ) {
                    $namespaceIsReplaced = true;
                    $pageTheme[$index] = str_replace('Concrete\Theme\Elemental', 'Application\Theme\\' . camelcase($this->handle), $line);
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

            // Update main.less to load core LESS file properly
            // new "../../../../concrete/css/build/core/include/mixins.less";
            // old "../../../css/build/core/include/mixins.less";
            $mainLess = $this->fs->read($this->destDir . '/css/main.less');
            $mainLess = str_replace(
                "../../../css/build/core/include/mixins.less",
                "../../../../concrete/css/build/core/include/mixins.less",
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
        $im = $this->app->make('helper/image');
        $ret = false;

        if ('upload' === $this->thumbSource && $this->vals->notEmpty($this->filename)) {
            $image = DIR_FILES_UPLOADED_STANDARD . '/tmp/elementalcloner/' . $this->filename;
            $func = 'rename';
        } elseif ('manager' === $this->thumbSource && (int) $this->fID > 0) {
            $image = File::getByID($this->fID);
            $func = 'copy';
        }

        $thumb = $im->getThumbnail($image, '360', '270', true);
        $this->fs->delete($this->destDir . '/thumbnail.png');
        $thumbSrc = ltrim((string) Url::createFromUrl($thumb->src)->getPath(), '/');
        $relPath = trim($this->app->make('app_relative_path'), '/');
        $thumbSrc = str_replace($relPath, '', $thumbSrc);

        $ret = $this->fs->$func($thumbSrc, $this->destDir . '/thumbnail.png');

        if (!$ret) {
            throw new Exception(
                t("There was a problem setting up your new theme's thumbnail. Let's give it another try shall we?")
            );
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
        if ($this->vals->notEmpty($this->filename)) {
            $image = REL_DIR_FILES_UPLOADED_STANDARD . '/tmp/elementalcloner/' . $this->filename;
            $relPath = trim($this->app->make('app_relative_path'), '/');
            $image = str_replace($relPath, '', $image);
            $this->fs->delete($image);
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
