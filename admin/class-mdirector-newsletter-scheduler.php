<?php
namespace MDirectorNewsletter\admin;

use MDirectorNewsletter\includes\Mdirector_Newsletter_Twig;
use MDirectorNewsletter\includes\Mdirector_Newsletter_Utils;
use MDOAuthException2;
use Twig_Error_Loader;
use Twig_Error_Runtime;
use Twig_Error_Syntax;
use Twig_TemplateWrapper;

require_once(plugin_dir_path(__FILE__) . '../vendor/autoload.php');

class MDirector_Newsletter_Scheduler
{
    const MDIRECTOR_SCHEDULER_NONCE_FIELD = 'mdirector_scheduler_nonce';
    const MDIRECTOR_SCHEDULER_ACTIVE_FIELD = 'mdirector_scheduler_active';
    const MDIRECTOR_SCHEDULER_TEMPLATE_FIELD = 'mdirector-scheduler-template';
    const MDIRECTOR_SCHEDULER_FROM_FIELD = 'mdirector-scheduler-from';
    const MDIRECTOR_SCHEDULER_SUBJECT_FIELD = 'mdirector-scheduler-subject';
    const MDIRECTOR_SCHEDULER_LISTS_FIELD = 'mdirector-scheduler-lists';
    const MDIRECTOR_SCHEDULER_SEGMENTS_FIELD = 'mdirector-scheduler-segments';
    const MDIRECTOR_SCHEDULER_GROUPS_FIELD = 'mdirector-scheduler-groups';
    const MDIRECTOR_SCHEDULER_CAMPAIGN_FIELD = 'mdirector-scheduler-campaign';
    const MDIRECTOR_SCHEDULER_DELIVERY_FIELD = 'mdirector-scheduler-delivery';
    const MDIRECTOR_SCHEDULER_PREVIEW_FIELD = 'mdirector-scheduler-preview';
    const MDIRECTOR_SCHEDULER_SEND_FIELD = 'mdirector-scheduler-send';

    const PREVIEW_MODE_ON = true;
    const PREVIEW_MODE_OFF = false;
    const DEFAULT_PRIORITY = 'default';
    const POST_PUBLISHED = 'publish';
    const AUTO_DRAFT_STATUS = 'auto-draft';

    private $postID;

    /**
     * @var Mdirector_Newsletter_Twig
     */
    private $twigInstance;

    /**
     * @var Twig_TemplateWrapper
     */
    protected $adminTemplate;

    /**
     * @var Mdirector_Newsletter_Utils
     */
    private $MDirectorUtils;

    public function __construct() {
        $this->loadDependencies();

        $this->MDirectorUtils = new Mdirector_Newsletter_Utils();

        $this->twigInstance = new Mdirector_Newsletter_Twig();
        $this->adminTemplate = $this->twigInstance->initAdminTemplate();

        add_action('add_meta_boxes', [$this, 'mdirectorScheduler']);
        add_action('save_post', [$this, 'mdirectorSchedulerData']);
        add_action('transition_post_status', [$this, 'newPostTrigger'], 10, 3 );
    }

    private function loadDependencies()
    {}

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

    public function mdirectorScheduler()
    {
        add_meta_box(
            'mdirector_scheduler_delivery_id',
            $this->t('MDSCHEDULER__TITLE'),
            [$this, 'MDSchedulerWidget'],
            $this->getSchedulerTaxonomies(),
            'side',
            self::DEFAULT_PRIORITY
        );
    }

    private function getSchedulerTaxonomies()
    {
        $options = $this->MDirectorUtils->getPluginOptions();
        $output = [];
        $taxonomies = [];

        if (isset($options['mdirector_scheduler_posts']) &&
            !empty($options['mdirector_scheduler_posts'])) {
            $output[] = 'post';
        }

        if (isset($options['mdirector_scheduler_taxonomies']) &&
            !empty($options['mdirector_scheduler_taxonomies'])) {
            $taxonomies = array_map('trim', explode(',', $options['mdirector_scheduler_taxonomies']));
        }

        return array_unique(array_merge($output, $taxonomies));
    }

    private function isSchedulerActive()
    {
        $metas = get_post_meta($this->postID);

        return isset($metas[self::MDIRECTOR_SCHEDULER_ACTIVE_FIELD][0])
            ? $metas[self::MDIRECTOR_SCHEDULER_ACTIVE_FIELD][0]
            : '0';
    }

    private function getMetaValue($field)
    {
        $metas = get_post_meta($this->postID);

        return isset($metas[$field][0])
            ? $metas[$field][0]
            : '';
    }

    private function getUserTemplates()
    {
        $availableTemplates = $this->MDirectorUtils->getPluginTemplates();
        $currentTemplateSelected = $this->getMetaValue(self::MDIRECTOR_SCHEDULER_TEMPLATE_FIELD);

        return $this->MDirectorUtils->getTemplateOptionsHTML($availableTemplates, $currentTemplateSelected);
    }

    private function getUserCampaigns()
    {
        $availableCampaigns = $this->MDirectorUtils->getUserCampaignsByAPI();
        $currentCampaignSelected = $this->getMetaValue(self::MDIRECTOR_SCHEDULER_CAMPAIGN_FIELD);

        return $this->MDirectorUtils->getUserCampaignsHTML($availableCampaigns, $currentCampaignSelected);
    }

    /**
     * @return string
     * @throws MDOAuthException2
     */
    private function getUserLists()
    {
        $currentListsSelected = unserialize($this->getMetaValue(self::MDIRECTOR_SCHEDULER_LISTS_FIELD)) ?: [];
        $currentSegmentsSelected = unserialize($this->getMetaValue(self::MDIRECTOR_SCHEDULER_SEGMENTS_FIELD)) ?: [];
        $currentGroupsSelected = unserialize($this->getMetaValue(self::MDIRECTOR_SCHEDULER_GROUPS_FIELD)) ?: [];
        $selected = [
            Mdirector_Newsletter_Utils::USER_LIST => $currentListsSelected,
            Mdirector_Newsletter_Utils::USER_SEGMENT => $currentSegmentsSelected,
            Mdirector_Newsletter_Utils::USER_GROUP => $currentGroupsSelected
        ];

        return $this->MDirectorUtils->getUserListsHTML($selected);
    }

    private function getPluginOption($option)
    {
        $options = get_option('mdirector_settings') ?: [];

        return isset($options[$option])
            ? $options[$option]
            : null;
    }

    private function getDefaultSchedulerFrom()
    {
        $from = $this->getPluginOption('mdirector_scheduler_default_from_name');

        return !empty($from)
            ? $from
            : '';
    }

    private function isSchedulerEnabled()
    {
        $status = get_post_status($this->postID);

        return $status !== self::AUTO_DRAFT_STATUS;
    }

    public function MDSchedulerWidget()
    {
        $this->postID = get_post()->ID;
        $title = get_post()->post_title;
        $from = $this->getDefaultSchedulerFrom();
        $active = $this->isSchedulerActive();
        $templates = $this->getUserTemplates();
        $campaigns = $this->getUserCampaigns();
        $userLists = $this->getUserLists();

        wp_nonce_field( plugin_basename( __FILE__ ), self::MDIRECTOR_SCHEDULER_NONCE_FIELD );

        $templateData = [
            'mdSchedulerActiveField' => self::MDIRECTOR_SCHEDULER_ACTIVE_FIELD,
            'mdSchedulerActive' => $active,
            'mdSchedulerFromField' => self::MDIRECTOR_SCHEDULER_FROM_FIELD,
            'mdSchedulerFromFieldValue' => $this->getMetaValue(self::MDIRECTOR_SCHEDULER_FROM_FIELD) ?: $from,
            'mdSchedulerSubjectField' => self::MDIRECTOR_SCHEDULER_SUBJECT_FIELD,
            'mdSchedulerSubjectFieldValue' => $this->getMetaValue(self::MDIRECTOR_SCHEDULER_SUBJECT_FIELD) ?: $title,
            'mdSchedulerSubjectFieldPlaceholder' => $title,
            'mdSchedulerTemplateField' => self::MDIRECTOR_SCHEDULER_TEMPLATE_FIELD,
            'mdSchedulerTemplateHTML' => $templates,
            'mdSchedulerCampaignField' => self::MDIRECTOR_SCHEDULER_CAMPAIGN_FIELD,
            'mdSchedulerCampaignHTLM' => $campaigns,
            'mdSchedulerDeliveryField' => self::MDIRECTOR_SCHEDULER_DELIVERY_FIELD,
            'mdSchedulerDeliveryFieldValue' => $this->getMetaValue(self::MDIRECTOR_SCHEDULER_DELIVERY_FIELD) ?: $title,
            'mdSchedulerLists' => $userLists,
            'mdSchedulerPreviewField' => self::MDIRECTOR_SCHEDULER_PREVIEW_FIELD,
            'mdSchedulerSendField' => self::MDIRECTOR_SCHEDULER_SEND_FIELD,
            'postType' => $this->t('MDSCHEDULER__POST__TYPE--' . strtoupper(get_post_type($this->postID))),
        ];

        $template = $this->isSchedulerEnabled() ? 'MDSchedulerEnabled' : 'MDSchedulerDisabled';

        echo $this->adminTemplate->renderBlock($template, $templateData);
    }

    /**
     * @return bool
     */
    private function isPreviewMode()
    {
        return isset($_POST['save']) &&
            ($_POST['save'] === self::MDIRECTOR_SCHEDULER_PREVIEW_FIELD);
    }

    /**
     * @return bool
     */
    private function isSendMode()
    {
        return (
                isset($_POST['save']) &&
                ($_POST['save'] === self::MDIRECTOR_SCHEDULER_SEND_FIELD)
            ) || (
                isset($_POST[self::MDIRECTOR_SCHEDULER_SEND_FIELD]) &&
                ($_POST[self::MDIRECTOR_SCHEDULER_SEND_FIELD] === self::MDIRECTOR_SCHEDULER_SEND_FIELD)
            );
    }

    /**
     * @return bool
     */
    private function isStatusPublished()
    {
        return get_post_status($this->postID) === self::POST_PUBLISHED;
    }

    /**
     * @param $previewMode
     *
     * @return bool
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    private function deliveryPostRoutine($previewMode)
    {
        $posts = $this->MDirectorUtils->getAndParseCustomPost($this->postID);
        $frequency = Mdirector_Newsletter_Utils::DAILY_FREQUENCY;
        $lang = Mdirector_Newsletter_Utils::MDIRECTOR_DEFAULT_USER_LANG;
        $template = $this->getMetaValue(self::MDIRECTOR_SCHEDULER_TEMPLATE_FIELD);
        $campaign = $this->getMetaValue(self::MDIRECTOR_SCHEDULER_CAMPAIGN_FIELD);
        $lists = unserialize($this->getMetaValue(self::MDIRECTOR_SCHEDULER_LISTS_FIELD)) ?: [];
        $segments = unserialize($this->getMetaValue(self::MDIRECTOR_SCHEDULER_SEGMENTS_FIELD)) ?: [];
        $groups = unserialize($this->getMetaValue(self::MDIRECTOR_SCHEDULER_GROUPS_FIELD)) ?: [];
        $from = $this->getMetaValue(self::MDIRECTOR_SCHEDULER_FROM_FIELD);
        $subject = $this->getMetaValue(self::MDIRECTOR_SCHEDULER_SUBJECT_FIELD);
        $delivery = $this->getMetaValue(self::MDIRECTOR_SCHEDULER_DELIVERY_FIELD);

        return $this->MDirectorUtils->mdSendMail(
            $posts, $frequency, $lang, $previewMode, $template,
            $campaign, $lists, $segments, $groups, $from, $subject, $delivery
        );
    }

    private function showPreviewMode() {
        $this->deliveryPostRoutine(self::PREVIEW_MODE_ON);
        exit();
    }

    private function sendDelivery()
    {
        $delivery = $this->deliveryPostRoutine(self::PREVIEW_MODE_OFF);

        if ($delivery) {
            $templateData = [
                'message' => 'SENDING-TEST__DAILY-SENDING'
            ];

            $this->MDirectorUtils->reportNotice('updatedWithDetailsInfoNotice', $templateData);
        } else {
            $templateData = [
                'message' => 'SENDING-TEST__DAILY-SENDING-ERROR',
                'details' => ': ' . $this->t('NO-ENTRIES-IN-BLOG')
            ];

            $this->MDirectorUtils->reportNotice('updatedErrorNotice', $templateData);
        }
    }

    public function mdirectorSchedulerData($postId)
    {
        $this->setPostId($postId);
        $this->saveData();

        if ($this->isPreviewMode()) {
            $this->showPreviewMode();
        }

        if ($this->isStatusPublished() && $this->isSendMode()) {
            $this->sendDelivery();
        }

        $this->MDirectorUtils->bootNotices();

        // check if this isn't an auto save
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // security check via WP NONCE
        if (
            (isset($_POST[self::MDIRECTOR_SCHEDULER_NONCE_FIELD])) &&
            (!wp_verify_nonce($_POST[self::MDIRECTOR_SCHEDULER_NONCE_FIELD], plugin_basename(__FILE__)))
        ) {
            return;
        }
    }

    private function saveData()
    {
        update_post_meta(
            $this->postID,
            self::MDIRECTOR_SCHEDULER_ACTIVE_FIELD,
            (isset($_POST[self::MDIRECTOR_SCHEDULER_ACTIVE_FIELD]) ? 1 : 0)
        );

        update_post_meta($this->postID, self::MDIRECTOR_SCHEDULER_TEMPLATE_FIELD,
            isset($_POST[self::MDIRECTOR_SCHEDULER_TEMPLATE_FIELD])
                ? $_POST[self::MDIRECTOR_SCHEDULER_TEMPLATE_FIELD] : null);

        update_post_meta($this->postID, self::MDIRECTOR_SCHEDULER_FROM_FIELD,
            isset($_POST[self::MDIRECTOR_SCHEDULER_FROM_FIELD])
                ? $_POST[self::MDIRECTOR_SCHEDULER_FROM_FIELD] : null);

        update_post_meta($this->postID, self::MDIRECTOR_SCHEDULER_SUBJECT_FIELD,
            isset($_POST[self::MDIRECTOR_SCHEDULER_SUBJECT_FIELD])
                ? $_POST[self::MDIRECTOR_SCHEDULER_SUBJECT_FIELD] : null);

        update_post_meta($this->postID, self::MDIRECTOR_SCHEDULER_LISTS_FIELD,
            isset($_POST[self::MDIRECTOR_SCHEDULER_LISTS_FIELD])
                ? $_POST[self::MDIRECTOR_SCHEDULER_LISTS_FIELD] : null);

        update_post_meta($this->postID, self::MDIRECTOR_SCHEDULER_SEGMENTS_FIELD,
            isset($_POST[self::MDIRECTOR_SCHEDULER_SEGMENTS_FIELD])
                ? $_POST[self::MDIRECTOR_SCHEDULER_SEGMENTS_FIELD] : null);

        update_post_meta($this->postID, self::MDIRECTOR_SCHEDULER_GROUPS_FIELD,
            isset($_POST[self::MDIRECTOR_SCHEDULER_GROUPS_FIELD])
                ? $_POST[self::MDIRECTOR_SCHEDULER_GROUPS_FIELD] : null);

        update_post_meta($this->postID, self::MDIRECTOR_SCHEDULER_CAMPAIGN_FIELD,
            isset($_POST[self::MDIRECTOR_SCHEDULER_CAMPAIGN_FIELD])
                ? $_POST[self::MDIRECTOR_SCHEDULER_CAMPAIGN_FIELD] : null);

        update_post_meta($this->postID, self::MDIRECTOR_SCHEDULER_DELIVERY_FIELD,
            isset($_POST[self::MDIRECTOR_SCHEDULER_DELIVERY_FIELD])
                ? $_POST[self::MDIRECTOR_SCHEDULER_DELIVERY_FIELD] : null);
    }

    private function setPostId($postId)
    {
        $this->postID = $postId;

        return $this;
    }

    public function newPostTrigger($new_status, $old_status, $post)
    {
        if ($new_status !== self::POST_PUBLISHED || $old_status === self::POST_PUBLISHED) {
            return;
        }

        if ($post->post_type !== 'post') {
            return; // restrict the filter to a specific post type
        }

        $this->setPostId($post->ID);

        if (!$this->isSchedulerActive() || !$this->isStatusPublished()) {
            return;
        }

        $this->sendDelivery();
    }
}
