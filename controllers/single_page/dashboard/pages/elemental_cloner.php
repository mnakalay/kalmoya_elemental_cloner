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
                        $settings = [
                            'handle' => $task['handle'],
                            'action' => $task['action'],
                            'token' => $token,
                            'name' => $task['name'],
                            'description' => $task['description'],
                            'thumbSource' => $task['thumbSource'],
                            'googleFonts' => $task['googleFonts'],
                            'fID' => $task['fID'],
                            'filename' => $task['filename'],
                        ];

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
                $totalActions = 2;

                $queueData = [
                    'name' => $name,
                    'handle' => $handle,
                    'description' => $description,
                    'thumbSource' => $thumbSource,
                    'googleFonts' => $googleFonts,
                    'fID' => $fID,
                    'filename' => $filename,
                ];

                switch ($thumbSource) {
                    case 'upload':
                        $fID = null;
                        break;
                    case 'manager':
                        $filename = null;
                        break;
                    default:
                        $filename = null;
                        $fD = null;
                        break;
                }

                $q->send(
                    serialize(
                        array_merge(
                            [
                                'action' => 'clone',
                                'nextAction' => 'customize',
                                'actionNbr' => 1,
                            ],
                            $queueData
                        )
                    )
                );

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
                                'actionNbr' => 2,
                            ],
                            $queueData
                        )
                    )
                );

                if ('local' === $googleFonts) {
                    if (!empty($filename) || !empty($fID)) {
                        $nextAction = 'thumb';
                    } else {
                        $nextAction = null;
                    }
                    ++$totalActions;

                    $q->send(
                        serialize(
                            array_merge(
                                [
                                    'action' => 'googleFonts',
                                    'nextAction' => $nextAction,
                                    'actionNbr' => $totalActions,
                                ],
                                $queueData
                            )
                        )
                    );
                }

                if (!empty($filename) || !empty($fID)) {
                    ++$totalActions;

                    $q->send(
                        serialize(
                            array_merge(
                                [
                                    'action' => 'thumb',
                                    'nextAction' => null,
                                    'actionNbr' => $totalActions,
                                ],
                                $queueData
                            )
                        )
                    );
                }

                $response->action = 'clone';
                $response->actionNbr = 1;
                $response->totalActions = $totalActions;

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

        return $e;
    }
}
