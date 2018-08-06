<?php
namespace Concrete\Package\KalmoyaElementalCloner\Controller\SinglePage\Dashboard\Pages;

defined('C5_EXECUTE') or die("Access Denied.");

use stdClass;
use Exception;
use ElClKalmoya\KalmoyaInfo as Kalmoya;
use Concrete\Core\Foundation\Queue\Queue;
use Concrete\Core\Page\Controller\DashboardPageController;
use KalmoyaElementalCloner\ElementalClonerTool;

class ElementalCloner extends DashboardPageController
{
    protected $settingsManager = false;

    public function on_start()
    {
        $kalmoya = new Kalmoya(ELEMENTAL_CLONER_PACKAGE_HANDLE);
        $this->set('kalmoya', $kalmoya->getJSONKalmoya());
    }

    public function run()
    {
        try {
            $token = $this->request->request->get('ccm_token');

            $response = new stdClass();

            $q = Queue::get('create_elemental_clone');
            $process = $this->request->request->get('process');

            if (empty($process) && ($q->count() > 0)) {
                $q->deleteQueue();
                $q = Queue::get('create_elemental_clone');
            }

            if ($process) {
                $messages = $q->receive(1);

                foreach ($messages as $key => $msg) {
                    $task = unserialize($msg->body);

                    $err = $this->validate($task);
                    if ($err->has()) {
                        throw new Exception($err);
                    }

                    $response->action = $task['nextAction'];
                    $response->actionNbr = (int) $task['actionNbr'] + 1;
                    $response->totalActions = $this->request->request->get('totalActions');

                    if (!empty($task['action'])) {
                        $settings = $task;
                        $settings['token'] = $token;

                        $cloner = $this->app->make(
                            ElementalClonerTool::class,
                            ['settings' => $settings]
                        );

                        $ret = $cloner->process(empty($task['nextAction']));

                        if (!$ret) {
                            throw new Exception(t("WOW! Something was not right at all. Try again maybe?"));
                        }
                    }

                    if (empty($task['nextAction'])) {
                        $response->successTitle = t("Theme Clonaging Complete!");
                        $successMessage = [];
                        $successMessage[] = '<ul>';
                        $successMessage[] = t("%sYour theme was cloned successfully.%s", '<li>', '</li>');
                        $successMessage[] = t("%sIt was renamed and customized.%s", '<li>', '</li>');

                        if (!empty($task['fID']) || !empty($task['filename'])) {
                            $successMessage[] = t(
                                "%sA gorgeous new thumbnail was set.%s",
                                '<li>',
                                '</li>'
                            );
                        }

                        if ('local' === $task['googleFonts']) {
                            $successMessage[] = t(
                                "%sAnd your Google fonts will be loaded from your server.%s",
                                '<li>',
                                '</li>'
                            );
                        }

                        if ($task['buildPackage']) {
                            $successMessage[] = t(
                                "%sLast but not least, the whole thing was packaged.%s",
                                '<li>',
                                '</li>'
                            );
                        }
                        $successMessage[] = '</ul>';

                        $response->successMessage = implode(' ', $successMessage);
                    }

                    $q->deleteMessage($msg);
                }

                if (0 == $q->count()) {
                    $q->deleteQueue();
                }

                echo json_encode($response);
                exit;
            } elseif (0 == $q->count()) {
                $valt = $this->app->make('token');

                if (!$valt->validate('create_elemental_clone', $token)) {
                    throw new Exception(t("This is embarassing! Could you reload the page and try again please?"));
                }

                $txt = $this->app->make('helper/text');
                $handle = $this->request->request->get('themeHandle');
                $filename = $this->request->request->get('filename');
                $fID = $this->request->request->get('fID');
                $thumbSource = $this->request->request->get('thumbSource');
                $googleFonts = $this->request->request->get('googleFonts');
                $name = $this->request->request->get('themeName');
                $name = empty($name) ? $txt->unhandle($handle) : $name;
                $description = $this->request->request->get('themeDescription');

                switch ($thumbSource) {
                    case 'upload':
                        $fID = null;
                        break;
                    case 'manager':
                        $filename = null;
                        break;
                    default:
                        $filename = null;
                        $fID = null;
                        break;
                }

                $actionNbr = 1;
                $queueData = [
                    'name' => $name,
                    'handle' => $handle,
                    'description' => $description,
                    'thumbSource' => $thumbSource,
                    'googleFonts' => $googleFonts,
                    'fID' => $fID,
                    'filename' => $filename,
                ];

                // I put this one here because I want it no matter what to clean up the file if needed
                $pkgIcon = $this->request->request->get('pkgIcon');
                if (!empty($pkgIcon)) {
                    $queueData['pkgIcon'] = $pkgIcon;
                }

                $buildPackage = (bool) $this->request->request->get('buildPackage');
                if ($buildPackage) {
                    $pkgHandle = $this->request->request->get('pkgHandle');
                    $pkgAppVersion = $this->request->request->get('pkgAppVersion');
                    $pkgVersion = $this->request->request->get('pkgVersion');
                    $pkgDescription = $this->request->request->get('pkgDescription');
                    $pkgName = $this->request->request->get('pkgName');

                    $pkgfID = $this->request->request->get('pkgfID');
                    $pkgThumbSource = $this->request->request->get('pkgThumbSource');

                    $firstAction = 'package';

                    switch ($pkgThumbSource) {
                        case 'upload':
                            $pkgfID = null;
                            break;
                        case 'manager':
                            $pkgIcon = null;
                            break;
                        default:
                            $pkgIcon = null;
                            $pkgfID = null;
                            break;
                    }

                    $queueData['buildPackage'] = true;
                    $queueData = array_merge(
                        [
                            'pkgHandle' => $pkgHandle,
                            'pkgThumbSource' => $pkgThumbSource,
                            'pkgfID' => $pkgfID,
                        ],
                        $queueData
                    );

                    $q->send(
                        serialize(
                            array_merge(
                                [
                                    'pkgAppVersion' => $pkgAppVersion,
                                    'pkgVersion' => $pkgVersion,
                                    'pkgDescription' => $pkgDescription,
                                    'pkgName' => $pkgName,
                                    'action' => 'package',
                                    'nextAction' => 'clone',
                                    'actionNbr' => $actionNbr,
                                ],
                                $queueData
                            )
                        )
                    );
                    ++$actionNbr;
                } else {
                    $firstAction = 'clone';
                    $queueData['buildPackage'] = false;
                }

                $q->send(
                    serialize(
                        array_merge(
                            [
                                'action' => 'clone',
                                'nextAction' => 'customize',
                                'actionNbr' => $actionNbr,
                            ],
                            $queueData
                        )
                    )
                );
                ++$actionNbr;

                if ('local' === $googleFonts) {
                    $nextAction = 'googleFonts';
                } elseif (!empty($filename) || !empty($fID)) {
                    $nextAction = 'thumb';
                } else {
                    $nextAction = null;
                }

                $q->send(
                    serialize(
                        array_merge(
                            [
                                'action' => 'customize',
                                'nextAction' => $nextAction,
                                'actionNbr' => $actionNbr,
                            ],
                            $queueData
                        )
                    )
                );
                ++$actionNbr;

                if ('local' === $googleFonts) {
                    if (!empty($filename) || !empty($fID)) {
                        $nextAction = 'thumb';
                    } else {
                        $nextAction = null;
                    }

                    $q->send(
                        serialize(
                            array_merge(
                                [
                                    'action' => 'googleFonts',
                                    'nextAction' => $nextAction,
                                    'actionNbr' => $actionNbr,
                                ],
                                $queueData
                            )
                        )
                    );
                    ++$actionNbr;
                }

                if (!empty($filename) || !empty($fID) || ($buildPackage && (!empty($pkgIcon) || !empty($pkgfID)))) {
                    $q->send(
                        serialize(
                            array_merge(
                                [
                                    'action' => 'thumb',
                                    'nextAction' => null,
                                    'actionNbr' => $actionNbr,
                                ],
                                $queueData
                            )
                        )
                    );
                    ++$actionNbr;
                }

                $response->action = $firstAction;
                $response->actionNbr = 1;
                $response->totalActions = $actionNbr - 1;

                echo json_encode($response);
                exit;
            }
        } catch (Exception $e) {
            $q->deleteQueue();
            $resp = new stdClass();
            $resp->error = true;
            $resp->errors = [(string) $e->getMessage()];
            echo json_encode($resp);

            exit;
        }
    }

    public function validate($args)
    {
        $e = $this->app->make('helper/validation/error');
        $vals = $this->app->make('helper/validation/strings');

        if (!$vals->notEmpty($args['handle'])) {
            $e->add(t("A theme handle is required."));
        }

        if ($vals->notEmpty($args['handle']) && !$vals->handle($args['handle'])) {
            $e->add(t("Theme handles may only contain letters, numbers and underscore %s_%s characters", '&ldquo;', '&rdquo;'));
        }

        if ((bool) $args['buildPackage']) {
            if (!$vals->notEmpty($args['pkgHandle'])) {
                $e->add(t("A package handle is required."));
            }

            if ($vals->notEmpty($args['pkgHandle']) && !$vals->handle($args['pkgHandle'])) {
                $e->add(t("Package handles may only contain letters, numbers and underscore %s_%s characters", '&ldquo;', '&rdquo;'));
            }
        }

        return $e;
    }
}
