<?php defined('C5_EXECUTE') or die('Access Denied'); ?>
<div class="row" style="text-align: center;">
    <p><strong><?php echo t("Congratulations, your Elemental Cloner is now installed"); ?></strong></p>
    <p><?php echo t("To start playing with it, click on the button below."); ?></p>
    <p><?php echo t("Alternatively you can type %sElemental cloner%s or simply %scloner%s in the intelligent search box of your top toolbar and you'll find the tool's page easily.", '&ldquo;', '&rdquo;', '&ldquo;', '&rdquo;'); ?></p>
    <p><?php echo t("Have fun!"); ?></p>
    <p style="text-transform: capitalize;"><a href="<?php echo URL::to('/dashboard/pages/elemental_cloner'); ?>" class="btn btn-info"><?php echo t("Start using your cloning tool"); ?>&nbsp;<i class="fa fa-arrow-circle-right"></i></a></p>
</div>
