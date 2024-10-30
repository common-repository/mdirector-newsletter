<?php

namespace MDirectorNewsletter\includes;

use Log\Log;
use MDirectorNewsletter\admin\MDirector_Newsletter_Admin;
use MDOAuthException2;
use Twig_Environment;
use Twig_Error_Loader;
use Twig_Error_Runtime;
use Twig_Error_Syntax;
use Twig_TemplateWrapper;

require_once(plugin_dir_path(__FILE__) . '../vendor/autoload.php');

/**
 * Fired during plugin activation.
 *
 *
 * @since      1.0.0
 * @package    Mdirector_Newsletter
 * @subpackage Mdirector_Newsletter/includes
 * @author     MDirector
 */
class Mdirector_Newsletter_Utils
{
    // Configs
    const SHOW_SEGMENTS_IN_USER_INTERFACE = true;

    // Paths
    const MDIRECTOR_MAIN_URL = 'http://www.mdirector.com';
    const MDIRECTOR_API_DELIVERY_ENDPOINT = self::MDIRECTOR_MAIN_URL . '/api_delivery';
    const MDIRECTOR_API_CONTACT_ENDPOINT = self::MDIRECTOR_MAIN_URL . '/api_contact';
    const MDIRECTOR_API_LIST_ENDPOINT = self::MDIRECTOR_MAIN_URL . '/api_list';
    const MDIRECTOR_API_CAMPAIGN_ENDPOINT = self::MDIRECTOR_MAIN_URL . '/api_campaign';

    const TEMPLATES_PATH = MDIRECTOR_TEMPLATES_PATH . self::DEFAULT_TEMPLATE . '/';
    const TEMPLATE_HTML_BASE_FILE = 'template.html';

    // Language / templates
    const MDIRECTOR_LANG_DOMAIN = 'mdirector-newsletter';
    const MDIRECTOR_DEFAULT_USER_LANG = 'es';
    const DEFAULT_TEMPLATE = 'default';
    const MAX_IMAGE_SIZE_MDIRECTOR_TEMPLATE = 143;
    const MAX_IMAGE_SIZE_DEFAULT_TEMPLATE = '100%';

    // Newsletter settings
    const DAILY_FREQUENCY = 'daily';
    const WEEKLY_FREQUENCY = 'weekly';
    const DEFAULT_DAILY_MAIL_SUBJECT = 'Daily mail';
    const DEFAULT_WEEKLY_MAIL_SUBJECT = 'Weekly mail';
    const DYNAMIC_SUBJECT = 'dynamic';
    const FIXED_SUBJECT = 'fixed';
    const DYNAMIC_CRITERIA_FIRST_POST = 'first_post';
    const DYNAMIC_CRITERIA_LAST_POST = 'last_post';
    const SETTINGS_OPTION_ON = 'yes';
    const SETTINGS_OPTION_OFF = 'no';
    const MIDNIGHT_HOUR = '00:00';
    const EXCERPT_LENGTH_CHARS = 200;
    const MDIRECTOR_SCHEDULER_LISTS_FIELD = 'mdirector-scheduler-lists';
    const MDIRECTOR_SCHEDULER_SEGMENTS_FIELD = 'mdirector-scheduler-segments';
    const MDIRECTOR_SCHEDULER_GROUPS_FIELD = 'mdirector-scheduler-groups';

    // Prefixes
    const FORM_PREFIX = 'mdirector_widget-';
    const DAILY_WEEKS_ALLOWED_PREFIX = 'mdirector_daily_weekday_';
    const FORM_CLASS = 'md__newsletter--form';
    const FORM_NAME = self::FORM_PREFIX . 'form';

    const USER_SEGMENT = 'segment';
    const USER_LIST = 'list';
    const USER_GROUP = 'group';
    const API_LIST_KEYWORD = 'LIST-';
    const API_GROUP_KEYWORD = 'SEG_GRU-';
    const MDIRECTOR_WEEKLY_CAMPAIGN = 'mdirector_weekly_campaign_';
    const MDIRECTOR_WEEKLY_CAMPAIGN_TESTS = 'mdirector_weekly_campaign_tests';
    const MDIRECTOR_DAILY_CAMPAIGN = 'mdirector_daily_campaign_';
    const MDIRECTOR_DAILY_CAMPAIGN_TESTS = 'mdirector_daily_campaign_tests';

    // LOG TYPES
    const LOG_DEBUG = 'DEBUG';
    const LOG_INFO = 'INFO';
    const LOG_ERROR = 'ERROR';
    const LOG_NOTICE = 'NOTICE';

    /**
     * @var Mdirector_Newsletter_Twig
     */
    protected $twigInstance;

    /**
     * @var Twig_Environment
     */
    protected $twigUserTemplate;

    /**
     * @var Twig_TemplateWrapper
     */
    protected $adminTemplate;

    protected $pluginNotices = [];

    /**
     * Mdirector_Newsletter_Utils constructor.
     *
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function __construct()
    {
        $this->twigInstance = new Mdirector_Newsletter_Twig();
        $this->adminTemplate = $this->twigInstance->initAdminTemplate();
    }

    public function isWPML()
    {
        return function_exists('icl_object_id');
    }

    public function getCurrentLanguages()
    {
        if ($this->isWPML()) {
            $languages = apply_filters('wpml_active_languages', null,
                'orderby=id&order=desc');

            if (
                !empty($languages) &&
                $this->findKeyInMultiArray($languages, 'code') &&
                $this->findKeyInMultiArray($languages, 'translated_name')
            ) {
                return $languages;
            }
        }

        $defaultName = explode('_', get_locale())[0];
        return [
            $defaultName => [
                'code' => $defaultName,
                'translated_name' => __('DEFAULT-LANGUAGE',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN)
            ]
        ];
    }

    public function getCurrentLang()
    {
        if ($this->isWPML()) {
            return apply_filters( 'wpml_current_language', NULL );
        }

        if (! empty($this->getCurrentLanguages())) {
            $languages = $this->getCurrentLanguages();
            $firstLang = reset($languages);
            if (isset($firstLang['code'])) {
                return $firstLang['code'];
            }
        }

        return self::MDIRECTOR_DEFAULT_USER_LANG;
    }

    public function log($message, $context, $type = self::LOG_DEBUG) {
        $options = $this->getPluginOptions();
        if ($options['mdirector_logs_deactivated']) {
            return true;
        }

        switch ($type) {
            case self::LOG_INFO:
                Log::info($message, $context);
                break;
            case self::LOG_NOTICE:
                Log::notice($message, $context);
                break;
            case self::LOG_ERROR:
                Log::error($message, $context);
                break;
            case self::LOG_DEBUG:
            default:
                Log::debug($message, $context);
                break;
        }
    }

    public function getRegisterFormHTML($args = [], $instance = null)
    {
        extract($args, EXTR_SKIP);
        $options = $this->getPluginOptions();

        $MDirectorActive = get_option('mdirector_active');
        $output = '';

        if (!isset($beforeTitle)) {
            $beforeTitle = null;
        }

        if (!isset($afterTitle)) {
            $afterTitle = null;
        }

        if (empty($options['mdirector_frequency_daily'])
            && empty($options['mdirector_frequency_weekly'])) {
            return false;
        }

        $title = empty($instance['title'])
            ? ' ' : apply_filters('widget_title', $instance['title']);

        if (!empty($title)) {
            $output .= $beforeTitle . $title . $afterTitle;
        }

        if (!empty($description)) {
            $output .= '<p class="md__newsletter--description">'
                . $instance['description'] . '</p>';
        }

        if ($MDirectorActive === self::SETTINGS_OPTION_ON) {
            if ($options['mdirector_api'] && $options['mdirector_secret']) {
                $selectFrequency = $this->getSelectFrequency();
                $currentLang = $this->getCurrentLang();
                $currentPrivacyText = 'mdirector_policy_text_' . $currentLang;
                $currentPrivacyUrl = 'mdirector_policy_url_' . $currentLang;

                $accept = (isset($options[$currentPrivacyText])
                    && $options[$currentPrivacyText] != '')
                    ? $options[$currentPrivacyText]
                    : __('WIDGET-PRIVACY__POLICY__ACCEPTED',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN);

                $mdPrivacyLink = (isset($options[$currentPrivacyUrl])
                    && $options[$currentPrivacyUrl] != '')
                    ? $options[$currentPrivacyUrl] : '#';

                $spinnerPath =
                    MDIRECTOR_NEWSLETTER_PLUGIN_URL . 'assets/ajax-loader.png';

                $templateData = [
                    'formClass' => self::FORM_CLASS,
                    'formName' => self::FORM_NAME,
                    'formPrefix' => self::FORM_PREFIX,
                    'selectFrequency' => $selectFrequency,
                    'isSpinner' => file_exists($spinnerPath),
                    'spinnerPath' => $spinnerPath,
                    'privacyLink' => $mdPrivacyLink,
                    'privacyLinkText' => $accept,
                    'userLang' => $this->getCurrentLang(),
                    'inputPlaceholder' => __('WIDGET-EMAIL', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN),
                    'actionButton' => __('WIDGET-SUBSCRIPTION', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN)
                ];
                $output .= $this->adminTemplate->renderBlock('subscriptionForm',
                    $templateData);
            }
        }

        return $output;
    }

    private function getSelectFrequency()
    {
        $options = $this->getPluginOptions();
        $selectFrequency = null;

        $templateData = [
            'fieldName' => self::FORM_PREFIX
        ];

        if ($options['mdirector_frequency_daily'] === self::SETTINGS_OPTION_ON
            && $options['mdirector_frequency_weekly']
            === self::SETTINGS_OPTION_ON) {
            $selectFrequency =
                $this->adminTemplate->renderBlock('selectFrequencyLayer',
                    $templateData);
        } else if ($options['mdirector_frequency_daily']
            === self::SETTINGS_OPTION_ON) {
            $templateData['value'] = self::DAILY_FREQUENCY;
            $selectFrequency =
                $this->adminTemplate->renderBlock('singleFrequencyLayer',
                    $templateData);
        } else if ($options['mdirector_frequency_weekly']
            === self::SETTINGS_OPTION_ON) {
            $templateData['value'] = self::WEEKLY_FREQUENCY;
            $selectFrequency =
                $this->adminTemplate->renderBlock('singleFrequencyLayer',
                    $templateData);
        }

        return $selectFrequency;
    }

    public function getPluginTemplates()
    {
        $options = $this->getPluginOptions();
        $customTemplates = $this->getUserCustomTemplates();

        if (isset($options['mdirector_hide_sample_templates']) &&
            $options['mdirector_hide_sample_templates'] === self::SETTINGS_OPTION_ON) {
            return $customTemplates;
        }

        $defaultTemplates =  $this->getDefaultTemplates();
        return array_merge($defaultTemplates, $customTemplates);
    }

    private function getDefaultTemplates()
    {
        return array_map('basename',
            glob(MDIRECTOR_TEMPLATES_PATH . '*', GLOB_ONLYDIR));
    }

    private function getUserCustomTemplates()
    {
        $customTemplatesOnChildThemePath = $this->getCustomTemplatesOnChildThemePath();
        $customTemplatesOnChildTheme = array_map('basename',
            glob( $customTemplatesOnChildThemePath . '*', GLOB_ONLYDIR));

        $customTemplatesOnParentThemePath = $this->getCustomTemplatesOnParentThemePath();
        $customTemplatesOnParentTheme = array_map('basename',
            glob( $customTemplatesOnParentThemePath . '*', GLOB_ONLYDIR));

        return array_merge($customTemplatesOnChildTheme, $customTemplatesOnParentTheme);
    }

    private function getCustomTemplatesPath($template)
    {
        $childThemeTemplatesPath = $this->getCustomTemplatesOnChildThemePath();

        if (file_exists($childThemeTemplatesPath . $template)) {
            return $childThemeTemplatesPath;
        }

        return $this->getCustomTemplatesOnParentThemePath();
    }

    private function getCustomTemplatesURI($template)
    {
        $childThemeTemplatesPath = $this->getCustomTemplatesOnChildThemePath();

        if (file_exists($childThemeTemplatesPath . $template)) {
            return get_stylesheet_directory_uri()
                . DIRECTORY_SEPARATOR
                . MDIRECTOR_NEWSLETTER
                . DIRECTORY_SEPARATOR;
        }

        return get_template_directory_uri()
            . DIRECTORY_SEPARATOR
            . MDIRECTOR_NEWSLETTER
            . DIRECTORY_SEPARATOR;
    }

    private function getCustomTemplatesOnParentThemePath()
    {
        return get_template_directory()
            . DIRECTORY_SEPARATOR
            . MDIRECTOR_NEWSLETTER
            . DIRECTORY_SEPARATOR;
    }

    private function getCustomTemplatesOnChildThemePath()
    {
        return get_stylesheet_directory()
            . DIRECTORY_SEPARATOR
            . MDIRECTOR_NEWSLETTER
            . DIRECTORY_SEPARATOR;
    }

    public function getCurrentTemplate($available_templates, $lang = null)
    {
        $options = $this->getPluginOptions();

        $template = 'mdirector_template_';
        $lang = is_array($lang) ? $lang['code'] : $lang;

        if (isset($options[$template . $lang])) {
            $currentTemplateSelected = $options[$template . $lang];
        } else if (isset($options[$template . 'general'])) {
            $currentTemplateSelected = $options[$template . 'general'];
        } else {
            return Mdirector_Newsletter_Utils::DEFAULT_TEMPLATE;
        }

        if (!in_array($currentTemplateSelected, $available_templates)) {
            return Mdirector_Newsletter_Utils::DEFAULT_TEMPLATE;
        }

        return $currentTemplateSelected;
    }

    public function getTemplateOptionsHTML($availableTemplates, $currentTemplateSelected) {
        $output = '';

        foreach ($availableTemplates as $template) {
            $baseTemplateName = basename($template);
            $isSelected = ($baseTemplateName === $currentTemplateSelected);

            $templateData = [
                'key' => $baseTemplateName,
                'isSelected' => $isSelected,
                'value' => $baseTemplateName
            ];

            $output .= $this->adminTemplate->renderBlock('buildOption',
                $templateData);
        }

        return $output;
    }

    public function cleanNewsletterProcess($frequency)
    {
        $options = $this->getPluginOptions();
        $process = ($frequency === self::DAILY_FREQUENCY)
            ? 'mdirector_daily_sent'
            : 'mdirector_weekly_sent';

        $options[$process] = date('Y-m-d H:i');

        update_option('mdirector_settings', $options);

        wp_reset_postdata();
        wp_reset_query();
    }

    public function resetNewsletterDeliveryDateSent($frequency)
    {
        $options = $this->getPluginOptions();
        $process = ($frequency === self::DAILY_FREQUENCY)
            ? 'mdirector_daily_sent'
            : 'mdirector_weekly_sent';

        $options[$process] = null;

        $this->log(
            'Reset newsletter delivery date sent...',
            ['frequency' => $frequency],
            self::LOG_INFO
        );

        update_option('mdirector_settings', $options);

        wp_reset_postdata();
        wp_reset_query();
    }

    public function resetDeliveriesSent()
    {
        $options = $this->getPluginOptions();

        $options['mdirector_daily_sent'] = null;
        $options['mdirector_weekly_sent'] = null;

        $this->log(
            'Reset deliveries sent...',
            [],
            self::LOG_INFO
        );

        update_option('mdirector_settings', $options);
    }

    public function getPluginOptions()
    {
        return get_option('mdirector_settings')
            ? get_option('mdirector_settings') : [];
    }

    private function textTruncate($string)
    {
        $string = wp_strip_all_tags($string);
        $string = preg_replace('|\[(.+?)\](.+?\[/\\1\])?|s', '', $string);

        if (preg_match('/<!--more(.*?)?-->/', $string, $matches)) {
            list($main) = explode($matches[0], $string, 2);

            return $main;
        } else {
            $string = htmlspecialchars($string);


            if (strlen($string) > self::EXCERPT_LENGTH_CHARS) {
                $string =
                    preg_replace('/\s+?(\S+)?$/', '...',
                        substr($string, 0, self::EXCERPT_LENGTH_CHARS));
            }

            return $string;
        }
    }

    private function getMainImageSize()
    {
        return (self::TEMPLATES_PATH === 'templates-mdirector/')
            ? self::MAX_IMAGE_SIZE_MDIRECTOR_TEMPLATE
            : self::MAX_IMAGE_SIZE_DEFAULT_TEMPLATE;
    }

    private function getMainImage($postId, $size)
    {
        if ($postId) {
            if (has_post_thumbnail($postId)) {
                $postThumbnailId = get_post_thumbnail_id($postId);
                $thumbnail =
                    wp_get_attachment_image_src($postThumbnailId, $size);

                return $thumbnail[0];
            }
        }

        return false;
    }

    /**
     * @param        $posts
     * @param        $frequency
     * @param string $lang
     * @param false  $previewMode
     * @param null   $template
     * @param null   $campaign
     * @param array  $lists
     * @param array  $segments
     * @param array  $groups
     * @param null   $from
     * @param null   $subject
     *
     * @return bool
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function mdSendMail(
        $posts,
        $frequency,
        $lang = self::MDIRECTOR_DEFAULT_USER_LANG,
        $previewMode = false,
        $template = null,
        $campaign = null,
        $lists = [],
        $segments = [],
        $groups = [],
        $from = null,
        $subject = null,
        $delivery = null
    ) {
        add_filter('wp_mail_content_type', [$this, 'setHTMLContentType']);

        if (!empty($posts)) {
            if (empty($template)) {
                $template = $this->getTemplateName($lang);
            }

            $mailSubject = $subject ?:
                $this->composeEmailSubject($posts, $frequency, $lang);

            $templatePath = $this->getTemplatePath($template);
            $templateURL = $this->getTemplateURL($template);
            $templateMainFile = $this->getTemplateMainFile($templatePath);

            $templateData = [
                'templateURL' => $templateURL,
                'templatePath' => $templatePath,
                'header_title' => get_bloginfo('name'),
                'site_link' => get_bloginfo('url'),
                'posts' => $posts
            ];

            $this->twigUserTemplate =
                $this->twigInstance->initUserTemplate($templateData);
            $this->twigUserTemplate->loadTemplate($templateMainFile);

            $mailContent = ($this->templateMainFileIsTwig($templateMainFile))
                ? $this->twigUserTemplate->render(Mdirector_Newsletter_Twig::USER_TEMPLATE,
                    $templateData)
                : $this->parsingTemplate($templateData);

            if ($previewMode) {
                echo $mailContent;
                exit();
            }

            return $this->sendMailAPI($mailContent, $mailSubject, $frequency,
                $lang, $campaign, $lists, $segments, $groups, $from, $delivery);
        }

        return false;
    }

    private function getTemplateMainFile($templatePath)
    {
        $templateHTML = $templatePath . self::TEMPLATE_HTML_BASE_FILE;
        $templateTwig = $templatePath . Mdirector_Newsletter_Twig::USER_TEMPLATE;

        if (file_exists($templateHTML)) {
            return self::TEMPLATE_HTML_BASE_FILE;
        } elseif (file_exists($templateTwig)) {
            return Mdirector_Newsletter_Twig::USER_TEMPLATE;
        }

        return false;
    }

    private function templateMainFileIsTwig($templateMainFile)
    {
        $fileParts = pathinfo($templateMainFile);

        return $fileParts['extension'] === 'twig';
    }

    public function getTemplatePath($template)
    {
        if ($this->isTemplateCustom($template)) {
            $customTemplatesPath = $this->getCustomTemplatesPath($template);
            return $customTemplatesPath . $template . DIRECTORY_SEPARATOR;
        }

        return MDIRECTOR_TEMPLATES_PATH . $template . DIRECTORY_SEPARATOR;
    }

    public function getTemplateURL($template)
    {
        if ($this->isTemplateCustom($template)) {
            return $this->getCustomTemplatesURI($template) . $template . DIRECTORY_SEPARATOR;
        }

        return plugin_dir_url(__DIR__) . 'templates' . DIRECTORY_SEPARATOR . $template . DIRECTORY_SEPARATOR;
    }

    public function getTemplateName($lang)
    {
        $templatesAvailable = $this->getPluginTemplates();

        return $this->getCurrentTemplate($templatesAvailable, $lang);
    }

    public function isTemplateCustom($templateName)
    {
        return !in_array($templateName, $this->getDefaultTemplates());
    }

    /**
     * @param $template_data
     *
     * @return string
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    private function parsingTemplate($template_data)
    {
        $template_data['list'] = implode('',
            array_map([$this, 'buildListFromPosts'], $template_data['posts']));

        return $this->twigUserTemplate->render(self::TEMPLATE_HTML_BASE_FILE,
            $template_data);
    }

    /**
     * @param $post
     *
     * @return string
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    private function buildListFromPosts($post)
    {
        $postData = [
            'post_image' => $this->getImageTagFromPost($post),
            'postImage' => $this->getImageTagFromPost($post),
            'titleURL' => $post['link'],
            'title' => $post['title'],
            'content' => $post['excerpt']
        ];

        return $this->twigUserTemplate->render('list.html', $postData);
    }

    private function getImageTagFromPost($post)
    {
        if (!$post['post_image']) {
            return '';
        }

        $templateData = [
            'post' => $post
        ];

        return $this->adminTemplate->renderBlock('imageTagFromPost',
            $templateData);
    }

    private function getDynamicPostTitle($posts, $criteria)
    {
        $titles = array_column($posts, 'title');
        $titlesSorted = ($criteria === self::DYNAMIC_CRITERIA_FIRST_POST)
            ? array_reverse($titles)
            : $titles;

        return reset($titlesSorted);
    }

    private function composeEmailSubject($posts, $frequency, $lang)
    {
        $options = $this->getPluginOptions();

        if ($frequency === self::DAILY_FREQUENCY) {
            $subject = isset($options['mdirector_subject_type_daily']) &&
                ($options['mdirector_subject_type_daily'] === self::DYNAMIC_SUBJECT)
                ? $options['mdirector_subject_dynamic_prefix_daily_' . $lang]
                . ' ' .
                $this->getDynamicPostTitle($posts,
                    $options['mdirector_subject_dynamic_value_daily'])
                : $options['mdirector_subject_daily_' . $lang];

            $subject = !empty(trim($subject))
                ? $subject
                : self::DEFAULT_DAILY_MAIL_SUBJECT;
        } else {
            $subject = isset($options['mdirector_subject_type_weekly']) &&
                ($options['mdirector_subject_type_weekly'] === self::DYNAMIC_SUBJECT)
                ? $options['mdirector_subject_dynamic_prefix_weekly_' . $lang]
                . ' ' .
                $this->getDynamicPostTitle($posts,
                    $options['mdirector_subject_dynamic_value_weekly'])
                : $options['mdirector_subject_weekly_' . $lang];

            $subject = !empty(trim($subject))
                ? $subject
                : self::DEFAULT_WEEKLY_MAIL_SUBJECT;
        }

        return $subject;
    }

    private function getDeliveryCampaignId(
        $frequency,
        $lang = self::MDIRECTOR_DEFAULT_USER_LANG
    ) {
        $options = $this->getPluginOptions();

        return ($frequency === self::DAILY_FREQUENCY)
            ? $options[self::MDIRECTOR_DAILY_CAMPAIGN . $lang]
            : $options[self::MDIRECTOR_WEEKLY_CAMPAIGN . $lang];
    }

    private function getCampaignsHTMLByFrequency($frequency)
    {
        $campaigns = [];
        $options = $this->getPluginOptions();
        $availableCampaigns = $this->getUserCampaignsByAPI();

        foreach ($this->getCurrentLanguages() as $language => $languageName) {
            $campaignFieldName = $frequency . $language;
            $currentCampaignSelected = isset($options[$campaignFieldName])
                ? $options[$campaignFieldName] : null;

            $templateCampaignSelectorData = [
                'campaignFieldName' => $campaignFieldName,
                'campaignsHMTL' => $this->getUserCampaignsHTML($availableCampaigns, $currentCampaignSelected)
            ];

            $templateCampaignBlockData = [
                'langName' => $languageName['translated_name'],
                'campaignSelector' => $this->adminTemplate->renderBlock('campaignSelector',
                    $templateCampaignSelectorData)
            ];

            $campaigns[] = $this->adminTemplate->renderBlock('campaignLanguageBlock', $templateCampaignBlockData);
        }

        return implode('', $campaigns);
    }

    private function getCampaignsForTestsHTMLByFrequency($frequency)
    {
        $options = $this->getPluginOptions();
        $availableCampaigns = $this->getUserCampaignsByAPI();
        $currentCampaignSelected =
            isset($options[$frequency]) ? $options[$frequency] : null;
        $templateData = [
            'campaignFieldName' => $frequency,
            'campaignsHMTL' => $this->getUserCampaignsHTML($availableCampaigns, $currentCampaignSelected)
        ];

        return $this->adminTemplate->renderBlock('campaignSelector', $templateData);
    }

    /**
     * @return string
     */
    public function getWeeklyCampaignsByLanguageHTML()
    {
        return $this->getCampaignsHTMLByFrequency(self::MDIRECTOR_WEEKLY_CAMPAIGN);
    }

    public function getWeeklyCampaignsTestsHTML()
    {
        return $this->getCampaignsForTestsHTMLByFrequency(self::MDIRECTOR_WEEKLY_CAMPAIGN_TESTS);
    }

    /**
     * @return string
     */
    public function getDailyCampaignsByLanguageHTML()
    {
        return $this->getCampaignsHTMLByFrequency(self::MDIRECTOR_DAILY_CAMPAIGN);
    }

    public function getDailyCampaignsTestsHTML()
    {
        return $this->getCampaignsForTestsHTMLByFrequency(self::MDIRECTOR_DAILY_CAMPAIGN_TESTS);
    }

    /**
     * @throws MDOAuthException2
     */
    public function getUserListsByAPI()
    {
        $options = $this->getPluginOptions();
        $MDirectorActive = get_option('mdirector_active');

        if ($MDirectorActive == self::SETTINGS_OPTION_ON) {
            $MDirectorAPI = new Mdirector_Newsletter_Api();
            $key = $options['mdirector_api'];
            $secret = $options['mdirector_secret'];

            $APIData = [];
            $MDirectorAPIResponse =
                json_decode(
                    $MDirectorAPI->callAPI(
                        $key,
                        $secret,
                        self::MDIRECTOR_API_LIST_ENDPOINT,
                        'GET',
                        $APIData
                    )
                );

            if (isset($MDirectorAPIResponse->response) && $MDirectorAPIResponse->response === 'error') {
                $this->log(
                    'Error retrieving lists via API',
                    [],
                    self::LOG_ERROR
                );

                return false;
            }

            return $MDirectorAPIResponse->lists;
        }

        return false;
    }

    public function getUserCampaignsByAPI()
    {
        $options = $this->getPluginOptions();
        $MDirectorActive = get_option('mdirector_active');

        if ($MDirectorActive == self::SETTINGS_OPTION_ON) {
            $MDirectorAPI = new Mdirector_Newsletter_Api();
            $key = $options['mdirector_api'];
            $secret = $options['mdirector_secret'];

            $MDirectorAPIResponse =
                json_decode(
                    $MDirectorAPI->callAPI(
                        $key,
                        $secret,
                        self::MDIRECTOR_API_CAMPAIGN_ENDPOINT,
                        'GET',
                        []
                    )
                );

            if (isset($MDirectorAPIResponse->response) && $MDirectorAPIResponse->response === 'error') {
                $this->log(
                    'Error retrieving campaigns via API',
                    [],
                    self::LOG_ERROR
                );

                return false;
            }

            return $MDirectorAPIResponse->data;
        }

        return false;
    }

    /**
     * @return string
     * @throws MDOAuthException2
     */
    public function getUserListsHTML($currentListsSelected = [])
    {
        $lists = $this->getUserListsByAPI();
        return $this->getListsHTML($lists, $currentListsSelected, self::USER_LIST);
    }

    private function getListsHTML($lists, $currentListsSelected, $type = self::USER_LIST, $fieldName = null, $lang = null)
    {
        $output = '<ul class="md-user-lists">';

        foreach ($lists as $list) {
            $output .= $this->getUserListItemHTML($list, $currentListsSelected, $type, $fieldName, $lang);
        }

        $output .= '</ul>';

        return $output;
    }

    private function getListsPrefix($frequency, $flags)
    {
        $lists = $frequency === Mdirector_Newsletter_Utils::DAILY_FREQUENCY
            ? 'mdirector_daily_list_'
            : 'mdirector_weekly_list_';

        if ($flags === MDirector_Newsletter_Admin::TEST_FLAG) {
            $lists .= 'test_';
        }

        return $lists;
    }

    public function getSelectedUserListsByLanguageHTML($frequency = self::DAILY_FREQUENCY, $flags = null)
    {
        $options = $this->getPluginOptions();
        $userLists = [];

        foreach ($this->getCurrentLanguages() as $language) {
            $lang = $language['code'];
            $lists = $this->getListsPrefix($frequency, $flags);
            $listsFieldName = $lists . $lang;
            $groupsFieldName = $lists . self::USER_GROUP . '_' . $lang;
            $segmentsFieldName = $lists . self::USER_SEGMENT . '_' . $lang;

            $availableLists = $this->getUserListsByAPI();
            $currentListsSelected = [
                self::USER_LIST => isset($options[$listsFieldName])
                    ? $options[$listsFieldName] : [],
                self::USER_GROUP => isset($options[$groupsFieldName])
                    ? $options[$groupsFieldName] : [],
                self::USER_SEGMENT => isset($options[$segmentsFieldName])
                    ? $options[$segmentsFieldName] : []
            ];

            $templateData = [
                'langName' => $language['translated_name'],
                'listsFieldName' => $listsFieldName,
                'listsHMTL' => $this->getListsHTML($availableLists, $currentListsSelected, self::USER_LIST, $lists, $lang)
            ];

            $userLists[] = $this->adminTemplate->renderBlock('listSelector',
                $templateData);
        }

        return implode('', $userLists);
    }

    private function getUserListNameByType($type, $fieldName, $lang)
    {
        switch ($type) {
            case self::USER_SEGMENT:
                return $fieldName
                    ? $fieldName . self::USER_SEGMENT . '_' . $lang
                    : self::MDIRECTOR_SCHEDULER_SEGMENTS_FIELD;
            case self::USER_GROUP:
                return $fieldName
                    ? $fieldName . self::USER_GROUP . '_' . $lang
                    : self::MDIRECTOR_SCHEDULER_GROUPS_FIELD;
            case self::USER_LIST:
            default:
                return $fieldName
                    ? $fieldName . $lang
                    : self::MDIRECTOR_SCHEDULER_LISTS_FIELD;
        }
    }

    private function getUserListValueByType($item, $type)
    {
        return $type !== self::USER_GROUP
            ? ($item->id ?: $item->segId)
            : $item->gruId;
    }

    private function getSegmentId($segment)
    {
        return $segment->segId;
    }

    private function getSegmentsInGroup($item)
    {
        $segments = $item->segments;

        return array_map([$this, 'getSegmentId'], $segments);
    }

    private function getUserListExtraData($item, $type)
    {
        if ($type !== self::USER_GROUP) {
            return '';
        }

        return join(';', $this->getSegmentsInGroup($item));
    }

    private function getUserListItemHTML($item, $currentItemsSelected, $type, $fieldName, $lang)
    {
        $output = '';
        $inputName = $this->getUserListNameByType($type, $fieldName, $lang);
        $inputValue = $this->getUserListValueByType($item, $type);
        $extraData = $this->getUserListExtraData($item, $type);

        if (empty($currentItemsSelected) || !is_array($currentItemsSelected[$type])) {
            $selected = '';
        } else {
            $selected = (in_array($inputValue, $currentItemsSelected[$type]))
                ? ' checked="checked"' : '';
        }

        $templateData = [
            'type' => $type,
            'extraData' => $extraData,
            'inputValue' => $inputValue,
            'title' => $item->name,
            'inputName' => $inputName,
            'selected' => $selected
        ];

        $output .= $this->adminTemplate->renderBlock('userListItem', $templateData);

        if (self::SHOW_SEGMENTS_IN_USER_INTERFACE) {
            if (isset($item->segment_group) && !empty($item->segment_group)) {
                $output .= $this->getListsHTML($item->segment_group,
                    $currentItemsSelected, self::USER_GROUP, $fieldName, $lang);
            }

            if ($type !== self::USER_GROUP && isset($item->segments) && !empty($item->segments)) {
                $output .= $this->getListsHTML($item->segments,
                    $currentItemsSelected, self::USER_SEGMENT, $fieldName, $lang);
            }
        }

        $output .= '</li>';

        return $output;
    }

    public function getUserCampaignsHTML($availableCampaigns, $currentCampaignSelected)
    {
        $output = '';

        foreach ($availableCampaigns as $campaign) {
            $isSelected = intval($campaign->id) === intval($currentCampaignSelected);
            $templateData = [
                'key' => $campaign->id,
                'isSelected' => $isSelected,
                'value' => '(' . $campaign->id . ') ' . $campaign->campaignName
            ];

            $output .= $this->adminTemplate->renderBlock('buildOption',
                $templateData);
        }

        return $output;
    }

    private function buildUserList($list)
    {
        return self::API_LIST_KEYWORD . $list;
    }

    private function buildUserGroupSegments($group)
    {
        return self::API_GROUP_KEYWORD . $group;
    }

    private function parseUserLists($lists)
    {
        return (is_array($lists))
            ? array_map([$this, 'buildUserList'], $lists)
            : (!empty($lists)
                ? [self::API_LIST_KEYWORD . $lists]
                : []);
    }

    private function parseUserSegments($lists)
    {
        return (is_array($lists))
            ? $lists
            : (!empty($lists)
                ? [$lists]
                : []);
    }

    private function parseUserGroups($lists)
    {
        return (is_array($lists))
            ? array_map([$this, 'buildUserGroupSegments'], $lists)
            : (!empty($lists)
                ? [self::API_GROUP_KEYWORD . $lists]
                : []);
    }

    private function buildUserListsForCampaign($lists, $segments, $groups)
    {
        return json_encode(array_merge(
            $this->parseUserLists($lists),
            $this->parseUserSegments($segments),
            $this->parseUserGroups($groups)
        ));
    }

    private function sendMailAPI(
        $mail_content,
        $mail_subject,
        $frequency = null,
        $lang = self::MDIRECTOR_DEFAULT_USER_LANG,
        $campaign = null,
        $lists = [],
        $segments = [],
        $groups = [],
        $from = null,
        $delivery = null
    ) {
        $options = $this->getPluginOptions();
        $MDirectorActive = get_option('mdirector_active');

        if ($MDirectorActive == self::SETTINGS_OPTION_ON) {
            $MDirectorNewsletterApi = new Mdirector_Newsletter_Api();
            $key = $options['mdirector_api'];
            $secret = $options['mdirector_secret'];

            $campaignId = $campaign ?: $this->getDeliveryCampaignId($frequency, $lang);

            if (empty($lists) && empty($segments) && empty($groups)) {
                $lists = $this->getCurrentListsIds($frequency, $lang);
                $groups = $this->getCurrentGroupsIds($frequency, $lang);
                $segments = $this->getCurrentSegmentsIds($frequency, $lang);
            }

            if (empty($lists) && empty($segments) && empty($groups)) {
                return false;
            }

            $segments =  $this->buildUserListsForCampaign($lists, $segments, $groups);

            $APIData = [
                'type' => 'email',
                'name' => $delivery ?: ($frequency . '_' . date('Y_m_d')),
                'fromName' => $from ?: ($options['mdirector_from_' . $frequency] ?: 'Hello'),
                'subject' => $mail_subject,
                'campaign' => $campaignId,
                'language' => $lang,
                'creativity' => base64_encode($mail_content),
                'segments' => $segments
            ];

            $MDirectorSendResp =
                json_decode(
                    $MDirectorNewsletterApi->callAPI(
                        $key,
                        $secret,
                        self::MDIRECTOR_API_DELIVERY_ENDPOINT,
                        'POST',
                        $APIData
                    )
                );

            if (isset($MDirectorSendResp->response) && $MDirectorSendResp->response === 'error') {
                unset($APIData['creativity']);
                $this->log(
                    'Error sending email API',
                    ['APIData' => $APIData],
                    self::LOG_ERROR
                );

                return false;
            }

            $envId = isset($MDirectorSendResp->data)
                ? $MDirectorSendResp->data->envId
                : null;

            // send the campaign
            if ($envId) {
                $campaignData = [
                    'envId' => $envId,
                    'date' => 'now'
                ];

                $MDirectorNewsletterApi->callAPI(
                    $key,
                    $secret,
                    self::MDIRECTOR_API_DELIVERY_ENDPOINT,
                    'PUT',
                    $campaignData
                );
            }

            unset($APIData['creativity']);
            $APIData['segments'] = json_decode($APIData['segments']);
            $this->log(
                'Email successfully send to API',
                ['APIData' => $APIData],
                self::LOG_INFO
            );

            return true;
        }

        return false;
    }

    private function setHTMLContentType()
    {
        return 'text/html';
    }

    private function isDeliveryActive($lang, $type, $mode = null)
    {
        $options = $this->getPluginOptions();
        $listForDelivery = 'mdirector_' . $type . '_list_';
        $segmentForDelivery = $listForDelivery;
        $groupForDelivery = $listForDelivery;
        $testing = false;

        if (
            isset($options['mdirector_use_test_lists']) &&
            ($options['mdirector_use_test_lists'] === self::SETTINGS_OPTION_ON)
        ) {
            $testing = true;
            $listForDelivery .= 'test_' . $lang;
            $segmentForDelivery .= 'test_segment_' . $lang;
            $groupForDelivery .= 'test_group_' . $lang;
        } else {
            $listForDelivery .= $lang;
            $segmentForDelivery .= 'segment_' . $lang;
            $groupForDelivery .= 'group_' . $lang;
        }

        $activationField = 'mdirector_frequency_' . $type;

        if ($testing) {
            return (
                (isset($options[$listForDelivery]) && !empty($options[$listForDelivery])) ||
                (isset($options[$segmentForDelivery]) && !empty($options[$segmentForDelivery])) ||
                (isset($options[$groupForDelivery]) && !empty($options[$groupForDelivery]))
            );
        }

        return (
            ($options[$activationField] === self::SETTINGS_OPTION_ON) &&
            (
                (isset($options[$listForDelivery]) && !empty($options[$listForDelivery])) ||
                (isset($options[$segmentForDelivery]) && !empty($options[$segmentForDelivery])) ||
                (isset($options[$groupForDelivery]) && !empty($options[$groupForDelivery]))
            )
        );
    }

    private function getExcludeCats()
    {
        $options = $this->getPluginOptions();

        $excludeCats = ($options['mdirector_exclude_cats'])
            ? unserialize($options['mdirector_exclude_cats'])
            : [];

        if (count($excludeCats) > 0) {
            for ($i = 0; $i < count($excludeCats); $i++) {
                $excludeCats[$i] = -1 * abs($excludeCats[$i]);
            }
        }

        return $excludeCats;
    }

    private function buildPosts($foundPosts, $lang)
    {
        $options = $this->getPluginOptions();
        $totalFoundPosts = count($foundPosts);
        $minimumEntries = $options['mdirector_minimum_entries'];

        if (
            is_numeric($minimumEntries) && $totalFoundPosts < $minimumEntries
        ) {
            $complementaryPosts =
                $this->getMinimumPostsForNewsletter($totalFoundPosts,
                    $minimumEntries, $lang);
            $foundPosts = array_merge($foundPosts, $complementaryPosts);
        }

        return array_map([$this, 'parsePost'], $foundPosts);
    }

    private function getMinimumPostsForNewsletter(
        $total_found_posts,
        $minimum_entries,
        $lang
    ) {
        $remainingItems = $minimum_entries - $total_found_posts;

        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'nopaging ' => true,
            'offset' => $total_found_posts,
            'posts_per_page' => $remainingItems
        ];

        if (!empty($excludeCats = $this->getExcludeCats())) {
            $args['cat'] = implode(', ', $excludeCats);
        }

        return $this->getPosts($args, $lang);
    }

    private function parsePost($post)
    {
        return [
            'ID' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'link' => get_permalink($post->ID),
            'truncate_content' => $this->textTruncate($post->post_content),
            'excerpt' => $post->post_excerpt ?: $this->textTruncate($post->post_content),
            'date' => $post->post_date,
            'post_image' => $this->getMainImage($post->ID, 'thumb'),
            'post_image_size' => $this->getMainImageSize()
        ];
    }

    private function getPosts($args, $lang)
    {
        do_action('wpml_switch_language', $lang);
        $query = new \WP_Query($args);
        do_action('wpml_switch_language', $this->getCurrentLang());

        return $query->posts;
    }

    public function getCustomPost($postId)
    {
        $args = $this->getCustomPostArg($postId);
        $lang = self::MDIRECTOR_DEFAULT_USER_LANG;

        return $this->getPosts($args, $lang);
    }

    public function getAndParseCustomPost($postId)
    {
        $foundPosts = $this->getCustomPost($postId);

        return array_map([$this, 'parsePost'], $foundPosts);
    }

    /**
     * @param $lang
     * @param $previewMode
     *
     * @return bool
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    private function mdSendDailyMails($lang, $previewMode, $batchMode = false)
    {
        $options = $this->getPluginOptions();

        $hour = ($options['mdirector_hour_daily'])
            ?: self::MIDNIGHT_HOUR;
        $timeExploded = explode(':', $hour);
        $actualTime = date('Y-m-d H:i:s');
        $mailSent = date('Y-m-d', strtotime(
            isset($options['mdirector_daily_sent'])
                ? $options['mdirector_daily_sent'] : null
        ));

        $canSend = $this->checkIfDeliveryCanBeSent($mailSent);

        $fromDate =
            $this->calculateFromDate($timeExploded, self::DAILY_FREQUENCY);
        $toDate = $this->calculateToDate($timeExploded);

        if (
            isset($_POST['cpt_submit_test_now'])
            || $batchMode
            || (strtotime($actualTime) >= strtotime($toDate) && $canSend)) {

            $args = $this->getPostsArgs($fromDate, $toDate);

            if (!empty($excludeCats = $this->getExcludeCats())) {
                $args['cat'] = implode(', ', $excludeCats);
            }

            $posts = $this->buildPosts($this->getPosts($args, $lang), $lang);

            if (!empty($posts)) {
                $this->cleanNewsletterProcess(self::DAILY_FREQUENCY);
                if ($this->mdSendMail($posts, self::DAILY_FREQUENCY, $lang, $previewMode)) {
                    return true;
                }

                // Process failed; reset last time sent...
                $this->log('Daily delivery failed...', [], self::LOG_ERROR);
                $this->resetNewsletterDeliveryDateSent(self::WEEKLY_FREQUENCY);
                return false;
            }

            $this->log(
                'Daily delivery aborted: there are no new posts for daily mails.',
                [],
                self::LOG_ERROR
            );

            trigger_error('There are no new posts for daily mails and lang ' .
                $lang . print_r($args, true), E_USER_NOTICE);
        } else {
            $this->log(
                "Daily delivery can not be sent (last delivery was $mailSent)",
                [
                    'Actual time' => $actualTime,
                    'Next delivery schedule' => $toDate,
                    'strTime' => strtotime($actualTime),
                    'strDate' => strtotime($toDate),
                    'canSend' => $canSend
                ],
                self::LOG_NOTICE);
        }

        return false;
    }

    private function getPostsArgs($fromDate, $toDate)
    {
        return [
            'post_type' => 'post',
            'post_status' => 'publish',
            'date_query' => [
                'after' => $fromDate,
                'before' => $toDate
            ],
            'nopaging ' => true
        ];
    }

    private function getCustomPostArg($postID) {
        return [
            'p'         => $postID,
            'post_type' => 'any'
        ];
    }

    private function checkIfDeliveryCanBeSent($mailSent)
    {
        $options = $this->getPluginOptions();
        $currentDayOption =
            self::DAILY_WEEKS_ALLOWED_PREFIX . strtolower(date('D'));

        if (isset($options[$currentDayOption])
            && $options[$currentDayOption] !== self::SETTINGS_OPTION_ON) {
            return false;
        }

        return $mailSent !== date('Y-m-d');
    }

    /**
     * @param $lang
     * @param $previewMode
     *
     * @return bool
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    private function mdSendWeeklyMails($lang, $previewMode, $batchMode = false)
    {
        $options = $this->getPluginOptions();

        $day = $options['mdirector_frequency_day']
            ?: '1'; # Default: Monday
        $hour = $options['mdirector_hour_weekly']
            ?: self::MIDNIGHT_HOUR;
        $timeExploded = explode(':', $hour);
        $actualTime = time();
        $mailSent = date('Y-m-d', strtotime(
            isset($options['mdirector_weekly_sent'])
                ? $options['mdirector_weekly_sent'] : null
        ));
        $canSend = $mailSent !== date('Y-m-d');

        $fromDate =
            $this->calculateFromDate($timeExploded, self::WEEKLY_FREQUENCY);
        $toDate = $this->calculateToDate($timeExploded);

        if (
            isset($_POST['cpt_submit_test_now'])
            || $batchMode
            || (date('N') === $day && ($actualTime >= strtotime($toDate))
                && $canSend)
        ) {
            $args = $this->getPostsArgs($fromDate, $toDate);

            if (!empty($excludeCats = $this->getExcludeCats())) {
                $args['cat'] = implode(', ', $excludeCats);
            }

            $posts = $this->buildPosts($this->getPosts($args, $lang), $lang);

            if (!empty($posts)) {
                $this->cleanNewsletterProcess(self::WEEKLY_FREQUENCY);
                if ($this->mdSendMail($posts, self::WEEKLY_FREQUENCY, $lang, $previewMode)) {
                    return true;
                }

                // Process failed; reset last time sent...
                $this->log('Weekly delivery failed...', [], self::LOG_ERROR);

                $this->resetNewsletterDeliveryDateSent(self::WEEKLY_FREQUENCY);
                return false;
            }

            $this->log(
                'Weekly delivery aborted: there are no new posts for daily mails.',
                [],
                self::LOG_ERROR
            );

            trigger_error('There are no new posts for weekly mails and lang ' .
                $lang . print_r($args, true), E_USER_NOTICE);
        } else {
            $this->log(
                "Weekly delivery can not be sent (last delivery was $mailSent)",
                [
                    'Current date' => date('l H:i'),
                    'Next delivery schedule' => jddayofweek($day - 1, 1) . ' ' . $hour,
                    'dateN' => date('N'),
                    'day' => $day,
                    'actualTime' => $actualTime,
                    'strTime' => strtotime($toDate),
                    'canSend' => $canSend
                ],
                self::LOG_NOTICE
            );
        }

        return false;
    }

    protected function calculateFromDate($time, $frequency)
    {
        $daysToSubtract = $frequency === self::DAILY_FREQUENCY ? 1 : 7;

        return date('Y-m-d H:i:s',
            mktime($time[0], $time[1], 00,
                date('m'), date('d') - $daysToSubtract, date('Y')));
    }

    protected function calculateToDate($time)
    {
        return date('Y-m-d H:i:s',
            mktime($time[0], $time[1], 00,
                date('m'), date('d'), date('Y')));
    }

    /**
     * @param $previewMode
     *
     * @return array
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function buildDailyMails($previewMode = false)
    {
        $response = [];
        $delivery = 0;
        $batchMode = false;

        foreach ($this->getCurrentLanguages() as $language) {
            $lang = $language['code'];

            if ($this->isDeliveryActive($lang, self::DAILY_FREQUENCY)) {
                if ($delivery > 0) {
                    $batchMode = true;
                }

                $response[$lang] = $this->mdSendDailyMails($lang, $previewMode, $batchMode);

                // TODO: This solution is not accurate
                if (!$response[$lang]) {
                    break;
                }

                $delivery++;
            }
        }

        return $response;
    }

    /**
     * @return array
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function buildDailyMailsInPreviewMode()
    {
        $isPreviewMode = true;

        return $this->buildDailyMails($isPreviewMode);
    }

    /**
     * @param $previewMode
     * @param $mode
     *
     * @return array
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function buildWeeklyMails($previewMode = false, $mode = null)
    {
        $response = [];
        $delivery = 0;
        $batchMode = false;

        foreach ($this->getCurrentLanguages() as $lang) {
            $lang = $lang['code'];

            if ($this->isDeliveryActive($lang, self::WEEKLY_FREQUENCY, $mode)) {
                if ($delivery > 0) {
                    $batchMode = true;
                }

                $response[$lang] = $this->mdSendWeeklyMails($lang, $previewMode, $batchMode);

                // TODO: This solution is not accurate
                if (!$response[$lang]) {
                    break;
                }

                $delivery++;
            }
        }

        return $response;
    }

    /**
     * @return array
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function buildWeeklyMailsInPreviewMode() {
        $isPreviewMode = true;

        return $this->buildWeeklyMails($isPreviewMode);
    }

    public function getCurrentListsIds($type, $lang)
    {
        $options = $this->getPluginOptions();

        if (isset($options['mdirector_use_test_lists'])
            && $options['mdirector_use_test_lists']
            === self::SETTINGS_OPTION_ON) {
            return isset($options['mdirector_' . $type . '_list_test_' . $lang])
                ? $options['mdirector_' . $type . '_list_test_' . $lang] : [];
        }

        return isset($options['mdirector_' . $type . '_list_' . $lang])
            ? $options['mdirector_' . $type . '_list_' . $lang] : [];
    }

    private function getCurrentGroupsIds($type, $lang)
    {
        $options = $this->getPluginOptions();

        if (isset($options['mdirector_use_test_lists'])
            && $options['mdirector_use_test_lists']
            === self::SETTINGS_OPTION_ON) {
            return isset($options['mdirector_' . $type . '_list_test_group_'
                . $lang]) ? $options['mdirector_' . $type . '_list_test_group_'
            . $lang] : [];
        }

        return isset($options['mdirector_' . $type . '_list_group_' . $lang])
            ? $options['mdirector_' . $type . '_list_group_' . $lang] : [];
    }

    private function getCurrentSegmentsIds($type, $lang)
    {
        $options = $this->getPluginOptions();

        if (isset($options['mdirector_use_test_lists'])
            && $options['mdirector_use_test_lists']
            === self::SETTINGS_OPTION_ON) {
            return isset($options['mdirector_' . $type . '_list_test_segment_'
                . $lang]) ? $options['mdirector_' . $type
            . '_list_test_segment_' . $lang] : [];
        }

        return isset($options['mdirector_' . $type . '_list_segment_' . $lang])
            ? $options['mdirector_' . $type . '_list_segment_' . $lang] : [];
    }

    public function getDefaultUserLists($frequency, $lang)
    {
        return [
            self::USER_LIST => $this->getCurrentListsIds($frequency, $lang),
            self::USER_GROUP => $this->getCurrentGroupsIds($frequency, $lang),
            self::USER_SEGMENT => $this->getCurrentSegmentsIds($frequency, $lang),
        ];

    }

    public function prettyPrintMultiArray($arr)
    {
        $arr = json_encode($arr);
        $arr = str_replace('[]', ' none', $arr);
        $arr = str_replace(['{', '}', '[', ']'], '', $arr);
        $arr = str_replace(',', ', ', $arr);

        return str_replace(':', ': ', $arr);
    }

    public function findKeyInMultiArray($array, $keySearch)
    {
        foreach ($array as $key => $item) {
            if ($key == $keySearch) {
                return true;
            } elseif (is_array($item) && $this->findKeyInMultiArray($item, $keySearch)) {
                return true;
            }
        }

        return false;
    }

    public function reportNotice($template, $templateData)
    {
        $this->pluginNotices[] =
            $this->adminTemplate->renderBlock($template,
                $templateData);
    }

    public function printNotices()
    {
        if (count($this->pluginNotices)) {
            echo join(' ', $this->pluginNotices);
        }
    }

    public function bootNotices()
    {
        add_action('admin_notices', [$this, 'printNotices']);
    }
}
