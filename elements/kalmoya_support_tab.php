<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>

<div id="ccm-tab-content-support" class="ccm-tab-content bp-tab-content">
    <div style="clear:both;">
        <h2><?php echo t('License'); ?></h2>
        <p>
            <?php
            echo t("%s is licensed under the %sMIT License%s. In simple non-legalese terms you're allowed to use it to your heart's content. Just don't pass it as your own because that would be uncool. Oh and don't blame me for anything related or otherwise to %s because that would be foolish and pointless (without mentionning uncalled for). Third party software components retain the original third party license.", '<a href="' . $kalmoya->addonMarketPage . '" target="_blank">' . $kalmoya->pkgName . '</a>', '<a href="https://opensource.org/licenses/MIT" target="_blank">', '</a>', '<a href="' . $kalmoya->addonMarketPage . '" target="_blank">' . $kalmoya->pkgName . '</a>');
            ?>
        </p>
    </div>

    <div style="clear:both;">
        <h2><?php echo  t('Developer'); ?></h2>
        <a href="<?php  echo $kalmoya->c5Profile; ?>" target="_blank" style="float:left;margin-right:15px;"><img src ="<?php echo  $kalmoya->c5Avatar; ?>"></a>
        <p>
            <?php
            echo t("%s%s%s was crafted with love and is 100%s maintained and supported by %s%s%s day and night, no matter the weather.", '<a href="' . $kalmoya->addonMarketPage . '" target="_blank">', $kalmoya->pkgName, '</a>', '%', '<a href="' . $kalmoya->c5Profile . '" target="_blank">', $kalmoya->devName, '</a>');
            ?>
        </p>
        <p>
            <?php
            echo t("Amazing coincidence: I also happen to offer other Concrete5 add-ons as well as custom Concrete5 development services at %s%s%s.", '<a href="' . $kalmoya->kalmoyaWebsite . '" target="_blank">', $kalmoya->kalmoyaWebsite, '</a>');
            ?>
        </p>
    </div>

    <div style="clear:both;">
        <h2><?php echo  t('Documentation and support'); ?></h2>
        <p>
            <?php
            echo t("You can find documentation for %s%s%s on %sconcrete5.org%s.", '<a href="' . $kalmoya->addonMarketPage . '" target="_blank">', $kalmoya->pkgName, '</a>', '<a href="' . $kalmoya->c5referralUrl . '" target="_blank">', '</a>');
            ?>
        </p>
        <p>
            <?php
            echo t("For support, please click the %sGet Help%s link on the  %smarketplace page%s. I usually provide same day support and no request goes unanswered.", '<a href="' . $kalmoya->addonMarketPage . '/support" target="_blank">', '</a>', '<a href="' . $kalmoya->addonMarketPage . '" target="_blank">', '</a>');
            ?>
        </p>
    </div>
</div> <!-- / #ccm-tab-content-support -->