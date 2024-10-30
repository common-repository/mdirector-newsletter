<?php /** @noinspection ALL */

namespace MDirectorNewsletter\admin;

use MDirectorNewsletter\includes\Mdirector_Newsletter_Api;
use MDirectorNewsletter\includes\Mdirector_Newsletter_Utils;
use MDirectorNewsletter\includes\Mdirector_Newsletter_Twig;
use Log\Log;
use MDOAuthException2;
use Throwable;
use Twig_Error_Loader;
use Twig_Error_Runtime;
use Twig_Error_Syntax;
use Twig_TemplateWrapper;

require_once(plugin_dir_path(__FILE__) . '../vendor/autoload.php');

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://mdirector.com
 * @since      1.0.0
 *
 * @package    MDirector_Newsletter
 * @subpackage MDirector_Newsletter/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    MDirector_Newsletter
 * @subpackage MDirector_Newsletter/admin
 * @author     MDirector
 */
class MDirector_Newsletter_Admin
{
    const ASSETS_PATH = MDIRECTOR_NEWSLETTER_PLUGIN_URL . 'assets';
    const REQUEST_RESPONSE_SUCCESS = 'ok';
    const NO_VALUE = '---';
    const DEFAULT_SETTINGS_TAB = 'settings';
    const STR_SEPARATOR = '-';
    const SETTINGS_SEPARATOR = '_';
    const MIDNIGHT = '23:59';
    const TEST_FLAG = 'test';
    const TEMPLATE_PREVIEW = 'template_preview';

    protected $frequencyDays;
    protected $dynamicSubjectValues;
    protected $currentLanguages = [];

    /**
     * @var Twig_TemplateWrapper
     */
    protected $adminTemplate;

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $MDirectorNewsletter The ID of this plugin.
     */
    private $MDirectorNewsletter;

    protected $apiKey;
    protected $apiSecret;

    /**
     * @var Mdirector_Newsletter_Twig
     */
    private $twigInstance;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    /**
     * @var Mdirector_Newsletter_Utils
     */
    private $MDirectorUtils;

    /**
     * @var Mdirector_Newsletter_Api
     */
    private $MDirectorNewsletterApi;

    /**
     * MDirector_Newsletter_Admin constructor.
     *
     * @param $mdirectorNewsletter
     * @param $version
     *
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function __construct($mdirectorNewsletter, $version)
    {
        $this->loadDependencies();

        $this->MDirectorNewsletter = $mdirectorNewsletter;
        $this->version = $version;
        $this->MDirectorUtils = new Mdirector_Newsletter_Utils();
        $this->MDirectorNewsletterApi = new Mdirector_Newsletter_Api();
        $this->apiKey = $this->getPluginApiKey();
        $this->apiSecret = $this->getPluginApiSecret();

        $this->twigInstance = new Mdirector_Newsletter_Twig();

        $this->adminTemplate = $this->twigInstance->initAdminTemplate();

        add_action('admin_menu', [$this, 'mdirectorNewsletterMenu']);
        $this->MDirectorUtils->bootNotices();
    }

    private function loadDependencies()
    {
        require_once MDIRECTOR_NEWSLETTER_PLUGIN_DIR
            . 'includes/class-mdirector-newsletter-utils.php';
        require_once MDIRECTOR_NEWSLETTER_PLUGIN_DIR
            . 'includes/class-mdirector-newsletter-twig.php';
    }

    private function getPluginApiKey()
    {
        return $this->getOption('mdirector_api');
    }

    private function getPluginApiSecret()
    {
        return $this->getOption('mdirector_secret');
    }

    private function setCurrentLanguages()
    {
        $this->currentLanguages = $this->MDirectorUtils->getCurrentLanguages();
    }

    private function isPluginConfigured()
    {
        $options = $this->MDirectorUtils->getPluginOptions();

        if (!isset($options['mdirector_api'])
            || !isset($options['mdirector_secret'])) {
            return false;
        }

        $this->apiKey || ($this->apiKey = $options['mdirector_api']);
        $this->apiSecret || ($this->apiSecret = $options['mdirector_secret']);

        return $this->apiKey && $this->apiSecret;
    }

    private function composeListName($list, $type)
    {
        $blog_name = sanitize_title_with_dashes(get_bloginfo('name'));
        $lang = key($list);

        return $blog_name . self::STR_SEPARATOR .
            ($type === Mdirector_Newsletter_Utils::DAILY_FREQUENCY
                ? Mdirector_Newsletter_Utils::DAILY_FREQUENCY
                : Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY) .
            self::STR_SEPARATOR . $lang;
    }

    /**
     * @param $listName
     *
     * @return array|mixed|object
     * @throws MDOAuthException2
     */
    private function createListViaAPI($listName)
    {
        return json_decode($this->MDirectorNewsletterApi->callAPI(
            $this->apiKey,
            $this->apiSecret,
            Mdirector_Newsletter_Utils::MDIRECTOR_API_LIST_ENDPOINT,
            'POST', [
                'listName' => $listName
            ]));
    }

    /**
     * @param $array_list_names
     *
     * @throws MDOAuthException2
     * @throws Throwable
     */
    private function createMdirectorDailyLists($array_list_names)
    {
        $options = $this->MDirectorUtils->getPluginOptions();
        $newListIds = [];

        foreach ($this->MDirectorUtils->getCurrentLanguages() as $lang => $id) {
            $dailyName = $this->composeListName([$lang => $id],
                Mdirector_Newsletter_Utils::DAILY_FREQUENCY);

            if (in_array($dailyName, $array_list_names)) {
                $dailyName .= self::STR_SEPARATOR . time();
            }

            $mdirectorDailyId = $this->createListViaAPI($dailyName);

            if ($mdirectorDailyId->response
                === self::REQUEST_RESPONSE_SUCCESS) {
                $options['mdirector_daily_list_' . $lang] =
                    $mdirectorDailyId->listId;
                $options['mdirector_daily_list_name_' . $lang] = $dailyName;
                update_option('mdirector_settings', $options);

                $templateData = [
                    'listName' => $dailyName,
                    'listId' => $mdirectorDailyId->listId,
                    'message' => 'DAILY-LISTS__NEW-DAILY-LIST-ADDED'
                ];
                $this->MDirectorUtils->reportNotice('updatedWithDetailsInfoNotice', $templateData);
                $newListIds[] = $mdirectorDailyId->listId;
            } else {
                $templateData = [
                    'message' => 'DAILY-LISTS__NEW-DAILY-LIST-ADDED-ERROR'
                ];
                $this->MDirectorUtils->reportNotice('updatedErrorNotice', $templateData);
                $this->MDirectorUtils->log(
                    'DAILY lists can not be created',
                    [],
                    $this->MDirectorUtils::LOG_ERROR
                );
            }
        }

        if (count($newListIds) > 0) {
            $this->MDirectorUtils->log(
                'New DAILY lists created',
                ['ids' => $newListIds],
                $this->MDirectorUtils::LOG_INFO
            );
        }
    }

    /**
     * @param $array_list_names
     *
     * @throws MDOAuthException2
     * @throws Throwable
     */
    private function createMdirectorWeeklyLists($array_list_names)
    {
        $options = $this->MDirectorUtils->getPluginOptions();
        $newListIds = [];

        foreach ($this->MDirectorUtils->getCurrentLanguages() as $lang => $id) {
            $weeklyName = $this->composeListName([$lang => $id],
                Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY);

            if (in_array($weeklyName, $array_list_names)) {
                $weeklyName .= self::STR_SEPARATOR . time();
            }

            $mdirectorWeeklyId = $this->createListViaAPI($weeklyName);

            if ($mdirectorWeeklyId->response
                === self::REQUEST_RESPONSE_SUCCESS) {

                $options['mdirector_weekly_list_' . $lang] =
                    $mdirectorWeeklyId->listId;
                $options['mdirector_weekly_list_name_' . $lang] = $weeklyName;
                update_option('mdirector_settings', $options);

                $templateData = [
                    'listName' => $weeklyName,
                    'listId' => $mdirectorWeeklyId->listId,
                    'message' => 'WEEKLY-LISTS__NEW-WEEKLY-LIST-ADDED'
                ];
                $this->MDirectorUtils->reportNotice('updatedWithDetailsInfoNotice', $templateData);
                $newListIds[] = $mdirectorWeeklyId->listId;
            } else {
                $templateData = [
                    'message' => 'WEEKLY-LISTS__NEW-WEEKLY-LIST-ADDED-ERROR'
                ];
                $this->MDirectorUtils->reportNotice('updatedErrorNotice', $templateData);

                $this->MDirectorUtils->log(
                    'WEEKLY lists can not be created',
                    [],
                    $this->MDirectorUtils::LOG_ERROR
                );
            }
        }

        if (count($newListIds) > 0) {
            $this->MDirectorUtils->log(
                'New WEEKLY lists created',
                ['ids' => $newListIds],
                $this->MDirectorUtils::LOG_INFO
            );
        }
    }

    /**
     * @return bool
     * @throws MDOAuthException2
     * @throws Throwable
     */
    public function createMdirectorLists()
    {
        if (!$this->isPluginConfigured()) {
            return false;
        }

        $mdirectorUserLists = [];

        $mdirectorRequest =
            json_decode($this->MDirectorNewsletterApi->callAPI(
                $this->apiKey,
                $this->apiSecret,
                Mdirector_Newsletter_Utils::MDIRECTOR_API_LIST_ENDPOINT,
                'GET'));

        if ($mdirectorRequest->response === self::REQUEST_RESPONSE_SUCCESS) {
            $mdirectorUserLists = array_map(function ($e) {
                return $e->name;
            }, $mdirectorRequest->lists);
        }

        $this->createMdirectorDailyLists($mdirectorUserLists);
        $this->createMdirectorWeeklyLists($mdirectorUserLists);

        return true;
    }

    /**
     * @param $arrayCampaignNames
     *
     * @throws MDOAuthException2
     */
    private function createMdirectorWeeklyCampaigns($arrayCampaignNames)
    {
        $options = $this->MDirectorUtils->getPluginOptions();

        foreach ($this->MDirectorUtils->getCurrentLanguages() as $lang => $id) {
            $weeklyName = $this->composeListName([$lang => $id],
                Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY);

            if (in_array($weeklyName, $arrayCampaignNames)) {
                $weeklyName .= self::STR_SEPARATOR . time();
            }

            $mdirectorWeeklyId = $this->createCampaignViaAPI($weeklyName);

            if ($mdirectorWeeklyId->response
                === self::REQUEST_RESPONSE_SUCCESS) {
                $options['mdirector_weekly_campaign_' . $lang] =
                    $mdirectorWeeklyId->data->camId;
                $options['mdirector_weekly_campaign_name_' . $lang] =
                    $weeklyName;
                update_option('mdirector_settings', $options);
            }
        }
    }

    /**
     * @param $campaignName
     *
     * @return mixed
     * @throws MDOAuthException2
     */
    private function createCampaignViaAPI($campaignName)
    {
        return json_decode($this->MDirectorNewsletterApi->callAPI(
            $this->apiKey,
            $this->apiSecret,
            Mdirector_Newsletter_Utils::MDIRECTOR_API_CAMPAIGN_ENDPOINT,
            'POST', [
                'name' => $campaignName
            ]));
    }

    /**
     * @param $array_campaign_names
     *
     * @throws MDOAuthException2
     */
    private function createMdirectorDailyCampaigns($array_campaign_names)
    {
        $options = $this->MDirectorUtils->getPluginOptions();

        foreach ($this->MDirectorUtils->getCurrentLanguages() as $lang => $id) {
            $dailyName = $this->composeListName([$lang => $id],
                Mdirector_Newsletter_Utils::DAILY_FREQUENCY);

            if (in_array($dailyName, $array_campaign_names)) {
                $dailyName .= self::STR_SEPARATOR . time();
            }

            $mdirectorDailyId = $this->createCampaignViaAPI($dailyName);

            if ($mdirectorDailyId->response
                === self::REQUEST_RESPONSE_SUCCESS) {
                $options['mdirector_daily_campaign_' . $lang] =
                    $mdirectorDailyId->data->camId;
                $options['mdirector_daily_campaign_name_' . $lang] = $dailyName;

                update_option('mdirector_settings', $options);
            }
        }
    }

    /**
     * @return bool
     * @throws MDOAuthException2
     */
    public function createMdirectorCampaigns()
    {
        if (!$this->isPluginConfigured()) {
            return false;
        }

        $MDirectorUserCampaigns = [];

        $MDirectorRequest =
            json_decode($this->MDirectorNewsletterApi->callAPI(
                $this->apiKey,
                $this->apiSecret,
                Mdirector_Newsletter_Utils::MDIRECTOR_API_CAMPAIGN_ENDPOINT,
                'GET'));

        if ($MDirectorRequest->response === self::REQUEST_RESPONSE_SUCCESS) {
            $MDirectorUserCampaigns = array_map(function ($e) {
                return $e->campaignName;
            }, $MDirectorRequest->data);
        }

        $this->createMdirectorDailyCampaigns($MDirectorUserCampaigns);
        $this->createMdirectorWeeklyCampaigns($MDirectorUserCampaigns);

        return true;
    }

    /**
     * Adds the plugin admin menu.
     *
     * @since    1.0.0
     */
    public function mdirectorNewsletterMenu()
    {
        $menu = add_menu_page('MDirector', 'MDirector', 'manage_options',
            'mdirector-newsletter', [$this, 'mdirectorNewsletterInit'],
            MDIRECTOR_NEWSLETTER_PLUGIN_URL . '/assets/icon_mdirector.png');

        add_action("load-{$menu}", [$this, 'mdirectorNewsletterSave']);
    }

    private function getCurrentTab()
    {
        return $_REQUEST['tab'] ?? self::DEFAULT_SETTINGS_TAB;
    }

    /*private function remove_all_settings_options() {
        update_option('mdirector_settings', []);
    }*/

    /**
     * @param $data
     *
     * @throws Throwable
     */
    private function saveSettings($data)
    {
        if ($this->getCurrentTab() === 'settings') {
            unset($data['mdirector-newsletter-submit']);

            $formFields = $this->pregGrepKeys('/^mdirector_/', $data);

            $options = array_merge(
                $this->MDirectorUtils->getPluginOptions(),
                $formFields,
                $this->checkCheckboxesValuesForSave($data));

            update_option('mdirector_settings', $options);

            $this->MDirectorUtils->log(
                'Saving new options.',
                ['options' => $options],
                $this->MDirectorUtils::LOG_INFO
            );

            if (isset($_POST['cpt_submit'])) {
                $templateData = [
                    'message' => 'MAIN-FORM__SAVE-SUCCESS'
                ];
                $this->MDirectorUtils->reportNotice('updatedInfoNotice', $templateData);
            }
        }
    }

    private function pregGrepKeys($pattern, $input, $flags = 0)
    {
        return array_intersect_key(
            $input,
            array_flip(preg_grep($pattern, array_keys($input), $flags))
        );
    }

    private function checkCheckboxesValuesForSave($data)
    {
        $options = [];
        $options['mdirector_frequency_weekly'] = $this->getOption('mdirector_frequency_weekly', $data);
        $options['mdirector_frequency_daily'] = $this->getOption('mdirector_frequency_daily', $data);
        $options['mdirector_exclude_cats'] =
            ((isset($data['mdirector_exclude_cats'])
                && count($data['mdirector_exclude_cats']) > 0)
                ? serialize($data['mdirector_exclude_cats'])
                : []);

        foreach ($this->getWeekdaySelectorArray() as $dayWeek => $dayWeekLong) {
            $fieldName = Mdirector_Newsletter_Utils::DAILY_WEEKS_ALLOWED_PREFIX
                . $dayWeek;
            $options[$fieldName] = $data[$fieldName] ??
                Mdirector_Newsletter_Utils::SETTINGS_OPTION_OFF;
        }

        foreach ($this->MDirectorUtils->getCurrentLanguages() as $language) {
            $lang = $language['code'];
            $prefix = 'mdirector_';
            $active_policy = $prefix . 'policy_' . $lang . '_active';
            $options[$active_policy] = $this->getOption($active_policy, $data);

            $lists = [
                $prefix . Mdirector_Newsletter_Utils::DAILY_FREQUENCY . '_list_' . $lang,
                $prefix . Mdirector_Newsletter_Utils::DAILY_FREQUENCY . '_list_group_' . $lang,
                $prefix . Mdirector_Newsletter_Utils::DAILY_FREQUENCY . '_list_segment_' . $lang,
                $prefix . Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY . '_list_' . $lang,
                $prefix . Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY . '_list_group_' . $lang,
                $prefix . Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY . '_list_segment_' . $lang,
            ];

            foreach ($lists as $list) {
                $options[$list] = $this->getOption($list, $data);
            }
        }

        return $options;
    }

    private function getOption($option, $options = null) {
        if (empty($options)) {
            $options = $this->MDirectorUtils->getPluginOptions();
        }

        return isset($options[$option])
            ? $options[$option]
            : null;
    }

    private function saveDebugSettings($data)
    {
        if ($this->getCurrentTab() === 'debug') {
            $debugOptions = $this->pregGrepKeys('/^mdirector_/', $data);
            $options = array_merge($this->MDirectorUtils->getPluginOptions(), $debugOptions);

            foreach ($this->MDirectorUtils->getCurrentLanguages() as $language) {
                $lang = $language['code'];
                $prefix = 'mdirector_';
                $debugOptionsCheckboxes = [
                    $prefix . Mdirector_Newsletter_Utils::DAILY_FREQUENCY . '_list_test_' . $lang,
                    $prefix . Mdirector_Newsletter_Utils::DAILY_FREQUENCY . '_list_test_group_' . $lang,
                    $prefix . Mdirector_Newsletter_Utils::DAILY_FREQUENCY . '_list_test_segment_' . $lang,
                    $prefix . Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY . '_list_test_' . $lang,
                    $prefix . Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY . '_list_test_group_' . $lang,
                    $prefix . Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY . '_list_test_segment_' . $lang,
                    'mdirector_use_test_lists'
                ];

                foreach ($debugOptionsCheckboxes as $option) {
                    $options[$option] = $this->getOption($option, $data);
                }
            }

            update_option('mdirector_settings', $options);

            $this->MDirectorUtils->log(
                'Saving new options.',
                ['options' => $options],
                $this->MDirectorUtils::LOG_INFO
            );
        }
    }

    private function saveAdvanceSettings($data)
    {
        $advanceOptions = $this->pregGrepKeys('/^mdirector_/', $data);
        $options = array_merge($this->MDirectorUtils->getPluginOptions(), $advanceOptions);

        $advanceOptionsCheckboxes = [
            'mdirector_hide_sample_templates',
            'mdirector_scheduler_posts',
            'mdirector_logs_deactivated'
        ];

        foreach ($advanceOptionsCheckboxes as $option) {
            $options[$option] = $this->getOption($option, $data);
        }

        update_option('mdirector_settings', $options);

        $this->MDirectorUtils->log(
            'Saving new options.',
            ['options' => $options],
            $this->MDirectorUtils::LOG_INFO
        );
    }

    /**
     * @throws MDOAuthException2
     * @throws Throwable
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    private function sendingTest()
    {
        $options = $this->MDirectorUtils->getPluginOptions();

        if ($options['mdirector_frequency_daily'] === Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) {
            $this->sendDailyTest();
        } else {
            $templateData = [
                'message' => 'SENDING-TEST__DAILY-DEACTIVATED'
            ];
            $this->MDirectorUtils->reportNotice('updatedErrorNotice', $templateData);
        }

        if ($options['mdirector_frequency_weekly'] === Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) {
            $this->sendWeeklyTest();
        } else {
            $templateData = [
                'message' => 'SENDING-TEST__WEEKLY-DEACTIVATED'
            ];
            $this->MDirectorUtils->reportNotice('updatedErrorNotice', $templateData);
        }
    }

    /**
     * @throws MDOAuthException2
     * @throws Throwable
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    private function sendDailyTest() {
        $options = $this->MDirectorUtils->getPluginOptions();
        $options['mdirector_hour_daily'] = self::MIDNIGHT;
        $mode = $_POST['cpt_submit_test_now'];

        $responseDailies = ($mode === self::TEMPLATE_PREVIEW)
            ? $this->MDirectorUtils->buildDailyMailsInPreviewMode()
            : $this->MDirectorUtils->buildDailyMails();

        if (!empty($responseDailies)) {
            foreach ($responseDailies as $lang => $result) {
                $listsID = $this->MDirectorUtils->prettyPrintMultiArray(
                    $this->MDirectorUtils->getDefaultUserLists(
                        Mdirector_Newsletter_Utils::DAILY_FREQUENCY, $lang)
                );

                if ($result) {
                    $templateData = [
                        'message' => 'SENDING-TEST__DAILY-SENDING',
                        'listName' => $listsID
                    ];

                    $this->MDirectorUtils->reportNotice('updatedWithDetailsInfoNotice', $templateData);
                } else {
                    $templateData = [
                        'message' => 'SENDING-TEST__DAILY-SENDING-ERROR',
                        'details' => ': ' . $listsID . ' '
                            . $this->t('NO-ENTRIES-IN-BLOG'),
                        'listName' => $listsID
                    ];

                    $this->MDirectorUtils->reportNotice('updatedErrorNotice', $templateData);
                }
            }
        } else {
            $templateData = [
                'message' => 'SENDING-TEST__DAILY-GENERAL-SENDING-ERROR-NO-ACTIVES'
            ];
            $this->MDirectorUtils->reportNotice('updatedErrorNotice', $templateData);
        }
    }

    /**
     * @throws MDOAuthException2
     * @throws Throwable
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    private function sendWeeklyTest() {
        $options = $this->MDirectorUtils->getPluginOptions();
        $options['mdirector_hour_weekly'] = self::MIDNIGHT;
        $mode = $_POST['cpt_submit_test_now'];

        $responseWeeklies = ($mode === self::TEMPLATE_PREVIEW)
            ? $this->MDirectorUtils->buildWeeklyMailsInPreviewMode()
            : $this->MDirectorUtils->buildWeeklyMails(false, $mode);

        if (!empty($responseWeeklies)) {
            foreach ($responseWeeklies as $lang => $result) {
                $listsID = $this->MDirectorUtils->prettyPrintMultiArray(
                    $this->MDirectorUtils->getDefaultUserLists(
                        Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY, $lang)
                );

                if ($result) {
                    $templateData = [
                        'message' => 'SENDING-TEST__WEEKLY-SENDING',
                        'listName' => $listsID
                    ];
                    $this->MDirectorUtils->reportNotice('updatedWithDetailsInfoNotice', $templateData);
                } else {
                    $templateData = [
                        'message' => 'SENDING-TEST__WEEKLY-SENDING-ERROR',
                        'details' => ': ' . $listsID . ' '
                            . $this->t('NO-ENTRIES-IN-BLOG'),
                        'listName' => $listsID
                    ];
                    $this->MDirectorUtils->reportNotice('updatedErrorNotice', $templateData);
                }
            }
        } else {
            $templateData = [
                'message' => 'SENDING-TEST__WEEKLY-GENERAL-SENDING-ERROR-NO-ACTIVES'
            ];
            $this->MDirectorUtils->reportNotice('updatedErrorNotice', $templateData);
        }
    }

    /**
     * @return bool
     * @throws MDOAuthException2
     * @throws Throwable
     */
    public function mdirectorNewsletterSave()
    {
        // Reset user values and restoring all to its default values
        if (isset($_POST['cpt_reset'])) {
            $this->resetOptions();
            unset($_REQUEST['tab']);

            $templateData = [
                'message' => 'RESET-SUCCESS'
            ];
            $this->MDirectorUtils->reportNotice('updatedWithDetailsInfoNotice', $templateData);
        }

        // Clean cache
        if (isset($_POST['cpt_cleancache'])) {
            $this->cleanCache();

            $templateData = [
                'message' => 'CLEAN-CACHE-SUCCESS'
            ];
            $this->MDirectorUtils->reportNotice('updatedWithDetailsInfoNotice', $templateData);
        }

        // Creating MDirector lists on demand
        if (isset($_POST['cpt_submit_create_lists'])) {
            $this->createMdirectorLists();
            return true;
        }

        if (isset($_POST['cpt_submit_create_campaings'])) {
            $this->createMdirectorCampaigns();
        }

        if (
            isset($_POST['mdirector-newsletter-submit'])
            && $_POST['mdirector-newsletter-submit'] === Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON
        ) {
            $this->saveSettings($_POST);
        } else if (
            isset($_POST['save-debug-submit'])
            && $_POST['save-debug-submit'] === Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON
        ) {
            $this->saveDebugSettings($_POST);
            $templateData = [
                'message' => 'DEBUG-FORM__SAVE-SUCCESS'
            ];
            $this->MDirectorUtils->reportNotice('updatedInfoNotice', $templateData);
        } else if (
            isset($_POST['save-advance-submit'])
            && $_POST['save-advance-submit'] === Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON
        ) {
            $this->saveAdvanceSettings($_POST);
            $templateData = [
                'message' => 'ADVANCE-FORM__SAVE-SUCCESS'
            ];
            $this->MDirectorUtils->reportNotice('updatedInfoNotice', $templateData);
        }

        // Sending the campaigns immediately
        if (isset($_POST['cpt_submit_test_now'])) {
            $this->MDirectorUtils->log(
                'Sending campaigns inmediately...',
                [],
                $this->MDirectorUtils::LOG_INFO
            );
            $this->saveSettings($_POST);
            $this->saveDebugSettings($_POST);
            $this->sendingTest();
        }

        // Reset counters
        if (isset($_POST['cpt_submit_reset_now'])) {
            $this->MDirectorUtils->resetDeliveriesSent();
            $templateData = [
                'message' => 'LAST-SENDINGS-RESTARTED'
            ];
            $this->MDirectorUtils->reportNotice('updatedWithDetailsInfoNotice', $templateData);
        }

        return true;
    }

    private function resetOptions()
    {
        update_option('mdirector_settings', []);
    }

    public static function deleteDir($dirPath) {
        if (! is_dir($dirPath)) {
            throw new InvalidArgumentException("$dirPath must be a directory");
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }

        $files = glob($dirPath . '*', GLOB_MARK);

        foreach ($files as $file) {
            if (is_dir($file)) {
                self::deleteDir($file);
            } else {
                unlink($file);
            }
        }
    }

    private function cleanCache()
    {
        $dir = Mdirector_Newsletter_Twig::TWIG_CACHE_PATH;
        self::deleteDir($dir);
    }

    private function setTranslationsStrings()
    {
        $this->frequencyDays = [
            '1' => $this->t('WEEKDAY__MONDAY'),
            '2' => $this->t('WEEKDAY__TUESDAY'),
            '3' => $this->t('WEEKDAY__WEDNESDAY'),
            '4' => $this->t('WEEKDAY__THURSDAY'),
            '5' => $this->t('WEEKDAY__FRIDAY'),
            '6' => $this->t('WEEKDAY__SATURDAY'),
            '7' => $this->t('WEEKDAY__SUNDAY'),
        ];

        $this->dynamicSubjectValues = [
            Mdirector_Newsletter_Utils::DYNAMIC_CRITERIA_FIRST_POST =>
                $this->t('SUBJECT-VALUES__FIRST-POST-TITLE'),
            Mdirector_Newsletter_Utils::DYNAMIC_CRITERIA_LAST_POST =>
                $this->t('SUBJECT-VALUES__LAST-POST-TITLE')
        ];
    }

    /**
     * Translate a string using land domain
     *
     * @param $string
     *
     * @return string
     */
    private function t($string)
    {
        return __($string,
            Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN);
    }

    /**
     * @throws MDOAuthException2
     * @throws Throwable
     */
    public function mdirectorNewsletterInit()
    {
        $this->mdirectorChecks();
        $this->setCurrentLanguages();
        $this->setTranslationsStrings();

        //$this->createMdirectorCampaigns();

        $tabs = [
            'settings' => $this->t('CONFIGURATION')
        ];

        if ($this->isPluginConfigured()) {
            $tabs = array_merge($tabs, [
                'debug' => $this->t('TESTS'),
                'advance' => $this->t('ADVANCE'),
                'logs' => 'Logs'
            ]);
        } else {
            $tabs = array_merge(['welcome' => $this->t('WELCOME')], $tabs);
        }

        $tabs = array_merge($tabs, ['help' => $this->t('HELP')]);
        $currentTab = $this->getCurrentTab();

        //$this->MdirectorUtils->printNotices();

        echo '<div id="' . Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN . '">';
        echo $this->adminTemplate->renderBlock('header',
            ['tabs' => $tabs, 'currentTab' => $currentTab]);

        echo '<form method="post" action="'
            . admin_url('admin.php?page=mdirector-newsletter')
            . '" class="form-table form-md">';
        wp_nonce_field('mdirector-settings-page');

        switch ($currentTab) {
            case 'settings':
                $this->mdTabContentSettings();
                break;
            case 'logs':
                $this->mdTabContentLogs();
                break;
            case 'help':
                $this->mdTabContentHelp();
                break;
            case 'welcome':
                $this->mdTabContentWelcome();
                break;
            case 'advance':
                $this->mdTabContentAdvance();
                break;
            case 'debug':
                $this->mdTabContentTests();
                break;
            default:
                ($this->isPluginConfigured())
                    ? $this->mdTabContentSettings()
                    : $this->mdTabContentWelcome();
                break;
        }
        echo '</form>';
        echo '</div>';
    }

    /**
     * @throws Throwable
     */
    public function mdTabContentHelp()
    {
        echo $this->adminTemplate->renderBlock('help',
            ['assets' => self::ASSETS_PATH]);
    }

    /**
     * @throws Throwable
     */
    public function mdTabContentWelcome()
    {
        echo $this->adminTemplate->renderBlock('welcome',
            ['assets' => self::ASSETS_PATH]);
    }

    private function getListsIds($type, $suffix = '')
    {
        $options = $this->MDirectorUtils->getPluginOptions();

        $target_list = 'mdirector' . self::SETTINGS_SEPARATOR .
            $type . self::SETTINGS_SEPARATOR;

        $prefix = $target_list .
            ($suffix ? $suffix . self::SETTINGS_SEPARATOR : '') . 'list' .
            self::SETTINGS_SEPARATOR;

        $lists = [];

        foreach ($this->currentLanguages as $language) {
            $lang = $language['code'];

            $lists[$lang] = [
                'origin' => $prefix . $lang,
                'campaign_id_string' => $campaignId,
                'translated_name' => $language['translated_name'],
                'value' => $options[$prefix . $lang] ?? null
            ];
        }

        return $lists;
    }

    private function getLastDateSend($frequency)
    {
        $dateSent = $this->getOption('mdirector_' . $frequency . '_sent');

        if ($lastDate = $dateSent) {
            return date('d-m-Y, H:i', strtotime($lastDate));
        }

        return self::NO_VALUE;
    }

    private function generateTemplateOptions($lang = null)
    {
        $availableTemplates = $this->MDirectorUtils->getPluginTemplates();

        $currentTemplateSelected =
            $this->MDirectorUtils->getCurrentTemplate($availableTemplates,
                $lang);

        return $this->MDirectorUtils->getTemplateOptionsHTML($availableTemplates, $currentTemplateSelected);
    }

    private function generateAllTemplateOptions()
    {
        $output = [];

        foreach ($this->currentLanguages as $lang) {
            $output[$lang['code']] = $this->generateTemplateOptions($lang);
        }

        return $output;
    }

    /**
     * @return string
     * @throws Throwable
     */
    private function buildOptionsForDays()
    {
        $optionsDays = '';
        $day = $this->getOption('mdirector_frequency_day');

        foreach ($this->frequencyDays as $key => $value) {
            $templateData = [
                'key' => $key,
                'value' => $value,
                'isSelected' => $day === strval($key)
            ];
            $optionsDays .= $this->adminTemplate->renderBlock('buildOption',
                $templateData);
        }

        return $optionsDays;
    }

    /**
     * @return string
     * @throws Throwable
     */
    private function buildSubjectWeeklyDynamic()
    {
        $optionsSubjectWeeklyDynamic = '';
        $subjectWeekly = $this->getOption('mdirector_subject_dynamic_value_weekly');

        foreach ($this->dynamicSubjectValues as $key => $value) {
            $templateData = [
                'key' => $key,
                'value' => $value,
                'isSelected' => $subjectWeekly === strval($key)
            ];
            $optionsSubjectWeeklyDynamic .= $this->adminTemplate->renderBlock('buildOption',
                $templateData);
        }

        return $optionsSubjectWeeklyDynamic;
    }

    /**
     * @return string
     * @throws Throwable
     */
    private function buildSubjectDailyDynamic()
    {
        $optionsSubjectDailyDynamic = '';
        $subjectValue = $this->getOption('mdirector_subject_dynamic_value_daily');

        foreach ($this->dynamicSubjectValues as $key => $value) {
            $templateData = [
                'key' => $key,
                'value' => $value,
                'isSelected' => $subjectValue === strval($key)
            ];
            $optionsSubjectDailyDynamic .= $this->adminTemplate->renderBlock('buildOption',
                $templateData);
        }

        return $optionsSubjectDailyDynamic;
    }

    /**
     * @return string
     * @throws Throwable
     */
    private function getWPMLCompatibilityTemplate()
    {
        return $this->adminTemplate->renderBlock('wpmlCompatibility',
            ['assets' => self::ASSETS_PATH]);
    }

    /**
     * @param $lists
     * @param $default_lists
     * @param $type
     *
     * @return string
     * @throws Throwable
     */
    private function getHTMLUsedLists($lists, $default_lists, $type)
    {
        $output = '';

        foreach ($lists as $lang => $data) {
            $id = $data['value'];
            $inputName = 'mdirector_' . $type . '_list_' . $lang;
            $activeField = $inputName . '_active';

            $templateData = [
                'on' => Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON,
                'langName' => $data['translated_name'],
                'defaultList' => $default_lists[$lang]['value'],
                'selectedId' => $id ? $id : $default_lists[$lang]['value'],
                'inputName' => $inputName,
                'activeField' => $activeField,
                'activeFieldValue' => $this->getOption($activeField),
                'placeholder' => 'ID'
            ];

            $output .= $this->adminTemplate->renderBlock('htmlUsedLists',
                $templateData);
        }

        return $output;
    }

    /**
     * @param $frequency
     *
     * @return string
     * @throws Throwable
     */
    private function getHTMLFixedSubjects($frequency)
    {
        $output = '';

        foreach ($this->currentLanguages as $lang => $data) {
            $input_name = 'mdirector_subject_' . $frequency . '_' . $lang;
            $subject = $this->getOption('mdirector_subject_type_' . $frequency);

            $templateData = [
                'langName' => $data['translated_name'],
                'inputName' => $input_name,
                'subject' => $subject,
                'subjectValue' => $this->getOption($input_name),
                'readOnly' => $subject
                    !== Mdirector_Newsletter_Utils::FIXED_SUBJECT
            ];

            $output .= $this->adminTemplate->renderBlock('fixedSubjects',
                $templateData);
        }

        return $output;
    }

    /**
     * @param $frequency
     *
     * @return string
     * @throws Throwable
     */
    private function getHTMLDynamicSubjects($frequency)
    {
        $output = '';

        foreach ($this->currentLanguages as $lang => $data) {
            $inputName =
                'mdirector_subject_dynamic_prefix_' . $frequency . '_' . $lang;
            $subjectType = $this->getOption('mdirector_subject_type_' . $frequency);

            $templateData = [
                'langName' => $data['translated_name'],
                'inputName' => $inputName,
                'subjectType' => $subjectType,
                'subjectValue' => $this->getOption($inputName),
                'readonly' => $subjectType
                    === Mdirector_Newsletter_Utils::FIXED_SUBJECT
            ];

            $output .= $this->adminTemplate->renderBlock('dynamicSubjects',
                $templateData);
        }

        return $output;
    }

    /**
     * @return string
     * @throws Throwable
     */
    private function getHTMLForPrivacyPolicy()
    {
        $output = '';
        $type = 'policy';

        foreach ($this->currentLanguages as $lang => $data) {
            $field_name = 'mdirector_' . $type . '_';
            $active_field = $field_name . $lang . '_active';
            $current_text = $field_name . 'text_' . $lang;
            $current_url = $field_name . 'url_' . $lang;
            $active_field_value = $this->getOption($active_field);

            $templateData = [
                'langName' => $data['translated_name'],
                'fieldName' => $field_name,
                'activeField' => $active_field,
                'currentText' => $current_text,
                'currentUrl' => $current_url,
                'currentTextValue' => $this->getOption($current_text),
                'currentUrlValue' => $this->getOption($current_url),
                'activeFieldValue' => $active_field_value,
                'on' => Mdirector_Newsletter_Utils:: SETTINGS_OPTION_ON,
                'checked' => $active_field_value ===
                    Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON
            ];

            $output .= $this->adminTemplate->renderBlock('privacyPolicy',
                $templateData);
        }

        return $output;
    }

    /**
     * @return string
     * @throws Throwable
     */
    private function getHTMLStep1()
    {
        $templateData = [
            'api' => $this->getOption('mdirector_api'),
            'secret' => $this->getOption('mdirector_secret')
        ];

        return $this->adminTemplate->renderBlock('firstStep', $templateData);
    }

    /**
     * @return string
     * @throws Throwable
     */
    private function getHTMLStep2()
    {
        $headerCampaignsTable = $this->adminTemplate->renderBlock('headerListsCampaignsTable', [
            'elementTitle' => 'STEP-2__CAMPAIGNS'
        ]);

        $headerListsTable = $this->adminTemplate->renderBlock('headerListsCampaignsTable', [
            'elementTitle' => 'STEP-2__LISTS'
        ]);

        $selectedDailyLists = $this->MDirectorUtils->getSelectedUserListsByLanguageHTML(Mdirector_Newsletter_Utils::DAILY_FREQUENCY);
        $selectedWeeklyLists = $this->MDirectorUtils->getSelectedUserListsByLanguageHTML(Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY);

        $weeklyCampaigns = $this->MDirectorUtils->getWeeklyCampaignsByLanguageHTML();
        $dailyCampaigns = $this->MDirectorUtils->getDailyCampaignsByLanguageHTML();

        $templateCampaignsData = [
            'headerCampaignsTable' => $headerCampaignsTable,
            'weeklyCampaigns' => $weeklyCampaigns,
            'dailyCampaigns' => $dailyCampaigns,
        ];

        $campaignsBlock = $this->adminTemplate->renderBlock('campaignsBlock', $templateCampaignsData);

        $templateData = [
            'on' => Mdirector_Newsletter_Utils:: SETTINGS_OPTION_ON,
            'headerListsTable' => $headerListsTable,
            'selectedDailyLists' => $selectedDailyLists,
            'selectedWeeklyLists' => $selectedWeeklyLists,
            'campaignsBlock' => $campaignsBlock
        ];

        return $this->adminTemplate->renderBlock('secondStep', $templateData);
    }

    /**
     * @return string
     * @throws Throwable
     */
    private function getHTMLStep4()
    {
        $options = $this->MDirectorUtils->getPluginOptions();
        $optionsSubjectWeeklyDynamic = $this->buildSubjectWeeklyDynamic();
        $typeWeekly = $this->getOption('mdirector_subject_type_weekly');

        $templateData = [
            'on' => Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON,
            'typeWeekly' => $typeWeekly,
            'fixedSubject' => Mdirector_Newsletter_Utils::FIXED_SUBJECT,
            'blogName' => get_bloginfo('name'),
            'frequencyWeeklyChecked' => ($options['mdirector_frequency_weekly']
                ===
                Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) ? 'checked'
                : '',
            'fixedSubjects' => $this->getHTMLFixedSubjects(Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY),
            'dynamicSubject' => Mdirector_Newsletter_Utils::DYNAMIC_SUBJECT,
            'isSubjectDynamic' => $typeWeekly
                === Mdirector_Newsletter_Utils::DYNAMIC_SUBJECT,
            'dynamicSubjects' => $this->getHTMLDynamicSubjects(Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY),
            'optionsSubjectWeeklyDynamic' => $optionsSubjectWeeklyDynamic,
            'optionsDays' => $this->buildOptionsForDays(),
            'hourWeekly' => $this->getOption('mdirector_hour_weekly'),
            'fromWeekly' => $this->getOption('mdirector_from_weekly')
        ];

        return $this->adminTemplate->renderBlock('fourthStep', $templateData);
    }

    /**
     * @return string
     * @throws Throwable
     */
    private function getHTMLStep5()
    {
        $options = $this->MDirectorUtils->getPluginOptions();

        $subjectType = $this->getOption('mdirector_subject_type_daily');

        $templateData = [
            'on' => Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON,
            'mdirectorFrequencyDaily' => $options['mdirector_frequency_daily'],
            'subjectType' => $subjectType,
            'fixedSubject' => Mdirector_Newsletter_Utils::FIXED_SUBJECT,
            'dynamicSubject' => Mdirector_Newsletter_Utils::DYNAMIC_SUBJECT,
            'fixedDailySubjects' => $this->getHTMLFixedSubjects(Mdirector_Newsletter_Utils::DAILY_FREQUENCY),
            'blogName' => get_bloginfo('name'),
            'isSubjectDynamic' => $subjectType
                === Mdirector_Newsletter_Utils::DYNAMIC_SUBJECT,
            'dynamicDailySubjects' => $this->getHTMLDynamicSubjects(Mdirector_Newsletter_Utils::DAILY_FREQUENCY),
            'optionsSubjectDailyDynamic' => $this->buildSubjectDailyDynamic(),
            'weekdaySelectorHTML' => $this->buildWeekdaySelectorHTML(),
            'hour' => $this->getOption('mdirector_hour_daily'),
            'fromDaily' => $this->getOption('mdirector_from_daily')
        ];

        return $this->adminTemplate->renderBlock('fifthStep', $templateData);
    }

    /**
     * @return string
     * @throws Throwable
     */
    private function buildWeekdaySelectorHTML()
    {
        $options = $this->MDirectorUtils->getPluginOptions();
        $weekDays = $this->getWeekdaySelectorArray();

        $html = '';

        foreach ($weekDays as $day => $longName) {
            $fieldName =
                Mdirector_Newsletter_Utils::DAILY_WEEKS_ALLOWED_PREFIX . $day;
            $templateData = [
                'on' => Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON,
                'fieldName' => $fieldName,
                'longNameLabel' => 'WEEKDAY__' . $longName,
                'longNameShortcutLabel' => 'WEEKDAY__' . $longName . '-SHORT',
                'isChecked' => $options[$fieldName]
                    == Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON
            ];

            $html .= $this->adminTemplate->renderBlock('weekDaySelector',
                $templateData);
        }

        return $html;
    }

    private function getWeekdaySelectorArray()
    {
        return [
            'mon' => 'MONDAY',
            'tue' => 'TUESDAY',
            'wed' => 'WEDNESDAY',
            'thu' => 'THURSDAY',
            'fri' => 'FRIDAY',
            'sat' => 'SATURDAY',
            'sun' => 'SUNDAY'
        ];
    }

    /**
     * @return string
     * @throws Throwable
     */
    private function getHTMLStep6()
    {
        $options = $this->MDirectorUtils->getPluginOptions();
        $excludeCategories = isset($options['exclude_categories'])
            && $options['exclude_categories']
            === Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON;

        $templateData = [
            'excludeCategories' => $excludeCategories,
            'categories' => $this->mdirectorGetCategories($options['mdirector_exclude_cats'])
        ];

        return $this->adminTemplate->renderBlock('sixthStep', $templateData);
    }

    /**
     * @return string
     * @throws Throwable
     */
    private function getHTMLStep7()
    {
        $templateData = [
            'privacyPolicy' => $this->getHTMLForPrivacyPolicy()
        ];

        return $this->adminTemplate->renderBlock('seventhBlock', $templateData);
    }

    /**
     * @return string
     * @throws Throwable
     */
    private function getHTMLStep8()
    {
        $minimumEntries = $this->getOption('mdirector_minimum_entries');

        $output = $this->adminTemplate->renderBlock('eighthStep', []);

        if ($this->MDirectorUtils->isWPML()) {
            $templateData = [
                'currentLanguages' => $this->currentLanguages,
                'templateOptions' => $this->generateAllTemplateOptions()
            ];

            $output .= $this->adminTemplate->renderBlock('templatesForWPML',
                $templateData);

        } else {
            $templateData = [
                'templateOptions' => $this->generateTemplateOptions()
            ];

            $output .= $this->adminTemplate->renderBlock('templatesWithoutWPML',
                $templateData);
        }

        $output .= $this->adminTemplate->renderBlock('eighthStepBottom',
            [
                'minimumEntries' => $minimumEntries,
                'templatePreview' => self::TEMPLATE_PREVIEW
            ]);

        return $output;
    }

    /**
     * @return string
     * @throws Throwable
     */
    private function getHTMLStep9()
    {
        $templateData = [
            'lastDailySendRaw' => $this->getOption('mdirector_daily_sent'),
            'lastWeeklySendRaw' => $this->getOption('mdirector_weekly_sent'),
            'lastDailySend' => $this->getLastDateSend(Mdirector_Newsletter_Utils::DAILY_FREQUENCY),
            'lastWeeklySend' => $this->getLastDateSend(Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY)
        ];

        return $this->adminTemplate->renderBlock('ninethStep', $templateData);
    }

    /**
     * @throws Throwable
     */
    public function mdTabContentSettings()
    {
        $options = $this->MDirectorUtils->getPluginOptions();
        update_option('mdirector-notice', 'true', true);

        $test_lists = $this->getOption('mdirector_use_test_lists');

        if ($this->isPluginConfigured()) {
            if (empty($options['mdirector_subject_type_daily'])) {
                $options['mdirector_subject_type_daily'] =
                    Mdirector_Newsletter_Utils::DEFAULT_DAILY_MAIL_SUBJECT;
            }

            if (empty($options['mdirector_subject_type_weekly'])) {
                $options['mdirector_subject_type_weekly'] =
                    Mdirector_Newsletter_Utils::DEFAULT_WEEKLY_MAIL_SUBJECT;
            }
        }

        if ($this->MDirectorUtils->isWPML()) {
            echo $this->getWPMLCompatibilityTemplate();
        }

        echo $this->getHTMLStep1();

        if ($this->isPluginConfigured()) {
            echo $this->getHTMLStep2();
            echo $this->getHTMLStep4();
            echo $this->getHTMLStep5();
            echo $this->getHTMLStep6();
            echo $this->getHTMLStep7();
            echo $this->getHTMLStep8();
            echo $this->getHTMLStep9();
        }

        $templateData = [
            'on' => Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON,
            'isPluginConfigured' => $this->isPluginConfigured(),
            'isEmptyTestLists' => empty($test_lists)
        ];

        echo $this->adminTemplate->renderBlock('submitForm', $templateData);
    }

    /**
     * @throws Throwable
     */
    public function mdTabContentLogs()
    {
        $options = $this->MDirectorUtils->getPluginOptions();
        $logs = '';

        foreach(glob(MDIRECTOR_LOGS_PATH . '*') as $file) {
            $logs .= basename($file) . "\n";
            $logs .= "---------------------------\n";
            $logs .= file_get_contents($file);
            $logs .= "\n\n\n";
        }

        $templateData = [
            'logsContent' => $logs,
            'mdirectorLogsDeactivated' => isset($options['mdirector_logs_deactivated'])
                ? $options['mdirector_logs_deactivated']
                : false,
        ];

        echo $this->adminTemplate->renderBlock('logsTab', $templateData);
    }

    /**
     * @throws Throwable
     */
    public function mdTabContentTests()
    {
        $options = $this->MDirectorUtils->getPluginOptions();

        $headerCampaignsTable = $this->adminTemplate->renderBlock('headerListsCampaignsTable', [
            'elementTitle' => 'STEP-2__CAMPAIGNS'
        ]);

        $headerListsTable = $this->adminTemplate->renderBlock('headerListsCampaignsTable', [
            'elementTitle' => 'STEP-2__LISTS'
        ]);

        $selectedDailyLists = $this->MDirectorUtils->getSelectedUserListsByLanguageHTML(
            Mdirector_Newsletter_Utils::DAILY_FREQUENCY, self::TEST_FLAG);
        $selectedWeeklyLists = $this->MDirectorUtils->getSelectedUserListsByLanguageHTML(
            Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY, self::TEST_FLAG);

        $weeklyCampaigns = $this->MDirectorUtils->getWeeklyCampaignsTestsHTML();
        $dailyCampaigns = $this->MDirectorUtils->getDailyCampaignsTestsHTML();

        if ($this->MDirectorUtils->isWPML()) {
            echo $this->getWPMLCompatibilityTemplate();
        }

        $templateCampaignsData = [
            'headerCampaignsTable' => $headerCampaignsTable,
            'weeklyCampaigns' => $weeklyCampaigns,
            'dailyCampaigns' => $dailyCampaigns,
        ];

        $campaignsBlock = $this->adminTemplate->renderBlock('campaignsBlock', $templateCampaignsData);

        $templateData = [
            'on' => Mdirector_Newsletter_Utils:: SETTINGS_OPTION_ON,
            'mdirectorUseTestLists' => isset($options['mdirector_use_test_lists'])
                ? $options['mdirector_use_test_lists']
                : false,
            'headerListsTable' => $headerListsTable,
            'selectedDailyLists' => $selectedDailyLists,
            'selectedWeeklyLists' => $selectedWeeklyLists,
            'campaignsBlock' => $campaignsBlock
        ];

        echo $this->adminTemplate->renderBlock('testsTab', $templateData);
    }

    /**
     * @throws Throwable
     */
    public function mdTabContentAdvance()
    {
        $options = $this->MDirectorUtils->getPluginOptions();

        $templateData = [
            'on' => Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON,
            'mdirectorHideSampleTemplates' => isset($options['mdirector_hide_sample_templates'])
                ? $options['mdirector_hide_sample_templates']
                : false,
            'mdirectorSchedulerTaxonomies' => isset($options['mdirector_scheduler_taxonomies'])
                ? $options['mdirector_scheduler_taxonomies']
                : '',
            'mdirectorSchedulerPosts' => isset($options['mdirector_scheduler_posts'])
                ? $options['mdirector_scheduler_posts']
                : '',
            'mdirectorSchedulerDefaultFromName' => isset($options['mdirector_scheduler_default_from_name'])
                ? $options['mdirector_scheduler_default_from_name']
                : '',
            'mdirectorLogsDeactivated' => isset($options['mdirector_logs_deactivated'])
                ? $options['mdirector_logs_deactivated']
                : false,
            'mdirectorVersion' => MDIRECTOR_NEWSLETTER_VERSION
        ];

        echo $this->adminTemplate->renderBlock('advanceTab', $templateData);
    }

    /**
     * @param null $selected
     *
     * @return string
     * @throws Throwable
     */
    public function mdirectorGetCategories($selected = null)
    {
        $selected = $selected ? unserialize($selected) : [];

        $cat_args = ['parent' => 0, 'hide_empty' => false];
        $parentCategories = get_categories($cat_args);

        if (empty(count($parentCategories))) {
            return false;
        }

        $categories = '';

        foreach ($parentCategories as $parentCategory) {
            $categories .= $this->buildCategoriesHTML($parentCategory,
                $selected);
            $categories .= $this->buildSubcategoriesHTML($parentCategory,
                $selected);
        }

        return $categories;
    }

    /**
     * @param $parentCategory
     * @param $selected
     *
     * @return string
     * @throws Throwable
     */
    private function buildCategoriesHTML($parentCategory, $selected)
    {
        $templateData = [
            'parentCategory' => $parentCategory,
            'isSelected' => in_array($parentCategory->term_id, $selected)
        ];

        return $this->adminTemplate->renderBlock('categoryOption',
            $templateData);
    }

    /**
     * @param $parentCategory
     * @param $selected
     *
     * @return string
     * @throws Throwable
     */
    private function buildSubcategoriesHTML($parentCategory, $selected)
    {
        $subcategories = '';
        $terms = get_categories([
            'child_of' => $parentCategory->term_id,
            'hide_empty' => false
        ]);

        foreach ($terms as $term) {
            $templateData = [
                'extraIndent' => ($term->parent != $parentCategory->term_id),
                'term' => $term,
                'isChecked' => in_array($term->term_id, $selected)
            ];

            $subcategories .= $this->adminTemplate->renderBlock('subcategoryOption',
                $templateData);
        }

        return $subcategories;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueueStyles()
    {
        wp_enqueue_style($this->MDirectorNewsletter,
            MDIRECTOR_NEWSLETTER_PLUGIN_URL
            . 'admin/css/mdirector-newsletter-admin.css?v=' . time(), [],
            $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueueScripts()
    {
        wp_register_script('timepicker', MDIRECTOR_NEWSLETTER_PLUGIN_URL .
            'admin/js/timepicker.js', ['jquery']);
        wp_enqueue_script('timepicker');
        wp_register_script('mdirector-admin', MDIRECTOR_NEWSLETTER_PLUGIN_URL .
            'admin/js/mdirector-newsletter-admin.js', ['jquery']);

        wp_enqueue_script('mdirector-admin');
    }

    /**
     * @return bool
     * @throws Throwable
     */
    public function checkVersion()
    {
        if (version_compare(MDIRECTOR_CURRENT_WP_VERSION,
            MDIRECTOR_MIN_WP_VERSION, '<=')) {
            unset($_GET['activate']);

            $templateData = [
                'message' => 'CHECK-WP-VERSION',
                'details' => MDIRECTOR_MIN_WP_VERSION
            ];

            echo $this->adminTemplate->renderBlock('errorGeneralNotice',
                $templateData);

            return false;
        }

        return true;
    }

    /**
     * @return bool
     * @throws Throwable
     */
    public function check_curl()
    {
        if (!(function_exists('curl_exec'))) {
            $templateData = [
                'message' => 'CHECK-CURL'
            ];

            echo $this->adminTemplate->renderBlock('errorGeneralNotice',
                $templateData);

            return false;
        }

        return true;
    }

    /**
     * @return bool
     * @throws MDOAuthException2
     * @throws Throwable
     */
    public function checkApi()
    {
        $options = $this->MDirectorUtils->getPluginOptions();

        if ($this->isPluginConfigured()) {
            $response = json_decode(
                $this->MDirectorNewsletterApi->callAPI(
                    $this->apiKey,
                    $this->apiSecret,
                    Mdirector_Newsletter_Utils::MDIRECTOR_API_LIST_ENDPOINT,
                    'GET'));
        } else {
            $templateData = [
                'message' => 'CHECK-API'
            ];

            echo $this->adminTemplate->renderBlock('errorGeneralNotice',
                $templateData);

            return false;
        }

        if (isset($response->code) && $response->code === '401') {
            $options['mdirector_api'] = '';
            $options['mdirector_secret'] = '';
            update_option('mdirector_settings', $options);

            $templateData = [
                'message' => 'CHECK-API-ERROR'
            ];

            echo $this->adminTemplate->renderBlock('errorGeneralNotice',
                $templateData);

            return false;
        }

        return true;
    }

    /**
     * @throws MDOAuthException2
     * @throws Throwable
     */
    public function mdirectorChecks()
    {
        if ($this->checkVersion() && $this->check_curl()
            && $this->checkApi()) {
            update_option('mdirector_active',
                Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON);
        } else {
            update_option('mdirector_active',
                Mdirector_Newsletter_Utils::SETTINGS_OPTION_OFF);
        }
    }
}
