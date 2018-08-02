<?php  defined('C5_EXECUTE') or die("Access Denied.");
// handle (required, unique)
// name (possibly unique, defaults to uncamelcase handle)
// description
// thumbnail
// getThemeDisplayName()
$app = \Concrete\Core\Support\Facade\Application::getFacadeApplication();
$al = $app->make('helper/concrete/asset_library');
$valt = $app->make('helper/validation/token');

?>
<style>
.box-wrapper {
    position: relative;
    overflow: hidden;
}
.thumb-manager {
    display: none;
}
.fileinput-box {
    position: relative;
    overflow: hidden;
    display: block;
    -webkit-transition: .1s linear all;
    -o-transition: .1s linear all;
    transition: .1s linear all;
    min-height: 340px;
    border: 2px dashed #09f;
    cursor: pointer;
    -webkit-border-radius: 3px;
    -moz-border-radius: 3px;
    border-radius: 3px;
    -ms-border-radius: 3px;
    background: rgba(0,0,0,0.03);
    padding: 23px;
}
.fileinput-box input {
  position: absolute;
  top: 0;
  right: 0;
  margin: 0;
  opacity: 0;
  -ms-filter: 'alpha(opacity=0)';
  font-size: 200px !important;
  direction: ltr;
  cursor: pointer;
}
.fileinput-box .cloner-process-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
}
.ccm-ui .progress.cloner-progress {
    height: 10px;
    margin-bottom: 0;
    background-color: transparent;
    -webkit-box-shadow: none;
     box-shadow: none;
}
#upload-message {
    opacity: 0;
    margin-bottom: 0;
    padding: 25px;
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    transform: translateY(100%);
    text-align: center;
    -webkit-transition: all .3s ease-in-out;
    -o-transition: all .3s ease-in-out;
    transition: all .3s ease-in-out;
    display: block;
    width: 100%;
}
#upload-message.showing {
    opacity: 1;
    transform: translateY(0);
}
.drop-box-msg {
    opacity: 1;
    filter: alpha(opacity=100);
    -webkit-transition: opacity .3s ease-in-out;
    -o-transition: opacity .3s ease-in-out;
    transition: opacity .3s ease-in-out;
    position: absolute;
    width: 100%;
    padding: 0 25px;
    /* max-width: 428px; */
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 36px;
    text-align: center;
    color: #999;
}
/* Fixes for IE < 8 */
@media screen\9 {
  .fileinput-box input {
    filter: alpha(opacity=0);
    font-size: 100%;
    height: 100%;
  }
}
</style>
<?php
\View::element(
    'kalmoya_dashboard_header',
    ['kalmoya' => $kalmoya],
    $kalmoya->pkgHandle
);

if (isset($errors)) {
    ?>
    <div class="alert alert-danger">
        <a data-dismiss="alert" href="#" class="close">&times;</a>
        <?php  echo $errors; ?>
    </div>
    <?php
} ?>
<?php
    echo $app->make('helper/concrete/ui')->tabs(
        [
            ['settings', t('Settings'), true],
            ['support', t('Support')],
        ]
    );
?>

<div id="ccm-tab-content-settings" class="ccm-tab-content">
    <div class="ccm-dashboard-content">
        <form id="elemental-cloner" method="post" action="<?php echo $view->action('run'); ?>">
            <?php echo $valt->output('create_elemental_clone'); ?>
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <?php echo $form->label('themeHandle', t("Theme's handle")); ?>
                        <?php echo $form->text('themeHandle', null); ?>
                        <small class="center-block text-danger">
                            <?php echo t("%s A valid and unique handle is required", '<i class="fa fa-star"></i>'); ?>
                        </small>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <?php echo $form->label('themeName', t("Theme's name")); ?>
                        <?php echo $form->text('themeName', null); ?>
                        <div class="help-block"><?php echo t("If left empty will be inferred from the handle"); ?></div>
                    </div>
                </div>
                <div class="col-sm-12">
                    <div class="form-group">
                        <?php echo $form->label('themeDescription', t("Theme's description")); ?>
                        <?php echo $form->text('themeDescription', null); ?>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="form-group">
                        <?php echo $form->label('googleFonts', t("Load the theme's font files")); ?>
                        <?php echo $form->select('googleFonts', ['google' => t("From's Google CDN (default)"), 'local' => t("From your server (good for GDPR)")], null); ?>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="form-group">
                        <?php echo $form->label('thumbSource', t("Get the theme's thumbnail")); ?>
                        <?php echo $form->select('thumbSource', ['upload' => t("From my computer"), 'manager' => t("From the file manager")], null); ?>
                        <div class="help-block"><?php echo t("The thumbnail will be resized to %s", '360 x 270'); ?></div>
                    </div>
                </div>
            </div>
            <div class="row form-group box-wrapper thumb-uploader">
                <div class="col-sm-12">
                    <div class="fileinput-box">
                        <span class="drop-box-msg"><?php echo t("Drop a PNG or JPG image file here or click to upload"); ?></span>
                        <?php echo $form->label('themeThumb', t("Select your theme's thumbnail from your computer"), ['class' => 'sr-only']); ?>
                        <input type="file" id="themeThumb" name="themeThumb">
                        <input type="hidden" id="uploaded-filename" name="filename" value="<?php echo $filename; ?>">
                        <div id="progress-wrapper" class="cloner-process-progress">
                            <div id="progress" class="progress cloner-progress progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="progress-bar progress-bar-warning" style="width:0%;"></div></div>
                        </div>
                        <div id="upload-message" class="files"></div>
                    </div>
                </div>
            </div>
            <div class="row thumb-manager">
                <div class="col-sm-12">
                    <div class="form-group">
                        <?php echo $form->label('fID', t("Select your theme's thumbnail from the file manager"), ['class' => 'sr-only']); ?>
                        <?php echo $al->image('manager-filename', 'fID', t('Choose Image'), null); ?>
                    </div>
                </div>
            </div>
            <div class="ccm-dashboard-form-actions-wrapper">
                <div class="ccm-dashboard-form-actions">
                    <button id="clone-theme" class="clone-theme pull-right btn btn-success" type="submit" ><i class="fa fa-wrench" aria-hidden="true"></i>&nbsp;<?php echo t('Build your theme'); ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
\View::element('kalmoya_support_tab', ['kalmoya' => $kalmoya], $kalmoya->pkgHandle);
?>

<script>
    $(function () {
        'use strict';
        // managing thumbnail import
        // Change this to the location of your server-side upload handler:
        $('#themeThumb').fileupload({
            url: '<?php echo \View::url("/elementalcloner/uploadThumb"); ?>',
            dataType: 'json',
            error: function(r) {
                $('#uploaded-filename').val(null);
                $('#upload-message').removeClass('alert-success').addClass('alert alert-danger showing');
                $('#progress .progress-bar').css(
                    'width',
                    '0%'
                );
                var message = r.responseText;

                try {
                    message = jQuery.parseJSON(message).errors;
                    $('#upload-message').empty();
                    _(message).each(function(error) {
                        $('<p/>').html(error).appendTo('#upload-message');
                    });
                } catch (e) {
                    message = message.split('<br />');
                    $('#upload-message').html(message[message.length - 1]);
                }
            },
            done: function (e, data) {
                $('#upload-message').removeClass('alert-danger').addClass('alert alert-success showing');
                if (data.result.file && data.result.file.length) {
                    $('#upload-message').empty();
                    $.each(data.result.file, function (index, file) {
                        $('#uploaded-filename').val(file.name);
                        $('<p/>').html(file.name + " <?php echo t('uploaded successfully'); ?>").appendTo('#upload-message');
                    });
                }

                $('#progress .progress-bar').css(
                    'width',
                    '100%'
                );

            },
            progressall: function (e, data) {
                var progress = parseInt(data.loaded * 0.85 / data.total * 100, 10);
                $('#progress .progress-bar').css(
                    'width',
                    progress + '%'
                );
            },
            always: function (e, data) {
                $('.ccm-dashboard-form-actions #clone-theme').removeProp('disabled');
            },
            change: function() {
                $('#progress .progress-bar').css(
                    'width',
                    '0%'
                );
                $('#upload-message').removeClass('showing').empty();

            },
            start: function() {
                $('#progress .progress-bar').css(
                    'width',
                    '0%'
                );
                $('#upload-message').removeClass('showing').empty();
                $('.ccm-dashboard-form-actions #clone-theme').prop('disabled', true);
            }
        }).prop('disabled', !$.support.fileInput)
            .parent().addClass($.support.fileInput ? undefined : 'disabled');

        $("#themeThumb").bind('fileuploadsubmit', function (e, data) {
            data.formData = {
                ccm_token: '<?php echo $app->make("token")->generate("upload_theme_thumb"); ?>',
            }
        });

        if ($('#uploaded-filename').val()) {
            $('#upload-message').removeClass('alert-danger').addClass('alert alert-success showing').empty();
            $('<p/>').html($('#uploaded-filename').val() + " <?php echo t('uploaded successfully'); ?>").appendTo('#upload-message');

            $('.ccm-dashboard-form-actions #clone-theme').removeProp('disabled');
            $('.next-wrapper').show();

            $('#progress .progress-bar').css(
                'width',
                '100%'
            );
        }

        // managing theme cloning
        var elementalCloner = {
            generating: false,
            button: null,
            operationStatusLabel: "",
            triggerProgressiveOperation: function(url, params, dialogTitle, onComplete, onError) {
                elementalCloner.button.addClass("disabled");
                NProgress.set(0);
                elementalCloner.generating = true;
                $.concreteAjax({
                    loader: false,
                    url: url,
                    type: 'POST',
                    data: params,
                    dataType: 'html',
                    timeout: 10000,
                    error: function(xhr, status, r) {
                        elementalCloner.button.removeClass("disabled");
                        elementalCloner.generating = false;
                        switch (status) {
                            case 'timeout':
                                var text = ccmi18n.requestTimeout;
                                break;
                            default:
                                var text = ConcreteAjaxRequest.errorResponseToString(xhr);
                                break;
                        }
                        NProgress.remove();

                        text = '<div class="alert alert-danger">' + text + '</div>';

                        ConcreteAlert.dialog(ccmi18n.error, text);

                        if (typeof(onError) == 'function') {
                            onError(r);
                        }
                    },
                    success: function(r) {
                        if (r && typeof r === "string") {
                            r = JSON.parse(r);
                        }

                        if (r.error) {
                            var text = '<div class="alert alert-danger">' + r.errors.toString() + '</div>';

                            NProgress.remove();
                            elementalCloner.button.removeClass("disabled");
                            elementalCloner.generating = false;

                            ConcreteAlert.dialog(ccmi18n.error, text);

                            if (typeof(onError) == 'function') {
                                onError(r);
                            }
                        } else {
                            var operationStatusLabel = "<?php echo t("Cloning Elemental%s", '&hellip;'); ?>";

                            var pNotifyText = '<div><span id="ccm-progressive-operation-status">' + operationStatusLabel + '</span></div>';
                            pNotifyText += '<div id="cloner-progress-wrapper" class="cloner-process-progress"><div id="progress" class="progress progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="progress-bar progress-bar-warning" style="width:0%;"></div></div></div>';
                            pNotifyText += '<br><div id="ccm-progressive-operation-refresh-warning" class="small"><i class="fa fa-exclamation-circle"></i>&nbsp;<?php echo t("Operation in progress%s please do not reload the page", '<br>'); ?></div>';

                            var pnotify = new PNotify({
                                text: pNotifyText,
                                hide: false,
                                title: dialogTitle,
                                width: '450px',
                                buttons: {
                                    closer: false
                                },
                                type: 'info',
                                icon: 'fa fa-hourglass fa-spin'
                            });
                            params.action = r.action;
                            params.actionNbr = r.actionNbr;
                            params.totalActions = r.totalActions;

                            elementalCloner.doProgressiveOperation(url, params, onComplete, onError, pnotify);
                        }
                        elementalCloner.generating = false;
                    }
                });
            },

            doProgressiveOperation: function(url, params, onComplete, onError, container) {

                var pnotify = container;
                params.process = true;

                $.ajax({
                    url: url,
                    dataType: 'json',
                    type: 'POST',
                    data: params,
                    timeout: 60000,
                }).done(function(r, textStatus, jqXHR) {
                        if (r && typeof r === "string") {
                            r = JSON.parse(r);
                        }

                        if (r.error) {
                            var text = '<div class="alert alert-danger">' + r.errors.toString() + '</div>';
                            pnotify.remove();
                            NProgress.remove();
                            ConcreteAlert.dialog(ccmi18n.error, text);

                            if (typeof(onError) == 'function') {
                                onError(r);
                            }
                        } else {
                            // update the percentage
                            if (r.action && r.actionNbr <= params.totalActions) {
                                var operationStatusLabel;
                                switch (r.action) {
                                    case 'customize':
                                        operationStatusLabel = "<?php echo t("Customizing your theme's information%s", '&hellip;'); ?>";
                                        break;
                                    case 'googleFonts':
                                        operationStatusLabel = "<?php echo t("Switching Google Fonts to local%s", '&hellip;'); ?>";
                                        break;
                                    case 'thumb':
                                        operationStatusLabel = "<?php echo t("Generating the theme's thumbnail%s", '&hellip;'); ?>";
                                        break;
                                    default:
                                        break;
                                }
                                $('#ccm-progressive-operation-status').html(operationStatusLabel);
                            }

                            if (r.actionNbr <= params.totalActions) {
                                var pct = (r.actionNbr - 1) / params.totalActions;
                                NProgress.set(pct);

                                var progress = parseInt(pct * 100, 10);
                                $("#cloner-progress-wrapper .progress-bar").css("width", progress + "%");

                                params.action = r.action;

                                setTimeout(function() {
                                    elementalCloner.doProgressiveOperation(url, params, onComplete, onError, container);
                                }, 250);
                            } else {
                                NProgress.set(1);
                                $("#cloner-progress-wrapper .progress-bar").css("width", "100%");
                                setTimeout(function() {
                                    // give the animation time to catch up.
                                    NProgress.done();
                                    pnotify.remove();
                                    pnotify = new PNotify({
                                        text: r.successMessage,
                                        title: r.successTitle,
                                        hide: true,
                                        delay: 5000,
                                        width: '450px',
                                        buttons: {
                                            closer: true
                                        },
                                        type: "success",
                                        icon: "fa fa-check"
                                    });

                                    if (typeof(onComplete) == 'function') {
                                        onComplete(r.callbackparameter);
                                    }
                                }, 1000);
                            }
                        }
                    }).always(function(){
                        elementalCloner.button.removeClass("disabled");
                        elementalCloner.generating = false;

                    }).fail(function(xhr, status, r) {
                        var text;
                        // if (status === 'parsererror') {
                        //     text = "<?php echo t('Requested JSON parse failed.'); ?>";
                        // } else if (status === 'timeout') {
                        //     text = "<?php echo t('Time out error.'); ?>";
                        // } else if (status === 'abort') {
                        //     text = "<?php echo t('Ajax request aborted.'); ?>";
                        // } else if (0 === xhr.status) {
                        //     text = "<?php echo t('Not connected.\n Verify Network.'); ?>";
                        // } else if (404 == xhr.status) {
                        //     text = "<?php echo t('Requested page not found. [404]'); ?>";
                        // } else if (500 == xhr.status) {
                        //     text = "<?php echo t('Internal Server Error [500].'); ?>";
                        // } else {
                        //     text = ConcreteAjaxRequest.errorResponseToString(xhr);
                        // }

                        switch (status) {
                            case 'timeout':
                                text = ccmi18n.requestTimeout;
                                break;
                            default:
                                text = ConcreteAjaxRequest.errorResponseToString(xhr);
                                break;
                        }
                        pnotify.remove();
                        NProgress.remove();
                        text = '<div class="alert alert-danger">' + text + '</div>';
                        ConcreteAlert.dialog(ccmi18n.error, text);

                        if (typeof(onError) == 'function') {
                            onError(r);
                        }
                    });
            }
        }

        $('#thumbSource').on('change', function(evt) {
            if ($(this).val() == 'upload') {
                $('.thumb-uploader').slideDown();
                $('.thumb-manager').slideUp();
            } else {
                $('.thumb-uploader').slideUp();
                $('.thumb-manager').slideDown();
            }
        });
        $('#thumbSource').trigger('change');

        $(document).ready(function() {
            var fileSelector = $('.thumb-manager').find('[data-file-selector="manager-filename"]'),
                fileSelectorTemplate = fileSelector.html();

            function htmlDecode(text){
                return $('<div/>').html(text).text();
            }

            var resetCloner = function() {
                $("#elemental-cloner").get(0).reset();
                $('#uploaded-filename').val(null);
                $('input[type=hidden][name=fID]').val(0);
                fileSelector.html(fileSelectorTemplate);

                $('#progress .progress-bar').css(
                    'width',
                    '0%'
                );
                $('#upload-message').removeClass('showing').empty();

                $('#thumbSource').trigger('change');
            }
            $("#elemental-cloner").on("submit", function(e) {
                e.preventDefault();
                if (!elementalCloner.generating) {
                    elementalCloner.button = $(this).find('button[type="submit"]');
                    var formdata = $(this).serializeArray(),
                    data = {},
                    url = "<?php echo $view->action('run'); ?>";
                    $(formdata).each(function(index, obj){
                        data[obj.name] = obj.value;
                    });

                    elementalCloner.triggerProgressiveOperation(url, data, "<?php echo t("Starting your (totally legal) cloning."); ?>", resetCloner);
                }
            });
        });
    });
</script>
