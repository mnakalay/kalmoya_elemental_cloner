<?php
namespace ElClKalmoya;

defined('C5_EXECUTE') or die('Access Denied.');

use StdClass;
use Concrete\Core\Package\Package;
use Concrete\Core\Controller\Controller;
use Concrete\Core\Support\Facade\Config;

class KalmoyaInfo extends Controller
{
    protected static $pkgHandle;
    protected static $pkg;
    protected static $c5URL = 'https://www.concrete5.org';
    protected static $c5UserID = '75201';
    protected static $devName = 'Nour Akalay (a.k.a. mnakalay)';
    protected static $kalmoyaWebsite = 'https://kalmoya.com';

//    [NOUR] Modify per package
    protected static $pkgMarketHandle = 'elemental-cloner';

    public function __construct($pkgHandle)
    {
        self::$pkgHandle = $pkgHandle;
        self::$pkg = Package::getByHandle($pkgHandle);
        // self::$config = $package->getConfig();
    }

    public static function getPackageVersion()
    {
        return 'v' . self::$pkg->getPackageVersion();
    }

    public static function getPackageName()
    {
        return self::$pkg->getPackageName();
    }

    public static function getMarketSlug()
    {
        return self::$pkgMarketHandle;
    }

    public static function getConcreteAddress()
    {
        $c5URL = Config::get('concrete.urls.concrete5');

        return $c5URL ? $c5URL : self::$c5URL;
    }

    public static function getC5Profile()
    {
        return self::getConcreteAddress() . '/profile/-/view/' . self::$c5UserID;
    }

    public static function getAddonMarketPage()
    {
        return self::getConcreteAddress() . '/marketplace/addons/' . self::getMarketSlug();
    }

    public static function getMarketLicense()
    {
        return self::getConcreteAddress() . '/help/legal/commercial_add-on_license/';
    }

    public static function getC5Avatar()
    {
        return self::getConcreteAddress() . '/files/avatars/' . self::$c5UserID . '.jpg';
    }

    public static function getC5referralUrl()
    {
        return self::getConcreteAddress() . '/r/-/' . self::$c5UserID;
    }

    public static function getDevName()
    {
        return self::$devName;
    }

    public static function getKalmoyaWebsite()
    {
        return self::$kalmoyaWebsite;
    }

    public function getJSONKalmoya()
    {
        $kalmoya = new stdClass();

        $kalmoya->pkgHandle = self::$pkgHandle;
        $kalmoya->pkgName = self::getPackageName();
        $kalmoya->pkgVersion = self::getPackageVersion();
        $kalmoya->pkgMarketSlug = self::getMarketSlug();
        $kalmoya->c5Profile = self::getC5Profile();
        $kalmoya->addonMarketPage = self::getAddonMarketPage();
        $kalmoya->marketLicense = self::getMarketLicense();
        $kalmoya->c5Avatar = self::getC5Avatar();
        $kalmoya->c5referralUrl = self::getC5referralUrl();
        $kalmoya->devName = self::getDevName();
        $kalmoya->kalmoyaWebsite = self::getKalmoyaWebsite();

        return $kalmoya;
    }
}
