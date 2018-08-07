<?php
namespace Concrete\Package\KalmoyaElementalCloner;

defined('C5_EXECUTE') or die('Access denied.');

use Concrete\Core\Page\Page;
use Concrete\Core\Package\Package;
use Concrete\Core\Support\Facade\Route;
use Concrete\Core\Block\BlockType\BlockType;
use Concrete\Core\Page\Single as SinglePage;

class Controller extends Package
{
    protected $pkgHandle = 'kalmoya_elemental_cloner';
    protected $appVersionRequired = '8.0.0';
    protected $pkgVersion = '1.2.2';

    protected $pkgAutoloaderRegistries = [
        'vendor/kalmoya' => '\ElClKalmoya',
        'src/ElementalCloner' => '\KalmoyaElementalCloner',
        ];

    public function getPackageName()
    {
        return t('Elemental Cloner');
    }

    public function getPackageDescription()
    {
        return t("Easily clone Elemental as a new theme anytime you like %s Developed by Nour Akalay @ %sKALMOYA - bespoke Concrete5 development%s", '<br /><span style="font-size:11px;">', '<a target="_blank" href="https://www.kalmoya.com">', '</a></span>');
    }

    public function on_start()
    {
        define('ELEMENTAL_CLONER_PACKAGE_HANDLE', $this->pkgHandle);
        Route::register(
            '/elementalcloner/uploadThumb/{input}',
            '\KalmoyaElementalCloner\ThumbnailUploader::uploadThumb'
        );
    }

    public function install()
    {
        $pkg = parent::install();

        $this->installPages($pkg);
    }

    public function upgrade()
    {
        $pkg = Package::getByHandle($this->pkgHandle);
        parent::upgrade();

        $this->installPages($pkg);
    }

    protected function installPages($pkg)
    {
        $singlePages = [
            [
                'path' => '/dashboard/pages/elemental_cloner',
                'cName' => t('Elemental Cloner'),
                ],
            ];

        foreach ($singlePages as $singlePage) {
            $singlePageObject = Page::getByPath($singlePage['path']);
            // Check if it exists, if not, add it
            if ($singlePageObject->isError() || (!is_object($singlePageObject))) {
                $sp = SinglePage::add($singlePage['path'], $pkg);
                unset($singlePage['path']);
                if (!empty($singlePage)) {
                    // And make sure we update the page with the remaining values
                    $sp->update($singlePage);
                }
            }
        }
    }
}
